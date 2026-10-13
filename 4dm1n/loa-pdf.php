<?php
/**
 * Admin: Letter of Acceptance satu paper (?id=<authors.id>).
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Response;
use Cenim\Domain\AuthorRepo;
use Cenim\Domain\LoaRepo;
use Cenim\Pdf\LoaPdf;

$paper = AuthorRepo::find(input_int('id'));

if ($paper === null || (int) $paper['edition_id'] !== (int) (current_edition()['id'] ?? 0)) {
    Response::abort(404, 'Paper not found.');
}

if (!LoaRepo::isAvailable($paper)) {
    Response::abort(409, 'The paper is not accepted, so no Letter of Acceptance is issued.');
}

Response::bytes(LoaPdf::render($paper), LoaPdf::filename($paper), 'application/pdf', input('download') === null);
