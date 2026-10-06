<?php
/**
 * Teacher live classes.
 *
 * The class and subject options come from this teacher's active assignments, and the server
 * re-checks the same rule on submit — the narrowed <select> is convenience, not the control.
 * Nothing about Google's credentials appears anywhere on this page.
 */
$row = static function (array $m): void { ?>
  <article class="live-row live-row--<?= e($m['state']['state']) ?>">
    <div class="live-row__main">
      <div class="live-row__head">
        <span class="live-badge live-badge--<?= e($m['state']['state']) ?>"><?= e($m['state']['label']) ?></span>
        <?php if(($m['google_sync_status'] ?? '')==='pending'):?><span class="live-badge live-badge--warn">Link pending</span><?php endif?>
      </div>
      <h3 class="live-row__title"><?= e($m['title']) ?></h3>
      <p class="live-row__meta"><?= e($m['class_name'] ?: '') ?><?php if($m['subject_name']):?> · <?= e($m['subject_name']) ?><?php endif?></p>
      <p class="live-row__time"><i data-lucide="calendar-clock"></i><?= e(date('D, d M Y · g:i A', strtotime($m['local_start']))) ?><?php if($m['duration_minutes']):?> · <?= e($m['duration_minutes']) ?> min<?php endif?></p>
    </div>
    <div class="live-row__actions">
      <?php if($m['meet_link'] && $m['status']!=='cancelled'):?>
        <a class="btn btn--primary" href="<?= e($m['meet_link']) ?>" target="_blank" rel="noopener"><i data-lucide="video"></i>Open Meet</a>
        <button type="button" class="btn btn--ghost" data-copy-link="<?= e($m['meet_link']) ?>"><i data-lucide="copy"></i>Copy link</button>
      <?php endif?>
      <?php if($m['can_manage'] && $m['status']!=='cancelled'):?>
        <form method="post" action="<?= url('/portal/employee/teaching/live-classes/'.$m['id'].'/cancel') ?>" onsubmit="return confirm('Cancel this live class? Your students will no longer be able to join.');"><?= csrf_field() ?>
          <button class="btn btn--danger" type="submit"><i data-lucide="x"></i>Cancel</button>
        </form>
      <?php endif?>
    </div>
  </article>
<?php };
?>
<main class="main"><div class="main__inner"><div class="portal-page">
  <header class="portal-page__header">
    <div><span class="module-eyebrow">My teaching</span><h1>Live Classes</h1><p>Schedule Google Meet classes for the classes you are assigned to. Times shown in <?= e($timezone) ?>.</p></div>
  </header>

  <?php if(!$available):?>
    <div class="module-card"><div class="module-card__body"><p class="live-connection__status live-connection__status--error">Google Live Classes are temporarily unavailable. Please contact the administrator.</p></div></div>
  <?php endif?>

  <?php if(!$scope):?>
    <div class="module-card"><div class="compact-empty"><h3>No assigned classes</h3><p>You can host a live class once an administrator assigns you a class or subject.</p></div></div>
  <?php else:?>
  <section class="module-card">
    <div class="module-card__body">
      <h2>Start or schedule a class</h2>
      <div class="live-quick__actions">
        <button type="button" class="btn btn--primary" data-toggle-instant><i data-lucide="zap"></i>Start Instant Class</button>
        <button type="button" class="btn btn--secondary" data-toggle-schedule><i data-lucide="calendar-plus"></i>Schedule Class</button>
      </div>

      <?php
      // Both forms share the same picker markup; only the action and the date/time fields differ.
      $picker = static function (array $scope): void { ?>
        <fieldset class="field live-class-picker" data-live-class-picker>
          <legend>Classes <em>*</em></legend>
          <span class="live-class-picker__hint">Select one or more classes. They will share the same Meet link.</span>
          <div class="live-class-picker__options">
            <?php foreach($scope as $c):?><label><input type="checkbox" name="class_ids[]" value="<?= e($c['id']) ?>" data-live-class-check><span><?= e($c['name']) ?></span></label><?php endforeach?>
          </div>
        </fieldset>
        <label class="field"><span>Subject <em>(one class only)</em></span>
          <select class="form-select" name="subject_id" data-live-subject>
            <option value="">No specific subject</option>
            <?php foreach($scope as $c):foreach($c['subjects'] as $id=>$name):?><option value="<?= e($id) ?>"><?= e($c['name']) ?> · <?= e($name) ?></option><?php endforeach;endforeach?>
          </select>
        </label>
      <?php }; ?>

      <form class="live-instant" method="post" action="<?= url('/portal/employee/teaching/live-classes/instant') ?>" hidden data-instant-form><?= csrf_field() ?>
        <div class="form-grid">
          <?php $picker($scope); ?>
          <label class="field"><span>Title <em>(optional)</em></span><input class="form-input" type="text" name="title" maxlength="160" placeholder="Defaults to the subject name"></label>
          <label class="field"><span>Duration</span><select class="form-select" name="duration"><option value="30">30 minutes</option><option value="45">45 minutes</option><option value="60" selected>60 minutes</option><option value="90">90 minutes</option></select></label>
        </div>
        <input type="hidden" name="audience_type" value="SC">
        <button class="btn btn--primary" type="submit" <?= $available?'':'disabled' ?>><i data-lucide="video"></i>Start now</button>
      </form>

      <form class="live-schedule" method="post" action="<?= url('/portal/employee/teaching/live-classes') ?>" hidden data-schedule-form><?= csrf_field() ?>
        <div class="form-grid">
          <label class="field field--wide"><span>Title <em>*</em></span><input class="form-input" type="text" name="title" maxlength="160" required></label>
          <?php $picker($scope); ?>
          <label class="field"><span>Date <em>*</em></span><input class="form-input" type="date" name="date" required value="<?= e(date('Y-m-d')) ?>"></label>
          <label class="field"><span>Start time <em>*</em></span><input class="form-input" type="time" name="time" required></label>
          <label class="field"><span>Duration <em>*</em></span><select class="form-select" name="duration"><option value="30">30 minutes</option><option value="45">45 minutes</option><option value="60" selected>60 minutes</option><option value="90">90 minutes</option></select></label>
          <label class="field field--wide"><span>Description</span><textarea class="form-input" name="description" rows="2"></textarea></label>
        </div>
        <input type="hidden" name="audience_type" value="SC">
        <button class="btn btn--primary" type="submit" <?= $available?'':'disabled' ?>><i data-lucide="calendar-check"></i>Schedule class</button>
      </form>
    </div>
  </section>
  <?php endif?>

  <section class="module-section">
    <h2 class="module-section__title">Upcoming</h2>
    <?php if(!$upcoming):?><div class="module-card"><div class="compact-empty"><p>No upcoming live classes.</p></div></div>
    <?php else:?><div class="live-list"><?php foreach($upcoming as $m) $row($m); ?></div><?php endif?>
  </section>

  <?php if($past):?>
  <section class="module-section">
    <h2 class="module-section__title">Past &amp; cancelled</h2>
    <div class="live-list live-list--muted"><?php foreach(array_slice($past,0,20) as $m) $row($m); ?></div>
  </section>
  <?php endif?>
</div></div></main>
