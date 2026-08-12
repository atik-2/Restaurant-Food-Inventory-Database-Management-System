/* ============================================================
   server.js — Restaurant & Food Inventory DBMS  (Node.js)

   Ki kore: ekta web "SQL Runner" dey — browser theke jekono SQL
   likhe cholano jay, output table hoye dekha jay.

   DUI MODE (nijei thik kore ney):
     * MySQL mode  — jodi MySQL 'restaurant_db' connect hoy
                     (XAMPP: user root, no password)
     * SQLite mode — jodi MySQL na pay, tahole ../database.sql ke
                     built-in node:sqlite te load kore. Kono install,
                     kono password lage na. (Node 22+ lage)

   Cholate:  cd node  ->  npm install  ->  npm start
   URL:      http://localhost:3000
   ============================================================ */
const express = require('express');
const path    = require('path');
const fs      = require('fs');

const PORT = process.env.PORT || 3000;
let DB;                       // { query(sql) -> rows|{affectedRows} }  wrapper
let MODE = '';                // 'MySQL' or 'SQLite (file: database.sql)'

// ---------------------------------------------------------- MySQL adapter
async function tryMySQL() {
  const mysql = require('mysql2/promise');
  const pool = mysql.createPool({
    host:     process.env.DB_HOST || 'localhost',
    user:     process.env.DB_USER || 'root',
    password: process.env.DB_PASS || '',
    database: process.env.DB_NAME || 'restaurant_db',
    waitForConnections: true, connectionLimit: 5, multipleStatements: false,
  });
  const c = await pool.getConnection();       // throws if not reachable
  await c.query('SELECT 1');
  c.release();
  return {
    async query(sql) {
      const [res] = await pool.query(sql);
      if (Array.isArray(res)) return { rows: res };
      return { affectedRows: res.affectedRows };
    },
  };
}

// ---------------------------------------------------------- SQLite adapter
function buildSQLite() {
  const { DatabaseSync } = require('node:sqlite');
  const db = new DatabaseSync(':memory:');

  // MySQL-lekha database.sql ke SQLite dialect-e halka convert kore ni
  let sql = fs.readFileSync(path.join(__dirname, '..', 'database.sql'), 'utf8');
  // shudhu table + data + view part; PART 7 (trigger) alada handle korbo
  const head = sql.split('PART 3:')[0];
  const views = (sql.split('PART 6:')[1] || '').split('PART 7:')[0] || '';
  let setup = head + views;

  setup = setup.replace(/--[^\n]*/g, '');
  setup = setup.replace(/`/g, '');
  setup = setup.replace(/AUTO_INCREMENT/gi, 'AUTOINCREMENT');
  setup = setup.replace(/INT AUTOINCREMENT PRIMARY KEY/gi, 'INTEGER PRIMARY KEY AUTOINCREMENT');
  setup = setup.replace(/\bTINYINT\(1\)/gi, 'INTEGER');
  setup = setup.replace(/\bDECIMAL\(\d+,\s*\d+\)/gi, 'REAL');
  setup = setup.replace(/\bVARCHAR\(\d+\)/gi, 'TEXT');
  setup = setup.replace(/\bDATETIME\b/gi, 'TEXT');
  setup = setup.replace(/CREATE DATABASE[^;]*;|DROP DATABASE[^;]*;|USE\s+\w+\s*;/gi, '');
  setup = setup.replace(/DEFAULT\s+CURRENT_TIMESTAMP/gi, "DEFAULT ''");
  // SHA2('x',256) -> literal hash (login demo-r jonno; runner-e important na)
  const crypto = require('crypto');
  setup = setup.replace(/SHA2\(\s*'([^']*)'\s*,\s*256\s*\)/gi,
    (_, t) => `'${crypto.createHash('sha256').update(t).digest('hex')}'`);

  db.exec(setup);

  // same two triggers (SQLite syntax)
  db.exec(`
    CREATE TRIGGER trg_purchase_add_stock AFTER INSERT ON purchases
    BEGIN
      UPDATE ingredients SET quantity = quantity + NEW.quantity
      WHERE ingredient_id = NEW.ingredient_id;
    END;
    CREATE TRIGGER trg_order_deduct_stock AFTER INSERT ON order_items
    BEGIN
      UPDATE ingredients SET quantity = quantity -
        (SELECT r.qty_required * NEW.quantity FROM recipes r
         WHERE r.menu_id = NEW.menu_id AND r.ingredient_id = ingredients.ingredient_id)
      WHERE ingredient_id IN (SELECT ingredient_id FROM recipes WHERE menu_id = NEW.menu_id);
      UPDATE orders SET total_amount = total_amount + (NEW.price * NEW.quantity)
      WHERE order_id = NEW.order_id;
    END;
  `);

  return {
    async query(sql) {
      const s = sql.trim();
      if (/^(select|with|pragma)\b/i.test(s) || /^show\s+tables/i.test(s)) {
        // SHOW TABLES -> sqlite equivalent
        const real = /^show\s+tables/i.test(s)
          ? "SELECT name AS Tables_in_restaurant_db FROM sqlite_master WHERE type='table' ORDER BY name"
          : s;
        return { rows: db.prepare(real).all() };
      }
      const info = db.prepare(s).run();
      return { affectedRows: Number(info.changes) };
    },
  };
}

// ---------------------------------------------------------- helpers
const esc = (v) => String(v == null ? '' : v)
  .replace(/&/g, '&amp;').replace(/</g, '&lt;')
  .replace(/>/g, '&gt;').replace(/"/g, '&quot;');

const SAMPLES = [
  ['Sob menu', 'SELECT * FROM menu_items;'],
  ['Discount ache ja te',
   'SELECT name, price, discount,\n       ROUND(price-(price*discount/100),2) AS final_price\nFROM menu_items WHERE discount > 0;'],
  ['Low stock (VIEW)', 'SELECT * FROM v_low_stock;'],
  ['Best selling (GROUP BY)',
   'SELECT m.name, SUM(oi.quantity) AS sold\nFROM order_items oi JOIN menu_items m ON oi.menu_id = m.menu_id\nGROUP BY m.name ORDER BY sold DESC;'],
  ['Daily sales (VIEW)', 'SELECT * FROM v_daily_sales;'],
  ['Ingredient + supplier (JOIN)',
   'SELECT i.name, i.quantity, i.unit, s.name AS supplier\nFROM ingredients i JOIN suppliers s ON i.supplier_id = s.supplier_id;'],
  ['Avg er cheye dami (SUBQUERY)',
   'SELECT name, price FROM menu_items\nWHERE price > (SELECT AVG(price) FROM menu_items);'],
  ['Table list', 'SHOW TABLES;'],
];

async function runSQL(sql) {
  const stmts = sql.split(';').map((s) => s.trim()).filter(Boolean);
  if (!stmts.length) return '<div class="err">Kono SQL nei.</div>';
  let out = '';
  for (const stmt of stmts) {
    try {
      const r = await DB.query(stmt);
      if (r.rows) {
        out += `<div class="card"><p class="lbl">${esc(stmt)}</p>` +
               `<p class="out">Output &mdash; ${r.rows.length} row</p>` +
               resultTable(r.rows) + '</div>';
      } else {
        out += `<div class="card"><div class="ok">OK &mdash; ` +
               `${r.affectedRows} row affected.</div><pre>${esc(stmt)}</pre></div>`;
      }
    } catch (e) {
      out += `<div class="card"><div class="err">SQL Error: ${esc(e.message)}</div>` +
             `<pre>${esc(stmt)}</pre></div>`;
    }
  }
  return out;
}

function resultTable(rows) {
  if (!rows.length) return '<p class="hint">0 row return holo.</p>';
  const cols = Object.keys(rows[0]);
  const head = cols.map((c) => `<th>${esc(c)}</th>`).join('');
  const body = rows.map((r) =>
    '<tr>' + cols.map((c) => `<td>${esc(r[c])}</td>`).join('') + '</tr>').join('');
  return `<div class="scroll"><table><tr>${head}</tr>${body}</table></div>`;
}

// ---------------------------------------------------------- HTML
function pageHTML(resultHTML, sql) {
  const chips = SAMPLES.map(([label, text]) =>
    `<button type="button" class="chip" onclick="setSQL(this)" data-sql="${esc(text)}">${esc(label)}</button>`
  ).join('');
  return `<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SQL Runner | Restaurant DBMS (Node.js)</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;font:15px/1.6 system-ui,"Segoe UI",sans-serif;background:#f4f6f8;color:#1c2733}
  header{background:#1f3a5f;color:#fff;padding:16px 22px}
  header strong{display:block;font-size:18px}
  header span{font-size:12px;opacity:.85}
  .mode{display:inline-block;margin-left:8px;padding:1px 8px;border-radius:20px;
        background:#2f7a55;font-size:11px;font-weight:700}
  nav{background:#16304f;padding:0 22px}
  nav a{color:#cdd7e5;text-decoration:none;display:inline-block;padding:11px 14px;font-size:14px}
  nav a:hover{color:#fff}
  main{max-width:1000px;margin:0 auto;padding:24px 22px 60px}
  h1{font-size:23px;margin:0 0 6px}
  .sub{color:#6b7a8d;margin:0 0 18px;font-size:14px}
  .card{background:#fff;border:1px solid #e3e8ee;border-radius:8px;padding:16px 18px;margin-bottom:16px}
  .chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px}
  .chip{background:#eef2f7;color:#1f3a5f;border:1px solid #d7dee7;font-size:12.5px;
        font-weight:600;padding:5px 11px;border-radius:20px;cursor:pointer}
  .chip:hover{background:#dce6f2}
  textarea{width:100%;min-height:150px;resize:vertical;font:13.5px/1.6 Consolas,monospace;
           padding:12px 14px;border:1px solid #cbd4de;border-radius:6px;background:#1c2733;color:#e6edf3}
  button.run{font:inherit;font-weight:600;cursor:pointer;margin-top:12px;padding:9px 18px;
             border:0;border-radius:5px;background:#1f3a5f;color:#fff}
  button.run:hover{background:#2b4d7c}
  table{width:100%;border-collapse:collapse;font-size:14px}
  th,td{border:1px solid #e3e8ee;padding:7px 10px;text-align:left}
  th{background:#f6ede2}
  tr:nth-child(even) td{background:#fbf7f2}
  .scroll{overflow-x:auto}
  .lbl{font:13px/1.5 Consolas,monospace;background:#eef2f7;color:#1f3a5f;padding:8px 10px;
       border-radius:5px;margin:0 0 10px;white-space:pre-wrap}
  .out{font-size:12px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#55637a;margin:0 0 8px}
  .ok{background:#e9f8ef;border:1px solid #a8ddbd;color:#14603c;padding:9px 12px;border-radius:5px}
  .err{background:#fdecec;border:1px solid #f0b4b4;color:#8f2020;padding:9px 12px;border-radius:5px}
  pre{background:#1c2733;color:#e6edf3;padding:10px 12px;border-radius:5px;overflow-x:auto;margin:8px 0 0}
  .hint{color:#6b7a8d;font-size:13px}
</style></head><body>
<header><strong>Restaurant &amp; Food Inventory DBMS</strong>
<span>Node.js + Express &mdash; live SQL Runner <span class="mode">${esc(MODE)}</span></span></header>
<nav>
  <a href="/">SQL Runner</a>
  <a href="/docs/index.html">Documentation</a>
  <a href="/docs/database.sql">database.sql</a>
</nav>
<main>
  <h1>SQL Runner</h1>
  <p class="sub">Box-e jekono SQL likhe <b>Run</b> chapo &mdash; sorasori database-e
  chole, output niche table hoye ashe. Onek statement ek sathe likhle <b>;</b> diye alada koro.</p>
  <div class="card">
    <div class="chips">${chips}</div>
    <form method="post" action="/run">
      <textarea name="sql" spellcheck="false">${esc(sql)}</textarea>
      <br><button class="run" type="submit">&#9654; Run SQL</button>
    </form>
  </div>
  ${resultHTML}
</main>
<script>function setSQL(b){document.querySelector('textarea').value=b.getAttribute('data-sql');}</script>
</body></html>`;
}

// ---------------------------------------------------------- app
const app = express();
app.use(express.urlencoded({ extended: false, limit: '1mb' }));
app.use('/docs', express.static(path.join(__dirname, '..')));

app.get('/', (req, res) => res.send(pageHTML('', 'SELECT * FROM menu_items;')));
app.post('/run', async (req, res) => {
  const sql = req.body.sql || '';
  let result;
  try { result = await runSQL(sql); }
  catch (e) { result = `<div class="card"><div class="err">Server error: ${esc(e.message)}</div></div>`; }
  res.send(pageHTML(result, sql));
});

// ---------------------------------------------------------- boot
(async () => {
  try {
    DB = await tryMySQL();
    MODE = 'MySQL';
    console.log('\n  MySQL connected: restaurant_db  (MySQL mode)');
  } catch (e) {
    try {
      DB = buildSQLite();
      MODE = 'SQLite (database.sql)';
      console.log('\n  MySQL na — built-in SQLite mode (database.sql loaded).');
      console.log('  (MySQL chaile: XAMPP MySQL start + database.sql import + DB_PASS set)');
    } catch (e2) {
      console.error('\n  Database load fail:', e2.message);
      process.exit(1);
    }
  }
  app.listen(PORT, () => {
    console.log(`  Restaurant DBMS (Node.js) running:  http://localhost:${PORT}`);
    console.log(`  Mode: ${MODE}\n`);
  });
})();
