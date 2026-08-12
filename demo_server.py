#!/usr/bin/env python3
"""
demo_server.py  —  XAMPP chara panel gulo chalanor jonno.

Eta PHP na — kintu PHP panel gulor MODEL hubohu ek: same table, same SQL,
same trigger, same discount hisheb. database.sql ta SQLite-e load kore, tar
upor login / admin / customer panel chalay.

Cholate:   python demo_server.py
URL:       http://localhost:8000

NOTE: eta demo/presentation runner. Asol submission ta PHP + MySQL
(login.php, admin/, customer/) — sei code o folder-e ache.
"""

import re, os, sqlite3, hashlib, html, threading, datetime, urllib.parse, http.cookies
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

ROOT = os.path.dirname(os.path.abspath(__file__))
PORT = int(os.environ.get('PORT', 8000))

# ------------------------------------------------------------------ DB build
def build_db():
    sql = open(os.path.join(ROOT, 'database.sql'), encoding='utf-8').read()
    setup = sql.split('PART 3:')[0] + sql.split('PART 6:')[1].split('PART 7:')[0]
    setup = re.sub(r'--[^\n]*', '', setup)
    setup = setup.replace('AUTO_INCREMENT', 'AUTOINCREMENT')
    setup = setup.replace('INT AUTOINCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT')
    setup = re.sub(r'\bTINYINT\(1\)', 'INTEGER', setup)
    setup = re.sub(r'\bDECIMAL\(\d+,\s*\d+\)', 'REAL', setup)
    setup = re.sub(r'\bVARCHAR\(\d+\)', 'TEXT', setup)
    setup = re.sub(r'\bDATETIME\b', 'TEXT', setup)
    setup = re.sub(r'(?i)CREATE DATABASE[^;]*;|DROP DATABASE[^;]*;|USE\s+\w+\s*;', '', setup)
    setup = re.sub(r'(?i)DEFAULT\s+CURRENT_TIMESTAMP', "DEFAULT ''", setup)

    con = sqlite3.connect(':memory:', check_same_thread=False)
    con.row_factory = sqlite3.Row
    con.create_function('SHA2', 2, lambda s, n: hashlib.sha256(str(s).encode()).hexdigest())
    con.execute('PRAGMA foreign_keys=ON')
    con.executescript(setup)
    # same two triggers as the MySQL version (SQLite syntax)
    con.executescript("""
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
    """)
    con.commit()
    return con

DB   = build_db()
LOCK = threading.Lock()
SESSIONS = {}   # sid -> {user_id, uname, role, customer_id, cart:{}, msg:(text,type)}

def q(sql, args=()):
    with LOCK:
        cur = DB.execute(sql, args)
        rows = cur.fetchall()
        return rows

def run(sql, args=()):
    with LOCK:
        cur = DB.execute(sql, args)
        DB.commit()
        return cur

# ------------------------------------------------------------------ helpers
def h(v):        return html.escape('' if v is None else str(v))
def tk(v):       return f"{float(v):,.2f}"
def num(v, d=0):
    try: return float(v)
    except (TypeError, ValueError): return d

def page(title, s, body, active=''):
    role  = s.get('role', '')
    uname = s.get('uname', '')
    if role == 'admin':
        bar, menus = 'admin', [('dashboard','Dashboard'),('food','Food & Price'),
            ('inventory','Inventory'),('purchase','Purchase'),('orders','Orders'),
            ('reports','Reports'),('sql','SQL Runner')]
        base = '/admin/'
    else:
        bar, menus = 'cust', [('menu','Menu'),('cart','My Cart'),('my_orders','My Orders')]
        base = '/customer/'
    nav = ''.join(
        f'<a href="{base}{f}" class="{"active" if active==f else ""}">{lbl}</a>'
        for f, lbl in menus)
    msg = ''
    if s.get('msg'):
        t, typ = s.pop('msg')
        msg = f'<div class="msg {typ}">{h(t)}</div>'
    return f"""<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{h(title)}</title><link rel="stylesheet" href="/panel.css"></head><body>
<header class="topbar {bar}">
  <div class="brand"><strong>{'Admin Panel' if role=='admin' else 'Customer Panel'}</strong>
  <span>Restaurant &amp; Food Inventory System</span></div>
  <div class="who">{h(uname)} <a class="logout" href="/logout">Logout</a></div>
</header>
<nav class="panelnav">{nav}<a href="/docs/index.html" class="right">&larr; Documentation</a></nav>
<main><h1>{h(title)}</h1>{msg}{body}</main>
<footer class="panelfoot">Restaurant &amp; Food Inventory DBMS &mdash;
demo runner (SQLite). Asol project: PHP + MySQL.</footer></body></html>"""

def sqlbox(sql):
    return (f'<details class="sqlbox"><summary>Ei page-e ja SQL cholche</summary>'
            f'<pre><code>{h(sql.strip())}</code></pre></details>')

def redirect(handler, to, sid=None):
    handler.send_response(303)
    handler.send_header('Location', to)
    if sid:
        c = http.cookies.SimpleCookie()
        c['sid'] = sid
        c['sid']['path'] = '/'
        handler.send_header('Set-Cookie', c['sid'].OutputString())
    handler.end_headers()

def send_html(handler, text, code=200):
    data = text.encode('utf-8')
    handler.send_response(code)
    handler.send_header('Content-Type', 'text/html; charset=utf-8')
    handler.send_header('Content-Length', str(len(data)))
    handler.end_headers()
    handler.wfile.write(data)

# ------------------------------------------------------------------ pages
def login_page(s, error=''):
    err = f'<div class="msg err">{h(error)}</div>' if error else ''
    return f"""<!DOCTYPE html><html><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | Restaurant DBMS</title><link rel="stylesheet" href="/panel.css"></head>
<body class="loginpage"><div class="loginbox">
<h1>Restaurant System</h1><p class="sub">Login to continue</p>{err}
<form method="post" action="/login">
  <label>Username<input type="text" name="username" required autofocus></label>
  <label>Password<input type="password" name="password" required></label>
  <button type="submit">Login</button>
</form>
<div class="demo"><strong>Demo accounts</strong><table>
<tr><th>Role</th><th>Username</th><th>Password</th></tr>
<tr><td>Admin</td><td>admin</td><td>admin123</td></tr>
<tr><td>Customer</td><td>rahim</td><td>1234</td></tr>
<tr><td>Customer</td><td>karim</td><td>1234</td></tr>
</table></div>
<p class="back"><a href="/docs/index.html">&larr; Project documentation</a></p>
</div></body></html>"""

def admin_dashboard(s):
    one = lambda sql: q(sql)[0][0]
    stats = [
        (one("SELECT COUNT(*) FROM menu_items"), 'Food items', ''),
        (one("SELECT COUNT(*) FROM menu_items WHERE discount>0"), 'On discount', ''),
        (one("SELECT COUNT(*) FROM orders"), 'Total orders', ''),
        (tk(one("SELECT IFNULL(SUM(total_amount),0) FROM orders WHERE status='Paid'")), 'Paid sales', 'money'),
        (tk(one("SELECT IFNULL(SUM(quantity*unit_price),0) FROM ingredients")), 'Stock value', 'money'),
        (one("SELECT COUNT(*) FROM ingredients WHERE quantity<=reorder_level"), 'Low stock', 'warn'),
    ]
    cards = ''.join(f'<div class="stat {c}"><span>{v}</span>{lbl}</div>' for v, lbl, c in stats)
    low = q("SELECT * FROM v_low_stock ORDER BY quantity")
    if low:
        rows = ''.join(f"<tr class='lowrow'><td>{h(r['name'])}</td><td>{tk(r['quantity'])} {h(r['unit'])}</td>"
                       f"<td>{tk(r['reorder_level'])} {h(r['unit'])}</td><td>{h(r['supplier'] or '-')}</td></tr>" for r in low)
        lowtbl = f"<table><tr><th>Ingredient</th><th>Stock</th><th>Reorder</th><th>Supplier</th></tr>{rows}</table><p class='hint'>Eta <code>v_low_stock</code> VIEW theke.</p>"
    else:
        lowtbl = "<p class='hint'>Sob thik ache.</p>"
    rec = q("""SELECT o.order_id, IFNULL(c.name,'Walk-in') customer, e.name staff,
                      o.order_date, o.status, o.total_amount FROM orders o
               LEFT JOIN customers c ON o.customer_id=c.customer_id
               JOIN employees e ON o.employee_id=e.employee_id
               ORDER BY o.order_id DESC LIMIT 6""")
    rrows = ''.join(f"<tr><td>{r['order_id']}</td><td>{h(r['customer'])}</td><td>{h(r['staff'])}</td>"
                    f"<td>{h(r['order_date'])}</td><td><span class='badge {r['status'].lower()}'>{r['status']}</span></td>"
                    f"<td>{tk(r['total_amount'])}</td></tr>" for r in rec)
    body = (f'<div class="stats">{cards}</div>'
            f'<div class="card"><h2>Low stock alert</h2>{lowtbl}</div>'
            f'<div class="card"><h2>Recent orders</h2><table>'
            f'<tr><th>#</th><th>Customer</th><th>Staff</th><th>Date</th><th>Status</th><th>Total</th></tr>{rrows}</table></div>')
    return page('Dashboard', s, body, 'dashboard')

def admin_food(s):
    items = q("""SELECT menu_id,name,category,price,discount,
                 ROUND(price-(price*discount/100),2) final_price,is_available
                 FROM menu_items ORDER BY category,name""")
    rows = ''
    for it in items:
        i = it['menu_id']
        if it['discount'] > 0:
            fp = f"<span class='old'>{tk(it['price'])}</span> <b class='new'>{tk(it['final_price'])}</b>"
        else:
            fp = f"<b>{tk(it['final_price'])}</b>"
        chk = 'checked' if it['is_available'] else ''
        rows += f"""<tr>
<td>{i}</td><td>{h(it['name'])}</td><td>{h(it['category'])}</td>
<td><input class="num" type="number" name="price" step="0.01" min="1" form="f{i}" value="{it['price']}"></td>
<td><input class="num" type="number" name="discount" step="0.01" min="0" max="100" form="f{i}" value="{it['discount']}"></td>
<td>{fp}</td>
<td style="text-align:center"><input type="checkbox" name="is_available" value="1" form="f{i}" {chk}></td>
<td class="nowrap"><form id="f{i}" method="post" action="/admin/food">
  <input type="hidden" name="action" value="update"><input type="hidden" name="menu_id" value="{i}">
  <button class="sm" type="submit">Save</button></form></td>
<td class="nowrap"><form method="post" action="/admin/food" onsubmit="return confirm('Delete {h(it['name'])}?')">
  <input type="hidden" name="action" value="delete"><input type="hidden" name="menu_id" value="{i}">
  <button class="sm danger" type="submit">Delete</button></form></td></tr>"""
    body = f"""<div class="card"><h2>New food item add koro</h2>
<form method="post" action="/admin/food" class="rowform">
<input type="hidden" name="action" value="add">
<label>Food name<input type="text" name="name" required placeholder="e.g. Chicken Roast"></label>
<label>Category<select name="category"><option>Main</option><option>Snacks</option>
<option>Drinks</option><option>Dessert</option></select></label>
<label>Price (BDT)<input type="number" name="price" step="0.01" min="1" required placeholder="280"></label>
<label>Discount (%)<input type="number" name="discount" step="0.01" min="0" max="100" value="0"></label>
<button type="submit">+ Add Food</button></form></div>
<div class="card"><h2>All food items ({len(items)})</h2>
<p class="hint">Price ba discount change kore <b>Save</b> chapo.</p>
<table><tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Discount %</th>
<th>Final price</th><th>Available</th><th colspan="2">Action</th></tr>{rows}</table></div>
{sqlbox('''INSERT INTO menu_items (name,category,price,discount,is_available) VALUES (?,?,?,?,1);
UPDATE menu_items SET price=?, discount=?, is_available=? WHERE menu_id=?;
DELETE FROM menu_items WHERE menu_id=?;
SELECT name,price,discount, ROUND(price-(price*discount/100),2) AS final_price FROM menu_items;''')}"""
    return page('Food, Price & Discount', s, body, 'food')

def admin_inventory(s):
    rows_d = q("""SELECT i.ingredient_id,i.name,i.unit,i.quantity,i.reorder_level,i.unit_price,
                  (i.quantity*i.unit_price) stock_value, s.name supplier FROM ingredients i
                  LEFT JOIN suppliers s ON i.supplier_id=s.supplier_id
                  ORDER BY (i.quantity<=i.reorder_level) DESC, i.name""")
    sup = q("SELECT supplier_id,name FROM suppliers ORDER BY name")
    supopt = '<option value="">-- none --</option>' + ''.join(
        f'<option value="{r["supplier_id"]}">{h(r["name"])}</option>' for r in sup)
    body_rows = ''
    for r in rows_d:
        i = r['ingredient_id']; low = r['quantity'] <= r['reorder_level']
        alert = " <b class='alert'>LOW</b>" if low else ""
        body_rows += f"""<tr class="{'lowrow' if low else ''}">
<td>{i}</td><td>{h(r['name'])}{alert}</td><td>{h(r['unit'])}</td>
<td><input class="num" type="number" name="quantity" step="0.01" min="0" form="g{i}" value="{r['quantity']}"></td>
<td><input class="num" type="number" name="reorder_level" step="0.01" min="0" form="g{i}" value="{r['reorder_level']}"></td>
<td><input class="num" type="number" name="unit_price" step="0.01" min="0" form="g{i}" value="{r['unit_price']}"></td>
<td>{tk(r['stock_value'])}</td><td>{h(r['supplier'] or '-')}</td>
<td><form id="g{i}" method="post" action="/admin/inventory">
<input type="hidden" name="action" value="update"><input type="hidden" name="ingredient_id" value="{i}">
<button class="sm" type="submit">Save</button></form></td>
<td><form method="post" action="/admin/inventory" onsubmit="return confirm('Delete {h(r['name'])}?')">
<input type="hidden" name="action" value="delete"><input type="hidden" name="ingredient_id" value="{i}">
<button class="sm danger" type="submit">Delete</button></form></td></tr>"""
    body = f"""<div class="card"><h2>New ingredient add koro</h2>
<form method="post" action="/admin/inventory" class="rowform">
<input type="hidden" name="action" value="add">
<label>Name<input type="text" name="name" required placeholder="e.g. Tomato"></label>
<label>Unit<select name="unit"><option>kg</option><option>litre</option><option>piece</option><option>packet</option></select></label>
<label>Quantity<input type="number" name="quantity" step="0.01" min="0" value="0"></label>
<label>Reorder level<input type="number" name="reorder_level" step="0.01" min="0" value="5"></label>
<label>Unit price<input type="number" name="unit_price" step="0.01" min="0" value="0"></label>
<label>Supplier<select name="supplier_id">{supopt}</select></label>
<button type="submit">+ Add</button></form></div>
<div class="card"><h2>Stock list ({len(rows_d)})</h2>
<p class="hint">Lal row mane stock reorder level er niche.</p>
<table><tr><th>ID</th><th>Name</th><th>Unit</th><th>Stock</th><th>Reorder</th>
<th>Unit price</th><th>Stock value</th><th>Supplier</th><th colspan="2">Action</th></tr>{body_rows}</table></div>
{sqlbox('''SELECT i.*, (i.quantity*i.unit_price) AS stock_value, s.name AS supplier
FROM ingredients i LEFT JOIN suppliers s ON i.supplier_id=s.supplier_id;
INSERT INTO ingredients (name,unit,quantity,reorder_level,unit_price,supplier_id) VALUES (?,?,?,?,?,?);
UPDATE ingredients SET quantity=?, reorder_level=?, unit_price=? WHERE ingredient_id=?;''')}"""
    return page('Inventory (Ingredient Stock)', s, body, 'inventory')

def admin_purchase(s):
    sup = q("SELECT supplier_id,name FROM suppliers ORDER BY name")
    ing = q("SELECT ingredient_id,name,unit,quantity FROM ingredients ORDER BY name")
    supopt = ''.join(f'<option value="{r["supplier_id"]}">{h(r["name"])}</option>' for r in sup)
    ingopt = ''.join(f'<option value="{r["ingredient_id"]}">{h(r["name"])} (ekhon {tk(r["quantity"])} {h(r["unit"])})</option>' for r in ing)
    hist = q("""SELECT p.purchase_id,s.name supplier,i.name ingredient,p.quantity,i.unit,p.cost,p.purchase_date
                FROM purchases p JOIN suppliers s ON p.supplier_id=s.supplier_id
                JOIN ingredients i ON p.ingredient_id=i.ingredient_id
                ORDER BY p.purchase_id DESC LIMIT 15""")
    hrows = ''.join(f"<tr><td>{r['purchase_id']}</td><td>{h(r['purchase_date'])}</td><td>{h(r['supplier'])}</td>"
                    f"<td>{h(r['ingredient'])}</td><td>{tk(r['quantity'])} {h(r['unit'])}</td><td>{tk(r['cost'])}</td></tr>" for r in hist)
    body = f"""<div class="card"><h2>Notun purchase entry</h2>
<p class="hint">Save korle <b>trigger</b> <code>trg_purchase_add_stock</code> nijei stock bariye dibe.</p>
<form method="post" action="/admin/purchase" class="rowform">
<input type="hidden" name="action" value="buy">
<label>Supplier<select name="supplier_id" required>{supopt}</select></label>
<label>Ingredient<select name="ingredient_id" required>{ingopt}</select></label>
<label>Quantity<input type="number" name="quantity" step="0.01" min="0.01" required></label>
<label>Total cost<input type="number" name="cost" step="0.01" min="0" required></label>
<label>Date<input type="date" name="purchase_date" value="{datetime.date.today()}"></label>
<button type="submit">Save purchase</button></form></div>
<div class="card"><h2>Purchase history (last 15)</h2>
<table><tr><th>#</th><th>Date</th><th>Supplier</th><th>Ingredient</th><th>Quantity</th><th>Cost</th></tr>{hrows}</table></div>
{sqlbox('''INSERT INTO purchases (supplier_id,ingredient_id,quantity,cost,purchase_date) VALUES (?,?,?,?,?);
-- TRIGGER trg_purchase_add_stock AFTER INSERT: UPDATE ingredients SET quantity = quantity + NEW.quantity ...''')}"""
    return page('Purchase (Stock IN)', s, body, 'purchase')

def admin_orders(s, params):
    flt = params.get('status', ['All'])[0]
    where, args = '', ()
    if flt in ('Pending', 'Served', 'Paid', 'Cancelled'):
        where, args = "WHERE o.status=?", (flt,)
    orders = q(f"""SELECT o.order_id, IFNULL(c.name,'Walk-in') customer, e.name staff,
                   o.order_date,o.status,o.total_amount, COUNT(oi.order_item_id) items
                   FROM orders o LEFT JOIN customers c ON o.customer_id=c.customer_id
                   JOIN employees e ON o.employee_id=e.employee_id
                   LEFT JOIN order_items oi ON o.order_id=oi.order_id {where}
                   GROUP BY o.order_id ORDER BY o.order_id DESC""", args)
    tabs = ''.join(f'<a href="/admin/orders?status={f}" class="{"on" if flt==f else ""}">{f}</a>'
                   for f in ['All','Pending','Served','Paid','Cancelled'])
    detail = ''
    if params.get('view'):
        vid = int(params['view'][0])
        d = q("""SELECT m.name,oi.quantity,oi.price,(oi.quantity*oi.price) subtotal
                 FROM order_items oi JOIN menu_items m ON oi.menu_id=m.menu_id WHERE oi.order_id=?""", (vid,))
        drows = ''.join(f"<tr><td>{h(r['name'])}</td><td>{r['quantity']}</td><td>{tk(r['price'])}</td><td>{tk(r['subtotal'])}</td></tr>" for r in d)
        tot = sum(r['subtotal'] for r in d)
        detail = (f'<div class="card"><h2>Order #{vid} — bill</h2><table>'
                  f'<tr><th>Food</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>{drows}'
                  f'<tr class="totalrow"><td colspan="3">Total</td><td>{tk(tot)}</td></tr></table>'
                  f'<p><a href="/admin/orders">Close</a></p></div>')
    rows = ''
    for o in orders:
        opts = ''.join(f'<option {"selected" if o["status"]==st else ""}>{st}</option>'
                       for st in ['Pending','Served','Paid','Cancelled'])
        rows += f"""<tr><td>{o['order_id']}</td><td>{h(o['customer'])}</td><td>{h(o['staff'])}</td>
<td>{h(o['order_date'])}</td><td>{o['items']}</td><td>{tk(o['total_amount'])}</td>
<td><span class="badge {o['status'].lower()}">{o['status']}</span></td>
<td><form method="post" action="/admin/orders" class="inline">
<input type="hidden" name="action" value="status"><input type="hidden" name="order_id" value="{o['order_id']}">
<select name="status">{opts}</select><button class="sm" type="submit">Set</button></form></td>
<td><a class="btnlink sm" href="/admin/orders?view={o['order_id']}">View</a></td></tr>"""
    body = (f'<div class="card"><h2>Filter</h2><p class="tabs">{tabs}</p></div>{detail}'
            f'<div class="card"><h2>Orders ({len(orders)})</h2><table>'
            f'<tr><th>#</th><th>Customer</th><th>Staff</th><th>Date</th><th>Items</th>'
            f'<th>Total</th><th>Status</th><th>Change</th><th>Bill</th></tr>{rows}</table></div>')
    return page('All Orders', s, body, 'orders')

def admin_reports(s):
    reports = [
      ('Best selling food (GROUP BY)',
       "SELECT m.name, SUM(oi.quantity) sold, SUM(oi.quantity*oi.price) revenue "
       "FROM order_items oi JOIN menu_items m ON oi.menu_id=m.menu_id GROUP BY m.name ORDER BY sold DESC"),
      ('Discount cholche ja te',
       "SELECT name,category,price,discount, ROUND(price-(price*discount/100),2) final_price, "
       "ROUND(price*discount/100,2) you_save FROM menu_items WHERE discount>0 ORDER BY discount DESC"),
      ('Customer wise kharoch (HAVING)',
       "SELECT c.name, COUNT(o.order_id) orders, IFNULL(SUM(o.total_amount),0) total_spent "
       "FROM customers c LEFT JOIN orders o ON c.customer_id=o.customer_id "
       "GROUP BY c.name HAVING total_spent>0 ORDER BY total_spent DESC"),
      ('Average er cheye dami (SUBQUERY)',
       "SELECT name,price FROM menu_items WHERE price>(SELECT AVG(price) FROM menu_items) ORDER BY price DESC"),
      ('Low stock (VIEW)', "SELECT * FROM v_low_stock ORDER BY quantity"),
      ('Supplier wise total kena',
       "SELECT s.name supplier, COUNT(p.purchase_id) purchases, IFNULL(SUM(p.cost),0) total_cost "
       "FROM suppliers s LEFT JOIN purchases p ON s.supplier_id=p.supplier_id GROUP BY s.name ORDER BY total_cost DESC"),
      ('Recipe: ek plate-e ki lage (JOIN)',
       "SELECT m.name food, i.name ingredient, r.qty_required, i.unit FROM recipes r "
       "JOIN menu_items m ON r.menu_id=m.menu_id JOIN ingredients i ON r.ingredient_id=i.ingredient_id ORDER BY m.name"),
    ]
    body = '<p class="hint">Protita report ekta SQL feature dekhay.</p>'
    for title, sql in reports:
        rows = q(sql)
        if rows:
            head = ''.join(f'<th>{h(k)}</th>' for k in rows[0].keys())
            trs = ''.join('<tr>' + ''.join(f'<td>{h(v)}</td>' for v in tuple(r)) + '</tr>' for r in rows)
            tbl = f'<table><tr>{head}</tr>{trs}</table>'
        else:
            tbl = '<p class="hint">Kono row nei.</p>'
        body += (f'<div class="card"><h2>{h(title)}</h2>{tbl}'
                 f'<details class="sqlbox"><summary>SQL</summary><pre><code>{h(sql)}</code></pre></details></div>')
    return page('Reports (SQL practice)', s, body, 'reports')

# ------------------------------------------------------------------ SQL runner
# Sample query gulo — button chaple textarea-te bose jabe
SQL_SAMPLES = [
    ("Sob menu",            "SELECT * FROM menu_items;"),
    ("Discount ache ja te", "SELECT name, price, discount,\n"
                            "       ROUND(price-(price*discount/100),2) AS final_price\n"
                            "FROM menu_items WHERE discount > 0;"),
    ("Low stock (VIEW)",    "SELECT * FROM v_low_stock;"),
    ("Best selling",        "SELECT m.name, SUM(oi.quantity) AS sold\n"
                            "FROM order_items oi JOIN menu_items m ON oi.menu_id=m.menu_id\n"
                            "GROUP BY m.name ORDER BY sold DESC;"),
    ("Daily sales",         "SELECT * FROM v_daily_sales;"),
    ("Ingredient + supplier (JOIN)",
                            "SELECT i.name, i.quantity, i.unit, s.name AS supplier\n"
                            "FROM ingredients i JOIN suppliers s ON i.supplier_id=s.supplier_id;"),
    ("Avg er cheye dami (SUBQUERY)",
                            "SELECT name, price FROM menu_items\n"
                            "WHERE price > (SELECT AVG(price) FROM menu_items);"),
    ("Table list",          "SELECT name FROM sqlite_master WHERE type='table';"),
]

def admin_sql(s, sql=None, ran=False):
    # default query
    if sql is None:
        sql = "SELECT * FROM menu_items;"

    result = ''
    if ran:
        result = run_free_sql(sql)

    # sample buttons — JS diye textarea-te boshe
    chips = ''.join(
        f'<button type="button" class="chip" onclick="setSQL(this)" '
        f'data-sql="{h(text)}">{h(label)}</button>'
        for label, text in SQL_SAMPLES)

    body = f"""<div class="card">
  <h2>Nijer SQL likhe cholau</h2>
  <p class="hint">SELECT / INSERT / UPDATE / DELETE — jekono SQL. Ek sathe onek line
  likhle <b>;</b> diye alada koro. (Eta live SQLite database — result sathe sathe.)</p>
  <div class="chips">{chips}</div>
  <form method="post" action="/admin/sql">
    <textarea name="sql" spellcheck="false" class="sqlarea">{h(sql)}</textarea>
    <div class="runbar">
      <button type="submit">&#9654; Run SQL</button>
      <a class="btnlink" href="/admin/sql">Reset</a>
    </div>
  </form>
</div>
{result}
<script>
function setSQL(b){{
  document.querySelector('.sqlarea').value = b.getAttribute('data-sql');
}}
</script>"""
    return page('SQL Runner', s, body, 'sql')

def run_free_sql(sql):
    """Run whatever the user typed, statement by statement, show each result."""
    out = []
    statements = [x.strip() for x in sql.split(';') if x.strip()]
    if not statements:
        return '<div class="msg err">Kono SQL nei.</div>'
    with LOCK:
        for stmt in statements:
            try:
                cur = DB.execute(stmt)
                if cur.description:                       # SELECT-jatiyo
                    cols = [d[0] for d in cur.description]
                    rows = cur.fetchall()
                    head = ''.join(f'<th>{h(c)}</th>' for c in cols)
                    if rows:
                        trs = ''.join('<tr>' + ''.join(f'<td>{h(v)}</td>' for v in tuple(r)) + '</tr>'
                                      for r in rows)
                        tbl = f'<table><tr>{head}</tr>{trs}</table>'
                    else:
                        tbl = f'<table><tr>{head}</tr></table><p class="hint">0 row.</p>'
                    out.append(f'<div class="card"><details class="sqlbox" open>'
                               f'<summary>{h(stmt[:80])}</summary>'
                               f'<pre><code>{h(stmt)}</code></pre></details>'
                               f'<p class="out-label">Output — {len(rows)} row</p>{tbl}</div>')
                else:                                     # INSERT/UPDATE/DELETE
                    DB.commit()
                    out.append(f'<div class="card"><div class="msg ok">'
                               f'OK — {cur.rowcount} row affected.</div>'
                               f'<pre><code>{h(stmt)}</code></pre></div>')
            except Exception as e:
                DB.rollback()
                out.append(f'<div class="card"><div class="msg err">'
                           f'SQL Error: {h(e)}</div>'
                           f'<pre><code>{h(stmt)}</code></pre></div>')
    return ''.join(out)

def cust_menu(s):
    items = q("""SELECT menu_id,name,category,price,discount,
                 ROUND(price-(price*discount/100),2) final_price FROM menu_items
                 WHERE is_available=1 ORDER BY category,name""")
    cart_count = sum(x['qty'] for x in s.get('cart', {}).values())
    bycat = {}
    for r in items: bycat.setdefault(r['category'], []).append(r)
    body = f'<p class="hint">Cart-e ekhon <b>{cart_count}</b> ta item. <a href="/customer/cart">Cart dekho &rarr;</a></p>'
    for cat, rows in bycat.items():
        cards = ''
        for it in rows:
            i = it['menu_id']
            if it['discount'] > 0:
                tag = f"<span class='tag'>{it['discount']:g}% OFF</span>"
                price = f"<span class='old'>{tk(it['price'])}</span> <b class='new'>{tk(it['final_price'])}</b> BDT"
            else:
                tag = ''
                price = f"<b>{tk(it['price'])}</b> BDT"
            cards += f"""<div class="food">{tag}<h3>{h(it['name'])}</h3><p class="price">{price}</p>
<form method="post" action="/customer/menu" class="inline">
<input type="hidden" name="action" value="add_cart"><input type="hidden" name="menu_id" value="{i}">
<input class="num" type="number" name="quantity" value="1" min="1" max="20">
<button class="sm" type="submit">Add to cart</button></form></div>"""
        body += f'<div class="card"><h2>{h(cat)}</h2><div class="foodgrid">{cards}</div></div>'
    body += sqlbox("SELECT name,price,discount, ROUND(price-(price*discount/100),2) AS final_price "
                   "FROM menu_items WHERE is_available=1 ORDER BY category,name;")
    return page('Menu', s, body, 'menu')

def cust_cart(s):
    cart = s.get('cart', {})
    lines, grand, saved = [], 0, 0
    for mid, row in cart.items():
        it = q("SELECT name,price,discount, ROUND(price-(price*discount/100),2) fp FROM menu_items WHERE menu_id=?", (mid,))
        if not it: continue
        it = it[0]; sub = it['fp'] * row['qty']
        grand += sub; saved += (it['price'] - it['fp']) * row['qty']
        lines.append((mid, it, row['qty'], sub))
    if not lines:
        body = '<div class="card"><p>Cart khali. <a href="/customer/menu">Menu theke khabar nao &rarr;</a></p></div>'
        return page('My Cart', s, body, 'cart')
    rows = ''
    for mid, it, qty, sub in lines:
        disc = f"<span class='tag sm'>{it['discount']:g}% off</span>" if it['discount'] > 0 else ''
        old = f"<span class='old'>{tk(it['price'])}</span> " if it['discount'] > 0 else ''
        rows += f"""<tr><td>{h(it['name'])} {disc}</td><td>{old}<b>{tk(it['fp'])}</b></td>
<td><form method="post" action="/customer/cart" class="inline"><input type="hidden" name="action" value="qty">
<input type="hidden" name="menu_id" value="{mid}"><input class="num" type="number" name="quantity" value="{qty}" min="0" max="20">
<button class="sm" type="submit">Set</button></form></td><td>{tk(sub)}</td>
<td><form method="post" action="/customer/cart"><input type="hidden" name="action" value="remove">
<input type="hidden" name="menu_id" value="{mid}"><button class="sm danger" type="submit">Remove</button></form></td></tr>"""
    savedrow = f'<tr><td colspan="3">Discount-e bachlo</td><td colspan="2" class="new">- {tk(saved)}</td></tr>' if saved > 0 else ''
    body = f"""<div class="card"><h2>Cart</h2><table>
<tr><th>Food</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr>{rows}{savedrow}
<tr class="totalrow"><td colspan="3">Grand total</td><td colspan="2">{tk(grand)} BDT</td></tr></table>
<form method="post" action="/customer/cart" style="margin-top:14px">
<input type="hidden" name="action" value="place"><button type="submit">Place order</button></form></div>
{sqlbox('''START TRANSACTION;
INSERT INTO orders (customer_id,employee_id,order_date,status,total_amount) VALUES (?,?,NOW(),'Pending',0);
INSERT INTO order_items (order_id,menu_id,quantity,price) VALUES (?,?,?,?);   -- trigger stock komay + total baray
SELECT total_amount FROM orders WHERE order_id=?;
COMMIT;''')}"""
    return page('My Cart', s, body, 'cart')

def cust_orders(s, params):
    cid = s['customer_id']
    detail = ''
    if params.get('view'):
        vid = int(params['view'][0])
        d = q("""SELECT m.name,oi.quantity,oi.price,(oi.quantity*oi.price) subtotal
                 FROM order_items oi JOIN menu_items m ON oi.menu_id=m.menu_id
                 JOIN orders o ON o.order_id=oi.order_id WHERE oi.order_id=? AND o.customer_id=?""", (vid, cid))
        if d:
            drows = ''.join(f"<tr><td>{h(r['name'])}</td><td>{r['quantity']}</td><td>{tk(r['price'])}</td><td>{tk(r['subtotal'])}</td></tr>" for r in d)
            tot = sum(r['subtotal'] for r in d)
            detail = (f'<div class="card"><h2>Bill — Order #{vid}</h2><table>'
                      f'<tr><th>Food</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>{drows}'
                      f'<tr class="totalrow"><td colspan="3">Total</td><td>{tk(tot)} BDT</td></tr></table>'
                      f'<p><a href="/customer/my_orders">Close</a></p></div>')
    orders = q("""SELECT o.order_id,o.order_date,o.status,o.total_amount, COUNT(oi.order_item_id) items
                  FROM orders o LEFT JOIN order_items oi ON o.order_id=oi.order_id
                  WHERE o.customer_id=? GROUP BY o.order_id ORDER BY o.order_id DESC""", (cid,))
    if not orders:
        body = detail + '<div class="card"><p class="hint">Ekhono order koro nai. <a href="/customer/menu">Menu dekho &rarr;</a></p></div>'
        return page('My Orders', s, body, 'my_orders')
    rows = ''
    for o in orders:
        cancel = ''
        if o['status'] == 'Pending':
            cancel = (f'<form method="post" action="/customer/my_orders" onsubmit="return confirm(\'Cancel?\')">'
                      f'<input type="hidden" name="action" value="cancel"><input type="hidden" name="order_id" value="{o["order_id"]}">'
                      f'<button class="sm danger" type="submit">Cancel</button></form>')
        rows += f"""<tr><td>{o['order_id']}</td><td>{h(o['order_date'])}</td><td>{o['items']}</td>
<td>{tk(o['total_amount'])}</td><td><span class="badge {o['status'].lower()}">{o['status']}</span></td>
<td><a class="btnlink sm" href="/customer/my_orders?view={o['order_id']}">View</a></td><td>{cancel}</td></tr>"""
    body = (detail + f'<div class="card"><h2>Order history ({len(orders)})</h2><table>'
            f'<tr><th>#</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th>Bill</th><th></th></tr>{rows}</table></div>')
    return page('My Orders', s, body, 'my_orders')

# ------------------------------------------------------------------ actions
def do_login(form):
    u = form.get('username', [''])[0].strip()
    p = form.get('password', [''])[0]
    row = q("""SELECT u.user_id,u.password,u.role,u.customer_id,c.name cname
               FROM users u LEFT JOIN customers c ON u.customer_id=c.customer_id
               WHERE u.username=?""", (u,))
    if row and row[0]['password'] == hashlib.sha256(p.encode()).hexdigest():
        r = row[0]
        return {'user_id': r['user_id'], 'uname': r['cname'] or u, 'role': r['role'],
                'customer_id': r['customer_id'], 'cart': {}}
    return None

def food_action(s, form):
    a = form.get('action', [''])[0]
    if a == 'add':
        name = form.get('name', [''])[0].strip(); cat = form.get('category', ['Main'])[0]
        price = num(form.get('price', [0])[0]); disc = num(form.get('discount', [0])[0])
        if not name or price <= 0: s['msg'] = ('Name dao ar price 0 er beshi.', 'err')
        elif not 0 <= disc <= 100: s['msg'] = ('Discount 0-100 er moddhe.', 'err')
        else:
            try:
                run("INSERT INTO menu_items (name,category,price,discount,is_available) VALUES (?,?,?,?,1)",
                    (name, cat, price, disc))
                s['msg'] = (f"'{name}' add kora hoyeche.", 'ok')
            except sqlite3.IntegrityError:
                s['msg'] = (f"'{name}' name-e already ache.", 'err')
    elif a == 'update':
        mid = int(form['menu_id'][0]); price = num(form.get('price', [0])[0])
        disc = num(form.get('discount', [0])[0]); av = 1 if form.get('is_available') else 0
        if price <= 0 or not 0 <= disc <= 100: s['msg'] = ('Value thik na.', 'err')
        else:
            run("UPDATE menu_items SET price=?,discount=?,is_available=? WHERE menu_id=?", (price, disc, av, mid))
            s['msg'] = ('Update hoyeche.', 'ok')
    elif a == 'delete':
        mid = int(form['menu_id'][0])
        try:
            run("DELETE FROM menu_items WHERE menu_id=?", (mid,))
            s['msg'] = ('Delete kora hoyeche.', 'ok')
        except sqlite3.IntegrityError:
            s['msg'] = ('Delete kora jabe na — ei item ager order-e ache.', 'err')

def inventory_action(s, form):
    a = form.get('action', [''])[0]
    if a == 'add':
        name = form.get('name', [''])[0].strip(); unit = form.get('unit', ['kg'])[0]
        qty = num(form.get('quantity', [0])[0]); re_ = num(form.get('reorder_level', [5])[0])
        price = num(form.get('unit_price', [0])[0]); sup = form.get('supplier_id', [''])[0]
        sup = int(sup) if sup else None
        if not name: s['msg'] = ('Name dao.', 'err')
        else:
            try:
                run("INSERT INTO ingredients (name,unit,quantity,reorder_level,unit_price,supplier_id) VALUES (?,?,?,?,?,?)",
                    (name, unit, qty, re_, price, sup))
                s['msg'] = (f"'{name}' add hoyeche.", 'ok')
            except sqlite3.IntegrityError:
                s['msg'] = (f"'{name}' already ache.", 'err')
    elif a == 'update':
        i = int(form['ingredient_id'][0])
        run("UPDATE ingredients SET quantity=?,reorder_level=?,unit_price=? WHERE ingredient_id=?",
            (num(form.get('quantity', [0])[0]), num(form.get('reorder_level', [0])[0]),
             num(form.get('unit_price', [0])[0]), i))
        s['msg'] = ('Stock update hoyeche.', 'ok')
    elif a == 'delete':
        i = int(form['ingredient_id'][0])
        try:
            run("DELETE FROM ingredients WHERE ingredient_id=?", (i,))
            s['msg'] = ('Delete hoyeche.', 'ok')
        except sqlite3.IntegrityError:
            s['msg'] = ('Delete kora jabe na — purchase/recipe-te use hoyeche.', 'err')

def purchase_action(s, form):
    if form.get('action', [''])[0] == 'buy':
        sup = int(form['supplier_id'][0]); ing = int(form['ingredient_id'][0])
        qty = num(form['quantity'][0]); cost = num(form['cost'][0])
        date = form.get('purchase_date', [str(datetime.date.today())])[0] or str(datetime.date.today())
        if qty <= 0: s['msg'] = ('Quantity 0 er beshi.', 'err'); return
        before = q("SELECT name,quantity FROM ingredients WHERE ingredient_id=?", (ing,))[0]
        run("INSERT INTO purchases (supplier_id,ingredient_id,quantity,cost,purchase_date) VALUES (?,?,?,?,?)",
            (sup, ing, qty, cost, date))
        after = q("SELECT quantity FROM ingredients WHERE ingredient_id=?", (ing,))[0]
        s['msg'] = (f"Purchase save holo. TRIGGER stock barieche: {before['name']} "
                    f"{tk(before['quantity'])} -> {tk(after['quantity'])}", 'ok')

def orders_action(s, form):
    if form.get('action', [''])[0] == 'status':
        oid = int(form['order_id'][0]); st = form['status'][0]
        if st in ('Pending', 'Served', 'Paid', 'Cancelled'):
            run("UPDATE orders SET status=? WHERE order_id=?", (st, oid))
            s['msg'] = (f"Order #{oid} ekhon '{st}'.", 'ok')

def menu_action(s, form):
    if form.get('action', [''])[0] == 'add_cart':
        mid = int(form['menu_id'][0]); qty = max(1, int(num(form.get('quantity', [1])[0], 1)))
        it = q("SELECT name FROM menu_items WHERE menu_id=? AND is_available=1", (mid,))
        if not it: s['msg'] = ('Ei item pawa jacche na.', 'err'); return
        cart = s.setdefault('cart', {})
        cart.setdefault(mid, {'name': it[0]['name'], 'qty': 0})['qty'] += qty
        s['msg'] = (f"{qty} x {it[0]['name']} cart-e add hoyeche.", 'ok')

def cart_action(s, form):
    a = form.get('action', [''])[0]; cart = s.setdefault('cart', {})
    if a == 'remove':
        cart.pop(int(form['menu_id'][0]), None); s['msg'] = ('Cart theke bad deya holo.', 'ok')
    elif a == 'qty':
        mid = int(form['menu_id'][0]); qty = int(num(form['quantity'][0]))
        if qty <= 0: cart.pop(mid, None)
        elif mid in cart: cart[mid]['qty'] = qty
    elif a == 'place' and cart:
        emp = q("SELECT employee_id FROM employees WHERE role='Waiter' ORDER BY employee_id LIMIT 1")
        emp = emp[0]['employee_id'] if emp else q("SELECT employee_id FROM employees LIMIT 1")[0]['employee_id']
        with LOCK:
            try:
                now = datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                cur = DB.execute("INSERT INTO orders (customer_id,employee_id,order_date,status,total_amount) VALUES (?,?,?,'Pending',0)",
                                 (s['customer_id'], emp, now))
                oid = cur.lastrowid
                for mid, row in cart.items():
                    p = DB.execute("SELECT ROUND(price-(price*discount/100),2) fp FROM menu_items WHERE menu_id=? AND is_available=1", (mid,)).fetchone()
                    if not p: raise Exception(f"'{row['name']}' available na")
                    DB.execute("INSERT INTO order_items (order_id,menu_id,quantity,price) VALUES (?,?,?,?)",
                               (oid, mid, row['qty'], p['fp']))
                total = DB.execute("SELECT total_amount FROM orders WHERE order_id=?", (oid,)).fetchone()['total_amount']
                DB.commit()
                s['cart'] = {}
                s['msg'] = (f"Order #{oid} place hoyeche. Total {tk(total)} BDT.", 'ok')
            except Exception as e:
                DB.rollback()
                s['msg'] = (f'Order hoy ni (rollback): {e}', 'err')

def myorders_action(s, form):
    if form.get('action', [''])[0] == 'cancel':
        oid = int(form['order_id'][0])
        cur = run("UPDATE orders SET status='Cancelled' WHERE order_id=? AND customer_id=? AND status='Pending'",
                  (oid, s['customer_id']))
        s['msg'] = ((f"Order #{oid} cancel kora hoyeche.", 'ok') if cur.rowcount
                    else ("Ei order ekhon cancel kora jabe na.", 'err'))

# ------------------------------------------------------------------ routing
STATIC = {'.css': 'text/css', '.html': 'text/html; charset=utf-8',
          '.sql': 'text/plain; charset=utf-8', '.js': 'text/javascript',
          '.png': 'image/png', '.jpg': 'image/jpeg', '.svg': 'image/svg+xml'}

class Handler(BaseHTTPRequestHandler):
    def log_message(self, *a): pass

    def sess(self):
        c = http.cookies.SimpleCookie(self.headers.get('Cookie', ''))
        sid = c['sid'].value if 'sid' in c else None
        return sid, SESSIONS.get(sid)

    def new_sid(self):
        sid = hashlib.sha256(os.urandom(16)).hexdigest()[:24]
        return sid

    def serve_static(self, relpath):
        # docs live at repo root; expose them under /docs/ and /panel.css /style.css
        safe = os.path.normpath(relpath).lstrip('\\/')
        full = os.path.join(ROOT, safe)
        if not full.startswith(ROOT) or not os.path.isfile(full):
            self.send_error(404); return
        ext = os.path.splitext(full)[1].lower()
        data = open(full, 'rb').read()
        self.send_response(200)
        self.send_header('Content-Type', STATIC.get(ext, 'application/octet-stream'))
        self.send_header('Content-Length', str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def do_GET(self):
        u = urllib.parse.urlparse(self.path)
        path = u.path
        params = urllib.parse.parse_qs(u.query)
        sid, s = self.sess()

        if path in ('/panel.css', '/style.css'):
            return self.serve_static(path.lstrip('/'))
        if path.startswith('/docs/'):
            return self.serve_static(path[len('/docs/'):])
        if path == '/database.sql':
            return self.serve_static('database.sql')

        if path == '/' :
            if s: return redirect(self, '/admin/dashboard' if s['role']=='admin' else '/customer/menu')
            return redirect(self, '/login')
        if path == '/login':
            if s: return redirect(self, '/admin/dashboard' if s['role']=='admin' else '/customer/menu')
            return send_html(self, login_page({}))
        if path == '/logout':
            if sid in SESSIONS: del SESSIONS[sid]
            return redirect(self, '/login')

        if not s: return redirect(self, '/login')

        if path.startswith('/admin/') and s['role'] != 'admin': return redirect(self, '/login')
        if path.startswith('/customer/') and s['role'] != 'customer': return redirect(self, '/login')

        routes = {
            '/admin/dashboard': lambda: admin_dashboard(s),
            '/admin/food':      lambda: admin_food(s),
            '/admin/inventory': lambda: admin_inventory(s),
            '/admin/purchase':  lambda: admin_purchase(s),
            '/admin/orders':    lambda: admin_orders(s, params),
            '/admin/reports':   lambda: admin_reports(s),
            '/admin/sql':       lambda: admin_sql(s),
            '/customer/menu':   lambda: cust_menu(s),
            '/customer/cart':   lambda: cust_cart(s),
            '/customer/my_orders': lambda: cust_orders(s, params),
        }
        if path in routes:
            return send_html(self, routes[path]())
        self.send_error(404)

    def do_POST(self):
        length = int(self.headers.get('Content-Length', 0))
        form = urllib.parse.parse_qs(self.rfile.read(length).decode('utf-8'))
        path = urllib.parse.urlparse(self.path).path
        sid, s = self.sess()

        if path == '/login':
            s2 = do_login(form)
            if s2:
                sid = self.new_sid(); SESSIONS[sid] = s2
                return redirect(self, '/admin/dashboard' if s2['role']=='admin' else '/customer/menu', sid=sid)
            return send_html(self, login_page({}, 'Username ba password thik nei.'))

        if not s: return redirect(self, '/login')

        # SQL runner: result ta sorasori dekhai (redirect kori na)
        if path == '/admin/sql':
            if s['role'] != 'admin': return redirect(self, '/login')
            sql = form.get('sql', [''])[0]
            return send_html(self, admin_sql(s, sql=sql, ran=True))

        handlers = {
            '/admin/food':      (food_action, '/admin/food'),
            '/admin/inventory': (inventory_action, '/admin/inventory'),
            '/admin/purchase':  (purchase_action, '/admin/purchase'),
            '/admin/orders':    (orders_action, '/admin/orders'),
            '/customer/menu':   (menu_action, '/customer/menu'),
            '/customer/cart':   (cart_action, '/customer/cart'),
            '/customer/my_orders': (myorders_action, '/customer/my_orders'),
        }
        if path in handlers:
            fn, back = handlers[path]
            fn(s, form)
            return redirect(self, back)
        self.send_error(404)


if __name__ == '__main__':
    print(f"Restaurant DBMS demo running:  http://localhost:{PORT}")
    print("  admin/admin123   |   rahim/1234")
    ThreadingHTTPServer(('0.0.0.0', PORT), Handler).serve_forever()
