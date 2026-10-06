<?php
/** Fee charges billed per particular. Expects $feeCharges (from AccountRepository::feeChargesBilled) and $cur. */
$fc = $feeCharges ?? ['rows' => [], 'totals' => []]; $ft = $fc['totals'];
$fmt = static fn($v): string => e($cur) . ' ' . number_format((float) $v, 2);
?>
<section class="module-card"><header class="module-card__header"><div><h2>Fee Charges Billed</h2><p>Every particular on fee invoices for <?= e($feeChargesLabel ?? 'these months') ?> — tuition, fines, exam fees, trips and any extra charge. Fee income is counted in the ledger when it is paid.</p></div></header><div class="module-card__body">
<?php if (!$fc['rows']): ?><div class="compact-empty"><h3>No fee invoices</h3><p>No invoices for these months.</p></div><?php else: ?>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Particular</th><th>Invoices</th><th>Billed</th></tr></thead><tbody>
<?php foreach ($fc['rows'] as $row): ?><tr><td><?= e($row['particular']) ?></td><td><?= (int) $row['invoices'] ?></td><td><?= $fmt($row['billed']) ?></td></tr><?php endforeach ?>
</tbody><tfoot>
<tr><td><strong>Total charges</strong></td><td><?= (int) ($ft['invoices'] ?? 0) ?></td><td><strong><?= $fmt($ft['billed'] ?? 0) ?></strong></td></tr>
<tr><td>Less discounts</td><td></td><td>- <?= $fmt($ft['discount'] ?? 0) ?></td></tr>
<tr><td>Previous balances carried in</td><td></td><td><?= $fmt($ft['previous'] ?? 0) ?></td></tr>
<tr><td><strong>Collected on these invoices</strong></td><td></td><td><strong class="is-income"><?= $fmt($ft['collected'] ?? 0) ?></strong></td></tr>
<tr><td><strong>Still due</strong></td><td></td><td><strong class="is-expense"><?= $fmt($ft['remaining'] ?? 0) ?></strong></td></tr>
</tfoot></table></div><?php endif ?>
</div></section>
