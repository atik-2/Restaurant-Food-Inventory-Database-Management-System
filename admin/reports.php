<?php
/* ============================================================
   admin/reports.php  —  GROUP BY / HAVING / subquery / VIEW er demo
   ============================================================ */
require __DIR__ . '/../config.php';
require_role('admin');

$reports = [

'Best selling food (GROUP BY + ORDER BY)' => [
 'sql' => "SELECT m.name, SUM(oi.quantity) AS sold,
                  SUM(oi.quantity * oi.price) AS revenue
           FROM order_items oi
           JOIN menu_items m ON oi.menu_id = m.menu_id
           GROUP BY m.name
           ORDER BY sold DESC",
 'note' => 'Kon khabar koto plate bikri holo.'],

'Discount cholche ja te (WHERE + calculation)' => [
 'sql' => "SELECT name, category, price, discount,
                  ROUND(price - (price * discount / 100), 2) AS final_price,
                  ROUND(price * discount / 100, 2) AS you_save
           FROM menu_items
           WHERE discount > 0
           ORDER BY discount DESC",
 'note' => 'Admin je discount set korche, tar hisheb.'],

'Customer wise total kharoch (GROUP BY + HAVING)' => [
 'sql' => "SELECT c.name, COUNT(o.order_id) AS orders,
                  IFNULL(SUM(o.total_amount),0) AS total_spent
           FROM customers c
           LEFT JOIN orders o ON c.customer_id = o.customer_id
           GROUP BY c.name
           HAVING total_spent > 0
           ORDER BY total_spent DESC",
 'note' => 'HAVING group toiri howar por filter kore.'],

'Average er cheye dami khabar (SUBQUERY)' => [
 'sql' => "SELECT name, price
           FROM menu_items
           WHERE price > (SELECT AVG(price) FROM menu_items)
           ORDER BY price DESC",
 'note' => 'Vitorer query prothome average ber kore, tarpor bahirer query compare kore.'],

'Low stock (VIEW)' => [
 'sql' => "SELECT * FROM v_low_stock ORDER BY quantity",
 'note' => 'v_low_stock ekta VIEW — boro query ta name diye save kora ache.'],

'Supplier wise total kena (GROUP BY)' => [
 'sql' => "SELECT s.name AS supplier, COUNT(p.purchase_id) AS purchases,
                  IFNULL(SUM(p.cost),0) AS total_cost
           FROM suppliers s
           LEFT JOIN purchases p ON s.supplier_id = p.supplier_id
           GROUP BY s.name
           ORDER BY total_cost DESC",
 'note' => 'Kon supplier theke koto takar maal kena hoyeche.'],

'Stock er total dam' => [
 'sql' => "SELECT COUNT(*) AS items,
                  ROUND(SUM(quantity * unit_price), 2) AS stock_value,
                  ROUND(AVG(unit_price), 2) AS avg_unit_price
           FROM ingredients",
 'note' => 'COUNT, SUM, AVG — teen ta aggregate function ek sathe.'],

'Recipe: ek plate-e ki ki lage (4 table JOIN)' => [
 'sql' => "SELECT m.name AS food, i.name AS ingredient,
                  r.qty_required, i.unit
           FROM recipes r
           JOIN menu_items  m ON r.menu_id       = m.menu_id
           JOIN ingredients i ON r.ingredient_id = i.ingredient_id
           ORDER BY m.name, i.name",
 'note' => 'recipes holo bridge table — ei jonyei bikri hole stock kome.'],
];

$page_title = 'Reports (SQL practice)';
require __DIR__ . '/../header.php';
?>

<p class="hint">Protita report ekta alada SQL feature dekhay. Query ta table-er niche ache —
   viva-te ei page ta khule dekhale sob prosner uttor pawa jabe.</p>

<?php foreach ($reports as $title => $r):
      $res = $conn->query($r['sql']); ?>
<div class="card">
  <h2><?= h($title) ?></h2>
  <p class="hint"><?= h($r['note']) ?></p>

  <?php if ($res->num_rows === 0): ?>
    <p class="hint">Kono row nei.</p>
  <?php else: ?>
    <table>
      <tr><?php foreach ($res->fetch_fields() as $f): ?>
            <th><?= h($f->name) ?></th>
          <?php endforeach; ?></tr>
      <?php while ($row = $res->fetch_assoc()): ?>
        <tr><?php foreach ($row as $v): ?>
              <td><?= h($v ?? '-') ?></td>
            <?php endforeach; ?></tr>
      <?php endwhile; ?>
    </table>
  <?php endif; ?>

  <details class="sqlbox"><summary>SQL</summary><pre><code><?= h($r['sql']) ?></code></pre></details>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/../footer.php';
