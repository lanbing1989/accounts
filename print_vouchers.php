<?php
require_once 'inc/functions.php';
checkLogin();
global $db;
session_start();

$book = getCurrentBook();
if (!$book) exit('未选择账套');
$book_id = intval($book['id']);
$company = $book['name'];

// 新增：支持批量打印，接收 voucher_ids=1,2,3 形式
if (isset($_GET['voucher_ids'])) {
    $ids = array_filter(array_map('intval', explode(',', $_GET['voucher_ids'])));
    if (!$ids) exit('未选中凭证');
    if (!function_exists('getVoucherById')) {
        function getVoucherById($id, $book_id) {
            global $db;
            $stmt = $db->prepare("SELECT * FROM vouchers WHERE id=? AND book_id=?");
            $stmt->execute([$id, $book_id]);
            $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($voucher) {
                $voucher['items'] = function_exists('getVoucherItems') ? getVoucherItems($voucher['id']) : [];
            }
            return $voucher;
        }
    }
    $vouchers = [];
    foreach ($ids as $vid) {
        $v = getVoucherById($vid, $book_id);
        if ($v) $vouchers[] = $v;
    }
    if (!$vouchers) exit('凭证不存在');
    $show_all = false;
} elseif (isset($_GET['voucher_id']) && is_numeric($_GET['voucher_id'])) {
    // 单张打印
    $voucher_id = intval($_GET['voucher_id']);
    if (!function_exists('getVoucherById')) {
        function getVoucherById($id, $book_id) {
            global $db;
            $stmt = $db->prepare("SELECT * FROM vouchers WHERE id=? AND book_id=?");
            $stmt->execute([$id, $book_id]);
            $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($voucher) {
                $voucher['items'] = function_exists('getVoucherItems') ? getVoucherItems($voucher['id']) : [];
            }
            return $voucher;
        }
    }
    $v = getVoucherById($voucher_id, $book_id);
    if ($v) {
        $vouchers = [$v];
        $show_all = false;
    } else {
        exit('凭证不存在');
    }
} else {
    $period_param = isset($_GET['period']) ? $_GET['period'] : null;
    if ($period_param && $period_param === 'all') {
        $show_all = true;
    } else if ($period_param && preg_match('/^(\d{4})(\d{2})$/', $period_param, $marr)) {
        $year = intval($marr[1]);
        $month = intval($marr[2]);
        $show_all = false;
    } else {
        $year = date('Y');
        $month = date('n');
        $show_all = false;
    }

    $start_year = intval($book['start_year']);
    $start_month = intval($book['start_month']);

    $periods = [];
    $t = strtotime("$start_year-$start_month-01");
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
    while (date('Ym', $t) <= $period_end_ym) {
        $y = date('Y', $t);
        $m = date('n', $t);
        $periods[] = ['year'=>$y, 'month'=>$m];
        $t = strtotime("+1 month", $t);
    }

    // 获取凭证
    if ($show_all ?? false) {
        $date1 = sprintf('%04d-%02d-01', $start_year, $start_month);
        $date2 = date('Y-m-t', strtotime($date1 . " + " . (count($periods)-1) . " months"));
        $vouchers = getVouchersByDate($date1, $date2, $book_id);
    } else {
        $date1 = sprintf('%04d-%02d-01', $year, $month);
        $date2 = date('Y-m-t', strtotime($date1));
        $vouchers = getVouchersByDate($date1, $date2, $book_id);
    }
}
usort($vouchers, function($a, $b) {
    $numA = isset($a['number']) ? intval($a['number']) : intval($a['id']);
    $numB = isset($b['number']) ? intval($b['number']) : intval($b['id']);
    return $numA - $numB;
});
$accounts = getAccounts($book_id);
$acct_map = [];
foreach($accounts as $a){
    $acct_map[$a['code']] = $a['code'].' '.$a['name'];
}
function get_items($v) {
    if (isset($v['items']) && is_array($v['items'])) return $v['items'];
    if (function_exists('getVoucherItems')) return getVoucherItems($v['id']);
    return [];
}
function pad_rows($rows, $count = 5) {
    $n = count($rows);
    for($i=$n; $i<$count; $i++) $rows[] = ['summary'=>'','account_code'=>'','debit'=>'','credit'=>''];
    return $rows;
}
function split_voucher_pages($voucher, $max_lines = 8) {
    $items = get_items($voucher);
    $pages = [];
    $total = count($items);
    $page_count = ceil($total / $max_lines);
    for ($i = 0; $i < $page_count; $i++) {
        $page = $voucher;
        $page['items'] = array_slice($items, $i * $max_lines, $max_lines);
        $page['page_note'] = $page_count > 1 ? ($i + 1).'/'.$page_count : '';
        $pages[] = $page;
    }
    return $pages;
}
function money_cap($num) {
    if (!is_numeric($num)) return '';
    $c1 = "零壹贰叁肆伍陆柒捌玖";
    $c2 = "分角元拾佰仟万拾佰仟亿";
    $num = round($num, 2) * 100;
    if (strlen($num) > 12) return "金额太大";
    $i = 0; $c = "";
    do {
        $n = $num % 10;
        $c = mb_substr($c1, $n, 1) . mb_substr($c2, $i, 1) . $c;
        $i++; $num = floor($num / 10);
    } while ($num > 0);
    $c = preg_replace("/零[拾佰仟]/u", "零", $c);
    $c = preg_replace("/零+万/u", "万", $c);
    $c = preg_replace("/零+亿/u", "亿", $c);
    $c = preg_replace("/亿万/u", "亿", $c);
    $c = preg_replace("/零+元/u", "元", $c);
    $c = preg_replace("/零+角/u", "", $c);
    $c = preg_replace("/零+分/u", "", $c);
    $c = preg_replace("/零+/u", "零", $c);
    if (mb_substr($c, -1, 1) != "分" && mb_substr($c, -1, 1) != "角") $c .= "整";
    return $c;
}

// 拆分所有凭证（超长的分页为多个子凭证）
$max_lines_per_voucher = 8; // 可根据实际A4半页高度调整
$flat_vouchers = [];
foreach ($vouchers as $vo) {
    $items = get_items($vo);
    $total = count($items);
    if ($total <= $max_lines_per_voucher) {
        $vo['page_note'] = '';
        $flat_vouchers[] = $vo;
    } else {
        foreach (split_voucher_pages($vo, $max_lines_per_voucher) as $subvo) {
            $flat_vouchers[] = $subvo;
        }
    }
}
$total_count = count($flat_vouchers);
$only_one_sheet = ($total_count <= 2);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>凭证打印</title>
    <style>
    @media print {
        html, body {
            width: 210mm;
            margin: 0;
            padding: 0;
            background: #fff !important;
        }
        .print-root {
            width: 210mm !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .print-sheet {
            width: 190mm;
            margin: 0 auto;
            padding: 0;
            background: #fff !important;
            box-shadow: none !important;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }
        .page-break { page-break-after: always; }
        .print-voucher {
            width: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            display: block;
            position: relative;
            break-inside: avoid;
            page-break-inside: avoid;
            <?php if(!$only_one_sheet): ?>
            min-height: 130mm;
            <?php endif; ?>
        }
        .print-sep {
            display: block !important;
            height: 0;
            border-bottom: 1.5px dashed #444;
            margin: 7px 0 7px 0;
            width: 100%;
        }
        .no-print { display: none !important; }
    }
    body, html {
        background: #eee;
        margin: 0;
        padding: 0;
    }
    .print-root {
        width: 210mm;
        margin: 0 auto;
        padding: 0;
    }
    .print-sheet {
        width: 190mm;
        margin: 18mm auto 0 auto;
        background: #fff;
        box-shadow: none;
        padding: 0;
        box-sizing: border-box;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
    }
    .print-voucher {
        width: 100%;
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        display: block;
        position: relative;
        break-inside: avoid;
        page-break-inside: avoid;
        <?php if(!$only_one_sheet): ?>
        min-height: 130mm;
        <?php endif; ?>
    }
    .voucher-meta-top {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        font-size: 13px;
        margin-bottom: 2px;
    }
    .voucher-meta-top .meta-left { flex: 1; text-align: left; }
    .voucher-meta-top .meta-center { flex: 1; text-align: center; }
    .voucher-meta-top .meta-right { flex: 1; text-align: right; }
    .print-title {
        font-size: 20px;
        font-weight: bold;
        text-align: center;
        letter-spacing: 2px;
        margin-bottom: 5px;
        font-family: SimSun, serif;
    }
    .voucher-meta-second {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        font-size: 13px;
        margin-bottom: 7px;
    }
    .voucher-meta-second .meta-left { flex: 1; text-align: left; }
    .voucher-meta-second .meta-center { flex: 1; text-align: center; }
    .voucher-meta-second .meta-right { flex: 1; text-align: right; }
    .print-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 7px;
    }
    .print-table th, .print-table td {
        border: 1px solid #222;
        padding: 7px 3px;
        font-size: 14px;
        font-family: SimSun, serif;
        text-align: left;
        vertical-align: middle;
        height: 25px;
    }
    .print-table th {
        background: #fff;
        text-align: center;
        font-weight: bold;
    }
    .print-table .amt {
        text-align: right;
        font-family: 'Consolas', 'Courier New', monospace;
    }
    .print-table .totalcap {
        font-weight: normal;
        font-size: 13px;
        color: #555;
        text-align: left;
    }
    .print-table .totalamt {
        font-weight: bold;
        font-size: 14px;
    }
    .print-sign {
        margin-top: 15px;
        display: flex;
        justify-content: space-between;
    }
    .print-sign span {
        flex: 1;
        text-align: left;
        padding-right: 12px;
        font-family: SimSun, serif;
    }
    .no-print {
        margin: 20px auto;
        text-align: center;
    }
    .no-print button {
        font-size: 16px;
        padding: 7px 28px;
        background: #3386f1;
        color: #fff;
        border: none;
        border-radius: 5px;
        margin: 0 12px;
        cursor: pointer;
    }
    .no-print button:hover {
        background: #195aaa;
    }
    </style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()">打印</button>
    <button onclick="window.close()">关闭</button>
</div>
<div class="print-root">
<?php
for ($i = 0; $i < $total_count; $i += 2):
    $is_last = ($i + 2 >= $total_count);
?>
<div class="print-sheet<?= $is_last ? '' : ' page-break' ?>">
    <?php for($j = 0; $j < 2; $j++):
        $idx = $i + $j;
        if ($idx >= $total_count) break;
        $v = $flat_vouchers[$idx];
        $items = isset($v['items']) ? $v['items'] : get_items($v);
        $total_debit = 0; $total_credit = 0;
        foreach($items as $row){
            $total_debit += floatval($row['debit']);
            $total_credit += floatval($row['credit']);
        }
        $items = pad_rows($items, 5);
    ?>
    <div class="print-voucher">
        <div class="meta-center print-title" style="margin-bottom:20;"><?= '记账凭证' ?></div>
        <div class="voucher-meta-top">
            <div class="meta-left">
                单位：<?=htmlspecialchars($company)?>
            </div>
            <div class="meta-center">
                日期：<?=isset($v['date']) ? date('Y-m-d', strtotime($v['date'])) : ''?>
            </div>
            <div class="meta-right">
                凭证号：记-<?=isset($v['number']) ? htmlspecialchars($v['number']) : str_pad($v['id'],3,'0',STR_PAD_LEFT)?>
                <?php if(!empty($v['page_note'])): ?>
                    <span style="font-size:12px;">(<?=htmlspecialchars($v['page_note'])?>)</span>
                <?php endif; ?>
            </div>
        </div>
        <table class="print-table">
            <tr>
                <th style="width:34%;">摘要</th>
                <th style="width:33%;">科目</th>
                <th style="width:16%;">借方金额</th>
                <th style="width:17%;">贷方金额</th>
            </tr>
            <?php foreach($items as $row): ?>
            <tr>
                <td><?=htmlspecialchars($row['summary'])?></td>
                <td><?=isset($acct_map[$row['account_code']]) ? htmlspecialchars($acct_map[$row['account_code']]) : htmlspecialchars($row['account_code'])?></td>
                <td class="amt"><?= $row['debit']!=='' ? number_format(floatval($row['debit']),2) : '' ?></td>
                <td class="amt"><?= $row['credit']!=='' ? number_format(floatval($row['credit']),2) : '' ?></td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td class="totalcap" colspan="2">合计：<?=money_cap($total_debit)?></td>
                <td class="amt totalamt"><?=number_format($total_debit,2)?></td>
                <td class="amt totalamt"><?=number_format($total_credit,2)?></td>
            </tr>
        </table>
        <div class="print-sign">
            <span>主管：</span>
            <span>记账：</span>
            <span>审核：</span>
            <span>出纳：</span>
            <span>制单：<?=isset($v['creator']) ? htmlspecialchars($v['creator']) : ''?></span>
        </div>
    </div>
    <?php if($j == 0 && ($i+1)<$total_count): ?>
        <div class="print-sep"></div>
    <?php endif; ?>
    <?php endfor; ?>
</div>
<?php endfor; ?>
</div>
<script>
window.onload = function(){
    setTimeout(function(){
        window.print();
    }, 400);
};
</script>
</body>
</html>