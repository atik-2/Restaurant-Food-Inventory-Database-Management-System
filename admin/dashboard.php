<?php
/* ============================================================
   admin/dashboard.php  —  ek nojore sob kichu
   ============================================================ */
require __DIR__ . '/../config.php';
require_role('admin');

// chotto helper: ekta query theke ekta value
function one($conn, $sql) {
    return $conn->query($sql)->fetch_row()[0] ?? 0;
}

$total_food   = one($conn, "SELECT COUNT(*) FROM menu_items");
$total_orders = one($conn, "SELECT COUNT(*) FROM orders");
$total_sales  = one($conn, "SELECT IFNULL(SUM(total_amount),0) FROM orders WHERE status='Paid'");
$low_count    = one($conn, "SELECT COUNT(*) FROM ingredients WHERE quantity <= reorder_level");
$stock_value  = one($conn, "SELECT IFNULL(SUM(quantity*unit_price),0) FROM ingredients");
$offer_count  = one($conn, "SELECT COUNT(*) FROM menu_items WHERE discount > 0");

$low = $conn->query("SELECT * FROM v_low_stock ORDER BY quantity");

$recent = $conn->query(
    "SELECT o.order_id, IFNULL(c.name,'Walk-in') AS customer,
            e.name AS staff, o.order_date, o.status, o.total_amount
     FROM orders o
     LEFT JOIN customers c ON o.customer_id = c.customer_id
     JOIN employees e      ON o.employee_id = e.employee_id
     ORDER BY o.order_id DESC
     LIMIT 6");

$page_title = 'Dashboard';
require __DIR__ . '/../header.php';
?>

<div class="stats">
  <div class="stat"><span><?= $total_food ?></span>Food items</div>
  <div class="stat"><span><?= $offer_count ?></span>On discount</div>
  <div class="stat"><span><?= $total_orders ?></span>Total orders</div>
  <div class="stat money"><span><?= tk($total_sales) ?></span>Paid sales (BDT)</div>
  <div class="stat money"><span><?= tk($stock_value) ?></span>Stock value (BDT)</div>
  <div class="stat <?= $low_count ? 'warn' : '' ?>"><span><?= $low_count ?></span>Low stock</div>
</div>

<div class="card">
  <h2>Low stock alert</h2>
  <?php if ($low->num_rows === 0): ?>
    <p class="hint">Sob ingredient thik ache.</p>
  <?php else: ?>
    <table>
      <tr><th>Ingredient</th><th>Stock</th><th>Reorder level</th><th>Supplier</th></tr>
      <?php while ($r = $low->fetch_assoc()): ?>
      <tr class="lowrow">
        <td><?= h($r['name']) ?></td>
        <td><?= tk($r['quantity']) ?> <?= h($r['unit']) ?></td>
        <td><?= tk($r['reorder_level']) ?> <?= h($r['unit']) ?></td>
        <td><?= h($r['supplier'] ?? '-') ?></td>
      </tr>
      <?php endwhile; ?>
    </table>
    <p class="hint">Eta <code>v_low_stock</code> <b>VIEW</b> theke asche.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Recent orders</h2>
  <table>
    <tr><th>#</th><th>Customer</th><th>Staff</th><th>Date</th><th>Status</th><th>Total</th></tr>
    <?php while ($o = $recent->fetch_assoc()): ?>
    <tr>
      <td><?= (int)$o['order_id'] ?></td>
      <td><?= h($o['customer']) ?></td>
      <td><?= h($o['staff']) ?></td>
      <td><?= h($o['order_date']) ?></td>
      <td><span class="badge <?= strtolower($o['status']) ?>"><?= h($o['status']) ?></span></td>
      <td><?= tk($o['total_amount']) ?></td>
    </tr>
    <?php endwhile; ?>
  </table>
</div>

<?php
sql_box("SELECT COUNT(*) FROM menu_items;
SELECT IFNULL(SUM(total_amount),0) FROM orders WHERE status='Paid';
SELECT COUNT(*) FROM ingredients WHERE quantity <= reorder_level;
SELECT IFNULL(SUM(quantity*unit_price),0) FROM ingredients;

-- VIEW use kora hoyeche
SELECT * FROM v_low_stock ORDER BY quantity;

-- recent orders (3 table JOIN)
SELECT o.order_id, IFNULL(c.name,'Walk-in') AS customer, e.name AS staff,
       o.order_date, o.status, o.total_amount
FROM orders o
LEFT JOIN customers c ON o.customer_id = c.customer_id
JOIN employees e      ON o.employee_id = e.employee_id
ORDER BY o.order_id DESC LIMIT 6;");

require __DIR__ . '/../footer.php';
