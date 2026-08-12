<?php
/* ============================================================
   customer/my_orders.php  —  nijer order history + bill
   ============================================================ */
require __DIR__ . '/../config.php';
require_role('customer');

$cid = (int)$_SESSION['customer_id'];

/* customer nijer order cancel korte pare (shudhu Pending obosthay) */
if (($_POST['action'] ?? '') === 'cancel') {
    $order_id = (int)$_POST['order_id'];
    // WHERE-e customer_id o ache -> onner order cancel kora jabe na
    $stmt = $conn->prepare(
        "UPDATE orders SET status = 'Cancelled'
         WHERE order_id = ? AND customer_id = ? AND status = 'Pending'");
    $stmt->bind_param('ii', $order_id, $cid);
    $stmt->execute();
    set_msg($stmt->affected_rows
        ? "Order #$order_id cancel kora hoyeche."
        : "Ei order ta ekhon cancel kora jabe na.", $stmt->affected_rows ? 'ok' : 'err');
    $stmt->close();
    header('Location: my_orders.php'); exit;
}

$list_sql = "SELECT o.order_id, o.order_date, o.status, o.total_amount,
                    COUNT(oi.order_item_id) AS items
             FROM orders o
             LEFT JOIN order_items oi ON o.order_id = oi.order_id
             WHERE o.customer_id = ?
             GROUP BY o.order_id, o.order_date, o.status, o.total_amount
             ORDER BY o.order_id DESC";
$stmt = $conn->prepare($list_sql);
$stmt->bind_param('i', $cid);
$stmt->execute();
$orders = $stmt->get_result();

/* ekta order-er bill */
$bill = null;
if (!empty($_GET['view'])) {
    $vid = (int)$_GET['view'];
    $b = $conn->prepare(
        "SELECT m.name, oi.quantity, oi.price, (oi.quantity * oi.price) AS subtotal
         FROM order_items oi
         JOIN menu_items m ON oi.menu_id = m.menu_id
         JOIN orders     o ON o.order_id = oi.order_id
         WHERE oi.order_id = ? AND o.customer_id = ?");
    $b->bind_param('ii', $vid, $cid);
    $b->execute();
    $bill = ['id' => $vid, 'rows' => $b->get_result()];
}

$page_title = 'My Orders';
require __DIR__ . '/../header.php';
?>

<?php if ($bill): ?>
<div class="card">
  <h2>Bill — Order #<?= (int)$bill['id'] ?></h2>
  <table>
    <tr><th>Food</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
    <?php $sum = 0; while ($r = $bill['rows']->fetch_assoc()): $sum += $r['subtotal']; ?>
      <tr><td><?= h($r['name']) ?></td><td><?= (int)$r['quantity'] ?></td>
          <td><?= tk($r['price']) ?></td><td><?= tk($r['subtotal']) ?></td></tr>
    <?php endwhile; ?>
    <tr class="totalrow"><td colspan="3">Total</td><td><?= tk($sum) ?> BDT</td></tr>
  </table>
  <p><a href="my_orders.php">Close</a></p>
</div>
<?php endif; ?>

<div class="card">
  <h2>Order history (<?= $orders->num_rows ?>)</h2>
  <?php if ($orders->num_rows === 0): ?>
    <p class="hint">Ekhono kono order koro nai. <a href="menu.php">Menu dekho &rarr;</a></p>
  <?php else: ?>
  <table>
    <tr><th>#</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th>Bill</th><th></th></tr>
    <?php while ($o = $orders->fetch_assoc()): ?>
    <tr>
      <td><?= (int)$o['order_id'] ?></td>
      <td><?= h($o['order_date']) ?></td>
      <td><?= (int)$o['items'] ?></td>
      <td><?= tk($o['total_amount']) ?></td>
      <td><span class="badge <?= strtolower($o['status']) ?>"><?= h($o['status']) ?></span></td>
      <td><a class="sm btnlink" href="?view=<?= (int)$o['order_id'] ?>">View</a></td>
      <td>
        <?php if ($o['status'] === 'Pending'): ?>
        <form method="post" onsubmit="return confirm('Order cancel korbe?')">
          <input type="hidden" name="action" value="cancel">
          <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
          <button class="sm danger" type="submit">Cancel</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
  <?php endif; ?>
</div>

<?php
sql_box("-- shudhu NIJER order (WHERE customer_id = ?)
$list_sql

-- bill
SELECT m.name, oi.quantity, oi.price, (oi.quantity * oi.price) AS subtotal
FROM order_items oi
JOIN menu_items m ON oi.menu_id = m.menu_id
JOIN orders     o ON o.order_id = oi.order_id
WHERE oi.order_id = ? AND o.customer_id = ?;

-- cancel (WHERE-e customer_id thakay onner order touch kora jabe na)
UPDATE orders SET status = 'Cancelled'
WHERE order_id = ? AND customer_id = ? AND status = 'Pending';");

require __DIR__ . '/../footer.php';
