<?php
require_once 'inc/functions.php';
checkLogin();
$msg = '';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['dbfile'])) {
    $dbfile = defined('DB_FILE') ? DB_FILE : (dirname(__DIR__) . '/db/accounting.db');
    if ($dbfile && $_FILES['dbfile']['error'] === 0) {
        $up = $_FILES['dbfile']['tmp_name'];
        // 简单校验：必须是sqlite文件头
        $fp = fopen($up, 'rb');
        $head = fread($fp, 16);
        fclose($fp);
        if (substr($head,0,15) == 'SQLite format 3') {
            move_uploaded_file($up, $dbfile);
            $msg = "数据库恢复成功！";
            $success = true;
        } else {
            $msg = "文件格式错误，必须是SQLite数据库文件";
        }
    } else {
        $msg = "上传失败";
    }
}
include 'templates/header.php';
?>
<style>
.restore-panel {
    max-width:420px;
    margin:48px auto 0 auto;
    background:#fff;
    border-radius:12px;
    box-shadow:0 2px 16px #e5eaf7;
    padding:36px 38px 32px 38px;
}
.restore-title {
    font-size:22px;
    color:#247bfc;
    font-weight:700;
    margin-bottom:20px;
    text-align:center;
    letter-spacing:2px;
}
.restore-tip {
    background:#fdf6ec;
    color:#e6a23c;
    border-left:4px solid #fabb3d;
    padding:12px 15px;
    border-radius: 7px;
    font-size:15px;
    margin-bottom:24px;
}
.restore-form label {
    font-size:15px;
    font-weight:600;
    display:block;
    margin-bottom:8px;
}
.restore-form input[type="file"] {
    padding:6px 0;
    font-size:15px;
}
.restore-btn {
    background:#247bfc;
    color:#fff;
    border:none;
    padding:8px 30px;
    border-radius:6px;
    font-size:16px;
    cursor:pointer;
    margin-right:12px;
}
.restore-btn-danger {
    background:#f63a3a;
}
.restore-btn[disabled], .restore-btn:disabled {
    background:#bbb;
    cursor:not-allowed;
}
.restore-msg {
    margin:16px 0 0 0;
    text-align:center;
    font-size:16px;
    font-weight:600;
}
.restore-msg-success {
    color:#09b95b;
}
.restore-msg-error {
    color:#e14646;
}
@media(max-width:600px){
    .restore-panel{padding:18px 3vw;}
}
</style>

<div class="restore-panel">
    <div class="restore-title">数据库恢复</div>
    <div class="restore-tip">
        请选择SQLite数据库备份文件上传，<b>恢复后将覆盖当前所有数据</b>，请谨慎操作！
    </div>
    <?php if($msg): ?>
        <div class="restore-msg <?=$success?'restore-msg-success':'restore-msg-error'?>">
            <?=htmlspecialchars($msg)?>
        </div>
    <?php endif;?>
    <form class="restore-form" method="post" enctype="multipart/form-data" style="margin-top:18px;">
        <label for="dbfile">选择数据库文件</label>
        <input type="file" name="dbfile" id="dbfile" accept=".sqlite,.db" required>
        <div style="margin-top:26px;text-align:center;">
            <button type="submit" class="restore-btn restore-btn-danger" onclick="return confirm('确认恢复？此操作不可逆！')">立即恢复</button>
            <a href="settings.php" class="restore-btn" style="background:#bbb;color:#333;">取消</a>
        </div>
    </form>
</div>
<?php include 'templates/footer.php'; ?>