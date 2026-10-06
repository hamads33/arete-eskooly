<?php
/** Small "Inactive since …" tag for a register row. Expects $row with person_status / status_changed_at (optional). */
$__st = (string) ($row['person_status'] ?? 'active');
if ($__st !== 'active'):
    $__since = !empty($row['status_changed_at']) ? ' since ' . date('d M Y', strtotime((string) $row['status_changed_at'])) : '';
?><small class="person-status-tag person-status-tag--<?= e($__st) ?>"><?= e(\App\Support\PersonStatus::label($__st) . $__since) ?></small><?php endif;
