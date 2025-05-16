<?php
require_once 'inc/functions.php';
checkLogin();
global $db;
session_start();

// 获取提示消息
$msg = '';
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

// 删除账套
if (isset($_GET['delete']) && intval($_GET['delete']) > 0) {
    $del_id = intval($_GET['delete']);
    if (isset($_SESSION['book_id']) && $_SESSION['book_id'] == $del_id) {
        $msg = "不能删除当前正在使用的账套，请先切换到其它账套！";
    } else {
        $stmt = $db->prepare("DELETE FROM books WHERE id=?");
        $stmt->execute([$del_id]);
        // 操作完成后重定向，避免重复提交
        header("Location: books.php?msg=" . urlencode("账套已删除！"));
        exit;
    }
}

// 复制账套（包含科目、期初余额、凭证及凭证明细）
if (isset($_GET['copy']) && intval($_GET['copy']) > 0) {
    $copy_id = intval($_GET['copy']);
    $stmt = $db->prepare("SELECT * FROM books WHERE id=?");
    $stmt->execute([$copy_id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($old) {
        $newname = $old['name'] . ' - 复制' . date('His');
        $stmt2 = $db->prepare("INSERT INTO books (name, start_year, start_month, system_id) VALUES (?, ?, ?, ?)");
        $stmt2->execute([$newname, $old['start_year'], $old['start_month'], $old['system_id']]);
        $newBookId = $db->lastInsertId();
        // 复制科目
        $db->exec("INSERT INTO accounts (book_id, code, name, category, direction, parent_code, is_custom)
            SELECT {$newBookId}, code, name, category, direction, parent_code, is_custom FROM accounts WHERE book_id={$copy_id}");
        // 复制期初余额
        $db->exec("INSERT INTO balances (book_id, account_code, year, month, amount)
            SELECT {$newBookId}, account_code, year, month, amount FROM balances WHERE book_id={$copy_id}");

        // 复制凭证
        $voucherIdMap = [];
        $stmt = $db->prepare("SELECT * FROM vouchers WHERE book_id=?");
        $stmt->execute([$copy_id]);
        $oldVouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($oldVouchers as $voucher) {
            $insert = $db->prepare("INSERT INTO vouchers (book_id, number, date, description, creator, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([
                $newBookId,
                $voucher['number'],
                $voucher['date'],
                $voucher['description'],
                $voucher['creator'],
                $voucher['created_at'],
                $voucher['updated_at']
            ]);
            $voucherIdMap[$voucher['id']] = $db->lastInsertId();
        }

        // 复制凭证明细
        if (!empty($voucherIdMap)) {
            $oldVoucherIds = array_keys($voucherIdMap);
            $inQuery = implode(',', array_fill(0, count($oldVoucherIds), '?'));
            $stmt = $db->prepare("SELECT * FROM voucher_items WHERE voucher_id IN ($inQuery)");
            $stmt->execute($oldVoucherIds);
            $oldItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $insert = $db->prepare("INSERT INTO voucher_items (voucher_id, summary, account_code, debit, credit) VALUES (?, ?, ?, ?, ?)");
            foreach ($oldItems as $item) {
                $newVoucherId = $voucherIdMap[$item['voucher_id']];
                $insert->execute([
                    $newVoucherId,
                    $item['summary'],
                    $item['account_code'],
                    $item['debit'],
                    $item['credit']
                ]);
            }
        }

        // 操作完成后重定向，避免重复提交
        header("Location: books.php?msg=" . urlencode("账套已复制，名称：$newname"));
        exit;
    }
}

$books = getBooks();
$currentBookId = isset($_SESSION['book_id']) ? $_SESSION['book_id'] : 0;

include 'templates/header.php';
?>
<style>
.books-card {
    max-width: 850px;
    margin: 38px auto;
    padding: 32px 36px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 16px #eaeaea;
    transition: box-shadow 0.18s;
}
.books-card:hover {
    box-shadow: 0 4px 32px #d7eaf7;
}
.books-card h2 {
    margin-bottom: 22px;
    font-size: 25px;
    color: #2d8ecd;
    letter-spacing: 1px;
}
.books-table {
    width: 100%;
    border-collapse: collapse;
    background: #f8fcff;
    border-radius: 7px;
    overflow: hidden;
    box-shadow: 0 1px 2px #f0f7fa;
}
.books-table th, .books-table td {
    padding: 13px 8px;
    text-align: left;
    font-size: 16px;
}
.books-table th {
    background: #f2f7fa;
    color: #3282c2;
    font-size: 15.5px;
    font-weight: bold;
    border-bottom: 2px solid #e0eefa;
    letter-spacing: 1px;
}
.books-table tr {
    transition: background 0.2s;
}
.books-table tr:not(:first-child):hover {
    background: #f1f9fb !important;
}
.books-table tr.current {
    background: #f6fbfa !important;
}
.books-table td {
    vertical-align: middle;
}
.books-btn {
    display: inline-block;
    background: #3795d4;
    color: #fff;
    border: none;
    padding: 7px 18px;
    border-radius: 5px;
    margin-right: 4px;
    font-size: 15px;
    cursor: pointer;
    transition: background 0.14s, opacity 0.12s;
    box-shadow: 0 1px 3px rgba(55,149,212,0.09);
    text-decoration: none;
    outline: none;
}
.books-btn:active { opacity: .92; }
.books-btn.switch { background: #5bc97c; }
.books-btn.edit   { background: #428bca; }
.books-btn.copy   { background: #ffb84d; color: #874d00; }
.books-btn.export { background: #c9a6ff; color: #4d3277; opacity:.7; pointer-events:none; }
.books-btn.archive{ background: #888; color: #fff; opacity:.7; pointer-events:none; }
.books-btn.del    { background: #e55d53; }
.books-btn[disabled], .books-btn.disabled { background: #eee !important; color: #bbb !important; cursor: not-allowed; pointer-events:none;}
.books-btn.current { background: #e8f6ff; color: #3795d4; border: 1.5px solid #3795d4; font-weight: bold; cursor:default; pointer-events:none;}
.books-btn.add {
    background: #2d8ecd;
    font-size: 16px;
    padding: 8px 26px;
    margin-bottom: 12px;
    margin-top: 6px;
    letter-spacing: 1px;
    font-weight: bold;
}
.books-card-desc {
    color: #888;
    margin-top: 18px;
    font-size: 13px;
    background:#f6fcff;
    padding:8px 12px;
    border-radius: 7px;
}
.books-table td .current-label {
    color: #3795d4;
    font-size: 13px;
    font-weight: bold;
    margin-left: 4px;
    letter-spacing: .5px;
}
@media (max-width: 700px) {
    .books-card { padding: 15px 5px; }
    .books-table th, .books-table td { font-size: 14px; padding: 8px 4px; }
    .books-btn { font-size: 13px; padding: 6px 8px;}
    .books-btn.add { font-size: 14px; padding: 7px 12px;}
}
</style>

<div class="books-card">
    <h2>账套管理</h2>
    <?php if(!empty($msg)): ?>
        <div style="color:#e54f4f;margin-bottom:18px;"><?=htmlspecialchars($msg)?></div>
    <?php endif;?>
    <div style="margin-bottom:20px;">
        <a href="books_add.php" class="books-btn add">+ 新建账套</a>
    </div>
    <table class="books-table">
        <tr>
            <th>账套名称</th>
            <th>起始期间</th>
            <th style="min-width:340px;">操作</th>
        </tr>
        <?php foreach($books as $book): ?>
        <tr class="<?= $book['id'] == $currentBookId ? 'current' : '' ?>">
            <td style="font-weight:<?=$book['id']==$currentBookId?'bold':'normal';?>;">
                <?=htmlspecialchars($book['name'])?>
                <?php if($book['id']==$currentBookId): ?>
                    <span class="current-label">(当前账套)</span>
                <?php endif; ?>
            </td>
            <td><?=intval($book['start_year'])?>年<?=intval($book['start_month'])?>月</td>
            <td>
                <?php if($book['id']!=$currentBookId): ?>
                    <a href="switch_book.php?book_id=<?=$book['id']?>" class="books-btn switch">切换</a>
                    <a href="books_edit.php?id=<?=$book['id']?>" class="books-btn edit">编辑</a>
                    <a href="books.php?copy=<?=$book['id']?>" class="books-btn copy" onclick="return confirm('确定要复制该账套吗？此操作会复制全部科目、期初余额和所有凭证。')">复制</a>
                    <a href="books_export.php?id=<?=$book['id']?>" class="books-btn export">导出</a>
                    <a href="books_archive.php?id=<?=$book['id']?>" class="books-btn archive">归档</a>
                    <a href="javascript:void(0);" onclick="if(confirm('确定要删除该账套吗？'))window.location='books.php?delete=<?=$book['id']?>';" class="books-btn del">删除</a>
                <?php else: ?>
                    <span style="color:#aaa;">切换/编辑/复制/导出/归档/删除</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <div class="books-card-desc">
        <b>说明：</b>
        当前账套禁止删除与编辑。仅支持改名，起始期间如无凭证数据可编辑，否则只读。
        复制功能包含会计科目、期初余额和所有凭证。
        导出、归档功能后续支持。
    </div>
</div>
<?php include 'templates/footer.php'; ?>