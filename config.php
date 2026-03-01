<?php

declare(strict_types=1);

/* ===============================================
   1. セッション設定 & 開始 (最重要)
   =============================================== */
if (session_status() === PHP_SESSION_NONE) {
    // セッションのセキュリティ設定（環境に合わせて調整してください）
    session_set_cookie_params([
        'lifetime' => 3600,       // 1時間
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,      // ★HTTPS化したら true に変更してください
        'httponly' => true,       // JavaScriptからのアクセス禁止（XSS対策）
        'samesite' => 'Strict'    // CSRF対策の強化
    ]);
    session_start();
}

/* ===============================================
   2. 定数定義
   =============================================== */
define('SESSION_LIFETIME', 60); // 分

/* ===============================================
   3. CSRF対策関数
   =============================================== */

/**
 * CSRFトークンを初期化する
 * セッションにトークンがない場合のみ生成する（上書き防止）
 */
function csrf_init(): void
{
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            // random_bytesが使えない場合のフォールバック
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }
}

/**
 * 現在のCSRFトークンを取得（フォーム埋め込み用）
 */
function csrf_token(): string
{
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * POST送信されたCSRFトークンを検証する
 * 一致しない場合は強制終了
 */
function csrf_validate(): void
{
    // POSTメソッド以外は検証対象外とする場合はここでreturnしても良いが、
    // 安全のためPOST前提でチェックする
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $tokenPost = $_POST['csrf_token'] ?? '';
    $tokenSession = $_SESSION['csrf_token'] ?? '';

    if ($tokenPost === '' || $tokenSession === '' || !hash_equals($tokenSession, $tokenPost)) {
        http_response_code(403);
        exit('CSRF validation failed: トークンが無効か、セッションが切れています。');
    }
}

/* ===============================================
   4. 指紋生成 (フィンガープリント)
   =============================================== */
if (!function_exists('generate_fingerprint')) {
    function generate_fingerprint(): string
    {
        $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '';

        // IPアドレスの第2オクテットまでを使用（プライバシー配慮＆変動対策）
        $ipPrefix = implode('.', array_slice(explode('.', $ip), 0, 2));

        return hash('sha256', $ua . '|' . $lang . '|' . $ipPrefix);
    }
}

/* ===============================================
   5. 環境変数(.env)読み込み
   =============================================== */
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    // 本番環境などでは .env がなくても環境変数が設定されている場合があるため
    // 必須でなければ warning に留めるか、適切にハンドリングしてください
    exit('.env not found');
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines !== false) {
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

/* ===============================================
   6. データベース接続 (PDO)
   =============================================== */
try {
    $dsn = sprintf(
        "mysql:host=%s;dbname=%s;charset=utf8mb4",
        $_ENV['DB_HOST'] ?? 'localhost',
        $_ENV['DB_NAME'] ?? 'test_db'
    );
    
    $pdo = new PDO(
        $dsn,
        $_ENV['DB_USER'] ?? 'root',
        $_ENV['DB_PASS'] ?? '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // 本番では詳細なエラーメッセージを出さないほうが安全
    exit('Database Connection Failed: ' . $e->getMessage());
}

/* ===============================================
   7. Authクラスの初期化
   =============================================== */
require_once __DIR__ . '/classes/Auth.php';

// Authクラスが存在する場合のみインスタンス化
if (class_exists('Auth')) {
    $auth = new Auth($pdo);
} else {
    exit('Class Auth not found.');
}