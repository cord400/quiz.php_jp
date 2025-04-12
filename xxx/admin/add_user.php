<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user']) || $_SESSION['role_rank'] < 2) {
    header('Location: ../account/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role_rank = 1;

    $stmt = $pdo->prepare("INSERT INTO user (email, password, role, role_rank) VALUES (?, ?, 'admin', ?)");
    $stmt->execute([$email, $pass, $role_rank]);

    echo "管理者アカウントを作成しました！";
}
?>

<h2>管理者アカウント作成</h2>
<form method="post">
  <label>メールアドレス:</label><br>
  <input type="email" name="email" required><br>
  <label>パスワード:</label><br>
  <input type="password" name="password" required><br>
  <input type="submit" value="作成">
</form>
