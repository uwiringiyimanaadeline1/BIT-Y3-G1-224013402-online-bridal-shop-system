<?php
$pageTitle = $pageTitle ?? 'ONLINE BRIDAL SHOP';
$page = $page ?? pathinfo(basename((string)($_SERVER['SCRIPT_NAME'] ?? '')), PATHINFO_FILENAME);
$stylePath = __DIR__ . '/../../assets/css/style.css';
$scriptPath = __DIR__ . '/../../assets/js/app.js';
$styleVersion = (string)filemtime($stylePath) . '-' . (string)filesize($stylePath);
$scriptVersion = (string)filemtime($scriptPath) . '-' . (string)filesize($scriptPath);
$dashboardPages = ['admin','dresses','my-rentals','fittings','users','password-change'];
$isDashboardLayout = can_manage_shop() && in_array($page, $dashboardPages, true);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | Online Bridal Shop</title>
  <link rel="stylesheet" href="assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= e($styleVersion) ?>">
</head>
<body class="<?= $isDashboardLayout ? 'dashboard-page' : 'public-page' ?>">
<?php if ($isDashboardLayout): ?>
<div class="dashboard-shell">
  <aside class="dashboard-sidebar">
    <a class="brand" href="admin.php"><i class="bi bi-gem" aria-hidden="true"></i> Bridal Shop</a>
    <span class="sidebar-label">WORKSPACE</span>
    <nav class="sidebar-nav" aria-label="Dashboard navigation">
      <a class="<?= $page==='admin'?'active':'' ?>" href="admin.php"><i class="bi bi-grid-1x2" aria-hidden="true"></i> Overview</a>
      <a class="<?= $page==='dresses'?'active':'' ?>" href="dresses.php"><i class="bi bi-tags" aria-hidden="true"></i> Dresses</a>
      <a class="<?= $page==='my-rentals'?'active':'' ?>" href="my-rentals.php"><i class="bi bi-bag-check" aria-hidden="true"></i> Rentals</a>
      <a class="<?= $page==='fittings'?'active':'' ?>" href="fittings.php"><i class="bi bi-calendar-heart" aria-hidden="true"></i> Fittings</a>
      <a class="<?= $page==='users'?'active':'' ?>" href="users.php"><i class="bi bi-people" aria-hidden="true"></i> Users</a>
    </nav>
    <a class="sidebar-public-link" href="home.php"><i class="bi bi-arrow-up-right" aria-hidden="true"></i> View public shop</a>
  </aside>
  <div class="dashboard-main">
    <header class="dashboard-topbar">
      <form class="topbar-search" method="get" action="dresses.php" role="search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search" name="q" value="<?= e((string)($_GET['q'] ?? '')) ?>" placeholder="Search dresses..." aria-label="Search dresses">
      </form>
      <div class="profile-menu">
        <button class="profile-toggle" id="profileToggle" type="button" aria-expanded="false" aria-controls="profileDropdown">
          <span class="profile-avatar"><i class="bi bi-person" aria-hidden="true"></i></span>
          <span class="profile-name"><?= e($_SESSION['user']['username']) ?><small><?= e(ucfirst($_SESSION['user']['role'])) ?></small></span>
          <i class="bi bi-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="profile-dropdown" id="profileDropdown" hidden>
          <a href="password-change.php"><i class="bi bi-key" aria-hidden="true"></i> Change password</a>
          <form method="post" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Log out</button>
          </form>
        </div>
      </div>
    </header>
    <main class="container dashboard-content">
<?php else: ?>
<header class="site-header">
  <a class="brand" href="home.php"><i class="bi bi-gem" aria-hidden="true"></i> Online Bridal Shop</a>
  <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false"><i class="bi bi-list" aria-hidden="true"></i></button>
  <nav id="mainNav">
    <a href="home.php">Home</a>
    <a href="dresses.php">Dresses</a>
    <?php if (is_logged_in()): ?>
      <?php if (can_manage_shop()): ?><a href="admin.php">Dashboard</a><?php else: ?>
        <a href="my-rentals.php">My rentals</a>
        <a href="fittings.php">Fittings</a>
      <?php endif; ?>
      <form class="inline-form" method="post" action="logout.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button class="nav-link" type="submit">Log out (<?= e($_SESSION['user']['username']) ?>)</button>
      </form>
    <?php else: ?>
      <a class="nav-auth-button" href="login.php">Log in</a>
      <a class="nav-cta" href="register.php">Sign up</a>
    <?php endif; ?>
  </nav>
</header>
<main class="container">
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): $f=$_SESSION['flash']; unset($_SESSION['flash']); ?>
  <div class="alert <?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
<?php endif; ?>
