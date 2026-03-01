<?php
/* include/header.php */

// ページタイトル初期化
if (!isset($pageTitle)) {
    $pageTitle = 'My Application';
}

// Auth情報の確認 (config.phpで生成済みと想定)
$currentUser = null;
if (isset($auth) && $auth instanceof Auth) {
    $currentUser = $auth->user();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- カスタムCSS -->
    <style>
        body { 
            padding-top: 4.5rem; /* Navbar の高さ分だけ */
            background-color: #f8f9fa; 
        }
        .navbar-brand {
            transition: opacity 0.3s;
        }
        .navbar-brand:hover {
            opacity: 0.8;
        }
        /* アラートの余白調整 */
        .alert-top {
            margin-top: 0; /* Navbar 下にぴったり */
        }
        main.container {
            margin-top: 0; /* Bootstrap デフォルト余白リセット */
        }
    </style>
</head>
<body>

<!-- ナビゲーションバー -->
<nav class="navbar navbar-expand-md navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand" href="/">My App</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" 
                aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarCollapse">
            <ul class="navbar-nav me-auto mb-2 mb-md-0">
                <li class="nav-item">
                    <a class="nav-link <?= ($_SERVER['SCRIPT_NAME'] === '/dashboard.php') ? 'active' : '' ?>" 
                       href="/dashboard.php">ダッシュボード</a>
                </li>
                <!-- 必要なリンクがあればここに追加 -->
            </ul>

            <div class="d-flex">
                <?php if ($currentUser): ?>
                    <span class="navbar-text me-3 text-white" 
                          title="<?= htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8') ?>">
                        <i class="bi bi-person-circle"></i> 
                        <?= htmlspecialchars(mb_strimwidth($currentUser['username'], 0, 12, '…'), ENT_QUOTES, 'UTF-8') ?> さん
                    </span>
                    <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#logoutModal">ログアウト</button>
                <?php else: ?>
                    <a href="/auth/login.php" class="btn btn-outline-light btn-sm me-2">ログイン</a>
                    <a href="/auth/register.php" class="btn btn-primary btn-sm">新規登録</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- 未認証ユーザーへの警告アラート -->
<?php if ($currentUser && empty($currentUser['is_verified'])): ?>
    <div class="alert alert-warning alert-dismissible fade show alert-top" role="alert">
        <strong><i class="bi bi-exclamation-triangle-fill"></i> メールアドレス未認証</strong>
        <span class="ms-2">現在、一部の機能が制限されています。登録メールアドレスをご確認ください。</span>
        <a href="/auth/resend.php" class="btn btn-sm btn-warning ms-2">認証メール再送</a>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- ログアウト確認モーダル -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="logoutModalLabel">ログアウト確認</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <div class="modal-body">
        本当にログアウトしますか？
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">キャンセル</button>
        <a href="/auth/logout.php" class="btn btn-danger btn-sm">ログアウト</a>
      </div>
    </div>
  </div>
</div>

<!-- メインコンテナ開始 -->
<main class="container">