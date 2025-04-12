<?php
require_once './config/db.php';
session_start();

$user_id = $_SESSION['user_id'] ?? $_COOKIE['user_id'] ?? null;
if (!$user_id) {
    header('Location: account/login.php');
    exit;
}

// 問題取得
$stmt = $pdo->query("SELECT * FROM quiz ORDER BY RAND() LIMIT 1");
$quiz = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_answer = trim($_POST['answer']);
    $correct = ($user_answer === $quiz['answer']);

    // 答えを記録
    $stmt = $pdo->prepare("INSERT INTO quiz_result (user_id, quiz_id, correct) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $quiz['id'], $correct ? 1 : 0]);

    // 正解数をアップデート
    if ($correct) {
        $pdo->prepare("UPDATE user SET correct_count = correct_count + 1 WHERE id = ?")->execute([$user_id]);
        echo "<script>alert('Correct!');location.href='quiz.php';</script>";
    } else {
        echo "<script>alert('Wrong! Answer: {$quiz['answer']}');location.href='quiz.php';</script>";
    }
}
?>
<h1><?php echo $quiz['question']; ?></h1>
<form method="post">
<?php if ($quiz['type'] === 'choice'): ?>
  <?php foreach (json_decode($quiz['choices']) as $choice): ?>
    <label><input type="radio" name="answer" value="<?php echo $choice; ?>"><?php echo $choice; ?></label><br>
  <?php endforeach; ?>
<?php else: ?>
  <input type="text" name="answer">
<?php endif; ?>
  <button type="submit">Submit</button>
</form>
