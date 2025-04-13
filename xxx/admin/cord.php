<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user']) || $_SESSION['role_rank'] < 1) {
  header('Location: ../account/login.php');
  exit;
}

if (isset($_GET['uid'])) {
  $uid = $_GET['uid'];
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM 正解記録 WHERE user_id = ?");
  $stmt->execute([$uid]);
  $count = $stmt->fetchColumn();
  echo "<h2>✅ {$uid} の正解数：{$count}</h2>";
} else {
  echo '<form><label>ユーザーID：<input type="text" name="uid" required></label><button type="submit">確認</button></form>';
}
