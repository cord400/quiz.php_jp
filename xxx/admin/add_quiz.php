<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user']) || $_SESSION['role_rank'] < 1) {
  header('Location: ../account/login.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = $_POST['id'];
  $format = $_POST['format'];
  $question = $_POST['question'];
  $explanation = $_POST['explanation'];

  $stmt = $pdo->prepare("INSERT INTO Quiz (id, 形式, 問題, 解説) VALUES (?, ?, ?, ?)");
  $stmt->execute([$id, $format, $question, $explanation]);

  if ($format === '選択式') {
    $choices = $_POST['choices'];
    $stmt = $pdo->prepare("INSERT INTO 選択式 (id, 選択式) VALUES (?, ?)");
    $stmt->execute([$id, $choices]);
  } else {
    $answer = $_POST['answer'];
    $stmt = $pdo->prepare("INSERT INTO 記述式 (id, 答え) VALUES (?, ?)");
    $stmt->execute([$id, $answer]);
  }
  echo "<p>✅ クイズを追加しました。</p>";
}
?>
<form method="post">
  <label>ID: <input type="text" name="id" required></label><br>
  <label>形式:
    <select name="format" onchange="toggleFields(this.value)">
      <option value="選択式">選択式</option>
      <option value="記述式">記述式</option>
    </select>
  </label><br>
  <label>問題:<br><textarea name="question" required></textarea></label><br>
  <label>解説:<br><textarea name="explanation"></textarea></label><br>
  <div id="choice-area">
    <label>選択肢（カンマ区切り）:<br><input type="text" name="choices"></label>
  </div>
  <div id="text-answer-area" style="display:none">
    <label>答え:<br><input type="text" name="answer"></label>
  </div>
  <button type="submit">追加</button>
</form>
<script>
function toggleFields(format) {
  document.getElementById('choice-area').style.display = format === '選択式' ? 'block' : 'none';
  document.getElementById('text-answer-area').style.display = format === '記述式' ? 'block' : 'none';
}
</script>
