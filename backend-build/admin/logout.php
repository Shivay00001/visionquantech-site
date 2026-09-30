<?php
// Admin sign-out.
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

vq_logout();
header('Location: login.php');
exit;
