<?php
/**
 * Letter of Acceptance paper terpilih — untuk semua author paper itu,
 * tersedia begitu status paper "accepted".
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Logger;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Domain\LoaRepo;
use Cenim\Pdf\LoaPdf;

$paper = current_author();

if ($paper === null || !LoaRepo::isAvailable($paper)) {
    Session::flash('info', 'The Letter of Acceptance is available once the paper is accepted.');
    Response::redirect(author_url('index.php'));
}

try {
    $bytes = LoaPdf::render($paper);
} catch (Throwable $e) {
    Logger::error('Gagal membuat LoA', ['author_id' => (int) $paper['id'], 'error' => $e->getMessage()]);
    Session::flash('error', 'The Letter of Acceptance could not be generated. Please contact the secretariat.');
    Response::redirect(author_url('index.php'));
}

Response::bytes($bytes, LoaPdf::filename($paper), 'application/pdf', input('download') === null);
