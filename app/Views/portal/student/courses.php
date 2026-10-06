<?php
$initials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $parts = array_values(array_filter($parts, static fn($p) => !in_array(strtolower(rtrim($p, '.')), ['mr', 'mrs', 'ms', 'miss', 'dr', 'prof', 'sir'], true)));
    return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
};
$liveCount = count(array_filter($courses, static fn($c) => $c['live'] && $c['live']['state']['state'] === 'live'));
?>
<main class="main"><div class="main__inner"><div class="portal-page courses-page">
<header class="portal-page__header">
  <div><span class="module-eyebrow"><?= e($student['class_name'] ?? 'My class') ?></span><h1>My Courses</h1><p><?= count($courses) ?> subject<?= count($courses) === 1 ? '' : 's' ?> this session<?= $liveCount ? ' · <strong class="courses-live-note">' . $liveCount . ' live now</strong>' : '' ?></p></div>
</header>

<?php if (!$courses): ?>
<div class="lecture-empty"><i data-lucide="graduation-cap"></i><h3>No courses yet</h3><p>Your subjects will appear here once they are assigned to your class.</p></div>
<?php else: ?>
<div class="course-grid">
<?php foreach ($courses as $c):
    $t = $c['teacher'];
    $live = $c['live'];
    $isLive = $live && $live['state']['state'] === 'live';
    $tiles = [
        ['Lectures', 'clapperboard', $c['lectures'], url('/portal/student/recorded-lectures?subject=' . $c['id'])],
        ['Assignments', 'notebook-pen', $c['homework'], url('/portal/student/homework')],
        ['Quizzes', 'clipboard-check', $c['tests'], url('/portal/student/online-tests')],
        ['Notes', 'file-text', $c['notes'], url('/portal/student/notes')],
        ['Announcements', 'megaphone', ['total' => $c['announcements'], 'new' => 0], url('/portal/announcements')],
    ];
?>
  <article class="course-card<?= $isLive ? ' is-live' : '' ?>" style="--course-hue: <?= ($c['id'] * 47) % 360 ?>">
    <header class="course-card__head">
      <div class="course-card__title">
        <h2><?= e($c['name']) ?></h2>
        <p><?= e($student['class_name'] ?? '') ?><?= $c['total_marks'] ? ' · ' . e(rtrim(rtrim(number_format((float) $c['total_marks'], 2, '.', ''), '0'), '.')) . ' marks' : '' ?></p>
      </div>
      <a class="course-card__count" href="<?= e(url('/portal/student/recorded-lectures?subject=' . $c['id'])) ?>" title="Recorded lectures in <?= e($c['name']) ?>">
        <strong><?= (int) $c['lectures']['total'] ?></strong><span>lecture<?= $c['lectures']['total'] === 1 ? '' : 's' ?></span>
      </a>
    </header>

    <div class="course-card__teacher">
      <?php if ($t): ?>
        <span class="course-avatar"><?php if ($t['picture'] && is_file(BASE_PATH . '/public/' . ltrim($t['picture'], '/'))): ?><img src="<?= e(url('/' . ltrim($t['picture'], '/'))) ?>" alt="" loading="lazy"><?php else: ?><?= e($initials($t['name'])) ?><?php endif ?></span>
        <div><strong><?= e($t['name']) ?></strong><span><?= e($t['education'] ?: ($t['role'] ?: 'Teacher')) ?></span></div>
      <?php else: ?>
        <span class="course-avatar course-avatar--empty"><i data-lucide="user-round"></i></span>
        <div><strong>Teacher to be assigned</strong><span>Your institute will add the teacher soon.</span></div>
      <?php endif ?>
    </div>

    <?php if ($live): ?>
    <a class="course-card__live" href="<?= e(url($isLive ? '/portal/student/live-classes/' . $live['id'] . '/join' : '/portal/student/live-classes')) ?>"<?= $isLive ? ' target="_blank" rel="noopener"' : '' ?>>
      <span class="course-live-dot" aria-hidden="true"></span>
      <span><strong><?= $isLive ? 'Live now' : 'Starting soon' ?></strong> · <?= e($live['title']) ?></span>
      <span class="course-card__live-go"><?= $isLive ? 'Join' : 'View' ?> <i data-lucide="arrow-right"></i></span>
    </a>
    <?php endif ?>

    <nav class="course-card__tiles" aria-label="<?= e($c['name']) ?> resources">
      <?php foreach ($tiles as [$label, $icon, $n, $href]): ?>
      <a class="course-tile<?= $n['total'] ? '' : ' is-empty' ?>" href="<?= e($href) ?>">
        <span class="course-tile__icon"><i data-lucide="<?= $icon ?>"></i><?php if ($n['new']): ?><span class="course-tile__new" title="<?= (int) $n['new'] ?> new"><?= (int) $n['new'] ?></span><?php endif ?></span>
        <span class="course-tile__label"><?= e($label) ?></span>
        <span class="course-tile__num"><?= (int) $n['total'] ?></span>
      </a>
      <?php endforeach ?>
      <a class="course-tile course-tile--live<?= $isLive ? ' is-live' : ($live ? ' is-soon' : ' is-empty') ?>" href="<?= e(url('/portal/student/live-classes')) ?>">
        <span class="course-tile__icon"><i data-lucide="video"></i></span>
        <span class="course-tile__label">Live Class</span>
        <span class="course-tile__num"><?= $isLive ? 'On air' : ($live ? 'Soon' : '—') ?></span>
      </a>
    </nav>
  </article>
<?php endforeach ?>
</div>
<?php endif ?>
</div></div></main>
