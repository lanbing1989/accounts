<?php
require_once 'inc/functions.php';
session_start();
if (!empty($_SESSION['user_id'])) { header("Location: index.php"); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD']=='POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $user = getUserByName($username);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header('Location: index.php');
        exit;
    }
    $error = '用户名或密码错误';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>登录 - 会计软件</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body {
            background: #f5f7fa;
            font-family: "Segoe UI", "Microsoft YaHei", Arial, sans-serif;
        }
        .login-box {
            margin: 80px auto 0 auto;
            width: 350px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.10);
            background: #fff;
            border-radius: 10px;
            padding: 30px 35px 25px 35px;
        }
        .login-title {
            text-align: center;
            font-size: 1.5em;
            color: #2b3547;
            margin-bottom: 22px;
            letter-spacing: 2px;
        }
        .login-form label {
            display: block;
            margin-bottom: 8px;
            color: #5a6070;
            font-weight: bold;
        }
        .login-form input[type="text"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 10px 10px;
            margin-bottom: 18px;
            border: 1px solid #bfc5d0;
            border-radius: 5px;
            font-size: 1em;
            box-sizing: border-box;
        }
        .login-form button {
            width: 100%;
            background: #2676f5;
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 12px 0;
            font-size: 1.05em;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(38,118,245,0.10);
            transition: background 0.2s;
        }
        .login-form button:hover {
            background: #185fcb;
        }
        .error-message {
            margin-bottom: 16px;
            color: #d93025;
            text-align: center;
            font-size: 1em;
        }
        .login-footer {
            margin-top: 15px;
            text-align: center;
            color: #999;
            font-size: 0.95em;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="login-title">会计软件登录</div>
        <?php if($error): ?><div class="error-message"><?=$error?></div><?php endif;?>
        <form method="post" class="login-form" autocomplete="off">
            <label for="username">用户名</label>
            <input type="text" name="username" id="username" required autofocus autocomplete="username">

            <label for="password">密码</label>
            <input type="password" name="password" id="password" required autocomplete="current-password">

            <button type="submit">登录</button>
        </form>
        <div class="login-footer">© <?=date('Y')?> 会计软件系统</div>
    </div>
</body>
</html>