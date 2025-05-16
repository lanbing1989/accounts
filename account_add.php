<?php
require_once 'inc/functions.php';
checkLogin();
global $db;

// 当前账套ID
session_start();
$book_id = $_SESSION['book_id'] ?? 0;

// 读取父级科目信息，必须传parent
$parent_code = isset($_GET['parent']) ? trim($_GET['parent']) : '';
if (!$parent_code) {
    header("Location: accounts.php?msg=必须通过上级科目添加下级科目");
    exit;
}
$stmt = $db->prepare("SELECT * FROM accounts WHERE book_id=? AND code=?");
$stmt->execute([$book_id, $parent_code]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$parent) die('上级科目不存在');

// 自动生成建议编号
$stmt = $db->prepare("SELECT code FROM accounts WHERE book_id=? AND parent_code=? ORDER BY code DESC LIMIT 1");
$stmt->execute([$book_id, $parent_code]);
$last = $stmt->fetch(PDO::FETCH_ASSOC);

if ($last) {
    $prefix = $parent_code;
    $suffix_len = max(strlen($last['code']) - strlen($parent_code), 2);
    $last_suffix = intval(substr($last['code'], strlen($parent_code)));
    $next_suffix = str_pad($last_suffix + 1, $suffix_len, '0', STR_PAD_LEFT);
    $suggest_code = $prefix . $next_suffix;
} else {
    $suffix_len = 2;
    $suggest_code = $parent_code . str_pad('1', $suffix_len, '0', STR_PAD_LEFT);
}

// 检查父级科目是否已被引用
$has_data = false;
$stmt_check = $db->prepare("SELECT COUNT(*) FROM voucher_items WHERE account_code=?");
$stmt_check->execute([$parent_code]);
if ($stmt_check->fetchColumn() > 0) $has_data = true;
$stmt_check = $db->prepare("SELECT COUNT(*) FROM balances WHERE account_code=?");
$stmt_check->execute([$parent_code]);
if ($stmt_check->fetchColumn() > 0) $has_data = true;
// 如有其他相关表，继续添加...

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $category = $parent['category'];
    $direction = $parent['direction'];
    $parent_code = $parent['code'];

    if (!$code || !$name) {
        $msg = "请填写完整信息";
    } else {
        // 检查编码唯一且格式正确
        $stmt = $db->prepare("SELECT COUNT(*) FROM accounts WHERE book_id=? AND code=?");
        $stmt->execute([$book_id, $code]);
        if ($stmt->fetchColumn() > 0) {
            $msg = "科目编号已存在！";
        } elseif (strpos($code, $parent_code)!==0 || strlen($code)<=strlen($parent_code)) {
            $msg = "科目编号必须以上级科目编号开头，且比上级多至少2位！";
        } else {
            // 1. 新增子科目
            $stmt = $db->prepare("INSERT INTO accounts (book_id, code, name, category, direction, parent_code, is_custom) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$book_id, $code, $name, $category, $direction, $parent_code]);

            if ($has_data) {
                // 2. 自动迁移数据到新下级科目
                $stmt = $db->prepare("UPDATE voucher_items SET account_code=? WHERE account_code=?");
                $stmt->execute([$code, $parent_code]);
                $stmt = $db->prepare("UPDATE balances SET account_code=? WHERE account_code=?");
                $stmt->execute([$code, $parent_code]);
                // 如有其他相关表，继续添加...

                $msg = "父级科目有数据，数据已自动转移至新下级科目【".$code."】";
            }
            header("Location: accounts.php?msg=" . urlencode($msg ?? "addok"));
            exit;
        }
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
    <h2>新增下级科目</h2>
    <?php if(isset($msg)): ?><div class="msg-err"><?=htmlspecialchars($msg)?></div><?php endif;?>
    <form method="post" autocomplete="off">
        <label>上级科目</label>
        <input type="text" value="<?=htmlspecialchars($parent['code'].' '.$parent['name'])?>" disabled>
        <input type="hidden" name="parent_code" value="<?=htmlspecialchars($parent['code'])?>">
        <label>科目编号</label>
        <input type="text" name="code" maxlength="20" required value="<?=isset($_POST['code'])?htmlspecialchars($_POST['code']):htmlspecialchars($suggest_code)?>">
        <div class="tip">建议编号：<span style="color:#2676f5"><?=htmlspecialchars($suggest_code)?></span>，须以上级编号开头</div>
        <label>科目名称</label>
        <input type="text" name="name" maxlength="30" required value="<?=isset($_POST['name'])?htmlspecialchars($_POST['name']):''?>" placeholder="如：支付宝账户">
        <label>类别</label>
        <input type="text" value="<?=htmlspecialchars($parent['category'])?>" disabled>
        <label>方向</label>
        <input type="text" value="<?=htmlspecialchars($parent['direction'])?>" disabled>
        <div class="tip">类别、方向均与上级保持一致。<?php if($has_data): ?><br><span style="color:#e54f4f">父级已有数据，创建后数据将自动转移到此新科目。</span><?php endif;?></div>
        <button type="submit" class="btn">保存</button>
        <a href="accounts.php" class="btn cancel">返回</a>
    </form>
</div>
<?php include 'templates/footer.php'; ?>