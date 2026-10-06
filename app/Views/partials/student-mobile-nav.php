<?php
// Shortcuts use the existing self-scoped routes. The full drawer remains available.
if (($audience ?? '') !== 'student') return;
$mobileLinks = [
    ['/portal/student', 'house', 'Home'],
    ['/portal/student/courses', 'book-open', 'Courses'],
    ['/portal/student/online-tests', 'clipboard-check', 'Tests'],
    ['/portal/student/homework', 'notebook-pen', 'Homework'],
    ['/portal/student/profile', 'user-round', 'Profile'],
];
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
?>
<nav class="student-mobile-nav" aria-label="Student shortcuts">
<?php foreach ($mobileLinks as [$path, $icon, $label]):
    $active = $path === '/portal/student' ? $currentPath === $path : ($currentPath === $path || str_starts_with((string) $currentPath, $path . '/'));
?>
  <a href="<?= e(url($path)) ?>"<?= $active ? ' aria-current="page"' : '' ?>><i data-lucide="<?= e($icon) ?>" aria-hidden="true"></i><span><?= e($label) ?></span></a>
<?php endforeach; ?>
</nav>
