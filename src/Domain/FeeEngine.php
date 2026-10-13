<?php

declare(strict_types=1);

namespace Cenim\Domain;

use Cenim\Core\Db;

/**
 * Penghitung biaya registrasi.
 *
 * Semua angka diambil dari tabel `fee_categories` / `packages` dan tabel
 * `settings` — tidak ada nominal yang ditulis di dalam kode, sehingga tabel
 * biaya di halaman publik dan nominal di invoice selalu bersumber sama.
 */
final class FeeEngine
{
    private const IDR_COUNTRIES = ['indonesia', 'id', 'idn', 'republic of indonesia', 'republik indonesia'];

    public static function currencyForCountry(?string $country): string
    {
        $normalized = mb_strtolower(trim((string) $country));
        return in_array($normalized, self::IDR_COUNTRIES, true) ? 'IDR' : 'USD';
    }

    /** @return array<int,array<string,mixed>> */
    public static function categories(int $editionId, ?string $currency = null): array
    {
        $sql    = 'SELECT * FROM fee_categories WHERE edition_id = ? AND is_active = 1';
        $params = [$editionId];

        if ($currency !== null) {
            $sql     .= ' AND currency = ?';
            $params[] = $currency;
        }

        return Db::all($sql . ' ORDER BY sort, id', $params);
    }

    /** @return array<int,array<string,mixed>> */
    public static function packages(int $editionId): array
    {
        return Db::all(
            'SELECT * FROM packages WHERE edition_id = ? AND is_active = 1 ORDER BY sort, id',
            [$editionId]
        );
    }

    /**
     * Pilih kategori biaya yang cocok dengan pilihan author.
     * Untuk non-presenter, status student/IEEE tidak berpengaruh.
     */
    public static function pickCategory(
        int $editionId,
        string $currency,
        string $participantType,
        bool $isStudent,
        bool $isIeee
    ): ?array {
        $isPresenter = $participantType !== 'non_presenter';

        if (!$isPresenter) {
            return Db::one(
                'SELECT * FROM fee_categories
                 WHERE edition_id = ? AND is_active = 1 AND currency = ? AND is_presenter = 0
                 ORDER BY sort, id LIMIT 1',
                [$editionId, $currency]
            );
        }

        return Db::one(
            'SELECT * FROM fee_categories
             WHERE edition_id = ? AND is_active = 1 AND currency = ?
               AND is_presenter = 1 AND is_student = ? AND is_ieee = ?
             ORDER BY sort, id LIMIT 1',
            [$editionId, $currency, $isStudent ? 1 : 0, $isIeee ? 1 : 0]
        );
    }

    public static function includedPages(int $editionId): int
    {
        return max(0, (int) SettingRepo::get($editionId, 'included_pages', 6));
    }

    public static function extraPageFee(int $editionId, string $currency): float
    {
        $fees = SettingRepo::getArray($editionId, 'extra_page_fee', []);
        return (float) ($fees[$currency] ?? 0);
    }

    public static function extraPaperPercent(int $editionId): float
    {
        return (float) SettingRepo::get($editionId, 'extra_paper_percent', 50);
    }

    /** Jumlah halaman melebihi kuota, dihitung dari data paper — bukan input author. */
    public static function extraPages(int $editionId, int $totalPages): int
    {
        return max(0, $totalPages - self::includedPages($editionId));
    }

    /**
     * Hitung rincian biaya.
     *
     * @param array{
     *     country?:?string, participant_type?:string, is_student?:bool, is_ieee_member?:bool,
     *     total_pages?:int, extra_papers?:int, package_id?:?int, discount?:float,
     *     main_paper_id?:?string, attendees?:array<int,string>
     * } $input
     *
     * main_paper_id: paper ini adalah paper tambahan dari first author yang sama
     * → biaya registrasinya extra_paper_percent (50%) dari tarif kategori.
     * attendees: nama peserta tambahan non-presenter → satu baris per orang
     * dengan tarif kategori non-presenter.
     *
     * @return array{
     *     currency:string, fee_category_id:?int, category:?array, package:?array, package_id:?int,
     *     base:float, extra_pages:int, extra_page_fee:float, extra_pages_amount:float,
     *     extra_papers:int, extra_paper_percent:float, extra_papers_amount:float,
     *     items:array<int,array{description:string,qty:float,unit_price:float,amount:float}>,
     *     subtotal:float, discount:float, total:float, error:?string
     * }
     */
    public static function quote(int $editionId, array $input): array
    {
        $participantType = ($input['participant_type'] ?? 'presenter') === 'non_presenter' ? 'non_presenter' : 'presenter';
        $isStudent       = (bool) ($input['is_student'] ?? false);
        $isIeee          = (bool) ($input['is_ieee_member'] ?? false);
        $totalPages      = (int) ($input['total_pages'] ?? 0);
        $extraPapers     = max(0, (int) ($input['extra_papers'] ?? 0));
        $packageId       = ($input['package_id'] ?? null) !== null ? (int) $input['package_id'] : null;
        $discount        = (float) ($input['discount'] ?? 0);
        $mainPaperId     = trim((string) ($input['main_paper_id'] ?? ''));
        $attendees       = array_values(array_filter(array_map('strval', (array) ($input['attendees'] ?? [])), static fn (string $n): bool => trim($n) !== ''));

        $currency = self::currencyForCountry($input['country'] ?? null);

        $quote = [
            'currency'            => $currency,
            'fee_category_id'     => null,
            'category'            => null,
            'package'             => null,
            'package_id'          => null,
            'base'                => 0.0,
            'extra_pages'         => 0,
            'extra_page_fee'      => 0.0,
            'extra_pages_amount'  => 0.0,
            'extra_papers'        => $extraPapers,
            'extra_paper_percent' => self::extraPaperPercent($editionId),
            'extra_papers_amount' => 0.0,
            'items'               => [],
            'subtotal'            => 0.0,
            'discount'            => $discount,
            'total'               => 0.0,
            'error'               => null,
            'main_paper_id'       => null,
            'attendees'           => 0,
            'attendees_amount'    => 0.0,
        ];

        // ── Paket: harga tetap, tidak dipengaruhi kategori ──────────────────
        if ($packageId !== null && $packageId > 0) {
            $package = Db::one(
                'SELECT * FROM packages WHERE id = ? AND edition_id = ? AND is_active = 1',
                [$packageId, $editionId]
            );

            if ($package === null) {
                $quote['error'] = 'The selected package is no longer available.';
                return $quote;
            }

            $amount = (float) $package['amount'];

            $quote['package']    = $package;
            $quote['package_id'] = (int) $package['id'];
            $quote['currency']   = (string) $package['currency'];
            $quote['base']       = $amount;
            $quote['items'][]    = [
                'description' => 'Package: ' . $package['name'],
                'qty'         => 1.0,
                'unit_price'  => $amount,
                'amount'      => $amount,
            ];
            if (!self::addAttendees($quote, $editionId, $attendees)) {
                return $quote;
            }

            return self::finish($quote, $discount);
        }

        // ── Kategori biaya ──────────────────────────────────────────────────
        $category = self::pickCategory($editionId, $currency, $participantType, $isStudent, $isIeee);

        if ($category === null) {
            $quote['error'] = 'No registration fee is configured for this combination yet. Please contact the secretariat.';
            return $quote;
        }

        $base = (float) $category['amount'];

        $quote['category']        = $category;
        $quote['fee_category_id'] = (int) $category['id'];
        $quote['base']            = $base;

        if ($mainPaperId !== '' && $participantType === 'presenter') {
            // Paper tambahan dari first author yang sama: persentase dari tarif kategori.
            $unit = round($base * $quote['extra_paper_percent'] / 100, 2);

            $quote['main_paper_id'] = $mainPaperId;
            $quote['items'][]       = [
                'description' => sprintf(
                    '%s — additional paper of the same first author (%s%% of the fee; main paper %s)',
                    $category['label'],
                    rtrim(rtrim(number_format($quote['extra_paper_percent'], 2, '.', ''), '0'), '.'),
                    $mainPaperId
                ),
                'qty'         => 1.0,
                'unit_price'  => $unit,
                'amount'      => $unit,
            ];
        } else {
            $quote['items'][] = [
                'description' => (string) $category['label'],
                'qty'         => 1.0,
                'unit_price'  => $base,
                'amount'      => $base,
            ];
        }

        // ── Halaman berlebih (hanya untuk presenter) ────────────────────────
        if ($participantType === 'presenter') {
            $extraPages = self::extraPages($editionId, $totalPages);
            $pageFee    = self::extraPageFee($editionId, $quote['currency']);

            if ($extraPages > 0 && $pageFee > 0) {
                $amount = $extraPages * $pageFee;

                $quote['extra_pages']        = $extraPages;
                $quote['extra_page_fee']     = $pageFee;
                $quote['extra_pages_amount'] = $amount;
                $quote['items'][]            = [
                    'description' => sprintf(
                        'Extra page (%d page%s beyond %d included)',
                        $extraPages,
                        $extraPages === 1 ? '' : 's',
                        self::includedPages($editionId)
                    ),
                    'qty'        => (float) $extraPages,
                    'unit_price' => $pageFee,
                    'amount'     => $amount,
                ];
            }

            // ── Paper tambahan dengan first author yang sama ────────────────
            if ($extraPapers > 0) {
                $unit   = round($base * $quote['extra_paper_percent'] / 100, 2);
                $amount = $unit * $extraPapers;

                $quote['extra_papers_amount'] = $amount;
                $quote['items'][]             = [
                    'description' => sprintf(
                        'Additional paper (%d × %d%% of registration fee)',
                        $extraPapers,
                        (int) $quote['extra_paper_percent']
                    ),
                    'qty'        => (float) $extraPapers,
                    'unit_price' => $unit,
                    'amount'     => $amount,
                ];
            }
        }

        if (!self::addAttendees($quote, $editionId, $attendees)) {
            return $quote;
        }

        return self::finish($quote, $discount);
    }

    /** Tarif satu peserta tambahan non-presenter (null bila belum diatur). */
    public static function attendeeFee(int $editionId, string $currency): ?float
    {
        $category = self::pickCategory($editionId, $currency, 'non_presenter', false, false);

        return $category === null ? null : (float) $category['amount'];
    }

    /** Satu baris invoice per peserta tambahan; false + error bila tarifnya belum ada. */
    private static function addAttendees(array &$quote, int $editionId, array $attendees): bool
    {
        if ($attendees === []) {
            return true;
        }

        $fee = self::attendeeFee($editionId, (string) $quote['currency']);
        if ($fee === null) {
            $quote['error'] = 'No non-presenter fee is configured for ' . $quote['currency'] . ' yet. Please contact the secretariat.';
            return false;
        }

        foreach ($attendees as $name) {
            $quote['items'][] = [
                'description' => 'Additional attendee (non-presenter): ' . trim($name),
                'qty'         => 1.0,
                'unit_price'  => $fee,
                'amount'      => $fee,
            ];
        }
        $quote['attendees']        = count($attendees);
        $quote['attendees_amount'] = $fee * count($attendees);

        return true;
    }

    private static function finish(array $quote, float $discount): array
    {
        $subtotal = 0.0;
        foreach ($quote['items'] as $item) {
            $subtotal += (float) $item['amount'];
        }

        $quote['subtotal'] = $subtotal;
        $quote['total']    = max(0, $subtotal - $discount);

        return $quote;
    }
}
