<?php
$pageIntro = 'Reusable messages with variables filled from LMS records. Built-in templates power the automations.';
$headerActions = can('sms.templates.manage') ? '<a class="btn btn--primary" href="' . url('/admin/sms/templates/create') . '"><i data-lucide="file-plus-2"></i>New Template</a>' : '';
?>
<main class="main"><div class="main__inner"><div class="module-page sms-page">
<?php require __DIR__ . '/_header.php'; ?>
<section class="module-card">
  <div class="module-card__body">
  <?php if (!$templates): ?>
    <div class="compact-empty sms-empty"><i data-lucide="file-text"></i><h3>No templates yet</h3><p>Create a template to reuse a message.</p></div>
  <?php else: ?>
    <div class="table-scroll"><table class="data-table sms-table mobile-register">
      <thead><tr><th>Template</th><th>Category</th><th>Message</th><th>Variables</th><th>Status</th><?php if (can('sms.templates.manage')): ?><th>Actions</th><?php endif ?></tr></thead>
      <tbody>
      <?php foreach ($templates as $t): $vars = json_decode((string) $t['variables_json'], true) ?: []; ?>
        <tr>
          <td data-label="Template"><strong><?= e($t['name']) ?></strong><?php if ($t['is_system']): ?><small class="sms-sub">Built-in · used by automations</small><?php endif ?></td>
          <td data-label="Category"><?= e($categories[$t['category']] ?? sms_label($t['category'])) ?></td>
          <td data-label="Message"><span class="sms-snippet"><?= e(mb_strimwidth($t['message_body'], 0, 110, '…')) ?></span></td>
          <td data-label="Variables"><?php if ($vars): ?><span class="sms-varlist"><?php foreach ($vars as $v): ?><code>{{<?= e($v) ?>}}</code><?php endforeach ?></span><?php else: ?><span class="sms-muted">None</span><?php endif ?></td>
          <td data-label="Status"><span class="status-badge sms-badge sms-badge--<?= $t['is_active'] ? 'sent' : 'cancelled' ?>"><?= $t['is_active'] ? 'Active' : 'Inactive' ?></span></td>
          <?php if (can('sms.templates.manage')): ?>
          <td data-label="Actions"><div class="sms-row-actions">
            <a class="btn btn--secondary btn--sm" href="<?= url('/admin/sms/templates/' . $t['id'] . '/edit') ?>"><i data-lucide="pencil"></i>Edit</a>
            <?php if (!$t['is_system']): ?><form method="post" action="<?= url('/admin/sms/templates/' . $t['id'] . '/delete') ?>" onsubmit="return confirm('Delete this template?')"><?= csrf_field() ?><button class="class-action-button class-action-button--danger" aria-label="Delete template"><i data-lucide="trash-2"></i></button></form><?php endif ?>
          </div></td>
          <?php endif ?>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table></div>
  <?php endif ?>
  </div>
</section>
</div></div></main>
