<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user']) || $_SESSION['role_rank'] < 1) {
    header('Location: ../account/login.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM quiz ORDER BY id DESC");
$quizzes = $stmt->fetchAll();
?>

<h2>クイズアーカイブ</h2>
<table border="1">
  <tr><th>ID</th><th>問題</th><th>形式</th><th>正解</th></tr>
  <?php foreach ($quizzes as $quiz): ?>
    <tr>
      <td><?= $quiz['id'] ?></td>
      <td><?= htmlspecialchars($quiz['question']) ?></td>
      <td><?= $quiz['type'] ?></td>
      <td><?= htmlspecialchars($quiz['answer']) ?></td>
    </tr>
  <?php endforeach; ?>
</table>
