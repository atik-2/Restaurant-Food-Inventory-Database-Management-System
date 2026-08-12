<?php
/* ============================================================
   customer/menu.php  —  khabar dekhe cart-e add kora
   ============================================================ */
require __DIR__ . '/../config.php';
require_role('customer');

if (($_POST['action'] ?? '') === 'add_cart') {
    $menu_id = (int)$_POST['menu_id'];
    $qty     = max(1, (int)$_POST['quantity']);

    // dam session theke ni na — database theke ni (customer form change korte pare)
    $stmt = $conn->prepare(
        "SELECT name, price, discount,
                ROUND(price - (price * discount / 100), 2) AS final_price
         FROM menu_items
         WHERE menu_id = ? AND is_available = 1");
    $stmt->bind_param('i', $menu_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item) {
        set_msg('Ei item ekhon pawa jacche na.', 'err');
    } else {
        if (!isset($_SESSION['cart'][$menu_id])) {
            $_SESSION['cart'][$menu_id] = ['name' => $item['name'], 'qty' => 0];
        }
        $_SESSION['cart'][$menu_id]['qty'] += $qty;
        set_msg("$qty x {$item['name']} cart-e add hoyeche.");
    }
    header('Location: menu.php'); exit;
}

$list_sql = "SELECT menu_id, name, category, price, discount,
                    ROUND(price - (price * discount / 100), 2) AS final_price
             FROM menu_items
             WHERE is_available = 1
             ORDER BY category, name";
$items = $conn->query($list_sql);

// category onusare sajai
$by_cat = [];
while ($r = $items->fetch_assoc()) { $by_cat[$r['category']][] = $r; }

$cart_count = array_sum(array_column($_SESSION['cart'] ?? [], 'qty'));

$page_title = 'Menu';
require __DIR__ . '/../header.php';
?>

<p class="hint">
  Cart-e ekhon <b><?= $cart_count ?></b> ta item ache.
  <a href="cart.php">Cart dekho &rarr;</a>
</p>

<?php foreach ($by_cat as $cat => $rows): ?>
<div class="card">
  <h2><?= h($cat) ?></h2>
  <div class="foodgrid">
    <?php foreach ($rows as $it): ?>
    <div class="food">
      <?php if ($it['discount'] > 0): ?>
        <span class="tag"><?= rtrim(rtrim(number_format($it['discount'],2), '0'), '.') ?>% OFF</span>
      <?php endif; ?>
      <h3><?= h($it['name']) ?></h3>
      <p class="price">
        <?php if ($it['discount'] > 0): ?>
          <span class="old"><?= tk($it['price']) ?></span>
          <b class="new"><?= tk($it['final_price']) ?></b> BDT
        <?php else: ?>
          <b><?= tk($it['price']) ?></b> BDT
        <?php endif; ?>
      </p>
      <form method="post" class="inline">
        <input type="hidden" name="action" value="add_cart">
        <input type="hidden" name="menu_id" value="<?= (int)$it['menu_id'] ?>">
        <input class="num" type="number" name="quantity" value="1" min="1" max="20">
        <button class="sm" type="submit">Add to cart</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<?php
sql_box("-- Menu list. Discount baad diye final price ekhanei calculate hoy.
$list_sql

-- Cart-e add korar somoy dam abar database theke neya hoy (safety)
SELECT name, price, discount,
       ROUND(price - (price * discount / 100), 2) AS final_price
FROM menu_items
WHERE menu_id = ? AND is_available = 1;");

require __DIR__ . '/../footer.php';
