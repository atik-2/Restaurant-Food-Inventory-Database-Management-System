<?php
/* ============================================================
   admin/food.php  —  Food ADD / PRICE change / DISCOUNT set / DELETE
   Ekhane full CRUD ache: INSERT, SELECT, UPDATE, DELETE
   ============================================================ */
require __DIR__ . '/../config.php';
require_role('admin');

/* ---------------- ADD new food (INSERT) ---------------- */
if (($_POST['action'] ?? '') === 'add') {
    $name     = trim($_POST['name']);
    $category = $_POST['category'];
    $price    = (float)$_POST['price'];
    $discount = (float)$_POST['discount'];

    if ($name === '' || $price <= 0) {
        set_msg('Name dite hobe ar price 0 er beshi hote hobe.', 'err');
    } elseif ($discount < 0 || $discount > 100) {
        set_msg('Discount 0 theke 100 er moddhe hote hobe.', 'err');
    } else {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO menu_items (name, category, price, discount, is_available)
                 VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param('ssdd', $name, $category, $price, $discount);
            $stmt->execute();
            $stmt->close();
            set_msg("'$name' add kora hoyeche.");
        } catch (mysqli_sql_exception $e) {
            // name column UNIQUE, tai same name dile error code 1062
            set_msg($e->getCode() == 1062
                ? "'$name' name-e already ekta item ache."
                : 'Add kora jay ni: ' . $e->getMessage(), 'err');
        }
    }
    header('Location: food.php'); exit;
}

/* ---------------- UPDATE price + discount ---------------- */
if (($_POST['action'] ?? '') === 'update') {
    $menu_id   = (int)$_POST['menu_id'];
    $price     = (float)$_POST['price'];
    $discount  = (float)$_POST['discount'];
    $available = isset($_POST['is_available']) ? 1 : 0;

    if ($price <= 0 || $discount < 0 || $discount > 100) {
        set_msg('Price 0 er beshi ar discount 0-100 er moddhe hote hobe.', 'err');
    } else {
        $stmt = $conn->prepare(
            "UPDATE menu_items
             SET price = ?, discount = ?, is_available = ?
             WHERE menu_id = ?");
        $stmt->bind_param('ddii', $price, $discount, $available, $menu_id);
        $stmt->execute();
        $stmt->close();
        set_msg('Update hoyeche.');
    }
    header('Location: food.php'); exit;
}

/* ---------------- DELETE food ---------------- */
if (($_POST['action'] ?? '') === 'delete') {
    $menu_id = (int)$_POST['menu_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM menu_items WHERE menu_id = ?");
        $stmt->bind_param('i', $menu_id);
        $stmt->execute();
        $stmt->close();
        set_msg('Item delete kora hoyeche (tar recipe row gulo CASCADE-e chole geche).');
    } catch (mysqli_sql_exception $e) {
        // order_items ei menu_id use korle FK delete korte dibe na
        set_msg('Delete kora jabe na — ei item ager order-e ache. '
              . 'Delete korar bodole "Available" tick tule dao.', 'err');
    }
    header('Location: food.php'); exit;
}

/* ---------------- SELECT — list ---------------- */
$list_sql = "SELECT menu_id, name, category, price, discount,
                    ROUND(price - (price * discount / 100), 2) AS final_price,
                    is_available
             FROM menu_items
             ORDER BY category, name";
$items = $conn->query($list_sql);

$page_title = 'Food, Price & Discount';
require __DIR__ . '/../header.php';
?>

<div class="card">
  <h2>New food item add koro</h2>
  <form method="post" class="rowform">
    <input type="hidden" name="action" value="add">
    <label>Food name
      <input type="text" name="name" required placeholder="e.g. Chicken Roast">
    </label>
    <label>Category
      <select name="category">
        <option>Main</option>
        <option>Snacks</option>
        <option>Drinks</option>
        <option>Dessert</option>
      </select>
    </label>
    <label>Price (BDT)
      <input type="number" name="price" step="0.01" min="1" required placeholder="280">
    </label>
    <label>Discount (%)
      <input type="number" name="discount" step="0.01" min="0" max="100" value="0">
    </label>
    <button type="submit">+ Add Food</button>
  </form>
</div>

<div class="card">
  <h2>All food items (<?= $items->num_rows ?>)</h2>
  <p class="hint">Price ba discount change kore <b>Save</b> chapo.
     Discount 0 dile kono chhar thakbe na.</p>

  <table>
    <tr>
      <th>ID</th><th>Name</th><th>Category</th>
      <th>Price</th><th>Discount %</th><th>Final price</th>
      <th>Available</th><th colspan="2">Action</th>
    </tr>
    <?php while ($it = $items->fetch_assoc()):
          $id = (int)$it['menu_id']; ?>
    <tr>
      <td><?= $id ?></td>
      <td><?= h($it['name']) ?></td>
      <td><?= h($it['category']) ?></td>
      <td><input class="num" type="number" name="price" step="0.01" min="1"
                 form="f<?= $id ?>" value="<?= h($it['price']) ?>"></td>
      <td><input class="num" type="number" name="discount" step="0.01" min="0" max="100"
                 form="f<?= $id ?>" value="<?= h($it['discount']) ?>"></td>
      <td>
        <?php if ($it['discount'] > 0): ?>
          <span class="old"><?= tk($it['price']) ?></span>
          <b class="new"><?= tk($it['final_price']) ?></b>
        <?php else: ?>
          <b><?= tk($it['final_price']) ?></b>
        <?php endif; ?>
      </td>
      <td style="text-align:center">
        <input type="checkbox" name="is_available" value="1" form="f<?= $id ?>"
               <?= $it['is_available'] ? 'checked' : '' ?>>
      </td>
      <td class="nowrap">
        <!-- form ta td er vitore — tai HTML valid, ar input gula form="fID" diye jukto -->
        <form id="f<?= $id ?>" method="post">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="menu_id" value="<?= $id ?>">
          <button class="sm" type="submit">Save</button>
        </form>
      </td>
      <td class="nowrap">
        <form method="post" onsubmit="return confirm('Delete <?= h($it['name']) ?>?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="menu_id" value="<?= $id ?>">
          <button class="sm danger" type="submit">Delete</button>
        </form>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
</div>

<?php
sql_box("-- ADD (INSERT)
INSERT INTO menu_items (name, category, price, discount, is_available)
VALUES (?, ?, ?, ?, 1);

-- PRICE + DISCOUNT change (UPDATE)
UPDATE menu_items
SET price = ?, discount = ?, is_available = ?
WHERE menu_id = ?;

-- DELETE
DELETE FROM menu_items WHERE menu_id = ?;

-- LIST (discount baad diye final price calculate kora hoyeche)
$list_sql");

require __DIR__ . '/../footer.php';
