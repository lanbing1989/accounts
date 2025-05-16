<?php
require_once 'inc/functions.php';
checkLogin();

$username = $_SESSION['username'] ?? '';
if (!$username) {
    header("Location: login.php");
    exit;
}
global $db;
$stmt = $db->prepare("SELECT * FROM users WHERE username=? LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!is_array($user)) {
    echo "<!DOCTYPE html><meta charset='utf-8'><div style='color:red;text-align:center;margin-top:40px'>用户不存在<br><a href='login.php'>重新登录</a></div>";
    exit;
}

$msg = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldpw = $_POST['oldpw'] ?? '';
    $pw1 = $_POST['pw1'] ?? '';
    $pw2 = $_POST['pw2'] ?? '';
    if (!password_verify($oldpw, $user['password'])) {
        $msg = "原密码错误";
    } elseif (strlen($pw1) < 6) {
        $msg = "新密码不能少于6位";
    } elseif ($pw1 !== $pw2) {
        $msg = "两次输入新密码不一致";
    } elseif ($oldpw === $pw1) {
        $msg = "新密码不能与原密码相同";
    } else {
        $hash = password_hash($pw1, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->execute([$hash, $user['id']]);
        $success = true;
        $msg = "密码修改成功！请牢记新密码。";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>修改我的密码</title>
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
            border-top:4px solid #2676f5;
            animation:fadeInDown 0.7s;
        }
        @keyframes fadeInDown {
            from { opacity:0; transform:translateY(-36px);}
            to { opacity:1; transform:translateY(0);}
        }
        .resetpw-title {
            font-size:24px;
            color:#2676f5;
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
            color:#294b7b;
        }
        .resetpw-form input[type="password"] {
            width:100%;
            padding:10px 12px;
            border:1px solid #c7dafc;
            border-radius:7px;
            margin-bottom:18px;
            font-size:16px;
            background:#f7fbff;
            transition:border-color 0.2s;
        }
        .resetpw-form input[type="password"]:focus {
            border-color:#2676f5;
            background:#fff;
            outline:none;
        }
        .resetpw-btn {
            background:linear-gradient(90deg,#347eff 0%,#62b6ff 100%);
            color:#fff;
            border:none;
            padding:10px 38px;
            border-radius:7px;
            font-size:17px;
            cursor:pointer;
            font-weight:600;
            box-shadow:0 2px 8px #e5eaf7;
            transition:background 0.18s,box-shadow 0.18s;
        }
        .resetpw-btn:hover {
            background:linear-gradient(90deg,#2166e5 0%,#3fa9f5 100%);
            box-shadow:0 4px 20px #c7dafc;
        }
        .resetpw-btn-cancel {
            background:#eee;
            color:#333;
            margin-left:14px;
            font-weight:500;
            border:1px solid #d3dfea;
        }
        .resetpw-btn-cancel:hover {
            background:#d3dfea;
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
    <div class="resetpw-title">修改我的密码</div>
    <div style="text-align:center;color:#888;font-size:15px;margin-bottom:20px;">
        当前用户：<span style="color:#2676f5;font-weight:600"><?=htmlspecialchars($user['username'])?></span>
    </div>
    <?php if($msg): ?>
        <div class="resetpw-msg <?=$success?'resetpw-msg-success':'resetpw-msg-error'?>"><?=htmlspecialchars($msg)?></div>
    <?php endif;?>
    <?php if(!$success): ?>
    <form class="resetpw-form" method="post" action="">
        <label for="oldpw">原密码</label>
        <input type="password" id="oldpw" name="oldpw" autocomplete="current-password" required placeholder="请输入原密码">
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
            <a href="index.php" class="resetpw-btn resetpw-btn-cancel">返回设置</a>
        </div>
    <?php endif;?>
</div>
</body>
</html>