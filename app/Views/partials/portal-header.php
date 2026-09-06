<?php
/** Portal header. Deliberately omits the admin search and admin-only profile links. */
$currentUser = user();
$currentUserName = $currentUser['name'] ?? 'Portal user';
$currentUserRole = ($audience ?? 'student') === 'employee' ? 'Employee' : 'Student';
$currentUserInitials = strtoupper(implode('', array_map(
    static fn (string $part): string => $part[0] ?? '',
    array_slice(preg_split('/\s+/', trim($currentUserName)) ?: [], 0, 2)
)));
$home = ($audience ?? 'student') === 'employee' ? '/portal/employee' : '/portal/student';
$instituteName = $institute['name'] ?? 'Institute';
?>
<header class="app-header">
  <a class="brand" href="<?= e(url($home)) ?>" aria-label="<?= e($instituteName) ?> portal">
    <span class="brand__mark"><img src="<?= asset('images/arete-logo.png') ?>" alt="Institute logo"></span>
    <span class="brand__text">
      <strong data-field="institute-name"><?= e($instituteName) ?></strong>
      <span><?= e($currentUserRole) ?> Portal</span>
    </span>
  </a>

  <div class="header-main">
    <button class="icon-button" id="sidebarToggle" type="button" aria-label="Toggle sidebar" aria-controls="sidebar" aria-expanded="true">
      <i data-lucide="panel-left"></i>
    </button>

    <div class="header-spacer"></div>
    <div class="header-actions">
      <div class="dropdown" data-dropdown>
        <button class="profile-trigger" type="button" aria-expanded="false" data-dropdown-trigger>
          <span class="avatar" aria-hidden="true"><?= e($currentUserInitials ?: 'U') ?></span>
          <span class="profile-trigger__copy">
            <strong data-field="current-user-name"><?= e($currentUserName) ?></strong><span data-field="user-role"><?= e($currentUserRole) ?></span>
          </span>
          <i data-lucide="chevron-down" aria-hidden="true"></i>
        </button>
        <div class="dropdown-menu profile-menu" role="menu">
          <div class="profile-summary">
            <span class="avatar"><?= e($currentUserInitials ?: 'U') ?></span>
            <span><strong><?= e($currentUserName) ?></strong><span><?= e($currentUser['email'] ?? '') ?></span></span>
          </div>
          <a class="profile-link" href="<?= e(url($home . '/profile')) ?>" role="menuitem"><i data-lucide="user-round"></i>My Profile</a>
          <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="profile-link profile-link--danger" type="submit" role="menuitem"><i data-lucide="log-out"></i>Logout</button></form>
        </div>
      </div>
    </div>
  </div>
</header>
