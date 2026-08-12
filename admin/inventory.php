<?php
/* ============================================================
   admin/inventory.php  —  ingredient stock manage
   ============================================================ */
require __DIR__ . '/../config.php';
require_role('admin');

/* ---- ADD ingredient ---- */
if (($_POST['action'] ?? '') === 'add') {
    $name      = trim($_POST['name']);
    $unit      = $_POST['unit'];
    $qty       = (float)$_POST['quantity'];
    $reorder   = (float)$_POST['reorder_level'];
    $price     = (float)$_POST['unit_price'];
    $supplier  = $_POST['supplier_id'] !== '' ? (int)$_POST['supplier_id'] : null;

    if ($name === '' || $qty < 0 || $price < 0) {
        set_msg('Thik moto value dao.', 'err');
    } else {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO ingredients (name, unit, quantity, reorder_level, unit_price, supplier_id)
                 VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssdddi', $name, $unit, $qty, $reorder, $price, $supplier);
            $stmt->execute();
            $stmt->close();
            set_msg("'$name' stock-e add hoyeche.");
        } catch (mysqli_sql_exception $e) {
            set_msg($e->getCode() == 1062
                ? "'$name' already ache."
                : 'Add hoy ni: ' . $e->getMessage(), 'err');
        }
    }
    header('Location: inventory.php'); exit;
}

/* ---- UPDATE stock / reorder / price ---- */
if (($_POST['action'] ?? '') === 'update') {
    $id      = (int)$_POST['ingredient_id'];
    $qty     = (float)$_POST['quantity'];
    $reorder = (float)$_POST['reorder_level'];
    $price   = (float)$_POST['unit_price'];

    if ($qty < 0 || $reorder < 0 || $price < 0) {
        set_msg('Negative value dewa jabe na (CHECK constraint).', 'err');
    } else {
        $stmt = $conn->prepare(
            "UPDATE ingredients
             SET quantity = ?, reorder_level = ?, unit_price = ?
             WHERE ingredient_id = ?");
        $stmt->bind_param('dddi', $qty, $reorder, $price, $id);
        $stmt->execute();
        $stmt->close();
        set_msg('Stock update hoyeche.');
    }
    header('Location: inventory.php'); exit;
}

/* ---- DELETE ---- */
if (($_POST['action'] ?? '') === 'delete') {
    $id = (int)$_POST['ingredient_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM ingredients WHERE ingredient_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        set_msg('Ingredient delete hoyeche.');
    } catch (mysqli_sql_exception $e) {
        set_msg('Delete kora jabe na — ei ingredient purchase ba recipe-te use hoyeche '
              . '(Foreign Key protect korche).', 'err');
    }
    header('Location: inventory.php'); exit;
}

$list_sql = "SELECT i.ingredient_id, i.name, i.unit, i.quantity, i.reorder_level,
                    i.unit_price, (i.quantity * i.unit_price) AS stock_value,
                    s.name AS supplier
             FROM ingredients i
             LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
             ORDER BY (i.quantity <= i.reorder_level) DESC, i.name";
$rows = $conn->query($list_sql);
$suppliers = $conn->query("SELECT supplier_id, name FROM suppliers ORDER BY name");

$page_title = 'Inventory (Ingredient Stock)';
require __DIR__ . '/../header.php';
?>

<div class="card">
  <h2>New ingredient add koro</h2>
  <form method="post" class="rowform">
    <input type="hidden" name="action" value="add">
    <label>Name <input type="text" name="name" required placeholder="e.g. Tomato"></label>
    <label>Unit
      <select name="unit"><option>kg</option><option>litre</option>
      <option>piece</option><option>packet</option></select>
    </label>
    <label>Quantity <input type="number" name="quantity" step="0.01" min="0" value="0"></label>
    <label>Reorder level <input type="number" name="reorder_level" step="0.01" min="0" value="5"></label>
    <label>Unit price <input type="number" name="unit_price" step="0.01" min="0" value="0"></label>
    <label>Supplier
      <select name="supplier_id">
        <option value="">-- none --</option>
        <?php while ($s = $suppliers->fetch_assoc()): ?>
          <option value="<?= (int)$s['supplier_id'] ?>"><?= h($s['name']) ?></option>
        <?php endwhile; ?>
      </select>
    </label>
    <button type="submit">+ Add</button>
  </form>
</div>

<div class="card">
  <h2>Stock list (<?= $rows->num_rows ?>)</h2>
  <p class="hint">Lal row mane stock reorder level er niche neme geche.</p>
  <table>
    <tr><th>ID</th><th>Name</th><th>Unit</th><th>Stock</th><th>Reorder</th>
        <th>Unit price</th><th>Stock value</th><th>Supplier</th><th colspan="2">Action</th></tr>
    <?php while ($r = $rows->fetch_assoc()):
        $id  = (int)$r['ingredient_id'];
        $low = $r['quantity'] <= $r['reorder_level']; ?>
    <tr class="<?= $low ? 'lowrow' : '' ?>">
      <td><?= $id ?></td>
      <td><?= h($r['name']) ?> <?= $low ? '<b class="alert">LOW</b>' : '' ?></td>
      <td><?= h($r['unit']) ?></td>
      <td><input class="num" type="number" name="quantity" step="0.01" min="0"
                 form="g<?= $id ?>" value="<?= h($r['quantity']) ?>"></td>
      <td><input class="num" type="number" name="reorder_level" step="0.01" min="0"
                 form="g<?= $id ?>" value="<?= h($r['reorder_level']) ?>"></td>
      <td><input class="num" type="number" name="unit_price" step="0.01" min="0"
                 form="g<?= $id ?>" value="<?= h($r['unit_price']) ?>"></td>
      <td><?= tk($r['stock_value']) ?></td>
      <td><?= h($r['supplier'] ?? '-') ?></td>
      <td>
        <form id="g<?= $id ?>" method="post">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="ingredient_id" value="<?= $id ?>">
          <button class="sm" type="submit">Save</button>
        </form>
      </td>
      <td>
        <form method="post" onsubmit="return confirm('Delete <?= h($r['name']) ?>?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="ingredient_id" value="<?= $id ?>">
          <button class="sm danger" type="submit">Delete</button>
        </form>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
</div>

<?php
sql_box("-- LIST (LEFT JOIN — supplier na thakleo ingredient dekhabe)
$list_sql

-- ADD
INSERT INTO ingredients (name, unit, quantity, reorder_level, unit_price, supplier_id)
VALUES (?, ?, ?, ?, ?, ?);

-- UPDATE stock
UPDATE ingredients SET quantity = ?, reorder_level = ?, unit_price = ?
WHERE ingredient_id = ?;

-- DELETE
DELETE FROM ingredients WHERE ingredient_id = ?;");

require __DIR__ . '/../footer.php';
