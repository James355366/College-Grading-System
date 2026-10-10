<?php
/**
 * Shared layout shell — sidebar + topbar.
 * Callers must define: $navItems, $activeId, $roleLabel, $pageTitle, $assetBase
 */
$u = current_session();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?> — College Grading System</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= h($assetBase ?? '') ?>assets/css/style.css">
</head>
<body class="app-body">

<!-- ── SIDEBAR (Bootstrap offcanvas on mobile, static column on desktop) ── -->
<div class="offcanvas offcanvas-start sidebar-offcanvas" tabindex="-1" id="sidebarOffcanvas" data-bs-scroll="true">
  <div class="offcanvas-body p-0 d-flex flex-column h-100">
    <div class="sidebar-brand">
      <div class="brand-icon">🎓</div>
      <div>
        <p class="sb-title">Grading System</p>
        <p class="sb-role"><?= h($roleLabel) ?></p>
      </div>
      <button type="button" class="btn-close btn-close-white ms-auto d-lg-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <nav class="sidebar-nav flex-grow-1">
      <?php foreach ($navItems as $item): ?>
        <a href="<?= h($item['href']) ?>" class="sb-link <?= $item['id'] === $activeId ? 'active' : '' ?>">
          <span class="sb-icon"><?= $item['icon'] ?></span>
          <span><?= h($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
      <div class="saved-line"><i class="bi bi-floppy me-1"></i><span data-save-status role="status">All changes saved</span></div>
      <div class="who-row">
        <div class="overflow-hidden">
          <p class="uname"><?= h($u['name'] ?? '') ?></p>
          <p class="uhandle">@<?= h($u['username'] ?? '') ?></p>
        </div>
        <form method="post" action="<?= h(base_url()) ?>/logout.php">
          <?= csrf_field() ?>
          <button type="submit" class="logout-btn" title="Logout"><i class="bi bi-power"></i></button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ── STATIC SIDEBAR (desktop only, always visible) ── -->
<div class="d-none d-lg-flex flex-column sidebar-static" id="sidebarStatic">
  <div class="sidebar-brand">
    <div class="brand-icon">🎓</div>
    <div>
      <p class="sb-title">Grading System</p>
      <p class="sb-role"><?= h($roleLabel) ?></p>
    </div>
  </div>

  <nav class="sidebar-nav flex-grow-1">
    <?php foreach ($navItems as $item): ?>
      <a href="<?= h($item['href']) ?>" class="sb-link <?= $item['id'] === $activeId ? 'active' : '' ?>">
        <span class="sb-icon"><?= $item['icon'] ?></span>
        <span><?= h($item['label']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-foot">
    <div class="saved-line"><i class="bi bi-floppy me-1"></i><span data-save-status role="status">All changes saved</span></div>
    <div class="who-row">
      <div class="overflow-hidden">
        <p class="uname"><?= h($u['name'] ?? '') ?></p>
        <p class="uhandle">@<?= h($u['username'] ?? '') ?></p>
      </div>
      <form method="post" action="<?= h(base_url()) ?>/logout.php">
        <?= csrf_field() ?>
          <button type="submit" class="logout-btn" title="Logout"><i class="bi bi-power"></i></button>
      </form>
    </div>
  </div>
</div>

<!-- ── MAIN COLUMN ── -->
<div class="main-col">
  <header class="topbar">
    <button class="menu-toggle d-lg-none" type="button"
            data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
      <i class="bi bi-list"></i>
    </button>
    <h1 class="topbar-title"><?= h($pageTitle) ?></h1>
  </header>

  <main class="page-content">
    <div id="toast" class="toast-custom"></div>
