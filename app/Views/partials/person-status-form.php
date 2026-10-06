<?php
/**
 * Bulk status form shared by Student Status and Employee Status.
 * Expects $people (rows with id, status, status_changed_at, status_reason and a name), $kind ('student'|'employee'),
 * $idField (checkbox name), $action (POST url) and $search.
 */
use App\Support\PersonStatus;
$nameOf = static fn(array $p): string => (string) ($p['student_name'] ?? $p['name'] ?? '');
$noOf = static fn(array $p): string => (string) ($p['registration_no'] ?? $p['employee_no'] ?? '');
$groupOf = static fn(array $p): string => (string) ($p['class_name'] ?? $p['role'] ?? '');
$label = $kind === 'student' ? 'Student' : 'Employee';
?>
<form method="post" action="<?= e($action) ?>" data-status-form><?= csrf_field() ?><input type="hidden" name="search" value="<?= e($search) ?>">
<section class="module-card"><header class="module-card__header"><div><h2><?= $label ?> Status</h2><p>Tick <?= strtolower($label) ?>s, choose the new status, and (optionally) a reason and the date it takes effect.</p></div></header>
<div class="module-card__body">
  <div class="status-apply">
    <div class="module-field"><label for="status_reason">Reason (optional)</label><input class="module-input" id="status_reason" name="reason" maxlength="255" placeholder="e.g. Left the academy / fee not paid / disciplinary"></div>
    <div class="module-field"><label for="status_effective">Effective from</label><input class="module-input" id="status_effective" type="datetime-local" name="effective_at" value="<?= e(date('Y-m-d\TH:i')) ?>" max="<?= e(date('Y-m-d\TH:i')) ?>"><small>Records before this date and time stay visible. Leave as now for today.</small></div>
    <div class="status-apply__buttons"><button class="btn btn--primary" name="status" value="active"><i data-lucide="user-check"></i>Set Active</button><button class="btn btn--secondary" name="status" value="inactive"><i data-lucide="user-x"></i>Set Inactive</button><button class="btn btn--danger" name="status" value="suspended"><i data-lucide="ban"></i>Suspend</button></div>
  </div>
  <?php if (!$people): ?><div class="compact-empty"><h3>No <?= strtolower($label) ?>s found</h3></div><?php else: ?>
  <label class="check-row"><input type="checkbox" data-select-all> Select all shown</label>
  <div class="table-scroll"><table class="data-table"><thead><tr><th>Select</th><th><?= $label ?></th><th><?= $kind === 'student' ? 'Registration' : 'Employee No.' ?></th><th><?= $kind === 'student' ? 'Class' : 'Role' ?></th><th>Current status</th></tr></thead><tbody>
  <?php foreach ($people as $p): ?><tr><td><input type="checkbox" name="<?= e($idField) ?>" value="<?= (int) $p['id'] ?>" aria-label="Select <?= e($nameOf($p)) ?>"></td><td><?= e($nameOf($p)) ?></td><td><?= e($noOf($p)) ?></td><td><?= e($groupOf($p)) ?></td><td><span class="student-status student-status--<?= e($p['status']) ?>"><?= e(PersonStatus::label((string) $p['status'])) ?></span><?php if ($p['status'] !== 'active' && !empty($p['status_changed_at'])): ?><small class="status-since">since <?= e(date('d M Y, h:i A', strtotime($p['status_changed_at']))) ?><?= !empty($p['status_reason']) ? ' · ' . e($p['status_reason']) : '' ?></small><?php endif ?></td></tr><?php endforeach ?>
  </tbody></table></div><?php endif ?>
</div></section></form>
<script>
(()=>{const f=document.querySelector('[data-status-form]');if(!f)return;const all=f.querySelector('[data-select-all]');all&&all.addEventListener('change',()=>f.querySelectorAll('input[name="<?= e($idField) ?>"]').forEach(x=>x.checked=all.checked));
f.addEventListener('submit',e=>{const n=f.querySelectorAll('input[name="<?= e($idField) ?>"]:checked').length;const b=e.submitter;if(!n){e.preventDefault();alert('Tick at least one <?= strtolower($label) ?> first.');return;}if(b&&b.value!=='active'&&!confirm(`Set ${n} <?= strtolower($label) ?>(s) to ${b.textContent.trim()}? Their records before the effective date stay visible.`))e.preventDefault();});})();
</script>
