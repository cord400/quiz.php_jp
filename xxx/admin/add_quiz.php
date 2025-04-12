<?php
require_once '../config/db.php';
session_start();
// アドミンチェック
if ($_SESSION['user_id']) {
    $user = $pdo->prepare("SELECT role_rank FROM user WHERE id = ?");
    $user->execute([$_SESSION['user_id']]);
    $rank = $user->fetchColumn();
    if ($rank < 1) exit('Permission denied');
} else {
    exit('Login required');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("INSERT INTO quiz (question, answer, type, choices) VALUES (?, ?, ?, ?)");
    $choices = ($_POST['type'] === 'choice') ? json_encode($_POST['choices']) : null;
    $stmt->execute([$_POST['question'], $_POST['answer'], $_POST['type'], $choices]);
    echo "<script>alert('Quiz added!');location.href='add_quiz.php';</script>";
}
?>
<form method="post">
  <input name="question" placeholder="Question" required>
  <input name="answer" placeholder="Answer" required>
  <select name="type">
    <option value="text">Text</option>
    <option value="choice">Multiple Choice</option>
  </select>
  <textarea name="choices" placeholder="[\"A\",\"B\"] (if choice type)"></textarea>
  <button type="submit">Add Quiz</button>
</form>
