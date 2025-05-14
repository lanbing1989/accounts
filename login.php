<?php
require_once 'inc/functions.php';
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
<html><head><meta charset="utf-8"><title>登录</title></head>
<body>
<h2>会计软件登录</h2>
<?php if($error): ?><div style="color:red"><?=$error?></div><?php endif;?>
<form method="post">
    用户名：<input type="text" name="username" required><br><br>
    密码：<input type="password" name="password" required><br><br>
    <button type="submit">登录</button>
</form>
</body>
</html>