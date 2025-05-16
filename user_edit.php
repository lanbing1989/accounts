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

// 关键防御：不是数组就不输出任何HTML，直接退出
if (!is_array($user)) {
    echo "<!DOCTYPE html><meta charset='utf-8'><div style='color:red;text-align:center;margin-top:40px'>用户不存在或数据损坏<br><a href='settings.php'>返回</a></div>";
    exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    if ($role === 'admin') $role = '超级管理员';
    $roles = ['超级管理员', 'accountant', 'cashier', 'viewer'];

    if (!$username) {
        $msg = "用户名不能为空";
    } elseif (!in_array($role, $roles)) {
        $msg = "非法角色";
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE username=? AND id<>?");
        $stmt->execute([$username, $id]);
        if ($stmt->fetch()) {
            $msg = "用户名已存在";
        } elseif ($user['username'] === 'admin' && $username !== 'admin') {
            $msg = "admin账户用户名不可修改";
        } elseif ($user['username'] === 'admin' && $role !== '超级管理员') {
            $msg = "admin账户角色不可修改";
        } else {
            $stmt = $db->prepare("UPDATE users SET username=?, role=? WHERE id=?");
            $stmt->execute([$username, $role, $id]);
            header("Location: settings.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>编辑用户</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/static/main.css">
    <style>
        body {
            background: #f6f8fc;
            font-family: "Segoe UI", "Microsoft YaHei", Arial, sans-serif;
        }
        .edituser-panel {
            max-width: 410px;
            margin: 48px auto 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 28px #e5eaf7;
            padding: 42px 38px 32px 38px;
            border-top: 4px solid #347eff;
            animation: fadeInDown 0.7s;
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-36px);}
            to { opacity: 1; transform: translateY(0);}
        }
        .edituser-title {
            font-size: 24px;
            color: #347eff;
            font-weight: 700;
            margin-bottom: 28px;
            text-align: center;
            letter-spacing: 1.5px;
        }
        .edituser-form label {
            font-size: 15px;
            font-weight: 600;
            display: block;
            margin-bottom: 10px;
            color: #294b7b;
        }
        .edituser-form input[type="text"],
        .edituser-form select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #c7dafc;
            border-radius: 7px;
            margin-bottom: 18px;
            font-size: 16px;
            background: #f7fbff;
            transition: border-color 0.2s;
        }
        .edituser-form input[type="text"]:focus,
        .edituser-form select:focus {
            border-color: #347eff;
            background: #fff;
            outline: none;
        }
        .edituser-btn {
            background: linear-gradient(90deg, #347eff 0%, #62b6ff 100%);
            color: #fff;
            border: none;
            padding: 10px 38px;
            border-radius: 7px;
            font-size: 17px;
            cursor: pointer;
            font-weight: 600;
            box-shadow: 0 2px 8px #e5eaf7;
            transition: background 0.18s, box-shadow 0.18s;
        }
        .edituser-btn:hover {
            background: linear-gradient(90deg, #2166e5 0%, #3fa9f5 100%);
            box-shadow: 0 4px 20px #c7dafc;
        }
        .edituser-btn-cancel {
            background: #eee;
            color: #333;
            margin-left: 14px;
            font-weight: 500;
            border: 1px solid #d3dfea;
        }
        .edituser-btn-cancel:hover {
            background: #d3dfea;
            color: #222;
        }
        .edituser-msg {
            margin: 10px 0 18px 0;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
        }
        .edituser-msg-error {
            color: #e14646;
            background: #fff4f4;
            border: 1px solid #ffd2d2;
            border-radius: 6px;
            padding: 8px 0;
        }
        @media(max-width: 600px) {
            .edituser-panel {
                padding: 20px 2vw;
                margin: 16px 2vw 0 2vw;
            }
        }
    </style>
</head>
<body>
<div class="edituser-panel">
    <div class="edituser-title">编辑用户</div>
    <?php if($msg): ?>
        <div class="edituser-msg edituser-msg-error"><?=htmlspecialchars($msg)?></div>
    <?php endif;?>
    <form class="edituser-form" method="post" action="">
        <label for="username">用户名</label>
        <input type="text" id="username" name="username" value="<?=htmlspecialchars($user['username'])?>" autocomplete="off" required <?=$user['username']==='admin'?'readonly style="background:#f5f5f5;cursor:not-allowed;"':''?>>
        <label for="role">角色</label>
        <select name="role" id="role" <?=$user['username']==='admin'?'disabled style="background:#f5f5f5;cursor:not-allowed;"':''?>>
            <option value="admin" <?=$user['role']=='超级管理员'?'selected':''?>>管理员</option>
            <option value="accountant" <?=$user['role']=='accountant'?'selected':''?>>会计</option>
            <option value="cashier" <?=$user['role']=='cashier'?'selected':''?>>出纳</option>
            <option value="viewer" <?=$user['role']=='viewer'?'selected':''?>>只读</option>
        </select>
        <div style="margin-top:28px;text-align:center;">
            <button type="submit" class="edituser-btn">保存</button>
            <a href="settings.php" class="edituser-btn edituser-btn-cancel">取消</a>
        </div>
    </form>
</div>
</body>
</html>