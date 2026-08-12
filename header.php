<?php
/* ============================================================
   header.php  —  sob panel page-er upor-er ongsho
   ============================================================ */
if (!isset($conn)) { require __DIR__ . '/config.php'; }

$role  = $_SESSION['role']  ?? '';
$uname = $_SESSION['uname'] ?? '';
$base  = ($role === 'admin') ? 'admin' : 'customer';
$cur   = basename($_SERVER['PHP_SELF']);

$menus = ($role === 'admin')
    ? ['dashboard.php' => 'Dashboard',
       'food.php'      => 'Food & Price',
       'inventory.php' => 'Inventory',
       'purchase.php'  => 'Purchase',
       'orders.php'    => 'Orders',
       'reports.php'   => 'Reports']
    : ['menu.php'      => 'Menu',
       'cart.php'      => 'My Cart',
       'my_orders.php' => 'My Orders'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title ?? 'Restaurant DBMS') ?></title>
<link rel="stylesheet" href="../panel.css">
</head>
<body>

<header class="topbar <?= $role === 'admin' ? 'admin' : 'cust' ?>">
  <div class="brand">
    <strong><?= $role === 'admin' ? 'Admin Panel' : 'Customer Panel' ?></strong>
    <span>Restaurant &amp; Food Inventory System</span>
  </div>
  <div class="who">
    <?= h($uname) ?>
    <a class="logout" href="../logout.php">Logout</a>
  </div>
</header>

<nav class="panelnav">
  <?php foreach ($menus as $file => $label): ?>
    <a href="<?= h($file) ?>" class="<?= $cur === $file ? 'active' : '' ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
  <a href="../index.html" class="right">&larr; Documentation</a>
</nav>

<main>
<h1><?= h($page_title ?? '') ?></h1>
<?php show_msg(); ?>
