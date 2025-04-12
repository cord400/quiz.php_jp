<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user']) || $_SESSION['role_rank'] < 1) {
    header('Location: ../account/login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>管理者ページ</title>
</head>
<body>
  <h1>管理メニュー</h1>
  <ul>
    <li><a href="add_quiz.php">クイズを追加</a></li>
    <li><a href="archive.php">クイズのアーカイブ</a></li>
    <li><a href="cord.php">ユーザー正解数確認（QR/コード）</a></li>
    <?php if ($_SESSION['role_rank'] >= 2): ?>
      <li><a href="add_user.php">管理者アカウント作成</a></li>
    <?php endif; ?>
    <li><a href="../quiz.php">クイズページへ</a></li>
  </ul>
</body>
</html>
