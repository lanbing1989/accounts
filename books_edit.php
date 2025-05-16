<?php
require_once 'inc/functions.php';
checkLogin();
global $db;

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) die('账套不存在');

$stmt = $db->prepare("SELECT * FROM books WHERE id=?");
$stmt->execute([$id]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$book) die('账套不存在');

$hasData = false;
// 判断是否有凭证/余额等数据
$stmt2 = $db->prepare("SELECT COUNT(*) FROM vouchers WHERE book_id=?");
$stmt2->execute([$id]);
if ($stmt2->fetchColumn() > 0) $hasData = true;
$stmt2 = $db->prepare("SELECT COUNT(*) FROM balances WHERE book_id=?");
$stmt2->execute([$id]);
if ($stmt2->fetchColumn() > 0) $hasData = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $start_year = intval($_POST['start_year']);
    $start_month = intval($_POST['start_month']);
    if (!$name) {
        $msg = "账套名称不能为空";
    } else {
        if ($hasData) {
            // 只允许改名
            $stmt = $db->prepare("UPDATE books SET name=? WHERE id=?");
            $stmt->execute([$name, $id]);
        } else {
            // 可改名和起始期
            $stmt = $db->prepare("UPDATE books SET name=?, start_year=?, start_month=? WHERE id=?");
            $stmt->execute([$name, $start_year, $start_month, $id]);
        }
        header("Location: books.php?edit_ok=1");
        exit;
    }
}

include 'templates/header.php';
?>
<style>
.editbook-panel {
    max-width: 480px;
    margin: 44px auto;
    padding: 30px 32px;
    background: #fff;
    border-radius: 13px;
    box-shadow: 0 2px 14px #e7ecf5;
}
.editbook-panel h2 {
    margin-bottom: 22px;
    font-size: 23px;
    color: #2676f5;
    letter-spacing: 1.2px;
}
.editbook-panel label {
    font-weight: bold;
    color: #1954a2;
    display: block;
    margin-bottom: 6px;
    letter-spacing: 0.6px;
}
.editbook-panel input[type=text], .editbook-panel select {
    width: 97%;
    padding: 7px 8px;
    font-size: 16px;
    border: 1px solid #c2d2ea;
    border-radius: 5px;
    margin-bottom: 17px;
    background: #f8fbfd;
    transition: border 0.2s;
}
.editbook-panel input[type=text]:focus, .editbook-panel select:focus {
    border: 1.5px solid #2676f5;
    outline: none;
}
.editbook-panel .row { margin-bottom: 18px; }
.editbook-panel .msg-err { color:#e54f4f; margin-bottom: 16px; }
.editbook-panel .btn {
    display:inline-block;
    background: #2676f5;
    color: #fff;
    font-size: 16px;
    padding: 8px 28px;
    border: none;
    border-radius: 5px;
    letter-spacing: 1px;
    font-weight: bold;
    margin-top: 4px;
    margin-bottom: 3px;
    box-shadow: 0 2px 6px #2676f51a;
    cursor: pointer;
    transition: background 0.18s;
    text-decoration:none;
}
.editbook-panel .btn:hover { background: #185fcb; }
.editbook-panel .btn.cancel {
    background: #eee;
    color: #888;
    margin-left: 14px;
}
.editbook-panel .tip {
    color: #888;
    font-size: 13px;
    margin-top: 2px;
}
</style>
<div class="editbook-panel">
    <h2>编辑账套</h2>
    <?php if(isset($msg)): ?><div class="msg-err"><?=htmlspecialchars($msg)?></div><?php endif;?>
    <form method="post" autocomplete="off">
        <div class="row">
            <label>账套名称</label>
            <input type="text" name="name" value="<?=htmlspecialchars($book['name'])?>" required maxlength="30">
        </div>
        <div class="row">
            <label>起始会计期间</label>
            <select name="start_year" <?= $hasData ? 'disabled' : '' ?>>
                <?php
                $y = date('Y');
                for($i=$y-5;$i<=$y+2;$i++) {
                    $selected = ($i==$book['start_year']) ? 'selected' : '';
                    echo "<option value=\"$i\" $selected>$i 年</option>";
                }
                ?>
            </select>
            <select name="start_month" <?= $hasData ? 'disabled' : '' ?>>
                <?php
                for($m=1;$m<=12;$m++) {
                    $selected = ($m==$book['start_month']) ? 'selected' : '';
                    echo "<option value=\"$m\" $selected>$m 月</option>";
                }
                ?>
            </select>
            <?php if($hasData): ?>
                <br><span class="tip">已有数据，起始期间不可修改</span>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn">保存</button>
        <a href="books.php" class="btn cancel">返回</a>
    </form>
</div>
<?php include 'templates/footer.php'; ?>