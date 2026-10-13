<?php
/**
 * Unggah gambar dari editor WYSIWYG (Landing Content). Mengembalikan
 * {"location": url} sesuai format TinyMCE. Gambar disimpan sebagai media
 * publik (object storage public/media/…), jadi bisa dibuka pengunjung.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Response;
use Cenim\Domain\FileRepo;

if (!is_post()) {
    Response::json(['error' => 'POST required.'], 405);
}

Csrf::verify();

$edition = current_edition();
$upload  = $_FILES['file'] ?? null;
$info    = is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
    ? @getimagesize((string) $upload['tmp_name'])
    : false;

if ($info === false || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
    Response::json(['error' => 'Only PNG or JPG images can be inserted.'], 422);
}

try {
    $fileId = FileRepo::store($upload, 'editor_image', 'content', (int) ($edition['id'] ?? 0), (int) ($edition['id'] ?? 0), (int) ($edition['year'] ?? date('Y')));
} catch (InvalidArgumentException $e) {
    Response::json(['error' => $e->getMessage()], 422);
}

Audit::log('content.image_uploaded', ['meta' => ['file_id' => $fileId, 'name' => $upload['name'] ?? '']]);

Response::json(['location' => public_file_url(FileRepo::find($fileId) ?? [])]);
