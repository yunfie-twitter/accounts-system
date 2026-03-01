<?php
declare(strict_types=1);

/* 設定ファイル読み込み */
require_once __DIR__ . '/config.php';

/* Authインスタンス生成 (config.phpで生成済みなら不要だが念のため) */
if (!isset($auth) || !($auth instanceof Auth)) {
    require_once __DIR__ . '/classes/Auth.php';
    $auth = new Auth($pdo);
}

/* ユーザー情報取得（未ログインならnull） */
$user = $auth->user();

/* 未ログインならリダイレクト */
if ($user === null) {
    if ($auth->autoLogin()) {
        $user = $auth->user();
    } else {
        header('Location: /auth/login.php?timeout=1');
        exit;
    }
}

/* 最終アクセス日時を更新 */
$auth->touch();

/* ページタイトル設定 */
$pageTitle = 'ダッシュボード';

/* ヘッダー読み込み (Bootstrap含む) */
// header.php 内で <body> 直後に <nav class="fixed-top"> があると仮定
require_once __DIR__ . '/include/header.php';
?>

<!-- 
    ★重要: コンテンツが見切れないようにするためのスタイル調整 
    header.php 側ですでに設定されている場合は不要ですが、
    念のため個別に指定しておくと確実です。
-->
<style>
    body {
        /* ナビゲーションバーの高さに合わせて調整 (通常は56px〜70px) */
        padding-top: 70px; 
    }
    
    /* スマホ表示時の微調整 */
    @media (max-width: 768px) {
        body {
            padding-top: 80px; 
        }
    }
</style>

<!-- メインコンテンツ (header.php で <main class="container"> が開かれている前提) -->

    <!-- ウェルカムメッセージ -->
    <div class="bg-light p-5 rounded mb-4">
        <h1 class="display-4">ようこそ、<?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?> さん</h1>
        <p class="lead">これはダッシュボードのトップページです。</p>
        
        <?php if (!empty($user['is_verified'])): ?>
            <span class="badge bg-success">メール認証済み ✅</span>
        <?php else: ?>
            <span class="badge bg-warning text-dark">メール未認証 ⚠️</span>
            <p class="mt-2 text-muted small">すべての機能を利用するにはメール認証が必要です。</p>
        <?php endif; ?>
    </div>

    <!-- コンテンツカード例 -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">マイアカウント情報</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>ユーザーID:</strong> <?= (int)$user['id'] ?></li>
                        <li class="list-group-item"><strong>ユーザー名:</strong> <?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></li>
                        <!-- <li class="list-group-item"><strong>メール:</strong> <?= htmlspecialchars($user['email'] ?? '非表示', ENT_QUOTES, 'UTF-8') ?></li> -->
                    </ul>
                </div>
                <div class="card-footer text-muted">
                    最終アクセス: 現在
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">クイックメニュー</div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="#" class="btn btn-outline-primary">プロフィール編集</a>
                        <a href="#" class="btn btn-outline-secondary">パスワード変更</a>
                        <?php if (empty($user['is_verified'])): ?>
                            <a href="/auth/resend.php" class="btn btn-warning">認証メールを再送する</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ログアウトボタン（ヘッダーにもあるのでここには不要なら削除可） -->
    <div class="text-end mt-4">
        <a href="/auth/logout.php" class="btn btn-danger">ログアウト</a>
    </div>

<?php
/* フッター読み込み (</body> </html> 閉じタグ含む) */
require_once __DIR__ . '/include/footer.php';
?>