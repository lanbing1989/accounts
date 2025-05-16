<?php
require_once 'inc/functions.php';
require_once 'inc/closing_utils.php';
checkLogin();
global $db;

// -------- 账套和期间 --------
$books = getBooks();
$currentBookId = isset($_SESSION['book_id']) ? intval($_SESSION['book_id']) : (isset($books[0]['id']) ? intval($books[0]['id']) : 0);
$currentBook = null;
foreach ($books as $b) {
    if ($b['id'] == $currentBookId) { $currentBook = $b; break; }
}
$sel_year = isset($_SESSION['period_year']) ? intval($_SESSION['period_year']) : date('Y');
$sel_month = isset($_SESSION['period_month']) ? intval($_SESSION['period_month']) : date('n');
$month = sprintf('%04d-%02d', $sel_year, $sel_month);
$today = date('Y-m-t', strtotime($month . '-01'));
$year = date('Y', strtotime($today));
$year_start = "$year-01-01";
$last_year = date('Y-m-d', strtotime("$year_start -1 day"));
$month_num = intval(date('n', strtotime($today)));

// 页头信息
$report_title = "资产负债表";
$report_date = $today;
$report_unit = "元";
$report_template = "会小企01表";
$company = $currentBook['name'] ?? '';

// -------- 资产负债表配置 --------
$asset_config = [
    ['label'=>'流动资产:', 'idx'=>''],
    ['label'=>'货币资金', 'idx'=>1, 'accounts'=>['1001','1002','1012']],
    ['label'=>'短期投资', 'idx'=>2, 'accounts'=>['1101']],
    ['label'=>'应收票据', 'idx'=>3, 'accounts'=>['1121']],
    ['label'=>'应收账款', 'idx'=>4, 'accounts'=>['1122'], 'balance_type'=>'debit'],
    ['label'=>'预付账款', 'idx'=>5, 'accounts'=>['1123'], 'balance_type'=>'debit'],
    ['label'=>'应收股利', 'idx'=>6, 'accounts'=>['1133']],
    ['label'=>'应收利息', 'idx'=>7, 'accounts'=>['1132']],
    ['label'=>'其他应收款', 'idx'=>8, 'accounts'=>['1221']],
    ['label'=>'存货', 'idx'=>9, 'accounts'=>['1401','1402','1403','1404','1405','1407','1408','1411','1421']],
    ['label'=>'　其中：原材料', 'idx'=>10, 'accounts'=>['1403']],
    ['label'=>'　　　在产品', 'idx'=>11, 'accounts'=>['1408']],
    ['label'=>'　　　库存商品', 'idx'=>12, 'accounts'=>['1405']],
    ['label'=>'　　　周转材料', 'idx'=>13, 'accounts'=>['1411']],
    ['label'=>'其他流动资产', 'idx'=>14, 'accounts'=>[]],
    ['label'=>'流动资产合计', 'idx'=>15, 'formula'=>'(1)+(2)+(3)+(4)+(5)+(6)+(7)+(8)+(9)+(14)'],
    ['label'=>'非流动资产:', 'idx'=>''],
    ['label'=>'长期债券投资', 'idx'=>16, 'accounts'=>['1501']],
    ['label'=>'长期股权投资', 'idx'=>17, 'accounts'=>['1511']],
    ['label'=>'固定资产原价', 'idx'=>18, 'accounts'=>['1601']],
    ['label'=>'减：累计折旧', 'idx'=>19, 'accounts'=>['1602'], 'is_minus'=>true],
    ['label'=>'固定资产账面价值', 'idx'=>20, 'formula'=>'(18)-(19)'],
    ['label'=>'在建工程', 'idx'=>21, 'accounts'=>['1604']],
    ['label'=>'工程物资', 'idx'=>22, 'accounts'=>['1605']],
    ['label'=>'固定资产清理', 'idx'=>23, 'accounts'=>['1606']],
    ['label'=>'生产性生物资产', 'idx'=>24, 'accounts'=>['1621','1622']],
    ['label'=>'无形资产', 'idx'=>25, 'accounts'=>['1701','1702']],
    ['label'=>'开发支出', 'idx'=>26, 'accounts'=>['4301']],
    ['label'=>'长期待摊费用', 'idx'=>27, 'accounts'=>['1801']],
    ['label'=>'其他非流动资产', 'idx'=>28, 'accounts'=>[]],
    ['label'=>'非流动资产合计', 'idx'=>29, 'formula'=>'(16)+(17)+(20)+(21)+(22)+(23)+(24)+(25)+(26)+(27)+(28)'],
    ['label'=>'资产总计', 'idx'=>30, 'formula'=>'(15)+(29)'],
];

$liab_config = [
    ['label'=>'流动负债:', 'idx'=>''],
    ['label'=>'短期借款', 'idx'=>31, 'accounts'=>['2001']],
    ['label'=>'应付票据', 'idx'=>32, 'accounts'=>['2201']],
    ['label'=>'应付账款', 'idx'=>33, 'accounts'=>['2202'], 'balance_type'=>'credit'],
    ['label'=>'预收账款', 'idx'=>34, 'accounts'=>['2203'], 'balance_type'=>'credit'],
    ['label'=>'应付职工薪酬', 'idx'=>35, 'accounts'=>['2211']],
    ['label'=>'应交税费', 'idx'=>36, 'accounts'=>['2221'], 'balance_type'=>'credit'],
    ['label'=>'应付利息', 'idx'=>37, 'accounts'=>['2231']],
    ['label'=>'应付利润', 'idx'=>38, 'accounts'=>['2232']],
    ['label'=>'其他应付款', 'idx'=>39, 'accounts'=>['2241']],
    ['label'=>'', 'idx'=>''], // 对齐：留空
    ['label'=>'', 'idx'=>''], // 对齐：留空
    ['label'=>'', 'idx'=>''], // 对齐：留空
    ['label'=>'', 'idx'=>''],
    ['label'=>'其他流动负债', 'idx'=>40, 'accounts'=>[]],
    ['label'=>'流动负债合计', 'idx'=>41, 'formula'=>'(31)+(32)+(33)+(34)+(35)+(36)+(37)+(38)+(39)+(40)'],
    ['label'=>'非流动负债:', 'idx'=>''],
    ['label'=>'长期借款', 'idx'=>42, 'accounts'=>['2501']],
    ['label'=>'长期应付款', 'idx'=>43, 'accounts'=>['2701']],
    ['label'=>'递延收益', 'idx'=>44, 'accounts'=>['2401']],
    ['label'=>'其他非流动负债', 'idx'=>45, 'accounts'=>[]],
    ['label'=>'非流动负债合计', 'idx'=>46, 'formula'=>'(42)+(43)+(44)+(45)'],
    ['label'=>'负债合计', 'idx'=>47, 'formula'=>'(41)+(46)'],
    ['label'=>'', 'idx'=>''],
    ['label'=>'', 'idx'=>''],
    ['label'=>'所有者权益（或股东权益）', 'idx'=>''],
    ['label'=>'实收资本（或股本）', 'idx'=>48, 'accounts'=>['3001']],
    ['label'=>'资本公积', 'idx'=>49, 'accounts'=>['3002']],
    ['label'=>'盈余公积', 'idx'=>50, 'accounts'=>['3101']],
    ['label'=>'未分配利润', 'idx'=>51, 'special'=>'unallocated_profit'],
    ['label'=>'所有者权益（或股东权益）合计', 'idx'=>52, 'formula'=>'(48)+(49)+(50)+(51)'],
    ['label'=>'负债和所有者权益（或股东权益）总计', 'idx'=>53, 'formula'=>'(47)+(52)'],
];

// -------- 查询函数 --------
function get_account_balance($accounts, $date, $book_id, $is_minus = false, $balance_type = null) {
    global $db;
    if (!$accounts) return 0.0;
    $sum = 0.0;
    foreach($accounts as $code) {
        $category = $db->query("SELECT category FROM accounts WHERE book_id=$book_id AND code='$code'")->fetchColumn();
        $sql = ($category=='资产' || $category=='成本') ?
            "SELECT SUM(debit)-SUM(credit) FROM voucher_items vi JOIN vouchers v ON v.id=vi.voucher_id WHERE v.book_id=? AND vi.account_code=? AND v.date<=?" :
            "SELECT SUM(credit)-SUM(debit) FROM voucher_items vi JOIN vouchers v ON v.id=vi.voucher_id WHERE v.book_id=? AND vi.account_code=? AND v.date<=?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$book_id, $code, $date]);
        $val = floatval($stmt->fetchColumn());
        if ($balance_type == 'debit' && $val < 0) $val = 0;
        if ($balance_type == 'credit' && $val > 0) $val = 0;
        if ($is_minus) $val = -$val;
        $sum += $val;
    }
    return $sum;
}

// -------- 资产负债表辅助函数 --------
function calc_balance_row($row, $col, $today, $last_year, &$cache, $book_id) {
    if (!empty($row['special']) && $row['special']=='unallocated_profit') {
        $date = $col=='end' ? $today : $last_year;
        // 未分配利润 = 310401余额 + 3103本年利润余额
        $unallocated = get_account_balance(['310401'], $date, $book_id);
        $current_profit = get_account_balance(['3103'], $date, $book_id);
        return $unallocated + $current_profit;
    }
    if (!empty($row['formula'])) {
        preg_match_all('/\((\d+)\)/', $row['formula'], $m);
        $nums = $m[1];
        $exp = $row['formula'];
        foreach($nums as $n) {
            $val = isset($cache[$n][$col]) && $cache[$n][$col] !== '' ? $cache[$n][$col] : 0.0;
            $exp = str_replace("($n)", $val, $exp);
        }
        $exp = preg_replace('/--/', '+', $exp);
        $exp = preg_replace('/\+\+/', '+', $exp);
        $exp = preg_replace('/\+-/', '-', $exp);
        $exp = trim($exp);
        if ($exp === '' || $exp === ';' || $exp === 'return ;' || !preg_match('/[0-9]/', $exp)) return 0.0;
        if (!preg_match('/^[\d\.\+\-\*\/\(\) ]+$/', $exp)) return 0.0;
        $result = @eval("return $exp;");
        return $result;
    } elseif (!empty($row['accounts'])) {
        $date = $col=='end' ? $today : $last_year;
        $val = get_account_balance($row['accounts'], $date, $book_id, $row['is_minus']??false, $row['balance_type']??null);
        return $val;
    } else {
        return '';
    }
}

// -------- 生成表格行 --------
$maxlen = max(count($asset_config), count($liab_config));
$rows = [];
$cache = [];
for($i=0; $i<$maxlen; $i++) {
    $a = $asset_config[$i] ?? ['label'=>'','idx'=>''];
    $l = $liab_config[$i] ?? ['label'=>'','idx'=>''];
    $end_a = $a['label']!=='' ? calc_balance_row($a, 'end', $today, $last_year, $cache, $currentBookId) : '';
    $start_a = $a['label']!=='' ? calc_balance_row($a, 'start', $today, $last_year, $cache, $currentBookId) : '';
    if (isset($a['idx']) && $a['idx']!=='') $cache[$a['idx']] = ['end'=>$end_a,'start'=>$start_a];
    $end_l = $l['label']!=='' ? calc_balance_row($l, 'end', $today, $last_year, $cache, $currentBookId) : '';
    $start_l = $l['label']!=='' ? calc_balance_row($l, 'start', $today, $last_year, $cache, $currentBookId) : '';
    if (isset($l['idx']) && $l['idx']!=='') $cache[$l['idx']] = ['end'=>$end_l,'start'=>$start_l];
    // 保证每行都输出8列
    $rows[] = [
        $a['label']??'', $a['idx']??'', $end_a!==''? $end_a : '', $start_a!==''? $start_a : '',
        $l['label']??'', $l['idx']??'', $end_l!==''? $end_l : '', $start_l!==''? $start_l : ''
    ];
}

// --------- 导出CSV ---------
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment;filename=资产负债表.csv');
    $fp = fopen('php://output', 'w');
    // BOM for Excel
    fwrite($fp, chr(0xEF).chr(0xBB).chr(0xBF));
    // 页头
    fputcsv($fp, [$report_title]);
    fputcsv($fp, ["核算单位：$company", '', '', '', '', '', $report_date, "单位：$report_unit"]);
    fputcsv($fp, ['资产','行次','期末余额','年初余额','负债和所有者权益','行次','期末余额','年初余额']);
    foreach ($rows as $row) {
        // 格式化数字
        foreach ([2,3,6,7] as $idx) {
            if (isset($row[$idx]) && $row[$idx] !== '' && is_numeric($row[$idx])) {
                $row[$idx] = number_format($row[$idx],2,'.','');
            }
        }
        fputcsv($fp, $row);
    }
    fclose($fp);
    exit;
}

// --------- 导出按钮和原表格 ---------
include 'templates/header.php';
?>
<style>
.report-center-wrap {
    max-width: 1050px;
    margin: 36px auto 0 auto;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 16px #e7ecf5;
    padding: 26px 32px 36px 32px;
}
.btn-blue {
    background: #3490ff;
    border: none;
    color: #fff;
    padding: 7px 24px;
    border-radius: 6px;
    font-size: 15px;
    margin-right: 12px;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-blue:hover {
    background: #2779bd;
}
@media print {
    body * {
        visibility: hidden !important;
    }
    #print-area, #print-area * {
        visibility: visible !important;
    }
    #print-area {
        position: absolute;
        left: 0; top: 0; width: 210mm; min-height: 297mm;
        margin: 0 !important;
        background: #fff !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
    @page {
        size: A4 portrait;
        margin: 16mm 10mm 14mm 10mm;
    }
    html, body {
        width: 210mm;
        height: 297mm;
        background: #fff !important;
    }
}
@media (max-width: 1100px) {
    .report-center-wrap { max-width: 99vw; padding: 10px 2px; }
}
</style>
<div class="report-center-wrap">
    <div class="no-print" style="margin-bottom:14px;">
        <form method="get" action="" style="display:inline;">
            <input type="hidden" name="export" value="csv" />
            <button type="submit" class="btn-blue">导出CSV</button>
        </form>
        <button class="btn-blue" onclick="window.print()">打印</button>
    </div>
    <div id="print-area">
        <!-- 页头部分 -->
        <div style="width:100%;margin-bottom:8px;">
            <div style="text-align:center;font-weight:bold;font-size:22px;"><?= htmlspecialchars($report_title) ?></div>
            <table style="width:100%;border:none;font-size:15px;margin-top:3px;">
                <tr>
                    <td style="border:none;padding:0;" colspan="2">核算单位：<?= htmlspecialchars($company) ?></td>
                    <td style="border:none;padding:0;text-align:right;" colspan="2"><?= htmlspecialchars($report_template) ?></td>
                </tr>
                <tr>
                    <td style="border:none;padding:0;" colspan="2"><?= htmlspecialchars($report_date) ?></td>
                    <td style="border:none;padding:0;text-align:right;" colspan="2">单位：<?= htmlspecialchars($report_unit) ?></td>
                </tr>
            </table>
        </div>
        <!-- 正文表格 -->
        <table border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;font-size:15px;width:100%;">
            <tr style="background:#f6f6f6;">
                <th style="width:16%;">资产</th>
                <th style="width:4%;">行次</th>
                <th style="width:10%;">期末余额</th>
                <th style="width:10%;">年初余额</th>
                <th style="width:16%;">负债和所有者权益</th>
                <th style="width:4%;">行次</th>
                <th style="width:10%;">期末余额</th>
                <th style="width:10%;">年初余额</th>
            </tr>
            <?php foreach($rows as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row[0]) ?></td>
                <td align="center"><?= $row[1] ?></td>
                <td align="right"><?= $row[2] !== '' ? number_format($row[2],2) : '' ?></td>
                <td align="right"><?= $row[3] !== '' ? number_format($row[3],2) : '' ?></td>
                <td><?= htmlspecialchars($row[4]) ?></td>
                <td align="center"><?= $row[5] ?></td>
                <td align="right"><?= $row[6] !== '' ? number_format($row[6],2) : '' ?></td>
                <td align="right"><?= $row[7] !== '' ? number_format($row[7],2) : '' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php include 'templates/footer.php'; ?>