<link rel="stylesheet" href="<?= e(asset('css/schools.css?v=6')) ?>">
<div class="sp">
<?php if ($submitted !== ''): ?>
<div class="auth-heading signup-heading"><span class="eyebrow">APPLICATION SUBMITTED</span><h1>Thank you<span>.</span></h1><p>Your application for <strong><?= e($submitted) ?>.<?= e($domain) ?></strong> is with the Arete team for review.</p></div>
<ol class="sp-steps">
  <li class="sp-step sp-step--done"><span class="sp-step__dot">✓</span><div><strong>Application submitted</strong><span>We have your school details and your administrator login.</span></div></li>
  <li class="sp-step sp-step--current"><span class="sp-step__dot">2</span><div><strong>Review by Arete</strong><span>Usually within one working day. You may get a call on the mobile number you gave.</span></div></li>
  <li class="sp-step"><span class="sp-step__dot">3</span><div><strong>Your portal goes live</strong><span>Sign in at your school's address with the email and password you just chose.</span></div></li>
</ol>
<a class="auth-submit" href="<?= url('/school-status?school=' . rawurlencode($submitted)) ?>" style="text-decoration:none"><span>Check application status</span><span aria-hidden="true">→</span></a>
<p class="auth-switch">Bookmark the status page, or open <strong><?= e($submitted) ?>.<?= e($domain) ?></strong> at any time to see where things stand.</p>
<?php else: ?>
<div class="auth-heading signup-heading"><span class="eyebrow">REGISTER YOUR SCHOOL</span><h1>Your own school portal<span>.</span></h1><p>Apply in two minutes. Once approved, your school gets a private portal with its own data, staff, students and fees.</p></div>
<form class="auth-form signup-form sp-form" method="post" action="<?= url('/register-school') ?>" autocomplete="on" enctype="multipart/form-data" data-signup>
<?= csrf_field() ?>
<div style="display:none" aria-hidden="true"><label>Leave this empty <input name="zx_hp_field" tabindex="-1" autocomplete="one-time-code" value=""></label></div>
<p class="sp-legend">YOUR SCHOOL</p>
<?php $fields=[['school_name','School name','text','organization','e.g. Bright Future School']]; require BASE_PATH.'/app/Views/auth/fields.php'; ?>
<?php $slugError = error('slug'); ?>
<div class="auth-field sp-slug"><label for="slug">Portal address</label>
  <div class="auth-control"><input id="slug" name="slug" type="text" autocomplete="off" autocapitalize="none" spellcheck="false" inputmode="url" placeholder="brightfuture" value="<?= e(old('slug')) ?>" required minlength="3" maxlength="30" aria-describedby="slug-hint"<?= $slugError ? ' aria-invalid="true"' : '' ?>><span class="sp-slug__suffix">.<?= e($domain) ?></span></div>
  <p class="sp-hint<?= $slugError ? ' sp-hint--bad' : '' ?>" id="slug-hint" role="status"><?= $slugError ? e($slugError) : 'Lowercase letters, numbers and hyphens. This becomes your login address.' ?></p>
</div>
<?php $fields=[['city','City','text','address-level2','e.g. Lahore']]; require BASE_PATH.'/app/Views/auth/fields.php'; ?>
<?php $logoError = error('school_logo'); ?>
<div class="auth-field"><label for="school_logo">School logo <span style="font-weight:400;color:#8a7d82">(optional)</span></label>
  <div class="auth-control"><input id="school_logo" name="school_logo" type="file" accept="image/png,image/jpeg"></div>
  <p class="sp-hint<?= $logoError ? ' sp-hint--bad' : '' ?>"><?= $logoError ? e($logoError) : 'PNG or JPG, up to 1 MB. It appears on your portal, login page, receipts and app icon. You can change it later.' ?></p>
</div>
<p class="sp-legend">ADMINISTRATOR ACCOUNT</p>
<?php $fields=[['admin_name','Your full name','text','name','e.g. Sara Khan'],['email','Email address','email','email','you@school.edu'],['phone','Mobile number','tel','tel','03xx xxxxxxx'],['password','Password','password','new-password','At least 8 characters'],['password_confirmation','Confirm password','password','new-password','Repeat password']]; require BASE_PATH.'/app/Views/auth/fields.php'; ?>
<button class="auth-submit" type="submit"><span>Submit application</span><span aria-hidden="true">↗</span></button>
</form>
<script>
(function () {
  var input = document.getElementById('slug'), hint = document.getElementById('slug-hint'), timer = null, base = hint.textContent;
  var name = document.getElementById('school_name'), touched = input.value !== '';
  function show(text, tone) { hint.textContent = text; hint.className = 'sp-hint' + (tone ? ' sp-hint--' + tone : ''); }
  function check() {
    var v = input.value;
    if (v.length < 3) { show(base, ''); return; }
    fetch('<?= url('/register-school/check') ?>?slug=' + encodeURIComponent(v), {headers: {Accept: 'application/json'}})
      .then(function (r) { return r.json(); })
      .then(function (d) { if (input.value === v) show(d.message, d.ok ? 'ok' : 'bad'); })
      .catch(function () {});
  }
  function clean() {
    var v = input.value.toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/-{2,}/g, '-');
    if (v !== input.value) input.value = v;
    clearTimeout(timer); timer = setTimeout(check, 350);
  }
  input.addEventListener('input', function () { touched = true; clean(); });
  name && name.addEventListener('input', function () {
    if (touched) return;
    input.value = name.value.toLowerCase().replace(/\b(school|system|college|academy|institute|the)\b/g, '').replace(/[^a-z0-9]/g, '').slice(0, 30);
    clean();
  });
  document.querySelector('[data-signup]').addEventListener('submit', function (e) {
    var b = e.target.querySelector('.auth-submit'); setTimeout(function () { b.disabled = true; }, 0);
  });
  if (input.value) check();
})();
</script>
<?php endif ?>
<p class="auth-switch">Already applied? <a href="<?= url('/school-status') ?>">Check your application</a></p>
</div>
