<?php
// quiz.php
session_start();
require_once('config/db.php');

if (!isset($_SESSION['user'])) {
  header('Location: account/login.php');
  exit;
}

// ランダムで1問取得
$stmt = $pdo->query("SELECT * FROM Quiz ORDER BY RAND() LIMIT 1");
$quiz = $stmt->fetch();

if (!$quiz) {
  echo "<p>クイズが見つかりません。</p>";
  exit;
}

$quiz_id = $quiz['id'];
$format = $quiz['形式'];
$question = $quiz['問題'];
$explanation = $quiz['解説'];

// 回答処理
$result_message = "";
$correct = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $user_answer = trim($_POST['answer']);

  if ($format === '選択式') {
    $stmt = $pdo->prepare("SELECT 選択式 FROM 選択式 WHERE id = ?");
    $stmt->execute([$quiz_id]);
    $answers = explode(',', $stmt->fetchColumn());
    $correct_answer = $answers[0]; // 最初が正解と仮定
    if ($user_answer === $correct_answer) {
      $correct = true;
    }
  } else {
    $stmt = $pdo->prepare("SELECT 答え FROM 記述式 WHERE id = ?");
    $stmt->execute([$quiz_id]);
    $correct_answer = trim($stmt->fetchColumn());
    if ($user_answer === $correct_answer) {
      $correct = true;
    }
  }

  if ($correct) {
    $stmt = $pdo->prepare("INSERT INTO 正解記録 (user_id, quiz_id) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user'], $quiz_id]);
    $result_message = "<p class='correct'>🎉 正解！</p>";
  } else {
    $result_message = "<p class='wrong'>❌ 不正解。正解は：{$correct_answer}</p>";
  }
  $result_message .= "<p class='explain'>解説：{$explanation}</p>";
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>クイズ</title>
  <style>
    body { font-family: sans-serif; margin: 2rem; }
    .correct { color: green; font-weight: bold; animation: fadeIn 1s; }
    .wrong { color: red; font-weight: bold; animation: shake 0.5s; }
    .explain { margin-top: 1em; color: #333; }
    @keyframes fadeIn { from {opacity:0;} to {opacity:1;} }
    @keyframes shake {
      0% { transform: translateX(0); }
      25% { transform: translateX(-5px); }
      50% { transform: translateX(5px); }
      75% { transform: translateX(-5px); }
      100% { transform: translateX(0); }
    }
  </style>
</head>
<body>
<h1>🧠 クイズに挑戦！</h1>

<p><strong>問題：</strong> <?= htmlspecialchars($question) ?></p>

<form method="post">
<?php if ($format === '選択式'): ?>
  <?php
    $stmt = $pdo->prepare("SELECT 選択式 FROM 選択式 WHERE id = ?");
    $stmt->execute([$quiz_id]);
    $choices = explode(',', $stmt->fetchColumn());
    shuffle($choices);
    foreach ($choices as $choice):
  ?>
    <label><input type="radio" name="answer" value="<?= htmlspecialchars($choice) ?>" required> <?= htmlspecialchars($choice) ?></label><br>
  <?php endforeach; ?>
<?php else: ?>
  <input type="text" name="answer" required>
<?php endif; ?>
  <button type="submit">回答する</button>
</form>

<?= $result_message ?>

</body>
</html>
