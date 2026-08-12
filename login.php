<?php
/* ============================================================
   login.php  —  admin ar customer duijoner jonno ek e login
   ============================================================ */
require __DIR__ . '/config.php';

// already logged in? direct panel-e pathao
if (!empty($_SESSION['role'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin'
            ? 'admin/dashboard.php' : 'customer/menu.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Prepared statement -> SQL Injection thekay
    $sql = "SELECT u.user_id, u.username, u.password, u.role, u.customer_id,
                   c.name AS customer_name
            FROM users u
            LEFT JOIN customers c ON u.customer_id = c.customer_id
            WHERE u.username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // database-e SHA2(password,256) rakha ache, tai same vabe hash kore milai
    if ($user && $user['password'] === hash('sha256', $password)) {
        $_SESSION['user_id']     = $user['user_id'];
        $_SESSION['uname']       = $user['customer_name'] ?: $user['username'];
        $_SESSION['role']        = $user['role'];
        $_SESSION['customer_id'] = $user['customer_id'];

        header('Location: ' . ($user['role'] === 'admin'
                ? 'admin/dashboard.php' : 'customer/menu.php'));
        exit;
    }
    $error = 'Username ba password thik nei.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | Restaurant DBMS</title>
<link rel="stylesheet" href="panel.css">
</head>
<body class="loginpage">

<div class="loginbox">
  <h1>Restaurant System</h1>
  <p class="sub">Login to continue</p>

  <?php if ($error): ?>
    <div class="msg err"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <label>Username
      <input type="text" name="username" required autofocus
             value="<?= h($_POST['username'] ?? '') ?>">
    </label>
    <label>Password
      <input type="password" name="password" required>
    </label>
    <button type="submit">Login</button>
  </form>

  <div class="demo">
    <strong>Demo accounts</strong>
    <table>
      <tr><th>Role</th><th>Username</th><th>Password</th></tr>
      <tr><td>Admin</td><td>admin</td><td>admin123</td></tr>
      <tr><td>Customer</td><td>rahim</td><td>1234</td></tr>
      <tr><td>Customer</td><td>karim</td><td>1234</td></tr>
    </table>
  </div>

  <p class="back"><a href="index.html">&larr; Project documentation</a></p>
</div>

</body>
</html>
