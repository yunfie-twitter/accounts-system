<?php
require_once __DIR__ . "/../config.php";

csrf_validate(); 

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: register.php");
    exit;
}

if ($auth->register($_POST["username"], $_POST["email"], $_POST["password"])) {
    header("Location: login.php");
    exit;
}

exit("登録失敗");