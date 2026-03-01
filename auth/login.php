<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/* CSRF 初期化（←これが重要） */
csrf_init();

/* ログイン済みならダッシュボードへ */
if ($auth->check()) {
    header('Location: ../dashboard.php');
    exit;
}

$msg = '';
if (isset($_GET['timeout'])) {
    $msg = 'セッションの有効期限が切れました。再ログインしてください。';
}
if (isset($_GET['error'])) {
    $msg = 'メールアドレスまたはパスワードが正しくありません。';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>ログイン</title>
</head>
<body>

<?php if ($msg): ?>
<p style="color:red"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<form method="post" action="login_process.php">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div>
        <input type="email" name="email" required placeholder="メールアドレス">
    </div>

    <div>
        <input type="password" name="password" required placeholder="パスワード">
    </div>

    <div>
        <label>
            <input type="checkbox" name="remember" value="1">
            ログイン状態を保持する
        </label>
    </div>

    <button type="submit">ログイン</button>
</form>

</body>
</html>
