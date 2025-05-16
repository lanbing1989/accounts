<?php
require_once 'inc/functions.php';
checkLogin();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header("Location: settings.php");
    exit;
}
global $db;
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!is_array($user)) {
    echo "<!DOCTYPE html><meta charset='utf-8'><div style='color:red;text-align:center;margin-top:40px'>用户不存在或数据损坏<br><a href='settings.php'>返回</a></div>";
    exit;
}

$msg = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw1 = $_POST['pw1'] ?? '';
    $pw2 = $_POST['pw2'] ?? '';
    if (strlen($pw1) < 6) {
        $msg = "密码不能少于6位";
    } elseif ($pw1 !== $pw2) {
        $msg = "两次输入密码不一致";
    } else {
        $hash = password_hash($pw1, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->execute([$hash, $id]);
        $success = true;
        $msg = "密码重置成功！";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>重置用户密码</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/static/main.css">
    <style>
        body { background:#f6f8fc; font-family:"Segoe UI","Microsoft YaHei",Arial,sans-serif;}
        .resetpw-panel {
            max-width:410px;
            margin:48px auto 0 auto;
            background:#fff;
            border-radius:16px;
            box-shadow:0 4px 28px #e5eaf7;
            padding:42px 38px 32px 38px;
            border-top:4px solid #ff9800;
            animation:fadeInDown 0.7s;
        }
        @keyframes fadeInDown {
            from { opacity:0; transform:translateY(-36px);}
            to { opacity:1; transform:translateY(0);}
        }
        .resetpw-title {
            font-size:24px;
            color:#ff9800;
            font-weight:700;
            margin-bottom:28px;
            text-align:center;
            letter-spacing:1.5px;
        }
        .resetpw-form label {
            font-size:15px;
            font-weight:600;
            display:block;
            margin-bottom:10px;
            color:#7a5a26;
        }
        .resetpw-form input[type="password"] {
            width:100%;
            padding:10px 12px;
            border:1px solid #fad2a2;
            border-radius:7px;
            margin-bottom:18px;
            font-size:16px;
            background:#fffaf5;
            transition:border-color 0.2s;
        }
        .resetpw-form input[type="password"]:focus {
            border-color:#ff9800;
            background:#fff;
            outline:none;
        }
        .resetpw-btn {
            background:linear-gradient(90deg,#ff9800 0%,#ffc260 100%);
            color:#fff;
            border:none;
            padding:10px 38px;
            border-radius:7px;
            font-size:17px;
            cursor:pointer;
            font-weight:600;
            box-shadow:0 2px 8px #fbe7b2;
            transition:background 0.18s,box-shadow 0.18s;
        }
        .resetpw-btn:hover {
            background:linear-gradient(90deg,#e57c00 0%,#ffbc42 100%);
            box-shadow:0 4px 20px #fbe7b2;
        }
        .resetpw-btn-cancel {
            background:#eee;
            color:#333;
            margin-left:14px;
            font-weight:500;
            border:1px solid #ffe1b2;
        }
        .resetpw-btn-cancel:hover {
            background:#ffe1b2;
            color:#222;
        }
        .resetpw-msg {
            margin:10px 0 18px 0;
            text-align:center;
            font-size:16px;
            font-weight:600;
        }
        .resetpw-msg-error {
            color:#e14646;
            background:#fff4f4;
            border:1px solid #ffd2d2;
            border-radius:6px;
            padding:8px 0;
        }
        .resetpw-msg-success {
            color:#219a41;
            background:#eaffea;
            border:1px solid #b2eebc;
            border-radius:6px;
            padding:8px 0;
        }
        @media(max-width:600px) {
            .resetpw-panel { padding:20px 2vw; margin:16px 2vw 0 2vw;}
        }
    </style>
</head>
<body>
<div class="resetpw-panel">
    <div class="resetpw-title">重置密码</div>
    <div style="text-align:center;color:#888;font-size:15px;margin-bottom:20px;">
        用户：<span style="color:#ff9800;font-weight:600"><?=htmlspecialchars($user['username'])?></span>
    </div>
    <?php if($msg): ?>
        <div class="resetpw-msg <?=$success?'resetpw-msg-success':'resetpw-msg-error'?>"><?=htmlspecialchars($msg)?></div>
    <?php endif;?>
    <?php if(!$success): ?>
    <form class="resetpw-form" method="post" action="">
        <label for="pw1">新密码</label>
        <input type="password" id="pw1" name="pw1" autocomplete="new-password" required minlength="6" placeholder="请输入新密码">
        <label for="pw2">确认新密码</label>
        <input type="password" id="pw2" name="pw2" autocomplete="new-password" required minlength="6" placeholder="再次输入新密码">
        <div style="margin-top:28px;text-align:center;">
            <button type="submit" class="resetpw-btn">保存</button>
            <a href="settings.php" class="resetpw-btn resetpw-btn-cancel">取消</a>
        </div>
    </form>
    <?php else: ?>
        <div style="text-align:center;margin:28px 0 0 0;">
            <a href="settings.php" class="resetpw-btn resetpw-btn-cancel">返回管理</a>
        </div>
    <?php endif;?>
</div>
</body>
</html>