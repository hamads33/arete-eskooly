<?php
/**
 * Student live classes.
 *
 * No Meet URL is rendered here, deliberately: the Join button points at our own join route, which
 * re-checks audience, timing and cancellation before redirecting to Google. A link in this markup
 * would be a link anyone with the page source could reuse.
 */
$card = static function (array $m): void { ?>
  <article class="live-card live-card--<?= e($m['state']['state']) ?>">
    <div class="live-card__body">
      <div class="live-card__head">
        <span class="live-badge live-badge--<?= e($m['state']['state']) ?>"><?= e($m['state']['label']) ?></span>
        <?php if($m['subject']):?><span class="live-card__subject"><?= e($m['subject']) ?></span><?php endif?>
      </div>
      <h2 class="live-card__title"><?= e($m['title']) ?></h2>
      <dl class="live-card__meta">
        <?php if($m['teacher']):?><div><dt>Teacher</dt><dd><?= e($m['teacher']) ?></dd></div><?php endif?>
        <div><dt>Starts</dt><dd><?= e(date('D, d M Y · g:i A', strtotime($m['local_start']))) ?></dd></div>
        <?php if($m['duration']):?><div><dt>Duration</dt><dd><?= e($m['duration']) ?> minutes</dd></div><?php endif?>
      </dl>
      <div class="live-card__actions">
        <?php if($m['state']['joinable']):?>
          <a class="btn btn--primary live-card__join" href="<?= url('/portal/student/live-classes/'.$m['id'].'/join') ?>" target="_blank" rel="noopener"><i data-lucide="video"></i>Join Meeting</a>
        <?php elseif($m['state']['state']==='upcoming'):?>
          <span class="live-card__hint"><i data-lucide="clock-3"></i>Available <?= e($joinWindow) ?> minutes before class</span>
        <?php elseif($m['state']['state']==='cancelled'):?>
          <span class="live-card__hint"><i data-lucide="x-circle"></i>This live class was cancelled</span>
        <?php else:?>
          <span class="live-card__hint"><i data-lucide="check-circle-2"></i>This class has ended</span>
        <?php endif?>
      </div>
    </div>
  </article>
<?php };
?>
<main class="main"><div class="main__inner"><div class="portal-page">
  <header class="portal-page__header">
    <div><span class="module-eyebrow">Online learning</span><h1>Live Classes</h1><p>Your scheduled Google Meet classes. Times are shown in <?= e($timezone) ?>.</p></div>
  </header>

  <section class="module-section">
    <h2 class="module-section__title">Upcoming</h2>
    <?php if(!$upcoming):?>
      <div class="module-card"><div class="compact-empty"><h3>No live classes scheduled</h3><p>When your teacher schedules a live class it will appear here.</p></div></div>
    <?php else:?>
      <div class="live-grid"><?php foreach($upcoming as $m) $card($m); ?></div>
    <?php endif?>
  </section>

  <?php if($past):?>
  <section class="module-section">
    <h2 class="module-section__title">Past &amp; cancelled</h2>
    <div class="live-grid live-grid--muted"><?php foreach($past as $m) $card($m); ?></div>
  </section>
  <?php endif?>
</div></div></main>
