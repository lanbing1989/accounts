<?php
require_once 'inc/functions.php';
checkLogin();
global $db;
session_start();

// 获取当前账套
$book = getCurrentBook();
if (!$book) {
    header('Location: books_add.php');
    exit;
}
$book_id = intval($book['id']);
$start_year = intval($book['start_year']);
$start_month = intval($book['start_month']);

// 分页参数
$page_size = 50;
$page = isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1;

// 1. 生成所有会计期间及结账状态（供侧边栏/期间切换用）
$maxVoucher = $db->prepare("SELECT MAX(date) as maxdate FROM vouchers WHERE book_id = ?");
$maxVoucher->execute([$book_id]);
$maxVoucher = $maxVoucher->fetch(PDO::FETCH_ASSOC);
$maxVoucherYm = $maxVoucher && $maxVoucher['maxdate'] ? date('Ym', strtotime($maxVoucher['maxdate'])) : null;

$maxClosingRow = $db->prepare("SELECT year, month FROM closings WHERE book_id = ? ORDER BY year DESC, month DESC LIMIT 1");
$maxClosingRow->execute([$book_id]);
$maxClosingRow = $maxClosingRow->fetch(PDO::FETCH_ASSOC);
if ($maxClosingRow) {
    $closingMaxTime = strtotime(sprintf("%04d-%02d-01", $maxClosingRow['year'], $maxClosingRow['month']));
    $nextUnclosedTime = strtotime("+1 month", $closingMaxTime);
    $maxClosingYm = date('Ym', $nextUnclosedTime);
} else {
    $maxClosingYm = null;
}
$currentYm = date('Ym');
$period_end_ym = max(array_filter([$maxVoucherYm, $maxClosingYm, $currentYm]));

$periods = [];
$t = strtotime("$start_year-$start_month-01");
while (date('Ym', $t) <= $period_end_ym) {
    $y = date('Y', $t);
    $m = date('n', $t);
    $stmt = $db->prepare("SELECT 1 FROM closings WHERE book_id=? AND year=? AND month=? LIMIT 1");
    $stmt->execute([$book_id, $y, $m]);
    $closed = $stmt->fetchColumn() ? true : false;
    $periods[] = ['year'=>$y, 'month'=>$m, 'closed'=>$closed];
    $t = strtotime("+1 month", $t);
}

// 2. 期间筛选逻辑：统一使用 $_SESSION['global_period']，只在period=all时特殊处理
$period_param = isset($_GET['period']) ? $_GET['period'] : null;
if ($period_param && $period_param === 'all') {
    $show_all = true;
    $year = $month = null;
} else if ($period_param && preg_match('/^(\d{4})(\d{1,2})$/', $period_param, $marr)) {
    $year = intval($marr[1]);
    $month = intval($marr[2]);
    $_SESSION['global_period'] = sprintf('%04d-%02d', $year, $month);
    $show_all = false;
} else if (isset($_SESSION['global_period']) && preg_match('/^(\d{4})-(\d{1,2})$/', $_SESSION['global_period'], $marr)) {
    $year = intval($marr[1]);
    $month = intval($marr[2]);
    $show_all = false;
} else {
    $found = false;
    foreach ($periods as $p) {
        if (!$p['closed']) {
            $year = $p['year'];
            $month = $p['month'];
            $found = true;
            break;
        }
    }
    if (!$found && count($periods) > 0) {
        $last = end($periods);
        $year = $last['year'];
        $month = $last['month'];
    }
    $_SESSION['global_period'] = sprintf('%04d-%02d', $year, $month);
    $show_all = false;
}

// 获取凭证总数
if ($show_all) {
    $date1 = sprintf('%04d-%02d-01', $start_year, $start_month);
    $date2 = date('Y-m-t', strtotime($date1 . " + " . (count($periods)-1) . " months"));
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM vouchers WHERE book_id=? AND date>=? AND date<=?");
    $count_stmt->execute([$book_id, $date1, $date2]);
} else {
    $date1 = sprintf('%04d-%02d-01', $year, $month);
    $date2 = date('Y-m-t', strtotime($date1));
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM vouchers WHERE book_id=? AND date>=? AND date<=?");
    $count_stmt->execute([$book_id, $date1, $date2]);
}
$total_voucher_count = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_voucher_count / $page_size));
$page = min($page, $total_pages);

// 获取凭证（分页）
$offset = ($page - 1) * $page_size;
if ($show_all) {
    $voucher_stmt = $db->prepare("SELECT * FROM vouchers WHERE book_id=? AND date>=? AND date<=? ORDER BY date, number, id LIMIT $page_size OFFSET $offset");
    $voucher_stmt->execute([$book_id, $date1, $date2]);
    $vouchers = $voucher_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $voucher_stmt = $db->prepare("SELECT * FROM vouchers WHERE book_id=? AND date>=? AND date<=? ORDER BY date, number, id LIMIT $page_size OFFSET $offset");
    $voucher_stmt->execute([$book_id, $date1, $date2]);
    $vouchers = $voucher_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 获取所有凭证明细
$voucher_ids = array_column($vouchers, 'id');
$items_by_voucher = [];
if ($voucher_ids) {
    $in = implode(',', array_fill(0, count($voucher_ids), '?'));
    $stmt = $db->prepare("SELECT * FROM voucher_items WHERE voucher_id IN ($in) ORDER BY id");
    $stmt->execute($voucher_ids);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items_by_voucher[$row['voucher_id']][] = $row;
    }
}

// 科目映射
$accounts = $db->prepare("SELECT code, name FROM accounts WHERE book_id=?");
$accounts->execute([$book_id]);
$acct_map = [];
foreach($accounts->fetchAll(PDO::FETCH_ASSOC) as $a){
    $acct_map[$a['code']] = $a['code'].' '.$a['name'];
}

// 删除单个凭证
if (isset($_GET['delete']) && intval($_GET['delete']) > 0) {
    $del_id = intval($_GET['delete']);
    try {
        $db->beginTransaction();
        $db->prepare("DELETE FROM voucher_items WHERE voucher_id=?")->execute([$del_id]);
        $db->prepare("DELETE FROM vouchers WHERE id=?")->execute([$del_id]);
        $db->commit();
        header('Location: vouchers.php');
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $err = $e->getMessage();
    }
}

// 断号整理
if (isset($_GET['fix_number']) && $_GET['fix_number'] == '1') {
    if ($show_all) {
        $stmt = $db->prepare("SELECT id FROM vouchers WHERE book_id=? ORDER BY date ASC, id ASC");
        $stmt->execute([$book_id]);
    } else {
        $stmt = $db->prepare("SELECT id FROM vouchers WHERE book_id=? AND date>=? AND date<=? ORDER BY date ASC, id ASC");
        $stmt->execute([$book_id, $date1, $date2]);
    }
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $db->beginTransaction();
    foreach ($list as $i => $v) {
        $newnum = $i + 1;
        $db->prepare("UPDATE vouchers SET number=? WHERE id=?")->execute([$newnum, $v['id']]);
    }
    $db->commit();
    header("Location: vouchers.php?fix_ok=1" . ($show_all ? '&period=all' : ''));
    exit;
}

// 批量删除操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_delete']) && isset($_POST['voucher_ids']) && is_array($_POST['voucher_ids'])) {
    $ids = array_map('intval', $_POST['voucher_ids']);
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        try {
            $db->beginTransaction();
            $db->prepare("DELETE FROM voucher_items WHERE voucher_id IN ($in)")->execute($ids);
            $db->prepare("DELETE FROM vouchers WHERE id IN ($in)")->execute($ids);
            $db->commit();
            header('Location: vouchers.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $err = $e->getMessage();
        }
    }
}

// 批量打印选中
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_print']) && isset($_POST['voucher_ids']) && is_array($_POST['voucher_ids']) && count($_POST['voucher_ids']) > 0) {
    $vids = array_map('intval', $_POST['voucher_ids']);
    header("Location: print_vouchers.php?voucher_ids=" . implode(',', $vids));
    exit;
}

// 判断当前期间是否已结账
$is_closed = false;
foreach ($periods as $p) {
    if (!$show_all && $p['year'] == $year && $p['month'] == $month) {
        $is_closed = $p['closed'];
        break;
    }
}

include 'templates/header.php';
?>
<style>
body {
    background: #f8fbfd;
}
.voucher-main-wrap {
    display:flex;
    max-width: 1200px; margin: 32px auto;
}
.month-year-sidebar {
    width: 160px;
    background: #fafbfc;
    border-radius: 11px;
    padding: 27px 0 27px 0;
    margin-right: 24px;
    box-shadow: 0 2px 14px rgba(0,0,0,0.03);
    height: fit-content;
}
.month-year-sidebar ul {
    list-style: none; padding: 0; margin: 0;
}
.month-year-sidebar li {
    margin: 0 0 8px 0;
    text-align: center;
    position: relative;
}
.month-year-sidebar li a {
    display: block;
    width: 118px;
    margin: 0 auto;
    padding: 7px 0;
    border-radius: 5px;
    color: #555;
    text-decoration: none;
    font-size: 15px;
    background: none;
    transition: all 0.16s;
}
.month-year-sidebar li.active a,
.month-year-sidebar li a:hover {
    background: #ffe6ac;
    color: #d8830c;
    font-weight: bold;
    box-shadow: 0 2px 8px rgba(255,216,107,0.09);
}
.month-year-sidebar li a.all-link {
    color: #3386f1;
    font-weight: bold;
    font-size: 16px;
    background: #eaf2ff;
    margin-bottom: 8px;
    border: 1.5px solid #d2e4ff;
}
.voucher-list-content {
    flex:1; min-width:0;
}
.voucher-list-pagetitle {
    font-size: 22px;
    font-weight: bold;
    color: #3386f1;
    margin-bottom: 13px;
    text-align: center;
    letter-spacing: 2px;
}
.voucher-list-headerbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}
.voucher-list-headerbar {
    display: flex;
    align-items: center;
    justify-content: flex-end;  /* 让内容整体靠右 */
    gap: 16px;
    margin-bottom: 18px;
}
.voucher-list-header-title {
    display: none;  /* 如果不需要标题，直接隐藏 */
}
.voucher-list-header-actions {
    display: flex;
    gap: 16px;
}
.voucher-list-header-actions .btn {
    display: inline-block;
    background: #3386f1;
    color: #fff;
    padding: 8px 22px;
    border-radius: 5px;
    font-size: 15px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: background 0.16s;
}
.voucher-list-header-actions .btn.green {
    background: #5bc97c;
}
.voucher-list-header-actions .btn.print-btn {
    background: #4a90e2;
}
.voucher-list-header-actions .btn.red {
    background: #e55d53;
}
.voucher-list-header-actions .btn:hover {
    background: #2564ad;
    color: #fff;
}
.voucher-list-header-actions .btn.green:hover {
    background: #43925f;
}
.voucher-list-header-actions .btn.print-btn:hover {
    background: #357ab7;
}
.voucher-list-header-actions .btn.import-btn {
    background: #ffbe2b;
    color: #fff;
}
.voucher-list-header-actions .btn.import-btn:hover {
    background: #d89b0c;
}
.fixok-tip {background:#e7f9e7;color:#276c27;padding:7px 16px;border-radius:4px;margin-bottom:12px;}
.voucher-empty {
    color:#bbb;text-align:center;font-size:17px;margin:64px 0;
}
.voucher-head-op {
    display: flex;
    gap: 8px;
    align-items: center;
}
.voucher-head-op .btn {
    display: inline-block;
    background: #3386f1;
    color: #fff;
    padding: 4px 18px;
    border-radius: 5px;
    font-size: 14px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: background 0.16s;
    vertical-align: middle;
}
.voucher-head-op .btn.blue {
    background: #3386f1;
    color: #fff;
}
.voucher-head-op .btn.red {
    background: #f55;
    color: #fff;
}
.voucher-head-op .btn.print-btn {
    background: #4a90e2;
    color: #fff;
}
.voucher-head-op .btn.blue:hover {
    background: #2066c9;
}
.voucher-head-op .btn.red:hover {
    background: #c11c1c;
}
.voucher-head-op .btn.print-btn:hover {
    background: #357ab7;
}
.voucher-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    margin-bottom: 22px;
    padding: 0 0 12px 0;
    border:1px solid #f5f5f6;
}
.voucher-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom:1px solid #f0f0f1;
    padding: 16px 22px 10px 22px;
}
.voucher-head-meta span {
    color: #888;
    font-size: 14px;
    margin-right: 20px;
}
.voucher-items-table {
    width: 98%;
    margin: 15px auto 0 auto;
    border-collapse: collapse;
    background: #fcfcfc;
}
.voucher-items-table th, .voucher-items-table td {
    border: 1px solid #eee;
    padding: 7px 10px;
    font-size: 15px;
}
.voucher-items-table th {
    background: #f7f7f7;
    color: #7f7f7f;
    font-weight: normal;
}
.voucher-items-table .voucher-total-row td {
    background: #fff9e8;
    color: #b3830e;
    font-weight: bold;
}
</style>
<script>
function confirmDelete(id) {
    if(confirm('确定要删除该凭证吗？删除后无法恢复！')) {
        window.location = 'vouchers.php?delete=' + id + '<?= $show_all ?? false ? "&period=all" : "" ?>';
    }
}
function toggleAll(source) {
    var checkboxes = document.querySelectorAll('.voucher-checkbox');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}
</script>
<div class="voucher-main-wrap">
    <div class="month-year-sidebar">
        <ul>
            <li<?=($show_all ?? false) ? ' class="active"' : ''?>>
                <a href="vouchers.php?period=all" class="all-link">全部</a>
            </li>
            <?php
            foreach ($periods as $p) {
                $y = $p['year'];
                $m = $p['month'];
                $isActive = (!$show_all && $y == $year && $m == $month) ? 'active' : '';
                $label = $y.'年'.$m.'月';
                $href = 'vouchers.php?period='.$y.sprintf('%02d',$m);
                echo '<li class="'.$isActive.'"><a href="'.$href.'">'.$label.'</a></li>';
            }
            ?>
        </ul>
    </div>
    <div class="voucher-list-content">
        <!-- 批量操作表单 -->
        <form method="post" id="batchForm">
        <div class="voucher-list-pagetitle">凭证列表</div>
        <!-- 标题+按钮条 -->
        <div class="voucher-list-headerbar">
            <div class="voucher-list-header-actions">
                <a href="voucher_edit.php" class="btn">新建凭证</a>
                <a href="vouchers_import.php" class="btn import-btn">批量导入</a>
                <a href="vouchers_export.php<?= $show_all ? '?period=all' : ('?period='.sprintf('%04d%02d',$year,$month)) ?>" class="btn green">导出Excel</a>
                <a href="vouchers.php?fix_number=1<?= $show_all ? '&period=all' : '' ?>" class="btn green" onclick="return confirm('确定要自动修复凭证断号？');">断号整理</a>
                <a href="print_vouchers.php<?= $show_all ? '?period=all' : ('?period='.sprintf('%04d%02d',$year,$month)) ?>" class="btn print-btn" target="_blank">全部打印</a>
                <button type="submit" name="batch_delete" class="btn red" style="background:#e55d53;" onclick="return confirm('确定批量删除选中的凭证吗？');">批量删除</button>
                <button type="submit" name="batch_print" class="btn print-btn" style="background:#4a90e2;" formtarget="_blank">打印选中</button>
                <?php if (!$show_all): ?>
                  <?php if (!$is_closed): ?>
                    <a href="closing.php?year=<?= $year ?>&month=<?= $month ?>" class="btn" style="background:#ff9800;color:#fff;" target="_blank" title="期间结账">结账</a>
                  <?php else: ?>
                    <span class="btn" style="background:#aaa;cursor:not-allowed;">已结账</span>
                  <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php if(isset($_GET['fix_ok'])): ?>
            <div class="fixok-tip">已成功整理凭证号断号，所有凭证号已连续！</div>
        <?php endif;?>
        <?php if(isset($err) && $err): ?>
            <div class="fixok-tip" style="background:#ffeaea;color:#d83d31;">
                <?=htmlspecialchars($err)?>
            </div>
        <?php endif; ?>
        <?php if(!$vouchers): ?>
            <div class="voucher-empty">暂无凭证</div>
        <?php else: ?>
            <div style="margin-bottom: 16px;">
                <label style="cursor:pointer;font-size:15px;">
                    <input type="checkbox" onclick="toggleAll(this)" style="vertical-align:middle;margin-right:5px;">
                    全选本页所有凭证
                </label>
            </div>
            <?php foreach($vouchers as $v): ?>
            <div class="voucher-card">
                <div class="voucher-head">
                    <div class="voucher-head-meta">
                        <input type="checkbox" class="voucher-checkbox" name="voucher_ids[]" value="<?=$v['id']?>" style="margin-right:10px;vertical-align:middle;">
                        <span><?=htmlspecialchars($v['date'])?></span>
                        <span>记-<?=isset($v['number']) ? htmlspecialchars($v['number']) : str_pad($v['id'],3,'0',STR_PAD_LEFT)?></span>
                        <?php if(isset($v['creator'])): ?>
                        <span>制单人: <?=htmlspecialchars($v['creator'])?></span>
                        <?php endif; ?>
                    </div>
                    <div class="voucher-head-op">
                        <a href="voucher_edit.php?id=<?=$v['id']?>" class="btn blue">编辑</a>
                        <a href="javascript:void(0);" class="btn red" onclick="confirmDelete(<?=$v['id']?>)">删除</a>
                        <a href="print_vouchers.php?voucher_id=<?=$v['id']?>" class="btn print-btn" target="_blank">打印</a>
                    </div>
                </div>
                <table class="voucher-items-table">
                    <tr>
                        <th style="width:40%;">摘要</th>
                        <th style="width:28%;">科目</th>
                        <th style="width:16%;">借方金额</th>
                        <th style="width:16%;">贷方金额</th>
                    </tr>
                    <?php
                        $items = $items_by_voucher[$v['id']] ?? [];
                        $total_debit = 0; $total_credit = 0;
                        foreach($items as $row):
                            $debit = floatval($row['debit']);
                            $credit = floatval($row['credit']);
                            $total_debit += $debit;
                            $total_credit += $credit;
                    ?>
                    <tr>
                        <td><?=htmlspecialchars($row['summary'])?></td>
                        <td><?=isset($acct_map[$row['account_code']]) ? htmlspecialchars($acct_map[$row['account_code']]) : htmlspecialchars($row['account_code'])?></td>
                        <td style="text-align:right;"><?= $debit ? number_format($debit,2) : '' ?></td>
                        <td style="text-align:right;"><?= $credit ? number_format($credit,2) : '' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="voucher-total-row">
                        <td colspan="2" style="text-align:right;">合计：</td>
                        <td style="text-align:right;"><?=number_format($total_debit,2)?></td>
                        <td style="text-align:right;"><?=number_format($total_credit,2)?></td>
                    </tr>
                </table>
            </div>
            <?php endforeach; ?>

            <!-- 分页导航 -->
            <div style="text-align:center;margin:24px 0;">
                <?php if($total_pages > 1): ?>
                    <div style="display:inline-block;padding:8px 16px;">
                        <?php
                        // 构造分页URL
                        $baseurl = preg_replace('/([&?])page=\d+/', '$1', $_SERVER['REQUEST_URI']);
                        $baseurl = rtrim($baseurl, '&?');
                        $baseurl .= (strpos($baseurl, '?') === false ? '?' : '&');
                        ?>
                        <?php if($page > 1): ?>
                            <a href="<?=$baseurl?>page=1" style="margin:0 7px;">首页</a>
                            <a href="<?=$baseurl?>page=<?=($page-1)?>" style="margin:0 7px;">上一页</a>
                        <?php endif; ?>
                        <span style="font-weight:bold;color:#3386f1;margin:0 10px;"><?= $page ?> / <?= $total_pages ?></span>
                        <?php if($page < $total_pages): ?>
                            <a href="<?=$baseurl?>page=<?=($page+1)?>" style="margin:0 7px;">下一页</a>
                            <a href="<?=$baseurl?>page=<?=$total_pages?>" style="margin:0 7px;">末页</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <!-- end 分页导航 -->

        <?php endif; ?>
        </form>
    </div>
</div>
<?php include 'templates/footer.php'; ?>