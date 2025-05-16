<?php
require_once 'inc/functions.php';
checkLogin();
global $db;
session_start();
$book_id = $_SESSION['book_id'] ?? 0;

$code = isset($_GET['code']) ? trim($_GET['code']) : '';
if (!$code) die('参数错误');

$stmt = $db->prepare("SELECT * FROM accounts WHERE book_id=? AND code=?");
$stmt->execute([$book_id, $code]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$account) die('科目不存在');

// 不允许改编号、类别、方向
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    if (!$name) {
        $msg = "名称不能为空";
    } else {
        $stmt = $db->prepare("UPDATE accounts SET name=? WHERE book_id=? AND code=?");
        $stmt->execute([$name, $book_id, $code]);
        header("Location: accounts.php?msg=editok");
        exit;
    }
}

include 'templates/header.php';
?>
<style>
.account-panel {max-width:410px;margin:40px auto;padding:26px 30px;background:#fff;border-radius:12px;box-shadow:0 2px 12px #e7ecf5;}
.account-panel h2 {margin-bottom:18px;font-size:21px;color:#2676f5;}
.account-panel label {font-weight:bold;color:#1954a2;display:block;margin-bottom:6px;}
.account-panel input[type=text], .account-panel select {width:97%;padding:7px 8px;font-size:16px;border:1px solid #c2d2ea;border-radius:5px;margin-bottom:16px;background:#f8fbfd;}
.account-panel input[type=text]:focus, .account-panel select:focus {border:1.5px solid #2676f5;}
.account-panel .btn {background:#2676f5;color:#fff;font-size:16px;padding:8px 28px;border:none;border-radius:5px;letter-spacing:1px;font-weight:bold;margin-top:4px;box-shadow:0 2px 6px #2676f51a;cursor:pointer;transition:background 0.18s;}
.account-panel .btn.cancel {background:#eee;color:#888;margin-left:14px;}
.account-panel .msg-err {color:#e54f4f;margin-bottom:16px;}
.account-panel .tip {color:#888;font-size:13px;margin-top:2px;}
</style>
<div class="account-panel">
    <h2>编辑会计科目</h2>
    <?php if(isset($msg)): ?><div class="msg-err"><?=htmlspecialchars($msg)?></div><?php endif;?>
    <form method="post" autocomplete="off">
        <label>科目编号</label>
        <input type="text" value="<?=htmlspecialchars($account['code'])?>" disabled>
        <label>科目名称</label>
        <input type="text" name="name" maxlength="30" required value="<?=htmlspecialchars($account['name'])?>">
        <label>类别</label>
        <input type="text" value="<?=htmlspecialchars($account['category'])?>" disabled>
        <label>方向</label>
        <input type="text" value="<?=htmlspecialchars($account['direction'])?>" disabled>
        <button type="submit" class="btn">保存</button>
        <a href="accounts.php" class="btn cancel">返回</a>
    </form>
</div>
<?php include 'templates/footer.php'; ?>