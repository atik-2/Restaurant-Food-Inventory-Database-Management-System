<?php
/* ============================================================
   admin/purchase.php  —  supplier theke maal kena
   INSERT korlei TRIGGER stock bariye dey (automatic)
   ============================================================ */
require __DIR__ . '/../config.php';
require_role('admin');

if (($_POST['action'] ?? '') === 'buy') {
    $supplier_id   = (int)$_POST['supplier_id'];
    $ingredient_id = (int)$_POST['ingredient_id'];
    $qty           = (float)$_POST['quantity'];
    $cost          = (float)$_POST['cost'];
    $date          = $_POST['purchase_date'] ?: date('Y-m-d');

    if ($qty <= 0 || $cost < 0) {
        set_msg('Quantity 0 er beshi hote hobe.', 'err');
    } else {
        // chotto helper — ingredient-er ekhonkar stock ber kore
        $read = $conn->prepare(
            "SELECT name, quantity FROM ingredients WHERE ingredient_id = ?");

        // stock ager value ta dekhe rakhi, pore trigger-er kaj dekhabo
        $read->bind_param('i', $ingredient_id);
        $read->execute();
        $before = $read->get_result()->fetch_assoc();

        $stmt = $conn->prepare(
            "INSERT INTO purchases (supplier_id, ingredient_id, quantity, cost, purchase_date)
             VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('iidds', $supplier_id, $ingredient_id, $qty, $cost, $date);
        $stmt->execute();
        $stmt->close();

        // ekhane amra kono UPDATE likhi ni — trigger nijei kaj korche
        $read->bind_param('i', $ingredient_id);
        $read->execute();
        $after = $read->get_result()->fetch_assoc();
        $read->close();

        set_msg(sprintf('Purchase save holo. TRIGGER stock barieche: %s %s -> %s',
            $before['name'], tk($before['quantity']), tk($after['quantity'])));
    }
    header('Location: purchase.php'); exit;
}

$suppliers   = $conn->query("SELECT supplier_id, name FROM suppliers ORDER BY name");
$ingredients = $conn->query("SELECT ingredient_id, name, unit, quantity, unit_price
                             FROM ingredients ORDER BY name");

$hist_sql = "SELECT p.purchase_id, s.name AS supplier, i.name AS ingredient,
                    p.quantity, i.unit, p.cost, p.purchase_date
             FROM purchases p
             JOIN suppliers   s ON p.supplier_id   = s.supplier_id
             JOIN ingredients i ON p.ingredient_id = i.ingredient_id
             ORDER BY p.purchase_id DESC
             LIMIT 15";
$history = $conn->query($hist_sql);

$page_title = 'Purchase (Stock IN)';
require __DIR__ . '/../header.php';
?>

<div class="card">
  <h2>Notun purchase entry</h2>
  <p class="hint">Save korle <b>trigger</b> <code>trg_purchase_add_stock</code>
     nijei ingredient-er stock bariye dibe — alada UPDATE likhte hoy na.</p>
  <form method="post" class="rowform">
    <input type="hidden" name="action" value="buy">
    <label>Supplier
      <select name="supplier_id" required>
        <?php while ($s = $suppliers->fetch_assoc()): ?>
          <option value="<?= (int)$s['supplier_id'] ?>"><?= h($s['name']) ?></option>
        <?php endwhile; ?>
      </select>
    </label>
    <label>Ingredient
      <select name="ingredient_id" required>
        <?php while ($i = $ingredients->fetch_assoc()): ?>
          <option value="<?= (int)$i['ingredient_id'] ?>">
            <?= h($i['name']) ?> (ekhon <?= tk($i['quantity']) ?> <?= h($i['unit']) ?>)
          </option>
        <?php endwhile; ?>
      </select>
    </label>
    <label>Quantity <input type="number" name="quantity" step="0.01" min="0.01" required></label>
    <label>Total cost <input type="number" name="cost" step="0.01" min="0" required></label>
    <label>Date <input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>"></label>
    <button type="submit">Save purchase</button>
  </form>
</div>

<div class="card">
  <h2>Purchase history (last 15)</h2>
  <table>
    <tr><th>#</th><th>Date</th><th>Supplier</th><th>Ingredient</th>
        <th>Quantity</th><th>Cost</th></tr>
    <?php while ($p = $history->fetch_assoc()): ?>
    <tr>
      <td><?= (int)$p['purchase_id'] ?></td>
      <td><?= h($p['purchase_date']) ?></td>
      <td><?= h($p['supplier']) ?></td>
      <td><?= h($p['ingredient']) ?></td>
      <td><?= tk($p['quantity']) ?> <?= h($p['unit']) ?></td>
      <td><?= tk($p['cost']) ?></td>
    </tr>
    <?php endwhile; ?>
  </table>
</div>

<?php
sql_box("-- Shudhu ei INSERT ta hoy...
INSERT INTO purchases (supplier_id, ingredient_id, quantity, cost, purchase_date)
VALUES (?, ?, ?, ?, ?);

-- ...ar TRIGGER ta nijei stock bariye dey:
--   CREATE TRIGGER trg_purchase_add_stock AFTER INSERT ON purchases
--   FOR EACH ROW
--   BEGIN
--       UPDATE ingredients SET quantity = quantity + NEW.quantity
--       WHERE ingredient_id = NEW.ingredient_id;
--   END

-- history (3 table JOIN)
$hist_sql");

require __DIR__ . '/../footer.php';
