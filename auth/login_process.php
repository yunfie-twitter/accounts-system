<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/* POST以外は拒否 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}


/* CSRF検証 */
csrf_validate();

/* 入力取得 */
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']) && $_POST['remember'] === '1';

/* バリデーション */
if ($email === '' || $password === '') {
    header('Location: login.php?error=1');
    exit;
}

/* ログイン */
if ($auth->login($email, $password, $remember)) {
    header('Location: ../dashboard.php');
    exit;
}

/* 失敗 */
header('Location: login.php?error=1');
exit;