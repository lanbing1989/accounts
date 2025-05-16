<?php
require_once 'inc/functions.php';
checkLogin();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    if ($role === 'admin') $role = '超级管理员'; // 关键逻辑：管理员用“超级管理员”存储
    if (!$username || !$password) {
        $msg = "用户名和密码不能为空";
    } else {
        global $db;
        $stmt = $db->prepare("SELECT id FROM users WHERE username=?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $msg = "用户名已存在";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username,password,role) VALUES (?,?,?)");
            $stmt->execute([$username, $hash, $role]);
            header("Location: settings.php");
            exit;
        }
    }
}
include 'templates/header.php';
?>
<style>
.adduser-panel {
    max-width:420px;
    margin:48px auto 0 auto;
    background:#fff;
    border-radius:12px;
    box-shadow:0 2px 16px #e5eaf7;
    padding:36px 38px 32px 38px;
}
.adduser-title {
    font-size:22px;
    color:#247bfc;
    font-weight:700;
    margin-bottom:20px;
    text-align:center;
    letter-spacing:2px;
}
.adduser-form label {
    font-size:15px;
    font-weight:600;
    display:block;
    margin-bottom:8px;
}
.adduser-form input[type="text"],
.adduser-form input[type="password"],
.adduser-form select {
    width: 100%;
    padding: 7px 10px;
    border: 1px solid #c7dafc;
    border-radius: 5px;
    margin-bottom: 16px;
    font-size: 15px;
}
.adduser-btn {
    background:#247bfc;
    color:#fff;
    border:none;
    padding:8px 30px;
    border-radius:6px;
    font-size:16px;
    cursor:pointer;
    margin-right:12px;
}
.adduser-btn-cancel {
    background:#bbb;
    color:#333;
}
.adduser-msg {
    margin:16px 0 0 0;
    text-align:center;
    font-size:16px;
    font-weight:600;
}
.adduser-msg-error {
    color:#e14646;
}
@media(max-width:600px){
    .adduser-panel{padding:18px 3vw;}
}
</style>

<div class="adduser-panel">
    <div class="adduser-title">新增用户</div>
    <?php if($msg): ?>
        <div class="adduser-msg adduser-msg-error"><?=htmlspecialchars($msg)?></div>
    <?php endif;?>
    <form class="adduser-form" method="post" action="">
        <label>用户名</label>
        <input type="text" name="username" autocomplete="off" required>
        <label>密码</label>
        <input type="password" name="password" autocomplete="new-password" required>
        <label>角色</label>
        <select name="role">
            <option value="admin">管理员</option>
            <option value="accountant">会计</option>
            <option value="cashier">出纳</option>
            <option value="viewer">只读</option>
        </select>
        <div style="margin-top:26px;text-align:center;">
            <button type="submit" class="adduser-btn">保存</button>
            <a href="settings.php" class="adduser-btn adduser-btn-cancel">取消</a>
        </div>
    </form>
</div>
<?php include 'templates/footer.php'; ?>