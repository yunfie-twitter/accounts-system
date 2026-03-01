<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/* CSRF 初期化（必須） */
csrf_init();

/* ログイン済みならダッシュボードへ */
if ($auth->check()) {
    header('Location: ../dashboard.php');
    exit;
}

$msg = '';
if (isset($_GET['error'])) {
    $msg = match ($_GET['error']) {
        'empty'    => 'すべての項目を入力してください。',
        'email'    => 'そのメールアドレスは既に登録されています。',
        'password' => 'パスワードが一致しません。',
        default    => '登録に失敗しました。'
    };
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>新規登録</title>
</head>
<body>

<h1>新規登録</h1>

<?php if ($msg): ?>
<p style="color:red"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<form method="post" action="register_process.php">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div>
        <input
            type="text"
            name="username"
            required
            placeholder="ユーザー名">
    </div>

    <div>
        <input
            type="email"
            name="email"
            required
            placeholder="メールアドレス">
    </div>

    <div>
        <input
            type="password"
            name="password"
            required
            placeholder="パスワード">
    </div>

    <div>
        <input
            type="password"
            name="password_confirm"
            required
            placeholder="パスワード（確認）">
    </div>

    <button type="submit">登録する</button>
</form>

<p>
    <a href="login.php">ログインはこちら</a>
</p>

</body>
</html>