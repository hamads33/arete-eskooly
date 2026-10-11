<?php
$status = static fn(array $s): string => $s['status'] === 'failed' ? 'pending' : $s['status'];
$counts = ['all' => count($schools), 'pending' => 0, 'active' => 0, 'suspended' => 0, 'rejected' => 0];
foreach ($schools as $s) $counts[$status($s)]++;
$filter = $filter ?? '';
if (!isset($counts[$filter])) $filter = $counts['pending'] > 0 ? 'pending' : 'all';
$visible = $filter === 'all' ? $schools : array_values(array_filter($schools, static fn($s) => $status($s) === $filter));
$tabs = ['pending' => 'Awaiting approval', 'active' => 'Live', 'suspended' => 'Access revoked', 'rejected' => 'Rejected', 'all' => 'All requests'];
$pill = ['pending' => 'Awaiting approval', 'failed' => 'Setup failed', 'active' => 'Live', 'suspended' => 'Access revoked', 'rejected' => 'Rejected'];
$initials = static function (string $name): string {
    $words = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));
    return strtoupper(mb_substr($words[0] ?? '?', 0, 1) . mb_substr($words[1] ?? '', 0, 1));
};
$when = static fn(?string $d): string => $d ? date('d M Y, h:i A', strtotime($d)) : '—';
$host = static fn(string $url): string => str_replace('.', '<wbr>.', e(preg_replace('#^https?://#', '', $url)));
$age = static function (?string $d): string {
    $days = $d ? (int) floor((time() - strtotime($d)) / 86400) : 0;
    return $days <= 0 ? 'today' : ($days === 1 ? '1 day' : $days . ' days');
};
$signupUrl = url('/register-school');
$empty = [
    'pending' => ['No requests waiting', 'New school applications will appear here for your approval.'],
    'active' => ['No live schools yet', 'Schools you approve appear here with a link to their portal.'],
    'suspended' => ['No revoked schools', 'Schools whose access you revoke are listed here and can be restored at any time.'],
    'rejected' => ['No rejected requests', 'Requests you decline are kept here for reference.'],
    'all' => ['No school has applied yet', 'Share the registration link so other schools can apply for their own portal.'],
];
?>
<main class="main"><div class="main__inner"><div class="module-page sp">
<header class="sp-head">
  <div>
    <nav class="breadcrumb"><a href="<?= url('/admin/dashboard') ?>">Dashboard</a><i data-lucide="chevron-right"></i><span>School Sign-ups</span></nav>
    <h1>School Sign-ups</h1>
    <p>Review schools that applied for their own portal. Approving sets up a separate portal with its own data in a few seconds; Arete's data is never shared.</p>
  </div>
  <div class="sp-share" title="Registration link for schools">
    <code><?= e($signupUrl) ?></code>
    <button class="sp-btn sp-btn--sm" type="button" data-copy="<?= e($signupUrl) ?>"><i data-lucide="copy"></i><span>Copy link</span></button>
  </div>
</header>

<nav class="sp-tabs" aria-label="Filter requests">
<?php foreach ($tabs as $key => $label): ?>
  <a class="sp-tab sp-tab--<?= $key ?>" href="<?= url('/admin/schools?status=' . $key) ?>"<?= $filter === $key ? ' aria-current="page"' : '' ?>><span><?= e($label) ?></span><strong><?= (int) $counts[$key] ?></strong></a>
<?php endforeach ?>
</nav>

<?php if (count($visible) > 3): ?>
<div class="sp-search"><i data-lucide="search"></i><input type="search" placeholder="Search by school, contact, email, phone or address" aria-label="Search school requests" data-sp-search></div>
<p class="sp-empty sp-empty--search" hidden data-sp-noresults><span>No requests match your search.</span></p>
<?php endif ?>
<?php if (!$visible): [$title, $text] = $empty[$filter]; ?>
<div class="sp-empty"><i data-lucide="<?= $filter === 'pending' ? 'inbox' : 'building-2' ?>"></i><h2><?= e($title) ?></h2><p><?= e($text) ?></p></div>
<?php endif ?>

<div class="sp-list">
<?php foreach ($visible as $s): $address = $schoolUrl($s['slug']); $base = '/admin/schools/' . (int) $s['id']; $st = (string) $s['status']; ?>
<article class="sp-card sp-card--<?= e($st) ?>" data-sp-text="<?= e(mb_strtolower($s['name'] . ' ' . $s['slug'] . ' ' . $s['admin_name'] . ' ' . $s['admin_email'] . ' ' . $s['admin_phone'] . ' ' . $s['city'])) ?>">
  <div class="sp-card__top">
    <span class="sp-avatar" aria-hidden="true"><?= e($initials((string) $s['name'])) ?></span>
    <div class="sp-card__title">
      <h2><?= e($s['name']) ?></h2>
      <?php if ($st === 'active'): ?><a href="<?= e($address) ?>" target="_blank" rel="noopener"><?= $host($address) ?>&nbsp;↗</a><?php else: ?><span class="sp-addr"><?= $host($address) ?></span><?php endif ?>
    </div>
    <span class="sp-pill sp-pill--<?= e($st) ?>"><?= e($pill[$st] ?? ucfirst($st)) ?></span>
  </div>
  <dl class="sp-facts">
    <div><dt>Contact person</dt><dd><?= e($s['admin_name']) ?></dd></div>
    <div><dt>Email</dt><dd><a href="mailto:<?= e($s['admin_email']) ?>"><?= e($s['admin_email']) ?></a></dd></div>
    <div><dt>Mobile</dt><dd><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $s['admin_phone'])) ?>"><?= e($s['admin_phone']) ?></a></dd></div>
    <div><dt>City</dt><dd><?= e($s['city'] ?: '—') ?></dd></div>
    <div><dt>Applied</dt><dd><?= e($when($s['created_at'])) ?></dd></div>
    <?php if ($s['approved_at']): ?><div><dt>Approved</dt><dd><?= e($when($s['approved_at'])) ?></dd></div><?php endif ?>
    <?php if ($st === 'suspended'): ?><div><dt>Access revoked</dt><dd><?= e($when($s['updated_at'])) ?></dd></div><?php endif ?>
    <?php if ($st === 'rejected'): ?><div><dt>Rejected</dt><dd><?= e($when($s['updated_at'])) ?></dd></div><?php endif ?>
    <?php if ($st === 'pending' || $st === 'failed'): ?><div><dt>Waiting</dt><dd class="sp-wait"><?= $age($s['created_at']) === 'today' ? 'Applied today' : e($age($s['created_at'])) ?></dd></div><?php endif ?>
  </dl>
  <?php if ($st === 'failed' && $s['last_error']): $error = (string) $s['last_error']; $summary = trim(preg_replace('/\s+in \S+:\d+.*$/s', '', strtok($error, "
")) ?: 'Unknown error'); ?>
  <div class="sp-alert sp-alert--bad"><strong>Setup did not finish.</strong> Nothing is lost: press <strong>Retry setup</strong> and it continues where it stopped. If it fails again, share the details below with your developer.
    <button class="sp-link" type="button" data-details-toggle aria-expanded="false">Technical details</button>
    <pre class="sp-details" hidden><?= e($summary) ?>

<?= e(mb_substr($error, 0, 1500)) ?></pre></div>
  <?php endif ?>
  <?php if ($st === 'rejected' && trim((string) $s['note']) !== ''): ?><p class="sp-alert sp-alert--info"><strong>Reason:</strong> <?= e($s['note']) ?></p><?php endif ?>
  <?php if ($st === 'suspended'): ?><p class="sp-alert sp-alert--info">The school's portal shows an "access revoked" page. All of its data is kept and is back the moment you restore access.</p><?php endif ?>
  <div class="sp-actions">
  <?php if ($st === 'pending' || $st === 'failed'): ?>
    <form method="post" action="<?= url($base . '/approve') ?>" data-busy="Setting up portal…"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($filter) ?>"><button class="sp-btn sp-btn--primary" type="submit"><i data-lucide="check"></i><span><?= $st === 'failed' ? 'Retry setup' : 'Approve & create portal' ?></span></button></form>
    <div class="sp-reject">
      <button class="sp-btn sp-btn--danger" type="button" aria-expanded="false" aria-controls="reject-<?= (int) $s['id'] ?>" data-reject-toggle><i data-lucide="x"></i><span>Reject</span></button>
      <form class="sp-reject__panel" id="reject-<?= (int) $s['id'] ?>" method="post" action="<?= url($base . '/reject') ?>" hidden><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($filter) ?>">
        <label for="note-<?= (int) $s['id'] ?>">Reason (optional)</label>
        <textarea id="note-<?= (int) $s['id'] ?>" name="note" maxlength="500" placeholder="e.g. We could not verify this school."></textarea>
        <p>The school sees this reason on its application status page, and the address becomes free again.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="sp-btn sp-btn--danger" type="submit">Reject request</button><button class="sp-btn" type="button" data-reject-cancel>Cancel</button></div>
      </form>
    </div>
  <?php elseif ($st === 'active'): ?>
    <form method="post" action="<?= url($base . '/login') ?>" target="_blank" title="Opens the school's portal in a new tab, signed in as its administrator. No password needed."><?= csrf_field() ?><button class="sp-btn sp-btn--primary" type="submit"><i data-lucide="log-in"></i><span>Sign in as school admin</span></button></form>
    <a class="sp-btn" href="<?= e($address) ?>" target="_blank" rel="noopener"><i data-lucide="external-link"></i><span>Open login page</span></a>
    <form method="post" action="<?= url($base . '/revoke') ?>" data-confirm="Revoke access for <?= e($s['name']) ?>? Their portal will stop working until you restore it. No data is deleted."><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($filter) ?>"><button class="sp-btn sp-btn--danger" type="submit"><i data-lucide="ban"></i><span>Revoke access</span></button></form>
  <?php elseif ($st === 'suspended'): ?>
    <form method="post" action="<?= url($base . '/restore') ?>" data-busy="Restoring…"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($filter) ?>"><button class="sp-btn sp-btn--primary" type="submit"><i data-lucide="rotate-ccw"></i><span>Restore access</span></button></form>
  <?php endif ?>
  </div>
  <?php if (in_array($st, ['suspended', 'failed', 'rejected'], true)): $hostName = preg_replace('#^https?://#', '', $address); ?>
  <div class="sp-delete">
    <button class="sp-link sp-link--danger" type="button" aria-expanded="false" aria-controls="delete-<?= (int) $s['id'] ?>" data-delete-toggle><i data-lucide="trash-2"></i> Delete permanently…</button>
    <form class="sp-delete__panel" id="delete-<?= (int) $s['id'] ?>" method="post" action="<?= url($base . '/delete') ?>" hidden><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($filter) ?>">
      <strong>Permanently delete <?= e($s['name']) ?>?</strong>
      <ul>
        <?php if ($s['db_name']): ?><li>All of its data is erased: students, staff, fees, attendance, results and accounts.</li>
        <li>Its uploaded files, settings and sign-ins are removed from the server.</li><?php endif ?>
        <li><?= e($hostName) ?> stops working and becomes free for a new school.</li>
        <li>This cannot be undone.</li>
      </ul>
      <label for="confirm-<?= (int) $s['id'] ?>">Type <code><?= e($s['slug']) ?></code> to confirm</label>
      <input id="confirm-<?= (int) $s['id'] ?>" name="confirm" autocomplete="off" autocapitalize="none" spellcheck="false" data-expect="<?= e($s['slug']) ?>">
      <div class="sp-delete__actions"><button class="sp-btn sp-btn--danger-solid" type="submit" disabled>Delete forever</button><button class="sp-btn" type="button" data-delete-cancel>Cancel</button></div>
    </form>
  </div>
  <?php endif ?>
</article>
<?php endforeach ?>
</div>
</div></div></main>
<script>
document.querySelectorAll('.sp form[data-confirm], .sp form[data-busy]').forEach(function (form) {
  form.addEventListener('submit', function (event) {
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
    var button = form.querySelector('button');
    if (form.dataset.busy && button) setTimeout(function () { button.disabled = true; button.innerHTML = '<span>' + form.dataset.busy + '</span>'; }, 0);
  });
});
document.querySelectorAll('.sp [data-reject-toggle]').forEach(function (button) {
  var panel = document.getElementById(button.getAttribute('aria-controls'));
  function set(open) { panel.hidden = !open; button.setAttribute('aria-expanded', open ? 'true' : 'false'); if (open) panel.querySelector('textarea').focus(); }
  button.addEventListener('click', function () { set(panel.hidden); });
  panel.querySelector('[data-reject-cancel]').addEventListener('click', function () { set(false); button.focus(); });
});
document.querySelectorAll('.sp [data-details-toggle]').forEach(function (button) {
  button.addEventListener('click', function () { var pre = button.nextElementSibling; pre.hidden = !pre.hidden; button.setAttribute('aria-expanded', pre.hidden ? 'false' : 'true'); });
});
(function () {
  var box = document.querySelector('[data-sp-search]'); if (!box) return;
  var cards = document.querySelectorAll('.sp-card[data-sp-text]'), none = document.querySelector('[data-sp-noresults]');
  box.addEventListener('input', function () {
    var q = box.value.trim().toLowerCase(), shown = 0;
    cards.forEach(function (c) { var hit = !q || c.dataset.spText.indexOf(q) !== -1; c.hidden = !hit; if (hit) shown++; });
    none.hidden = shown !== 0;
  });
})();
document.querySelectorAll('.sp [data-delete-toggle]').forEach(function (button) {
  var panel = document.getElementById(button.getAttribute('aria-controls')), input = panel.querySelector('[data-expect]'), go = panel.querySelector('[type=submit]');
  function set(open) { panel.hidden = !open; button.setAttribute('aria-expanded', open ? 'true' : 'false'); if (open) input.focus(); else { input.value = ''; go.disabled = true; } }
  button.addEventListener('click', function () { set(panel.hidden); });
  panel.querySelector('[data-delete-cancel]').addEventListener('click', function () { set(false); button.focus(); });
  input.addEventListener('input', function () { go.disabled = input.value.trim().toLowerCase() !== input.dataset.expect; });
  panel.addEventListener('submit', function () { setTimeout(function () { go.disabled = true; go.textContent = 'Deleting…'; }, 0); });
});
document.querySelectorAll('.sp [data-copy]').forEach(function (button) {
  button.addEventListener('click', function () {
    var label = button.querySelector('span');
    (navigator.clipboard ? navigator.clipboard.writeText(button.dataset.copy) : Promise.reject()).then(function () {
      label.textContent = 'Copied'; setTimeout(function () { label.textContent = 'Copy link'; }, 1600);
    }).catch(function () { window.prompt('Copy this link', button.dataset.copy); });
  });
});
</script>
