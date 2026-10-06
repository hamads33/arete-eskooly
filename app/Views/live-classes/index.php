<?php
/**
 * Admin Live Classes.
 *
 * Sections, in the order the brief specifies: Google Connection, Quick Actions, Live Now,
 * Upcoming, Past, Cancelled. The connection card shows the account address and status only —
 * never a token, a client secret or a Google trace.
 */
$connected = ($connection['status'] ?? '') === 'connected';
$row = static function (array $m) use ($timezone): void { ?>
  <article class="live-row live-row--<?= e($m['state']['state']) ?>">
    <div class="live-row__main">
      <div class="live-row__head">
        <span class="live-badge live-badge--<?= e($m['state']['state']) ?>"><?= e($m['state']['label']) ?></span>
        <?php if(($m['google_sync_status'] ?? '')==='pending'):?><span class="live-badge live-badge--warn">Meet link pending</span><?php endif?>
        <?php if(($m['google_sync_status'] ?? '')==='failed'):?><span class="live-badge live-badge--error">Google sync failed</span><?php endif?>
      </div>
      <h3 class="live-row__title"><?= e($m['title']) ?></h3>
      <p class="live-row__meta">
        <?= e($m['class_name'] ?: 'No class') ?><?php if($m['section_name']):?> · <?= e($m['section_name']) ?><?php endif?>
        <?php if($m['subject_name']):?> · <?= e($m['subject_name']) ?><?php endif?>
        <?php if($m['teacher_name']):?> · <?= e($m['teacher_name']) ?><?php endif?>
      </p>
      <p class="live-row__time"><i data-lucide="calendar-clock"></i><?= e(date('D, d M Y · g:i A', strtotime($m['local_start']))) ?><?php if($m['duration_minutes']):?> · <?= e($m['duration_minutes']) ?> min<?php endif?></p>
      <?php if($m['last_google_error']):?><p class="live-row__error"><?= e($m['last_google_error']) ?></p><?php endif?>
    </div>
    <div class="live-row__actions">
      <?php if($m['meet_link'] && $m['status']!=='cancelled'):?>
        <a class="btn btn--primary" href="<?= e($m['meet_link']) ?>" target="_blank" rel="noopener"><i data-lucide="video"></i>Open Meet</a>
        <button type="button" class="btn btn--ghost" data-copy-link="<?= e($m['meet_link']) ?>"><i data-lucide="copy"></i>Copy link</button>
      <?php endif?>
      <?php if($m['status']!=='cancelled'):?>
        <a class="btn btn--secondary" href="<?= url('/admin/live-classes/'.$m['id'].'/edit') ?>"><i data-lucide="pencil"></i>Edit</a>
        <?php if(($m['google_sync_status'] ?? '')!=='synced'):?>
        <form method="post" action="<?= url('/admin/live-classes/'.$m['id'].'/sync') ?>"><?= csrf_field() ?>
          <button class="btn btn--ghost" type="submit"><i data-lucide="refresh-cw"></i>Retry Google sync</button>
        </form>
        <?php endif?>
        <form method="post" action="<?= url('/admin/live-classes/'.$m['id'].'/cancel') ?>" onsubmit="return confirm('Cancel this live class? Students will no longer be able to join it.');"><?= csrf_field() ?>
          <button class="btn btn--danger" type="submit"><i data-lucide="x"></i>Cancel</button>
        </form>
      <?php endif?>
    </div>
  </article>
<?php };
$section = static function (string $title, array $items, string $empty) use ($row): void { ?>
  <section class="module-section">
    <h2 class="module-section__title"><?= e($title) ?></h2>
    <?php if(!$items):?><div class="module-card"><div class="compact-empty"><p><?= e($empty) ?></p></div></div>
    <?php else:?><div class="live-list"><?php foreach($items as $m) $row($m); ?></div><?php endif?>
  </section>
<?php };
?>
<main class="main"><div class="main__inner"><div class="admin-page">
  <header class="admin-page__header">
    <div><span class="module-eyebrow">Online learning</span><h1>Live Classes</h1><p>Google Meet classes for this institute. Times shown in <?= e($timezone) ?>.</p></div>
  </header>

  <section class="module-card live-connection">
    <div class="module-card__body live-connection__body">
      <div>
        <h2>Google Connection</h2>
        <?php if(!$googleConfigured):?>
          <p class="live-connection__status live-connection__status--error">Google integration is not configured on this server. Set <code>GOOGLE_CLIENT_ID</code>, <code>GOOGLE_CLIENT_SECRET</code> and <code>GOOGLE_REDIRECT_URI</code> in the environment.</p>
        <?php elseif($connected):?>
          <p class="live-connection__status live-connection__status--ok">Connected<?php if($connection['account_email']):?> as <strong><?= e($connection['account_email']) ?></strong><?php endif?> · calendar <code><?= e($connection['calendar_id']) ?></code></p>
        <?php elseif(($connection['status'] ?? '')==='reconnect_required'):?>
          <p class="live-connection__status live-connection__status--error">Google account connection has expired or was revoked. Please reconnect Google Calendar.</p>
        <?php else:?>
          <p class="live-connection__status">Not connected. Connect the official institute Google account once; teachers then schedule through it without any Google sign-in of their own.</p>
        <?php endif?>
        <p class="live-connection__note"><i data-lucide="info"></i>Students join from this portal and never sign in to Google here. Whether Google admits a guest without the host present depends on your Google Workspace or Meet settings.</p>
      </div>
      <?php if($googleConfigured):?>
      <div class="live-connection__actions">
        <a class="btn btn--<?= $connected?'secondary':'primary' ?>" href="<?= url('/admin/integrations/google/connect') ?>"><i data-lucide="link"></i><?= $connected?'Reconnect':'Connect Google' ?></a>
        <?php if($connected||($connection['status'] ?? '')==='reconnect_required'):?>
        <form method="post" action="<?= url('/admin/integrations/google/disconnect') ?>" onsubmit="return confirm('Disconnect Google Calendar? Existing meetings keep their links, but no new ones can be created until you reconnect.');"><?= csrf_field() ?>
          <button class="btn btn--ghost" type="submit"><i data-lucide="unlink"></i>Disconnect</button>
        </form>
        <?php endif?>
      </div>
      <?php endif?>
    </div>
  </section>

  <section class="module-card live-quick">
    <div class="module-card__body">
      <h2>Quick actions</h2>
      <div class="live-quick__actions">
        <a class="btn btn--primary" href="<?= url('/admin/live-classes/create') ?>"><i data-lucide="calendar-plus"></i>Schedule Live Class</a>
        <button type="button" class="btn btn--secondary" data-toggle-instant><i data-lucide="zap"></i>Start Instant Class</button>
      </div>
      <?php if(!$connected):?><p class="live-quick__blocked">Google Calendar is not connected. Connect the institute Google account first.</p><?php endif?>
      <form class="live-instant" method="post" action="<?= url('/admin/live-classes/instant') ?>" hidden data-instant-form><?= csrf_field() ?>
        <div class="form-grid">
          <fieldset class="field field--wide live-class-picker" data-live-class-picker>
            <legend>Classes <em>*</em></legend>
            <small class="live-class-picker__hint">Select one or more classes for this shared Meet.</small>
            <div class="live-class-picker__options"><?php foreach($classes as $c):?><label><input type="checkbox" name="class_ids[]" value="<?= e($c['id']) ?>" data-live-class-check><span><?= e($c['name']) ?></span></label><?php endforeach?></div>
          </fieldset>
          <label class="field"><span>Title <em>(optional)</em></span><input class="form-input" type="text" name="title" maxlength="160" placeholder="Defaults to the subject name"></label>
          <label class="field"><span>Duration</span>
            <select class="form-select" name="duration"><option value="30">30 minutes</option><option value="45">45 minutes</option><option value="60" selected>60 minutes</option><option value="90">90 minutes</option></select>
          </label>
        </div>
        <input type="hidden" name="audience_type" value="SC">
        <button class="btn btn--primary" type="submit" <?= $connected?'':'disabled' ?>><i data-lucide="video"></i>Start now</button>
      </form>
    </div>
  </section>

  <?php $section('Live now', $buckets['live'], 'No class is running right now.'); ?>
  <?php $section('Upcoming', $buckets['upcoming'], 'No upcoming live classes.'); ?>
  <?php $section('Past', $buckets['past'], 'No past live classes yet.'); ?>
  <?php $section('Cancelled', $buckets['cancelled'], 'No cancelled live classes.'); ?>
</div></div></main>
