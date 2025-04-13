<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user']) || $_SESSION['role_rank'] < 2) {
  echo "許可されていません。";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = $_POST['id'];
  $username = $_POST['username'];
  $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
  $stmt = $pdo->prepare("INSERT INTO user (id, username, password, role) VALUES (?, ?, ?, '1')");
  $stmt->execute([$id, $username, $password]);
  echo "✅ 管理者アカウントを追加しました。";
}
?>
<form method="post">
  <label>ID:<input type="text" name="id" required></label><br>
  <label>ユーザー名:<input type="text" name="username" required></label><br>
  <label>パスワード:<input type="password" name="password" required></label><br>
  <button type="submit">追加</button>
</form>
