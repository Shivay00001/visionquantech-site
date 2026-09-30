<?php
// Admin sign-in.
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (vq_admin() !== null) {
    header('Location: index.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } elseif (vq_login($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
        sleep(1); // tiny brute-force friction
    }
}

vq_head('Login');
?>
<div class="card login-box">
  <h2>Sign in</h2>
  <?php if ($error !== ''): ?>
    <div class="error"><?= e($error) ?></div>
  <?php endif; ?>
  <form method="post" action="login.php" autocomplete="off">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" required autofocus>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>
    <p><button class="btn" type="submit">Sign in</button></p>
  </form>
</div>
<?php vq_foot(); ?>
