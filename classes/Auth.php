<?php

declare(strict_types=1);

class Auth
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* =========================
       新規会員登録
       (メール認証は必須にせず、登録直後にログイン可能にする)
    ========================= */
    public function register(string $username, string $email, string $password): int
    {
        // メールアドレス重複チェック
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            throw new Exception('このメールアドレスは既に登録されています。');
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $this->pdo->beginTransaction();
        try {
            // ステータス1（有効）で作成する
            $stmt = $this->pdo->prepare(
                "INSERT INTO users (username, email, password, status, created_at) 
                 VALUES (:name, :email, :pass, 1, NOW())"
            );
            $stmt->execute([
                ':name'  => $username,
                ':email' => $email,
                ':pass'  => $hashedPassword
            ]);
            
            $userId = (int)$this->pdo->lastInsertId();

            // 認証用トークンは裏で作成しておく (後で送信するため)
            $token = bin2hex(random_bytes(32));
            $stmt = $this->pdo->prepare(
                "INSERT INTO email_verifications (user_id, token, expires_at) 
                 VALUES (:uid, :token, DATE_ADD(NOW(), INTERVAL 24 HOUR))"
            );
            $stmt->execute([
                ':uid'   => $userId,
                ':token' => $token
            ]);

            $this->pdo->commit();

            // 必要ならここで $this->sendVerificationEmail($email, $token) を呼ぶ
            
            return $userId; // 登録されたユーザーIDを返す

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /* =========================
       メール認証処理
    ========================= */
    public function verifyEmail(string $token): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT user_id FROM email_verifications 
             WHERE token = :token AND expires_at > NOW()"
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        $userId = (int)$row['user_id'];

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE users SET email_verified_at = NOW() WHERE id = :id"
            );
            $stmt->execute([':id' => $userId]);

            $stmt = $this->pdo->prepare("DELETE FROM email_verifications WHERE user_id = :uid");
            $stmt->execute([':uid' => $userId]);

            $this->pdo->commit();
            
            // 現在ログイン中ならセッション内の認証状態も更新したいが、
            // ページリロード時にDBから再取得されるのでここでは何もしなくてOK
            
            return true;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /* =========================
       ログイン処理 (修正版)
    ========================= */
    public function login(string $email, string $password, bool $remember = false): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, password, email_verified_at, status
             FROM users
             WHERE email = :email"
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // ユーザーなし or パスワード不一致
        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        // 凍結アカウント(status=2)などは弾くが、未認証(status=1でもverified_at=NULL)は通す
        if ((int)$user['status'] === 2) {
            throw new Exception('アカウントが凍結されています。');
        }

        session_regenerate_id(true);

        $_SESSION['user_id']    = (int)$user['id'];
        $_SESSION['username']   = $user['username'];
        
        // CSRFトークン再生成
        if (function_exists('csrf_init')) {
            unset($_SESSION['csrf_token']);
            csrf_init();
        }

        $this->storeSession((int)$user['id']);

        if ($remember) {
            $this->createRememberToken((int)$user['id']);
        }

        return true;
    }

    /* =========================
       現在のユーザー情報を取得 (拡張)
    ========================= */
    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        // DBから最新の状態を取得（認証済みか確認するため）
        $stmt = $this->pdo->prepare("SELECT email_verified_at FROM users WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $userData = $stmt->fetch();

        return [
            'id'          => $_SESSION['user_id'],
            'username'    => $_SESSION['username'],
            'is_verified' => !empty($userData['email_verified_at']) // 認証済みならtrue
        ];
    }

    /* =========================
       以下、既存のヘルパーメソッド (変更なし)
    ========================= */

    private function storeSession(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO user_sessions
             (user_id, session_id, fingerprint_hash, ip_address, user_agent, last_activity)
             VALUES (:uid, :sid, :fp, :ip, :ua, NOW())"
        );
        $stmt->execute([
            ':uid' => $userId,
            ':sid' => session_id(),
            ':fp'  => generate_fingerprint(),
            ':ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'  => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    public function check(): bool
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "SELECT id FROM user_sessions
             WHERE session_id = :sid
               AND user_id = :uid
               AND fingerprint_hash = :fp
               AND last_activity > (NOW() - INTERVAL :min MINUTE)"
        );
        $stmt->execute([
            ':sid' => session_id(),
            ':uid' => $_SESSION['user_id'],
            ':fp'  => generate_fingerprint(),
            ':min' => SESSION_LIFETIME
        ]);

        if (!$stmt->fetch()) {
            $this->logout();
            return false;
        }
        $this->touch();
        return true;
    }

    public function touch(): void
    {
        if (session_id()) {
            $stmt = $this->pdo->prepare(
                "UPDATE user_sessions SET last_activity = NOW() WHERE session_id = :sid"
            );
            $stmt->execute([':sid' => session_id()]);
        }
    }

    public function logout(): void
    {
        if (session_id()) {
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE session_id = :sid");
            $stmt->execute([':sid' => session_id()]);
        }
        if (!empty($_COOKIE['remember_me'])) {
            $parts = explode(':', $_COOKIE['remember_me'], 2);
            if (count($parts) === 2) {
                $stmt = $this->pdo->prepare("DELETE FROM remember_tokens WHERE selector = :sel");
                $stmt->execute([':sel' => $parts[0]]);
            }
            setcookie('remember_me', '', time() - 3600, '/', '', true, true);
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    private function createRememberToken(int $userId): void
    {
        $selector = bin2hex(random_bytes(6));
        $token    = bin2hex(random_bytes(32));
        $hash     = hash('sha256', $token);

        $stmt = $this->pdo->prepare(
            "INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
             VALUES (:uid, :sel, :hash, DATE_ADD(NOW(), INTERVAL 30 DAY))"
        );
        $stmt->execute([':uid' => $userId, ':sel' => $selector, ':hash' => $hash]);

        setcookie('remember_me', $selector . ':' . $token, time() + 60 * 60 * 24 * 30, '/', '', true, true);
    }

    public function autoLogin(): bool
    {
        if (!empty($_SESSION['user_id']) || empty($_COOKIE['remember_me'])) {
            return false;
        }
        $parts = explode(':', $_COOKIE['remember_me'], 2);
        if (count($parts) !== 2) {
            return false;
        }
        [$selector, $token] = $parts;

        $stmt = $this->pdo->prepare(
            "SELECT user_id, token_hash FROM remember_tokens WHERE selector = :sel AND expires_at > NOW()"
        );
        $stmt->execute([':sel' => $selector]);
        $row = $stmt->fetch();

        if (!$row || !hash_equals($row['token_hash'], hash('sha256', $token))) {
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT id, username FROM users WHERE id = :id");
        $stmt->execute([':id' => $row['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id']  = (int)$user['id'];
        $_SESSION['username'] = $user['username'];

        if (function_exists('csrf_init')) {
            unset($_SESSION['csrf_token']);
            csrf_init();
        }
        $this->storeSession((int)$user['id']);
        return true;
    }

    public function sessions(): array
    {
        if (empty($_SESSION['user_id'])) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            "SELECT id, ip_address, user_agent, last_activity FROM user_sessions WHERE user_id = :uid ORDER BY last_activity DESC"
        );
        $stmt->execute([':uid' => $_SESSION['user_id']]);
        return $stmt->fetchAll();
    }

    public function killSession(int $sessionId): void
    {
        if (empty($_SESSION['user_id'])) {
            return;
        }
        $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $sessionId, ':uid' => $_SESSION['user_id']]);
    }

    public function requireAuth(): void
    {
        if (!$this->check()) {
            if (!$this->autoLogin()) {
                header('Location: /auth/login.php?timeout=1');
                exit;
            }
        }
    }
}
