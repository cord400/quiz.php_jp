<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user']) || $_SESSION['role_rank'] < 1) {
    header('Location: ../account/login.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $question = $_POST['question'];
    $type = $_POST['type'];
    $answer = $_POST['answer'];
    $choices = $_POST['choices'] ?? '';

    $stmt = $pdo->prepare("INSERT INTO quiz (question, type, choices, answer) VALUES (?, ?, ?, ?)");
    $stmt->execute([$question, $type, $choices, $answer]);
    echo "クイズを追加しました！";
}
?>

<h2>クイズ作成</h2>
<form method="post">
  <label>問題文:</label><br>
  <textarea name="question" required></textarea><br>

  <label>形式:</label>
  <select name="type">
    <option value="choice">選択式</option>
    <option value="text">記述式</option>
  </select><br>

  <label>選択肢（選択式のみ）<br>カンマ区切りで入力:</label>
  <input type="text" name="choices"><br>

  <label>正解:</label>
  <input type="text" name="answer" required><br>

  <input type="submit" value="クイズ追加">
</form>
