<?php
session_start();
require_once('../config/db.php');

if (!isset($_SESSION['user']) || $_SESSION['role_rank'] < 1) {
    header('Location: ../account/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_result WHERE user_id = ? AND correct = 1");
    $stmt->execute([$user_id]);
    $count = $stmt->fetchColumn();
}
?>

<h2>ユーザーの正解数を確認</h2>
<form method="post">
  <label>ユーザーID または コード:</label>
  <input type="text" name="user_id" required>
  <input type="submit" value="検索">
</form>

<?php if (isset($count)): ?>
  <p>正解数：<?= $count ?>問</p>
<?php endif; ?>
