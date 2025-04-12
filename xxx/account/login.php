<?php
require_once '../config/db.php';
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM user WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    $user = $stmt->fetch();

    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        setcookie("user_id", $user['id'], time()+3600*24*7); // cookie
        header('Location: ../quiz.php');
    } else {
        echo "Login failed";
    }
}
?>
<form method="post">
  <input type="email" name="email">
  <input type="password" name="password">
  <button type="submit">Login</button>
</form>
