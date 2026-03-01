<?php
declare(strict_types=1);

/* 1. 設定ファイル読み込み */
require_once __DIR__ . '/../config.php';

/* Authインスタンス生成 (config.phpで生成済みなら不要) */
if (!isset($auth) || !($auth instanceof Auth)) {
    require_once __DIR__ . '/classes/Auth.php';
    $auth = new Auth($pdo);
}

/* 2. ログインチェック (未ログインならログイン画面へ) */
$user = $auth->user();
if ($user === null) {
    header('Location: /auth/login.php');
    exit;
}

/* 3. すでに認証済みならダッシュボードへ */
if (!empty($user['is_verified'])) {
    header('Location: /dashboard.php');
    exit;
}

$message = '';
$isError = false;

/* 4. POSTリクエスト時の処理 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRFチェック
    if (function_exists('csrf_validate')) {
        csrf_validate();
    }

    // 連続送信防止 (60秒制限)
    if (isset($_SESSION['last_resend_time']) && (time() - $_SESSION['last_resend_time'] < 60)) {
        $message = 'メール送信の間隔が短すぎます。しばらく待ってから再試行してください。';
        $isError = true;
    } else {
        try {
            $userId = (int)$user['id'];
            
            // 古いトークンがあれば削除
            $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE user_id = :uid");
            $stmt->execute([':uid' => $userId]);

            // 新しいトークン生成
            $token = bin2hex(random_bytes(32));
            
            // トークン保存 (24時間有効)
            $stmt = $pdo->prepare(
                "INSERT INTO email_verifications (user_id, token, expires_at) 
                 VALUES (:uid, :token, DATE_ADD(NOW(), INTERVAL 24 HOUR))"
            );
            $stmt->execute([
                ':uid'   => $userId,
                ':token' => $token
            ]);

            // メール送信処理 (実際にはここにメール送信コードを書く)
            // 例: sendVerificationEmail($user['email'], $token);
            // $verifyUrl = "https://example.com/auth/verify.php?token=" . $token;
            // mb_send_mail(...)

            // 送信時刻を記録
            $_SESSION['last_resend_time'] = time();

            $message = '認証メールを再送信しました。受信トレイを確認してください。';
            
        } catch (Exception $e) {
            $message = 'エラーが発生しました: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
            $isError = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>認証メール再送信</title>
</head>
<body>

<div class="container">
    <h2>認証メールの再送信</h2>
    
    <p>登録されたメールアドレス宛に認証リンクを再送します。</p>

    <?php if ($message): ?>
        <div class="message <?= $isError ? 'error' : 'success' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if (!$message || $isError): ?>
        <form method="post" action="">
            <!-- CSRFトークン埋め込み -->
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <button type="submit">メールを送信する</button>
        </form>
    <?php endif; ?>

    <a href="/dashboard.php" class="back-link">ダッシュボードへ戻る</a>
</div>

</body>
</html>