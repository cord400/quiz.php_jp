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
<head><meta charset="UTF-8"><title>管理者ホーム</title></head>
<body>
<h1>🔒 管理者ホーム</h1>
<ul>
  <li><a href="add_quiz.php">クイズ作成</a></li>
  <li><a href="archive.php">アーカイブ一覧</a></li>
  <li><a href="cord.php">ユーザー正解数確認</a></li>
  <li><a href="add_user.php">管理者アカウント作成</a></li>
</ul>
</body>
</html>
