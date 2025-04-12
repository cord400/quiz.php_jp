<?php
require_once '../config/db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO user (email, password, correct_count, role, role_rank) VALUES (?, ?, 0, 'user', 0)");
    $stmt->execute([$email, $pass]);
    echo "<script>alert('Sign up successful!');location.href='login.php';</script>";
}
?>
<form method="post">
  <input type="email" name="email" required>
  <input type="password" name="password" required>
  <button type="submit">Sign Up</button>
</form>
