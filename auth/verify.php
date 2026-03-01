<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$message = '';
$isSuccess = false;

// GETパラメータからトークンを取得
$token = $_GET['token'] ?? '';

if ($token === '') {
    $message = '無効なリクエストです。';
} else {
    // トークン検証処理
    if ($auth->verifyEmail($token)) {
        $isSuccess = true;
        $message = 'メールアドレスの認証が完了しました！';
        
        // もしログイン中なら、セッション内のステータス情報を更新するためにリロードさせる等の工夫も可能ですが
        // 基本的には次回アクセス時に反映されます。
    } else {
        $message = 'トークンが無効か、有効期限が切れています。<br>再度、認証メールをリクエストしてください。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>メールアドレス認証</title>
</head>
<body>

<div class="container">
    <h1>メール認証</h1>

    <?php if ($isSuccess): ?>
        <p class="success"><?= $message ?></p>
        <p>すべての機能をご利用いただけます。</p>
        <a href="../dashboard.php" class="btn">ダッシュボードへ</a>
    <?php else: ?>
        <p class="error"><?= $message ?></p>
        <a href="../index.php" class="btn">トップページへ戻る</a>
    <?php endif; ?>
</div>

</body>
</html>