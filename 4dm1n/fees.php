<?php
/**
 * Kategori biaya, paket, dan aturan biaya tambahan.
 *
 * Semua angka di sini dipakai DUA tempat sekaligus: tabel biaya di halaman
 * publik dan FeeEngine saat menghitung tagihan author — jadi tidak mungkin beda.
 */

declare(strict_types=1);

require __DIR__ . '/_boot.php';

use Cenim\Core\Audit;
use Cenim\Core\Csrf;
use Cenim\Core\Db;
use Cenim\Core\Response;
use Cenim\Core\Session;
use Cenim\Core\View;
use Cenim\Domain\FeeEngine;
use Cenim\Domain\SettingRepo;

$edition   = current_edition();
$editionId = (int) ($edition['id'] ?? 0);

if (is_post()) {
    Csrf::verify();

    switch ((string) input('action', '')) {
        case 'rules':
            SettingRepo::set($editionId, 'included_pages', max(0, input_int('included_pages')));
            SettingRepo::set($editionId, 'extra_paper_percent', max(0, min(100, input_int('extra_paper_percent'))));
            SettingRepo::set($editionId, 'extra_page_fee', [
                'IDR' => (float) str_replace([',', ' '], '', (string) input('extra_page_idr', '0')),
                'USD' => (float) str_replace([',', ' '], '', (string) input('extra_page_usd', '0')),
            ]);

            Audit::log('fees.rules_updated', ['meta' => ['included_pages' => input_int('included_pages')]]);
            Session::flash('success', 'Fee rules saved.');
            break;

        case 'category':
            $id     = input_int('id');
            $amount = (float) str_replace([',', ' '], '', (string) input('amount', '0'));
            $row    = Db::one('SELECT * FROM fee_categories WHERE id = ? AND edition_id = ?', [$id, $editionId]);

            if ($row === null) {
                Session::flash('error', 'Fee category not found.');
                break;
            }

            Db::update('fee_categories', [
                'label'      => mb_substr(trim((string) input('label', '')), 0, 190),
                'amount'     => $amount,
                'is_active'  => input_bool('is_active') ? 1 : 0,
                'sort'       => input_int('sort'),
                'updated_at' => now(),
            ], 'id = :target_id', ['target_id' => $id]);

            Audit::log('fees.category_updated', [
                'target_type' => 'fee_category',
                'target_id'   => $id,
                'meta'        => ['code' => $row['code'], 'from' => (float) $row['amount'], 'to' => $amount],
            ]);

            Session::flash('success', 'Fee category "' . $row['code'] . '" updated.');
            break;

        case 'package_create':
            $code = mb_substr(trim((string) input('code', '')), 0, 40);
            $name = mb_substr(trim((string) input('name', '')), 0, 190);

            if ($code === '' || $name === '') {
                Session::flash('error', 'Package code and name are required.');
                break;
            }

            if (Db::value('SELECT 1 FROM packages WHERE edition_id = ? AND code = ?', [$editionId, $code]) !== null) {
                Session::flash('error', 'That package code already exists.');
                break;
            }

            Db::insert('packages', [
                'edition_id'  => $editionId,
                'code'        => $code,
                'name'        => $name,
                'description' => mb_substr(trim((string) input('description', '')), 0, 500) ?: null,
                'currency'    => input('currency') === 'USD' ? 'USD' : 'IDR',
                'amount'      => (float) str_replace([',', ' '], '', (string) input('amount', '0')),
                'sort'        => input_int('sort'),
                'is_active'   => 1,
                'created_at'  => now(),
            ]);

            Session::flash('success', 'Package added.');
            break;

        case 'package_update':
            $id = input_int('id');

            Db::update('packages', [
                'name'        => mb_substr(trim((string) input('name', '')), 0, 190),
                'description' => mb_substr(trim((string) input('description', '')), 0, 500) ?: null,
                'amount'      => (float) str_replace([',', ' '], '', (string) input('amount', '0')),
                'currency'    => input('currency') === 'USD' ? 'USD' : 'IDR',
                'is_active'   => input_bool('is_active') ? 1 : 0,
                'sort'        => input_int('sort'),
                'updated_at'  => now(),
            ], 'id = :target_id AND edition_id = :edition_id', ['target_id' => $id, 'edition_id' => $editionId]);

            Session::flash('success', 'Package updated.');
            break;

        case 'package_delete':
            $id   = input_int('id');
            $used = Db::value('SELECT 1 FROM registrations WHERE package_id = ? LIMIT 1', [$id]);

            if ($used !== null) {
                Session::flash('error', 'This package is already used by a registration and cannot be deleted. Deactivate it instead.');
                break;
            }

            Db::delete('packages', 'id = ? AND edition_id = ?', [$id, $editionId]);
            Session::flash('success', 'Package deleted.');
            break;

        default:
            Session::flash('error', 'Unknown action.');
    }

    Response::redirect(admin_url('fees.php'));
}

View::render('admin/fees', [
    'title'        => 'Fees & packages',
    'subtitle'     => ($edition['name'] ?? '') . ' — used by both the public fee table and the invoice calculation',
    'active'       => 'fees',
    'edition'      => $edition,
    'categories'   => FeeEngine::categories($editionId),
    'packages'     => Db::all('SELECT * FROM packages WHERE edition_id = ? ORDER BY sort, id', [$editionId]),
    'includedPages' => FeeEngine::includedPages($editionId),
    'paperPercent' => FeeEngine::extraPaperPercent($editionId),
    'extraPageIdr' => FeeEngine::extraPageFee($editionId, 'IDR'),
    'extraPageUsd' => FeeEngine::extraPageFee($editionId, 'USD'),
], 'admin');
