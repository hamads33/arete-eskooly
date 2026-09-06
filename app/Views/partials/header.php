<?php
/** Shared approved admin header. */
$currentUser = user();
$currentUserName = $currentUser['name'] ?? 'Administrator';
$currentUserRole = isset($currentUser['roles'][0]) ? ucwords(str_replace('-', ' ', $currentUser['roles'][0])) : 'Administrator';
$currentUserInitials = strtoupper(implode('', array_map(static fn (string $part): string => $part[0] ?? '', array_slice(preg_split('/\s+/', trim($currentUserName)) ?: [], 0, 2))));
?>
<header class="app-header">
      <?php $instituteName = $institute['name'] ?? ($demo['instituteName'] ?? 'Arete Coaching Center'); ?>
      <a class="brand" href="<?= url('/admin/dashboard') ?>" aria-label="<?= e($instituteName) ?> dashboard">
        <span class="brand__mark"><img src="<?= asset('images/arete-logo.png') ?>" alt="Arete logo"></span>
        <span class="brand__text">
          <strong data-field="institute-name"><?= e($instituteName) ?></strong>
          <span>Institute Admin</span>
        </span>
      </a>

      <div class="header-main">
        <button class="icon-button" id="sidebarToggle" type="button" aria-label="Toggle sidebar" aria-controls="sidebar" aria-expanded="true">
          <i data-lucide="panel-left"></i>
        </button>

        <div class="search" id="studentSearch">
          <button class="icon-button mobile-search-toggle" id="mobileSearchToggle" type="button" aria-label="Search students" aria-expanded="false">
            <i data-lucide="search"></i>
          </button>
          <div class="search__field">
            <i data-lucide="search" aria-hidden="true"></i>
            <label class="sr-only" for="studentSearchInput">Search student</label>
            <input id="studentSearchInput" type="search" placeholder="Search student..." autocomplete="off" aria-controls="studentSearchResults" aria-expanded="false">
            <span class="search__shortcut" aria-hidden="true">Ctrl K</span>
          </div>
          <div class="dropdown-menu search-results" id="studentSearchResults" role="listbox" aria-label="Student search results"></div>
        </div>

        <div class="header-spacer"></div>
        <div class="header-actions">
          <button class="icon-button header-action--optional" id="fullscreenButton" type="button" aria-label="Enter fullscreen" title="Fullscreen">
            <i data-lucide="maximize"></i>
          </button>

          <div class="dropdown" data-dropdown>
            <button class="icon-button" type="button" aria-label="Messages" aria-expanded="false" data-dropdown-trigger>
              <i data-lucide="messages-square"></i><span class="icon-button__badge">3</span>
            </button>
            <div class="dropdown-menu" role="menu">
              <div class="dropdown-menu__header"><strong>Messages</strong><button class="text-button" type="button" data-demo-action="New chat">New chat</button></div>
              <div class="dropdown-list">
                <!-- DEMO DATA ONLY: replace with current message previews in PHP. -->
                <a class="dropdown-item" href="#" role="menuitem"><span class="dropdown-item__icon"><i data-lucide="user-round"></i></span><span class="dropdown-item__content"><strong>Sarah Ahmed</strong><p>The parent meeting schedule is ready.</p><time>8 min ago</time></span></a>
                <a class="dropdown-item" href="#" role="menuitem"><span class="dropdown-item__icon"><i data-lucide="user-round"></i></span><span class="dropdown-item__content"><strong>Faculty Office</strong><p>Attendance report has been updated.</p><time>32 min ago</time></span></a>
                <a class="dropdown-item" href="#" role="menuitem"><span class="dropdown-item__icon"><i data-lucide="user-round"></i></span><span class="dropdown-item__content"><strong>Accounts Team</strong><p>August fee summary is now available.</p><time>1 hr ago</time></span></a>
              </div>
              <a class="dropdown-menu__footer" href="#">Open messaging</a>
            </div>
          </div>

          <div class="dropdown" data-dropdown>
            <button class="icon-button" type="button" aria-label="Notifications" aria-expanded="false" data-dropdown-trigger>
              <i data-lucide="bell"></i><span class="icon-button__badge">5</span>
            </button>
            <div class="dropdown-menu" role="menu">
              <div class="dropdown-menu__header"><strong>Notifications</strong><button class="text-button" type="button" data-demo-action="Notifications marked as read">Mark all read</button></div>
              <div class="dropdown-list">
                <!-- DEMO DATA ONLY: replace with notification records in PHP. -->
                <a class="dropdown-item" href="#" role="menuitem"><span class="dropdown-item__icon"><i data-lucide="user-plus"></i></span><span class="dropdown-item__content"><strong>New admission received</strong><p>Areeba Khan joined Grade 7-A.</p><time>12 min ago</time></span></a>
                <a class="dropdown-item" href="#" role="menuitem"><span class="dropdown-item__icon"><i data-lucide="badge-dollar-sign"></i></span><span class="dropdown-item__content"><strong>Fee payment recorded</strong><p>Invoice #GF-10248 was paid.</p><time>45 min ago</time></span></a>
                <a class="dropdown-item" href="#" role="menuitem"><span class="dropdown-item__icon"><i data-lucide="calendar-check"></i></span><span class="dropdown-item__content"><strong>Attendance completed</strong><p>18 of 22 classes have submitted.</p><time>2 hrs ago</time></span></a>
              </div>
              <a class="dropdown-menu__footer" href="#">View all notifications</a>
            </div>
          </div>

          <div class="dropdown" data-dropdown>
            <button class="profile-trigger" type="button" aria-expanded="false" data-dropdown-trigger>
              <span class="avatar" aria-hidden="true"><?= e($currentUserInitials ?: 'A') ?></span>
              <span class="profile-trigger__copy">
                <strong data-field="current-user-name"><?= e($currentUserName) ?></strong><span data-field="user-role"><?= e($currentUserRole) ?></span>
              </span>
              <i data-lucide="chevron-down" aria-hidden="true"></i>
            </button>
            <div class="dropdown-menu profile-menu" role="menu">
              <div class="profile-summary"><span class="avatar"><?= e($currentUserInitials ?: 'A') ?></span><span><strong><?= e($currentUserName) ?></strong><span><?= e($currentUser['email'] ?? '') ?></span></span></div>
              <a class="profile-link" href="<?= url('/admin/settings/account') ?>" role="menuitem"><i data-lucide="settings"></i>Account Settings</a>
              <a class="profile-link" href="<?= url('/admin/settings/institute-profile') ?>" role="menuitem"><i data-lucide="user-round"></i>Profile</a>
              <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="profile-link profile-link--danger" type="submit" role="menuitem"><i data-lucide="log-out"></i>Logout</button></form>
            </div>
          </div>
        </div>
      </div>
    </header>
