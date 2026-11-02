<?php
/**
 * PDF satu surat (Letter Generate), dibuka di browser. ?id=<letters.id>
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Response;
use Cenim\Domain\LetterRepo;
use Cenim\Pdf\LetterPdf;

$editionId = (int) (current_edition()['id'] ?? 0);
$letter    = LetterRepo::find(input_int('id'));

if ($letter === null || (int) $letter['edition_id'] !== $editionId) {
    Response::abort(404, 'Letter not found.');
}

Response::bytes(LetterPdf::render($letter), LetterPdf::filename($letter), 'application/pdf', input('download') === null);
