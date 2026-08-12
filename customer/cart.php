<?php
/* ============================================================
   customer/cart.php  —  cart dekha + order place kora
   Ekhane TRANSACTION use kora hoyeche (COMMIT / ROLLBACK)
   ============================================================ */
require __DIR__ . '/../config.php';
require_role('customer');

$cart = $_SESSION['cart'] ?? [];

/* ---- cart theke bad dao ---- */
if (($_POST['action'] ?? '') === 'remove') {
    unset($_SESSION['cart'][(int)$_POST['menu_id']]);
    set_msg('Cart theke bad deya hoyeche.');
    header('Location: cart.php'); exit;
}

/* ---- quantity change ---- */
if (($_POST['action'] ?? '') === 'qty') {
    $id  = (int)$_POST['menu_id'];
    $qty = (int)$_POST['quantity'];
    if ($qty <= 0) { unset($_SESSION['cart'][$id]); }
    else if (isset($_SESSION['cart'][$id])) { $_SESSION['cart'][$id]['qty'] = $qty; }
    header('Location: cart.php'); exit;
}

/* ---- ORDER PLACE ---- */
if (($_POST['action'] ?? '') === 'place' && $cart) {

    // je waiter der moddhe prothom jon, take assign kori
    $emp = $conn->query("SELECT employee_id FROM employees
                         WHERE role = 'Waiter' ORDER BY employee_id LIMIT 1")->fetch_assoc();
    if (!$emp) {
        $emp = $conn->query("SELECT employee_id FROM employees
                             ORDER BY employee_id LIMIT 1")->fetch_assoc();
    }

    $conn->begin_transaction();
    try {
        // 1) order-er header row (total 0 diye shuru, trigger bariye dibe)
        $stmt = $conn->prepare(
            "INSERT INTO orders (customer_id, employee_id, order_date, status, total_amount)
             VALUES (?, ?, NOW(), 'Pending', 0)");
        $stmt->bind_param('ii', $_SESSION['customer_id'], $emp['employee_id']);
        $stmt->execute();
        $order_id = $conn->insert_id;
        $stmt->close();

        // 2) protita item — dam abar database theke, discount soho
        $get  = $conn->prepare(
            "SELECT ROUND(price - (price * discount / 100), 2) AS final_price
             FROM menu_items WHERE menu_id = ? AND is_available = 1");
        $ins  = $conn->prepare(
            "INSERT INTO order_items (order_id, menu_id, quantity, price)
             VALUES (?, ?, ?, ?)");
        $total = 0;   // trigger hisheb korar por database theke neya hobe

        foreach ($cart as $menu_id => $row) {
            $get->bind_param('i', $menu_id);
            $get->execute();
            $p = $get->get_result()->fetch_assoc();
            if (!$p) { throw new Exception("'{$row['name']}' ekhon available na."); }

            $price = (float)$p['final_price'];
            $qty   = (int)$row['qty'];
            $ins->bind_param('iiid', $order_id, $menu_id, $qty, $price);
            $ins->execute();          // <- ei INSERT-e trigger stock kombe
                                      //    ar orders.total_amount o bariye dibe
        }
        $get->close(); $ins->close();

        // 3) trigger je total ta hisheb korlo, sheta database theke pori
        $stmt = $conn->prepare("SELECT total_amount FROM orders WHERE order_id = ?");
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $total = (float)$stmt->get_result()->fetch_assoc()['total_amount'];
        $stmt->close();

        $conn->commit();              // sob thik -> pakka
        $_SESSION['cart'] = [];
        set_msg("Order #$order_id place hoyeche. Total " . tk($total) . " BDT.");
        header('Location: my_orders.php'); exit;

    } catch (Exception $e) {
        $conn->rollback();            // ekta fail -> sob undo
        set_msg('Order hoy ni (rollback kora hoyeche): ' . $e->getMessage(), 'err');
        header('Location: cart.php'); exit;
    }
}

/* ---- cart dekhanor jonno current dam ---- */
$lines = []; $grand = 0; $saved = 0;
foreach ($cart as $menu_id => $row) {
    $stmt = $conn->prepare(
        "SELECT name, price, discount,
                ROUND(price - (price * discount / 100), 2) AS final_price
         FROM menu_items WHERE menu_id = ?");
    $stmt->bind_param('i', $menu_id);
    $stmt->execute();
    $it = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$it) continue;

    $sub    = $it['final_price'] * $row['qty'];
    $grand += $sub;
    $saved += ($it['price'] - $it['final_price']) * $row['qty'];
    $lines[] = $it + ['menu_id' => $menu_id, 'qty' => $row['qty'], 'sub' => $sub];
}

$page_title = 'My Cart';
require __DIR__ . '/../header.php';
?>

<?php if (!$lines): ?>
  <div class="card">
    <p>Cart khali. <a href="menu.php">Menu theke khabar nao &rarr;</a></p>
  </div>
<?php else: ?>
  <div class="card">
    <h2>Cart</h2>
    <table>
      <tr><th>Food</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr>
      <?php foreach ($lines as $l): ?>
      <tr>
        <td><?= h($l['name']) ?>
            <?php if ($l['discount'] > 0): ?>
              <span class="tag sm"><?= rtrim(rtrim(number_format($l['discount'],2),'0'),'.') ?>% off</span>
            <?php endif; ?></td>
        <td>
          <?php if ($l['discount'] > 0): ?>
            <span class="old"><?= tk($l['price']) ?></span>
          <?php endif; ?>
          <b><?= tk($l['final_price']) ?></b>
        </td>
        <td>
          <form method="post" class="inline">
            <input type="hidden" name="action" value="qty">
            <input type="hidden" name="menu_id" value="<?= (int)$l['menu_id'] ?>">
            <input class="num" type="number" name="quantity" value="<?= (int)$l['qty'] ?>" min="0" max="20">
            <button class="sm" type="submit">Set</button>
          </form>
        </td>
        <td><?= tk($l['sub']) ?></td>
        <td>
          <form method="post">
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="menu_id" value="<?= (int)$l['menu_id'] ?>">
            <button class="sm danger" type="submit">Remove</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if ($saved > 0): ?>
      <tr><td colspan="3">Discount-e bachlo</td><td colspan="2" class="new">- <?= tk($saved) ?></td></tr>
      <?php endif; ?>
      <tr class="totalrow"><td colspan="3">Grand total</td><td colspan="2"><?= tk($grand) ?> BDT</td></tr>
    </table>

    <form method="post" style="margin-top:14px">
      <input type="hidden" name="action" value="place">
      <button type="submit">Place order</button>
    </form>
  </div>
<?php endif; ?>

<?php
sql_box("-- Order place — puro ta ek TRANSACTION er vitore
START TRANSACTION;

INSERT INTO orders (customer_id, employee_id, order_date, status, total_amount)
VALUES (?, ?, NOW(), 'Pending', 0);

-- protita item er jonno (dam database theke, discount baad diye)
INSERT INTO order_items (order_id, menu_id, quantity, price)
VALUES (?, ?, ?, ?);
-- ^ ei INSERT-e trigger trg_order_deduct_stock duita kaj kore:
--     1. recipes onusare ingredient stock komay
--     2. orders.total_amount e price*quantity jog kore

-- tai amader alada kore total bosate hoy na, shudhu pore ni:
SELECT total_amount FROM orders WHERE order_id = ?;

COMMIT;      -- sob thik hole
-- ROLLBACK; -- kono ekta fail korle sob undo hoye jay");

require __DIR__ . '/../footer.php';
