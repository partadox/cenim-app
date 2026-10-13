<?php
/**
 * @var array<int,array<string,mixed>> $categories
 * @var array<int,array<string,mixed>> $packages
 * @var int   $includedPages
 * @var float $paperPercent
 * @var float $extraPageIdr
 * @var float $extraPageUsd
 */

use Cenim\Core\Csrf;
?>

<div class="alert alert-secondary" style="font-size:12.5px">
    <i class="fa-solid fa-circle-info me-1"></i>
    Changing an amount affects <b>new</b> invoices only — invoices already issued keep the amount they were issued with.
</div>

<div class="cn-card mb-3">
    <div class="cn-card-head">
        <i class="fa-solid fa-ruler text-muted"></i>
        <h2>Fee rules</h2>
    </div>
    <div class="cn-card-body">
        <form method="post" class="row g-2 align-items-end">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="rules">
            <div class="col-md-3">
                <label class="form-label" for="included_pages" style="font-size:12px">Pages included in the fee</label>
                <input type="number" class="form-control form-control-sm" id="included_pages" name="included_pages"
                       value="<?= e((string) $includedPages) ?>" min="0" max="50">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="extra_page_idr" style="font-size:12px">Extra page (IDR)</label>
                <input type="text" class="form-control form-control-sm" id="extra_page_idr" name="extra_page_idr"
                       value="<?= e((string) (int) $extraPageIdr) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="extra_page_usd" style="font-size:12px">Extra page (USD)</label>
                <input type="text" class="form-control form-control-sm" id="extra_page_usd" name="extra_page_usd"
                       value="<?= e((string) (int) $extraPageUsd) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="extra_paper_percent" style="font-size:12px">Extra paper (%)</label>
                <input type="number" class="form-control form-control-sm" id="extra_paper_percent" name="extra_paper_percent"
                       value="<?= e((string) (int) $paperPercent) ?>" min="0" max="100">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-navy w-100"><i class="fa-solid fa-check"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="cn-card mb-3">
    <div class="cn-card-head">
        <i class="fa-solid fa-tags text-muted"></i>
        <h2>Fee categories</h2>
    </div>
    <div class="cn-card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" style="font-size:13px">
                <thead>
                    <tr>
                        <th style="width:170px">Code</th>
                        <th>Label (shown publicly)</th>
                        <th style="width:150px">Amount</th>
                        <th style="width:90px">Active</th>
                        <th style="width:80px">Order</th>
                        <th class="text-end" style="width:70px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <form method="post" class="m-0">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="category">
                                <input type="hidden" name="id" value="<?= e((string) $category['id']) ?>">
                                <td>
                                    <code><?= e((string) $category['code']) ?></code>
                                    <div class="text-muted" style="font-size:11px"><?= e((string) $category['currency']) ?></div>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="label"
                                           value="<?= e((string) $category['label']) ?>" maxlength="190">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="amount"
                                           value="<?= e((string) (int) $category['amount']) ?>">
                                </td>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                            <?= (int) $category['is_active'] === 1 ? 'checked' : '' ?>>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" name="sort"
                                           value="<?= e((string) (int) $category['sort']) ?>">
                                </td>
                                <td class="text-end">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Save">
                                        <i class="fa-solid fa-floppy-disk"></i>
                                    </button>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="cn-card">
    <div class="cn-card-head">
        <i class="fa-solid fa-box text-muted"></i>
        <h2>Packages (optional)</h2>
        <span class="text-muted ms-auto" style="font-size:12px">A package replaces the category fee entirely</span>
    </div>
    <div class="cn-card-body">
        <?php foreach ($packages as $package): ?>
            <form method="post" class="row g-2 align-items-end mb-3 pb-3" style="border-bottom:1px solid var(--cn-border)">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="package_update">
                <input type="hidden" name="id" value="<?= e((string) $package['id']) ?>">
                <div class="col-md-3">
                    <label class="form-label" style="font-size:12px">Name <code><?= e((string) $package['code']) ?></code></label>
                    <input type="text" class="form-control form-control-sm" name="name"
                           value="<?= e((string) $package['name']) ?>" maxlength="190">
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-size:12px">Description</label>
                    <input type="text" class="form-control form-control-sm" name="description"
                           value="<?= e((string) ($package['description'] ?? '')) ?>" maxlength="500">
                </div>
                <div class="col-md-2">
                    <label class="form-label" style="font-size:12px">Amount</label>
                    <input type="text" class="form-control form-control-sm" name="amount"
                           value="<?= e((string) (int) $package['amount']) ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label" style="font-size:12px">Cur.</label>
                    <select class="form-select form-select-sm" name="currency">
                        <option value="IDR" <?= $package['currency'] === 'IDR' ? 'selected' : '' ?>>IDR</option>
                        <option value="USD" <?= $package['currency'] === 'USD' ? 'selected' : '' ?>>USD</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label" style="font-size:12px">Order</label>
                    <input type="number" class="form-control form-control-sm" name="sort"
                           value="<?= e((string) (int) $package['sort']) ?>">
                </div>
                <div class="col-md-2 d-flex gap-1 align-items-end">
                    <div class="form-check me-1">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1"
                            <?= (int) $package['is_active'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" style="font-size:12px">Active</label>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-floppy-disk"></i></button>
            </form>
                    <form method="post" class="m-0" onsubmit="return confirm('Delete this package?')">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="package_delete">
                        <input type="hidden" name="id" value="<?= e((string) $package['id']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </div>
        <?php endforeach; ?>

        <form method="post" class="row g-2 align-items-end">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="package_create">
            <div class="col-md-2">
                <label class="form-label" style="font-size:12px">Code</label>
                <input type="text" class="form-control form-control-sm" name="code" maxlength="40" placeholder="bundle_workshop">
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:12px">Name</label>
                <input type="text" class="form-control form-control-sm" name="name" maxlength="190">
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:12px">Description</label>
                <input type="text" class="form-control form-control-sm" name="description" maxlength="500">
            </div>
            <div class="col-md-2">
                <label class="form-label" style="font-size:12px">Amount</label>
                <input type="text" class="form-control form-control-sm" name="amount" value="0">
            </div>
            <div class="col-md-1">
                <label class="form-label" style="font-size:12px">Cur.</label>
                <select class="form-select form-select-sm" name="currency">
                    <option value="IDR">IDR</option>
                    <option value="USD">USD</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-navy w-100"><i class="fa-solid fa-plus"></i></button>
            </div>
        </form>
    </div>
</div>
