<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user']) || $_SESSION['role_rank'] < 1) {
    header("Location: ../account/login.php");
    exit;
}

// アーカイブ一覧取得
$archives = $pdo->query("SELECT * FROM archive WHERE archive = 'true'")->fetchAll();

// クエリパラメータでアーカイブを選択された場合
$selected_id = $_GET['id'] ?? null;
$quizzes = [];

if ($selected_id) {
    $stmt = $pdo->prepare("SELECT * FROM Quiz WHERE id LIKE ?");
    $stmt->execute([$selected_id . '%']); // アーカイブIDで始まるQuizを取得
    $quizzes = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>アーカイブ一覧</title>
</head>
<body>
  <h1>📚 クイズアーカイブ</h1>

  <h2>アーカイブ選択</h2>
  <ul>
    <?php foreach ($archives as $arc): ?>
      <li><a href="?id=<?= htmlspecialchars($arc['id']) ?>">
        <?= htmlspecialchars($arc['archive_name']) ?>
      </a></li>
    <?php endforeach; ?>
  </ul>

  <?php if ($selected_id): ?>
    <h2>アーカイブID：<?= htmlspecialchars($selected_id) ?> のクイズ</h2>
    <ol>
      <?php foreach ($quizzes as $quiz): ?>
        <li>
          <strong>形式：</strong><?= htmlspecialchars($quiz['形式']) ?><br>
          <strong>問題：</strong><?= nl2br(htmlspecialchars($quiz['問題'])) ?><br>

          <?php if ($quiz['形式'] === '選択式'): ?>
            <?php
              $stmt = $pdo->prepare("SELECT 選択式 FROM 選択式 WHERE id = ?");
              $stmt->execute([$quiz['id']]);
              $choices = explode(",", $stmt->fetchColumn());
            ?>
            <ul>
              <?php foreach ($choices as $choice): ?>
                <li><?= htmlspecialchars(trim($choice)) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php elseif ($quiz['形式'] === '記述式'): ?>
            <?php
              $stmt = $pdo->prepare("SELECT 答え FROM 記述式 WHERE id = ?");
              $stmt->execute([$quiz['id']]);
              $answer = $stmt->fetchColumn();
            ?>
            <p><strong>答え：</strong><?= htmlspecialchars($answer) ?></p>
          <?php endif; ?>

          <p><strong>解説：</strong><?= nl2br(htmlspecialchars($quiz['解説'])) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</body>
</html>

