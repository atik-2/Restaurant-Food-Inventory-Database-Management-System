<?php
/* ============================================================
   admin/orders.php  —  sob order dekha + status change
   ============================================================ */
require __DIR__ . '/../config.php';
require_role('admin');

if (($_POST['action'] ?? '') === 'status') {
    $order_id = (int)$_POST['order_id'];
    $status   = $_POST['status'];
    $allowed  = ['Pending', 'Served', 'Paid', 'Cancelled'];

    if (!in_array($status, $allowed, true)) {
        set_msg('Ei status ta allowed na.', 'err');
    } else {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $stmt->bind_param('si', $status, $order_id);
        $stmt->execute();
        $stmt->close();
        set_msg("Order #$order_id ekhon '$status'.");
    }
    header('Location: orders.php'); exit;
}

$filter = $_GET['status'] ?? 'All';
$where  = '';
if (in_array($filter, ['Pending', 'Served', 'Paid', 'Cancelled'], true)) {
    $where = "WHERE o.status = '" . $conn->real_escape_string($filter) . "'";
}

$list_sql = "SELECT o.order_id, IFNULL(c.name,'Walk-in') AS customer,
                    e.name AS staff, o.order_date, o.status, o.total_amount,
                    COUNT(oi.order_item_id) AS items
             FROM orders o
             LEFT JOIN customers   c  ON o.customer_id = c.customer_id
             JOIN employees        e  ON o.employee_id = e.employee_id
             LEFT JOIN order_items oi ON o.order_id    = oi.order_id
             $where
             GROUP BY o.order_id, c.name, e.name, o.order_date, o.status, o.total_amount
             ORDER BY o.order_id DESC";
$orders = $conn->query($list_sql);

// ekta order-er detail dekhte chaile
$detail = null;
if (!empty($_GET['view'])) {
    $vid = (int)$_GET['view'];
    $stmt = $conn->prepare(
        "SELECT m.name, oi.quantity, oi.price, (oi.quantity * oi.price) AS subtotal
         FROM order_items oi
         JOIN menu_items m ON oi.menu_id = m.menu_id
         WHERE oi.order_id = ?");
    $stmt->bind_param('i', $vid);
    $stmt->execute();
    $detail = ['id' => $vid, 'rows' => $stmt->get_result()];
}

$page_title = 'All Orders';
require __DIR__ . '/../header.php';
?>

<div class="card">
  <h2>Filter</h2>
  <p class="tabs">
    <?php foreach (['All','Pending','Served','Paid','Cancelled'] as $f): ?>
      <a href="?status=<?= $f ?>" class="<?= $filter === $f ? 'on' : '' ?>"><?= $f ?></a>
    <?php endforeach; ?>
  </p>
</div>

<?php if ($detail): ?>
<div class="card">
  <h2>Order #<?= (int)$detail['id'] ?> — bill</h2>
  <table>
    <tr><th>Food</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
    <?php $sum = 0; while ($d = $detail['rows']->fetch_assoc()): $sum += $d['subtotal']; ?>
    <tr><td><?= h($d['name']) ?></td><td><?= (int)$d['quantity'] ?></td>
        <td><?= tk($d['price']) ?></td><td><?= tk($d['subtotal']) ?></td></tr>
    <?php endwhile; ?>
    <tr class="totalrow"><td colspan="3">Total</td><td><?= tk($sum) ?></td></tr>
  </table>
  <p><a href="orders.php">Close</a></p>
</div>
<?php endif; ?>

<div class="card">
  <h2>Orders (<?= $orders->num_rows ?>)</h2>
  <table>
    <tr><th>#</th><th>Customer</th><th>Staff</th><th>Date</th><th>Items</th>
        <th>Total</th><th>Status</th><th>Change</th><th>Bill</th></tr>
    <?php while ($o = $orders->fetch_assoc()): ?>
    <tr>
      <td><?= (int)$o['order_id'] ?></td>
      <td><?= h($o['customer']) ?></td>
      <td><?= h($o['staff']) ?></td>
      <td><?= h($o['order_date']) ?></td>
      <td><?= (int)$o['items'] ?></td>
      <td><?= tk($o['total_amount']) ?></td>
      <td><span class="badge <?= strtolower($o['status']) ?>"><?= h($o['status']) ?></span></td>
      <td>
        <form method="post" class="inline">
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
          <select name="status">
            <?php foreach (['Pending','Served','Paid','Cancelled'] as $s): ?>
              <option <?= $o['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <button class="sm" type="submit">Set</button>
        </form>
      </td>
      <td><a class="sm btnlink" href="?view=<?= (int)$o['order_id'] ?>">View</a></td>
    </tr>
    <?php endwhile; ?>
  </table>
</div>

<?php
sql_box("-- order list (LEFT JOIN + GROUP BY diye item count)
$list_sql

-- ekta order-er bill
SELECT m.name, oi.quantity, oi.price, (oi.quantity * oi.price) AS subtotal
FROM order_items oi
JOIN menu_items m ON oi.menu_id = m.menu_id
WHERE oi.order_id = ?;

-- status change
UPDATE orders SET status = ? WHERE order_id = ?;");

require __DIR__ . '/../footer.php';
