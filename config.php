<?php
/* ============================================================
   config.php  —  database connection + chotto helper functions
   Sob PHP page ei file ta require kore.
   ============================================================ */

// duibar include hole session_start() abar call korle warning ase, tai check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Database setting (XAMPP default) ----
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';                 // XAMPP-e password khali thake
$DB_NAME = 'restaurant_db';

// error hole exception throw korbe, tai try/catch kaj kore
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    die('<div style="font:15px/1.6 system-ui;max-width:640px;margin:60px auto;padding:24px;
                     border:1px solid #d33;border-radius:8px;background:#fff5f5">
           <h2 style="margin:0 0 8px">Database connect hocche na</h2>
           <p><b>Error:</b> ' . htmlspecialchars($e->getMessage()) . '</p>
           <p>Check koro:</p>
           <ol>
             <li>XAMPP-e <b>Apache</b> ar <b>MySQL</b> duitai Start kora ache kina</li>
             <li>phpMyAdmin-e <b>database.sql</b> import kora hoyeche kina</li>
             <li>config.php-te $DB_USER / $DB_PASS thik ache kina</li>
           </ol>
         </div>');
}

/* ---------- helper functions ---------- */

// HTML escape — output safe rakhe (XSS bondho kore)
function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// taka format: 1234.5 -> 1,234.50
function tk($v) {
    return number_format((float)$v, 2);
}

// discount baad diye asol price
function final_price($price, $discount) {
    return round($price - ($price * $discount / 100), 2);
}

// ei page ta shudhu ei role dekhte parbe
function require_role($role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        header('Location: ../login.php');
        exit;
    }
}

// ek page theke onno page-e chotto message pathay
function set_msg($text, $type = 'ok') {
    $_SESSION['msg'] = ['text' => $text, 'type' => $type];
}

function show_msg() {
    if (!empty($_SESSION['msg'])) {
        $m = $_SESSION['msg'];
        unset($_SESSION['msg']);
        echo '<div class="msg ' . h($m['type']) . '">' . h($m['text']) . '</div>';
    }
}

// page-er niche SQL dekhay — viva-r somoy kaje lage
function sql_box($sql) {
    echo '<details class="sqlbox"><summary>Ei page-e ja SQL cholche</summary><pre><code>'
       . h(trim($sql)) . '</code></pre></details>';
}
