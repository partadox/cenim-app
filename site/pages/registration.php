<?php
/**
 * Halaman Registration Fee publik.
 *
 * Angka biaya diambil dari tabel fee_categories — sumber yang SAMA dengan yang
 * dipakai FeeEngine saat menghitung tagihan author, jadi tabel di halaman ini
 * tidak mungkin berbeda dengan invoice.
 */

declare(strict_types=1);

$pageTitle = 'Registration — ' . site_name();

site_partial('header');

$feeTable = site_fee_table();
?>
<!--==============================
Content Area
==============================-->
    <section class="space">
        <div class="container">
            <div class="team-details-wrap mb-80">
                <div class="row gy-40">
                    <div class="col-xl-8">
                        <h3 class="title mt-n2 mb-25"><?= cms_text('fee.title', 'registration') ?></h3>

                        <?php if ($feeTable === []): ?>
                            <p>The registration fee will be announced soon.</p>
                        <?php else: ?>
                            <table class="table table-bordered" style="border-color: #333;">
                                <?php foreach ($feeTable as $currency => $group): ?>
                                    <thead>
                                        <tr style="background-color: #808080; color: #fff;">
                                            <th colspan="2" class="text-center fw-bold">
                                                <?= e($currency === 'IDR' ? 'Indonesian Participant (IDR)' : 'International Participants (' . $currency . ')') ?>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($group['rows'] as $row): ?>
                                            <tr>
                                                <td><?= e(preg_replace('/\s*\((International|Indonesia)\)$/', '', (string) $row['label'])) ?></td>
                                                <td style="background-color: #c6efce;"><?= e(site_money((float) $row['amount'], (string) $currency)) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if ((float) ($group['extra_page'] ?? 0) > 0): ?>
                                            <tr>
                                                <td>Extra page</td>
                                                <td style="background-color: #c6efce;">
                                                    <?= e(site_money((float) $group['extra_page'], (string) $currency)) ?> per excess page
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>

                        <ol class="mt-25">
                            <?php foreach (cms_list('fee.notes', 'registration') as $note): ?>
                                <li><?= (string) ($note['text'] ?? '') ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="team-details-wrap mb-80">
                <div class="row gy-40">
                    <div class="col-xl-8">
                        <h3 class="title mt-n2 mb-25"><?= cms_text('procedure.title', 'registration') ?></h3>
                        <?= cms_html('procedure.body', 'registration') ?>

                        <?php if (site_registration_open()): ?>
                            <div class="btn-wrap mt-25">
                                <a href="<?= e(author_url('login.php')) ?>" class="th-btn">
                                    Author area<i class="fa-solid fa-arrow-up-right ms-2"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="mt-25"><b>Registration is currently closed.</b></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php $banks = site_bank_accounts(); ?>
            <?php if ($banks !== []): ?>
                <div class="team-details-wrap mb-80">
                    <div class="row gy-40">
                        <div class="col-xl-8">
                            <h3 class="title mt-n2 mb-25"><?= cms_text('bank.title', 'registration') ?></h3>
                            <table class="table table-bordered" style="border-color: #333;">
                                <tbody>
                                    <?php foreach ($banks as $bank): ?>
                                        <tr>
                                            <td style="width: 30%;"><b><?= e((string) ($bank['currency'] ?? '')) ?></b></td>
                                            <td>
                                                <?= e((string) ($bank['bank_name'] ?? '')) ?><br>
                                                <?= e((string) ($bank['account_no'] ?? '')) ?>
                                                &middot; <?= e((string) ($bank['account_name'] ?? '')) ?>
                                                <?php if (trim((string) ($bank['swift'] ?? '')) !== ''): ?>
                                                    <br>SWIFT / BIC: <?= e((string) $bank['swift']) ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

<?php site_partial('footer'); ?>
