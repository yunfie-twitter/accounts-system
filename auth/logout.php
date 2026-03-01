<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth($pdo);   // ★これが抜けていた

$auth->logout();

header('Location: /auth/login.php');
exit;