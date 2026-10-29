<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

/**
 * Dokumen tambahan yang diminta author (surat undangan, surat visa, surat
 * tugas, dll.). Diunggah admin di halaman Edit author ke storage default
 * (object storage s3.ceits.id), masing-masing dengan nama dan keterangan,
 * lalu bisa diunduh semua author paper itu di Portal.
 * Disimpan di tabel files: owner_type 'author', owner_id = authors.id.
 */
final class AuthorDocumentRepo
{
    public const OWNER   = 'author';
    public const PURPOSE = 'author_document';
    public const MAX_TITLE       = 190;
    public const MAX_DESCRIPTION = 1000;

    /** Ekstensi => MIME yang diterima (dokumen kantor ikut, tidak hanya PDF/gambar). */
    public const TYPES = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    ];

    /** @return array<int,array<string,mixed>> terbaru di atas */
    public static function forAuthor(int $authorId): array
    {
        return FileRepo::forOwner(self::OWNER, $authorId, self::PURPOSE);
    }

    public static function find(int $authorId, int $fileId): ?array
    {
        $file = FileRepo::find($fileId);

        return $file !== null && (string) $file['owner_type'] === self::OWNER && (int) $file['owner_id'] === $authorId
            && (string) $file['purpose'] === self::PURPOSE ? $file : null;
    }

    /**
     * Unggah satu dokumen. Kembalikan id file.
     *
     * @throws \InvalidArgumentException bila file tidak lolos validasi
     */
    public static function add(array $author, array $upload, string $title, string $description): int
    {
        $id = FileRepo::store(
            $upload,
            self::PURPOSE,
            self::OWNER,
            (int) $author['id'],
            (int) $author['edition_id'],
            (int) (EditionRepo::find((int) $author['edition_id'])['year'] ?? gmdate('Y')),
            self::TYPES
        );

        Db::update('files', [
            'title'       => mb_substr(trim($title), 0, self::MAX_TITLE),
            'description' => ($d = trim($description)) !== '' ? mb_substr($d, 0, self::MAX_DESCRIPTION) : null,
        ], 'id = :target_id', ['target_id' => $id]);

        return $id;
    }

    public static function updateInfo(int $fileId, string $title, string $description): void
    {
        Db::update('files', [
            'title'       => mb_substr(trim($title), 0, self::MAX_TITLE),
            'description' => ($d = trim($description)) !== '' ? mb_substr($d, 0, self::MAX_DESCRIPTION) : null,
        ], 'id = :target_id', ['target_id' => $fileId]);
    }

    /** "PDF, JPG, PNG, DOC, DOCX, XLS or XLSX" */
    public static function typesLabel(): string
    {
        $ext = array_map('strtoupper', array_values(array_diff(array_keys(self::TYPES), ['jpeg'])));

        return implode(', ', array_slice($ext, 0, -1)) . ' or ' . end($ext);
    }
}
