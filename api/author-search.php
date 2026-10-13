<?php
/**
 * Pencarian author (per orang, termasuk co-author) untuk autocomplete form sertifikat penghargaan.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Cenim\Core\AdminAuth;
use Cenim\Core\Csrf;
use Cenim\Core\Db;
use Cenim\Core\Response;
use Cenim\Domain\EditionRepo;

panel_boot('admin');
AdminAuth::requireLogin();
AdminAuth::applyEditionView();

if (!is_post()) {
    Response::json(['error' => 'POST required.'], 405);
}

Csrf::verify();

$term = trim((string) input('q', ''));

if (mb_strlen($term) < 2) {
    Response::json(['results' => []]);
}

$like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

// Per ORANG: semua author (first & co-author), karena sertifikat diterbitkan per orang.
$rows = Db::all(
    "SELECT pa.id, a.paper_id, pa.name, pa.email
     FROM paper_authors pa
     JOIN authors a ON a.id = pa.author_id
     WHERE pa.edition_id = ?
       AND (a.paper_id LIKE ? ESCAPE '\\' OR pa.name LIKE ? ESCAPE '\\' OR pa.email LIKE ? ESCAPE '\\')
     ORDER BY a.paper_id, pa.position LIMIT 10",
    [EditionRepo::contextId(), $like, $like, $like]
);

Response::json([
    'results' => array_map(
        static fn (array $row): array => [
            'id'       => (int) $row['id'],
            'paper_id' => (string) $row['paper_id'],
            'name'     => (string) $row['name'],
            'email'    => (string) ($row['email'] ?? ''),
        ],
        $rows
    ),
]);
