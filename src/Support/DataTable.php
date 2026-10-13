<?php

declare(strict_types=1);

namespace Cenim\Support;

use Cenim\Core\Db;
use Cenim\Core\Response;

/**
 * Mesin DataTables server-side: pencarian, pengurutan, dan paginasi dikerjakan
 * di SQL, bukan di browser — supaya tetap ringan walau data ribuan baris.
 *
 * Nama kolom untuk ORDER BY dan pencarian SELALU diambil dari whitelist $spec,
 * tidak pernah langsung dari request.
 *
 * Contoh spec:
 *   [
 *     'table'   => 'authors a',
 *     'columns' => [
 *         'paper_id' => ['select' => 'a.paper_id', 'searchable' => true],
 *         'actions'  => ['orderable' => false, 'searchable' => false],
 *     ],
 *     'where'         => 'a.edition_id = ?',
 *     'params'        => [$editionId],
 *     'default_order' => ['created_at', 'desc'],
 *     'row'           => fn (array $r) => ['paper_id' => e($r['paper_id']), ...],
 *   ]
 */
final class DataTable
{
    private const MAX_LENGTH = 2000;

    public static function respond(array $spec): never
    {
        $columns = $spec['columns'] ?? [];
        $table   = (string) ($spec['table'] ?? '');

        if ($table === '' || $columns === []) {
            Response::json(['error' => 'Invalid table specification.'], 500);
        }

        $draw   = (int) ($_POST['draw'] ?? $_GET['draw'] ?? 1);
        $start  = max(0, (int) ($_POST['start'] ?? 0));
        $length = (int) ($_POST['length'] ?? 25);
        $length = $length < 0 ? self::MAX_LENGTH : min($length, self::MAX_LENGTH);

        $baseWhere  = (string) ($spec['where'] ?? '1');
        $baseParams = array_values($spec['params'] ?? []);
        $joins      = (string) ($spec['joins'] ?? '');

        $total = (int) Db::value("SELECT COUNT(*) FROM {$table} {$joins} WHERE {$baseWhere}", $baseParams);

        // ── Pencarian global ────────────────────────────────────────────────
        $where  = $baseWhere;
        $params = $baseParams;
        $search = trim((string) ($_POST['search']['value'] ?? ''));

        if ($search !== '') {
            $like  = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $parts = [];

            foreach ($columns as $key => $definition) {
                if (($definition['searchable'] ?? true) === false) {
                    continue;
                }
                $parts[]  = self::selectFor($key, $definition) . " LIKE ? ESCAPE '\\'";
                $params[] = $like;
            }

            if ($parts !== []) {
                $where .= ' AND (' . implode(' OR ', $parts) . ')';
            }
        }

        $filtered = (int) Db::value("SELECT COUNT(*) FROM {$table} {$joins} WHERE {$where}", $params);

        // ── Pengurutan ──────────────────────────────────────────────────────
        [$orderColumn, $orderDir] = self::resolveOrder($spec, $columns);

        // ── Ambil data ──────────────────────────────────────────────────────
        $selects = [];
        foreach ($columns as $key => $definition) {
            if (($definition['select'] ?? null) === null && ($definition['virtual'] ?? false)) {
                continue; // kolom UI (mis. tombol aksi), tidak ada di SQL
            }
            $selects[] = self::selectFor($key, $definition) . ' AS "' . $key . '"';
        }
        foreach (($spec['extra_select'] ?? []) as $alias => $expression) {
            $selects[] = $expression . ' AS "' . $alias . '"';
        }

        $rows = Db::all(
            sprintf(
                'SELECT %s FROM %s %s WHERE %s ORDER BY %s %s LIMIT %d OFFSET %d',
                implode(', ', $selects),
                $table,
                $joins,
                $where,
                $orderColumn,
                $orderDir,
                $length,
                $start
            ),
            $params
        );

        $formatter = $spec['row'] ?? null;
        $data      = [];

        foreach ($rows as $row) {
            $data[] = is_callable($formatter) ? $formatter($row) : $row;
        }

        Response::json([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }

    /** @return array{0:string,1:string} */
    private static function resolveOrder(array $spec, array $columns): array
    {
        [$defaultColumn, $defaultDir] = $spec['default_order'] ?? [array_key_first($columns), 'asc'];

        $requestedIndex = $_POST['order'][0]['column'] ?? null;
        $requestedDir   = strtolower((string) ($_POST['order'][0]['dir'] ?? $defaultDir));
        $dir            = $requestedDir === 'desc' ? 'DESC' : 'ASC';

        $key = $defaultColumn;

        if ($requestedIndex !== null) {
            $name = $_POST['columns'][(int) $requestedIndex]['data'] ?? null;
            if (is_string($name) && isset($columns[$name]) && ($columns[$name]['orderable'] ?? true) !== false) {
                $key = $name;
            }
        }

        if (!isset($columns[$key])) {
            $key = (string) array_key_first($columns);
        }

        return [self::selectFor($key, $columns[$key]), $dir];
    }

    private static function selectFor(string $key, array $definition): string
    {
        $select = $definition['select'] ?? $key;

        // Hanya izinkan ekspresi kolom sederhana / fungsi SQL yang kita tulis sendiri
        // di spec — request pengguna tidak pernah sampai ke sini.
        return (string) $select;
    }
}
