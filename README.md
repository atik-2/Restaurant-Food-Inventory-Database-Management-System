# Restaurant & Food Inventory Database Management System

DBMS project — MySQL database + PHP panels (Admin & Customer).

---

## 1. Setup (XAMPP)

1. **XAMPP install koro** — https://www.apachefriends.org
2. XAMPP Control Panel-e **Apache** ar **MySQL** duitai **Start** koro.
3. Ei puro `work` folder ta copy kore `C:\xampp\htdocs\` er vitore rakho.
   (path hobe `C:\xampp\htdocs\work\`)
4. Browser-e jao: `http://localhost/phpmyadmin`
5. Upore **Import** tab → **Choose File** → `database.sql` select koro → **Go**.
   → `restaurant_db` database toiri hoye jabe, sob data soho.
6. Ekhon site kholo: **`http://localhost/work/`**

> Password thakle `config.php`-er `$DB_PASS` e boshiye dao. XAMPP-e default khali.

---

## 2. Login

| Panel | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Customer | `rahim` | `1234` |
| Customer | `karim` | `1234` |
| Customer | `nusrat` | `1234` |

---

## 3. File gulo ki kaj kore

```
work/
├── index.html        Documentation — introduction, objectives, features
├── schema.html       10 table-er structure, ER diagram, normalization
├── sql.html          Sob CREATE TABLE, VIEW, TRIGGER er code
├── queries.html      16 ta query + tader output
├── database.sql      ← eta phpMyAdmin-e import korte hobe
│
├── config.php        Database connection + helper function
├── login.php         Login (admin ar customer duijoner jonno ek e page)
├── logout.php
├── header.php        Sob panel page-er upor-er ongsho
├── footer.php
│
├── admin/
│   ├── dashboard.php   Stats, low stock alert, recent orders
│   ├── food.php        ★ Food ADD + PRICE change + DISCOUNT set + DELETE
│   ├── inventory.php   Ingredient stock add/update/delete
│   ├── purchase.php    Supplier theke maal kena (trigger stock baray)
│   ├── orders.php      Sob order dekha, status change, bill
│   └── reports.php     8 ta report — GROUP BY, HAVING, subquery, VIEW
│
├── customer/
│   ├── menu.php        Khabar dekha (discount soho), cart-e add
│   ├── cart.php        Cart + order place (TRANSACTION)
│   └── my_orders.php   Nijer order history, bill, cancel
│
├── style.css         Documentation page er style
└── panel.css         Panel er style
```

---

## 4. Kon file-e kon SQL concept ache

| Concept | Kothay |
|---|---|
| CREATE TABLE, PK, FK | `database.sql` PART 1 |
| Constraints (NOT NULL, UNIQUE, CHECK, DEFAULT) | `database.sql` PART 1 |
| INSERT / UPDATE / DELETE | `admin/food.php`, `admin/inventory.php` |
| SELECT + WHERE + ORDER BY | `customer/menu.php` |
| INNER JOIN / LEFT JOIN | `admin/orders.php`, `admin/inventory.php` |
| GROUP BY + HAVING | `admin/reports.php` |
| Aggregate (COUNT, SUM, AVG) | `admin/dashboard.php`, `admin/reports.php` |
| Subquery | `admin/reports.php` |
| VIEW | `admin/dashboard.php` (`v_low_stock`) |
| TRIGGER | `admin/purchase.php`, `customer/cart.php` |
| TRANSACTION (COMMIT/ROLLBACK) | `customer/cart.php` |
| Prepared statement (SQL injection protection) | sob PHP file |

> Proti panel page-er niche **"Ei page-e ja SQL cholche"** box ache —
> click korle oi page-er asol query dekha jabe. Viva-r somoy kaje lagbe.

---

## 5. Demo dekhanor niyom (viva)

**Discount:**
1. Admin → **Food & Price** → Chicken Biryani-r discount `10` → **Save**
2. Logout → `rahim` diye login → **Menu** → dam kata dekhabe (280 → 252)

**Trigger (main feature):**
1. Admin → **Inventory** → Rice ar Chicken-er stock note koro
2. Logout → customer login → Biryani order koro
3. Abar admin → **Inventory** → stock nije nije kome geche
   (recipes table onujayi: rice −0.25, chicken −0.30 per plate)

**Purchase trigger:**
- Admin → **Purchase** → Chicken 25 kg kino → stock 25 bere jabe.

---

## 6. Chalate somossa hole

| Problem | Solution |
|---|---|
| PHP code text hisebe dekhacche | Apache cholche na, ba file `htdocs`-e nei |
| "Database connect hocche na" | MySQL start koro, `database.sql` import koro |
| "Access denied for user root" | `config.php`-e `$DB_PASS` e MySQL password dao |
| Trigger kaj korche na | Import-er somoy error hoyechilo — `database.sql` abar import koro |
