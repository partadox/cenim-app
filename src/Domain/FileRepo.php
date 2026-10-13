<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;
use Cenim\Core\Logger;
use Cenim\Storage\StorageManager;
use InvalidArgumentException;
use Throwable;

/**
 * Pencatat file upload (bukti student/IEEE, bukti bayar, tanda tangan, dll).
 * Validasi jenis & ukuran dilakukan di sini, sebelum file dipindahkan.
 */
final class FileRepo
{
    /** Purpose yang boleh dilihat siapa saja (tampil di landing). */
    public const PUBLIC_PURPOSES = ['speaker_photo', 'edition_logo', 'previous_conf_image', 'user_guide', 'sponsor_logo', 'cfp_poster', 'gallery_photo', 'editor_image'];

    /**
     * Simpan satu file hasil upload.
     *
     * @param  array{name:string,type:string,tmp_name:string,error:int,size:int} $upload
     * @throws InvalidArgumentException bila file tidak lolos validasi
     */
    public static function store(
        array $upload,
        string $purpose,
        string $ownerType,
        int $ownerId,
        int $editionId,
        int $year
    ): int {
        self::validate($upload);

        $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
        // Gambar yang tampil di halaman publik (foto speaker) masuk "media/",
        // selebihnya "uploads/" yang hanya bisa dibuka lewat public/file.php.
        $folder    = in_array($purpose, self::PUBLIC_PURPOSES, true) ? 'media' : 'uploads';
        $relative  = sprintf('%s/%d/%s/%s.%s', $folder, $year, $purpose, random_token(16), $extension);
        $mime      = (string) (mime_content_type($upload['tmp_name']) ?: $upload['type']);
        $sha256    = (string) hash_file('sha256', $upload['tmp_name']);
        $size      = (int) $upload['size'];

        $stored = StorageManager::put($upload['tmp_name'], $relative, $mime);

        return Db::insert('files', [
            'edition_id'    => $editionId,
            'owner_type'    => $ownerType,
            'owner_id'      => $ownerId,
            'purpose'       => $purpose,
            'driver'        => $stored['driver'],
            'path'          => $stored['path'],
            'remote_id'     => $stored['remote_id'],
            'remote_url'    => $stored['remote_url'],
            'original_name' => mb_substr($upload['name'], 0, 190),
            'mime'          => $mime,
            'size'          => $size,
            'sha256'        => $sha256,
            'uploaded_at'   => now(),
        ]);
    }

    /**
     * Simpan salinan berkas lokal yang dibuat sistem sendiri (mis. logo hasil
     * generator) ke penyimpanan, tercatat seperti unggahan biasa.
     */
    public static function storeLocalCopy(
        string $path,
        string $originalName,
        string $purpose,
        string $ownerType,
        int $ownerId,
        int $editionId,
        int $year
    ): int {
        if (!is_file($path)) {
            throw new InvalidArgumentException('File not found: ' . basename($path));
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $folder    = in_array($purpose, self::PUBLIC_PURPOSES, true) ? 'media' : 'uploads';
        $relative  = sprintf('%s/%d/%s/%s.%s', $folder, $year, $purpose, random_token(16), $extension);
        $mime      = (string) (mime_content_type($path) ?: 'application/octet-stream');

        // put() memindahkan sumbernya, jadi yang dikirim adalah salinan.
        $tmp = storage_path('tmp/copy-' . random_token(8) . '.' . $extension);
        if (!ensure_dir(dirname($tmp)) || !copy($path, $tmp)) {
            throw new InvalidArgumentException('Temporary storage is not writable.');
        }

        $stored = StorageManager::put($tmp, $relative, $mime);

        return Db::insert('files', [
            'edition_id'    => $editionId,
            'owner_type'    => $ownerType,
            'owner_id'      => $ownerId,
            'purpose'       => $purpose,
            'driver'        => $stored['driver'],
            'path'          => $stored['path'],
            'remote_id'     => null,
            'remote_url'    => null,
            'original_name' => mb_substr($originalName, 0, 190),
            'mime'          => $mime,
            'size'          => (int) filesize($path),
            'sha256'        => (string) hash_file('sha256', $path),
            'uploaded_at'   => now(),
        ]);
    }

    /**
     * Simpan file baru dan hapus file lama dengan tujuan yang sama.
     * Dipakai saat author mengunggah ulang bukti.
     */
    public static function replace(
        array $upload,
        string $purpose,
        string $ownerType,
        int $ownerId,
        int $editionId,
        int $year
    ): int {
        $newId = self::store($upload, $purpose, $ownerType, $ownerId, $editionId, $year);

        foreach (self::forOwner($ownerType, $ownerId, $purpose) as $old) {
            if ((int) $old['id'] === $newId) {
                continue;
            }
            self::softDelete((int) $old['id']);
        }

        return $newId;
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM files WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function forOwner(string $ownerType, int $ownerId, ?string $purpose = null): array
    {
        $sql    = 'SELECT * FROM files WHERE owner_type = ? AND owner_id = ? AND deleted_at IS NULL';
        $params = [$ownerType, $ownerId];

        if ($purpose !== null) {
            $sql     .= ' AND purpose = ?';
            $params[] = $purpose;
        }

        return Db::all($sql . ' ORDER BY uploaded_at DESC', $params);
    }

    public static function latest(string $ownerType, int $ownerId, string $purpose): ?array
    {
        return Db::one(
            'SELECT * FROM files
             WHERE owner_type = ? AND owner_id = ? AND purpose = ? AND deleted_at IS NULL
             ORDER BY uploaded_at DESC LIMIT 1',
            [$ownerType, $ownerId, $purpose]
        );
    }

    /** Tandai terhapus di database dan hapus berkas fisiknya. */
    public static function softDelete(int $id): void
    {
        $file = self::find($id);
        if ($file === null) {
            return;
        }

        try {
            StorageManager::forFile($file)->delete($file);
        } catch (Throwable $e) {
            Logger::warn('Gagal menghapus berkas fisik', ['file_id' => $id, 'error' => $e->getMessage()]);
        }

        Db::update('files', ['deleted_at' => now()], 'id = :target_id', ['target_id' => $id]);
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1048576, 2) . ' MB';
    }

    /** @throws InvalidArgumentException */
    private static function validate(array $upload): void
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(match ($error) {
                UPLOAD_ERR_NO_FILE                        => 'No file was uploaded.',
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the server upload limit (max ' . upload_max_label() . ').',
                UPLOAD_ERR_PARTIAL                        => 'The upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not store the file.',
                default                                   => 'Upload failed (error code ' . $error . ').',
            });
        }

        $maxBytes = upload_max_bytes();
        if ((int) $upload['size'] <= 0) {
            throw new InvalidArgumentException('The file is empty.');
        }
        if ((int) $upload['size'] > $maxBytes) {
            throw new InvalidArgumentException('Maximum file size is ' . upload_max_label() . '.');
        }

        if (!is_uploaded_file((string) $upload['tmp_name'])) {
            throw new InvalidArgumentException('Invalid upload.');
        }

        $allowedExt = (array) cfg('upload.allowed_ext', ['pdf', 'jpg', 'jpeg', 'png']);
        $extension  = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExt, true)) {
            throw new InvalidArgumentException('Allowed file types: ' . implode(', ', $allowedExt) . '.');
        }

        // Jangan percaya Content-Type dari browser — periksa isi filenya.
        $allowedMime = (array) cfg('upload.allowed_mime', []);
        $mime        = (string) (mime_content_type((string) $upload['tmp_name']) ?: '');

        if ($allowedMime !== [] && !in_array($mime, $allowedMime, true)) {
            throw new InvalidArgumentException('The file content is not a valid PDF or image (detected: ' . ($mime ?: 'unknown') . ').');
        }
    }
}
