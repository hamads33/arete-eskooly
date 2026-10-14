<aside class="sidebar" id="sidebar" aria-label="Primary navigation">
  <button class="icon-button mobile-sidebar-close" id="mobileSidebarClose" type="button" aria-label="Close navigation"><i data-lucide="x"></i></button>
  <div class="sidebar__scroll">
    <div class="nav-heading">menu</div>
    <div class="sidebar-search">
      <i data-lucide="search" aria-hidden="true"></i>
      <label class="sr-only" for="sidebarSearchInput">Search menu</label>
      <input id="sidebarSearchInput" type="search" placeholder="Search menu..." autocomplete="off">
    </div>
    <nav><ul class="nav-list">
<?php foreach ($navigation as $item):
    // Teachers have no institute dashboard; this entry takes them back to their own portal.
    if ($item['key'] === 'dashboard' && (hasRole('teacher') && !can('*') && !hasRole('admin') && !hasRole('principal'))) { $item['label'] = 'My Portal'; $item['route'] = '/portal/employee'; }
    $isActive = $activeMenu === $item['key'];
    $hasChildren = $item['children'] !== [];
    $itemLocked = (bool)$item['locked'] || (!$hasChildren && !$item['route']);
    $href = $item['route'] ? url($item['route']) : '#';
?>
      <li class="nav-item<?= $hasChildren && $isActive ? ' is-open' : '' ?>">
<?php if ($itemLocked): ?>
        <button class="nav-link nav-link--locked" type="button" data-tooltip="<?= e($item['label']) ?>" data-locked-module="<?= e($item['label']) ?>"<?= !empty($item['plan_locked']) ? ' data-locked-reason="plan"' : '' ?> onclick="window.showLockedModule('<?= e($item['label']) ?>')"<?= $hasChildren ? ' aria-expanded="false"' : '' ?>><i data-lucide="<?= e($item['icon']) ?>"></i><span class="nav-link__label"><?= e($item['label']) ?></span><?php if ($hasChildren): ?><i class="nav-link__chevron" data-lucide="chevron-down"></i><?php endif; ?></button>
<?php elseif ($hasChildren): ?>
        <button class="nav-link<?= $isActive ? ' is-active' : '' ?>" type="button" data-tooltip="<?= e($item['label']) ?>" data-submenu-trigger aria-expanded="<?= $isActive ? 'true' : 'false' ?>"><i data-lucide="<?= e($item['icon']) ?>"></i><span class="nav-link__label"><?= e($item['label']) ?></span><i class="nav-link__chevron" data-lucide="chevron-down"></i></button>
        <div class="submenu"><div class="submenu__inner"><ul class="submenu-list">
<?php foreach ($item['children'] as $child): $childActive = $activeSubmenu === $child['key']; $childLocked = (bool)($child['locked'] ?? false) || (!$child['route'] && $child['key'] !== 'logout'); ?>
          <li><?php if($child['key']==='logout'): ?><form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="submenu-link" type="submit"><?= e($child['label']) ?></button></form><?php elseif($childLocked): ?><button class="submenu-link submenu-link--locked" type="button" data-locked-module="<?= e($child['label']) ?>"<?= !empty($child['plan_locked']) ? ' data-locked-reason="plan"' : '' ?>><span><?= e($child['label']) ?></span><i data-lucide="lock-keyhole"></i></button><?php else: ?><a class="submenu-link<?= $childActive ? ' is-active' : '' ?>" href="<?= e($child['route'] ? url($child['route']) : '#') ?>"<?= $childActive ? ' aria-current="page"' : '' ?>><?= e($child['label']) ?></a><?php endif ?></li>
<?php endforeach; ?>
        </ul></div></div>
<?php else: ?>
        <a class="nav-link<?= $isActive ? ' is-active' : '' ?>" href="<?= e($href) ?>" data-tooltip="<?= e($item['label']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><i data-lucide="<?= e($item['icon']) ?>"></i><span class="nav-link__label"><?= e($item['label']) ?></span></a>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul></nav>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>
