<?php
require_once 'inc/functions.php';
checkLogin();
global $db;

// -------- 账套和期间 --------
$books = getBooks();
$currentBookId = isset($_SESSION['book_id']) ? intval($_SESSION['book_id']) : (isset($books[0]['id']) ? intval($books[0]['id']) : 0);
if (isset($_GET['book_id']) && intval($_GET['book_id']) != $currentBookId) {
    $_SESSION['book_id'] = intval($_GET['book_id']);
    $currentBookId = $_SESSION['book_id'];
}
$currentBook = null;
foreach ($books as $b) {
    if ($b['id'] == $currentBookId) { $currentBook = $b; break; }
}

// 年份和季度选择
if (isset($_GET['period_year'])) {
    $_SESSION['period_year'] = intval($_GET['period_year']);
}
if (isset($_GET['quarter'])) {
    $_SESSION['quarter'] = intval($_GET['quarter']);
}
$sel_year = isset($_SESSION['period_year']) ? intval($_SESSION['period_year']) : date('Y');
$sel_quarter = isset($_SESSION['quarter']) ? intval($_SESSION['quarter']) : ceil(date('n') / 3);
if ($sel_quarter < 1 || $sel_quarter > 4) $sel_quarter = 1;

// 计算季度起止
$quarter_months = [
    1 => ['start' => 1,  'end' => 3 ],
    2 => ['start' => 4,  'end' => 6 ],
    3 => ['start' => 7,  'end' => 9 ],
    4 => ['start' => 10, 'end' => 12],
];
$q_start_month = $quarter_months[$sel_quarter]['start'];
$q_end_month   = $quarter_months[$sel_quarter]['end'];
$quarter_start = sprintf('%04d-%02d-01', $sel_year, $q_start_month);
$quarter_end   = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $sel_year, $q_end_month)));

$year_start = sprintf('%04d-01-01', $sel_year);
$year_end   = $quarter_end;

// 页头信息
$report_title = "利润表（季度统计）";
$report_date = "第{$sel_quarter}季度（{$quarter_start} ~ {$quarter_end}）";
$report_unit = "元";
$company = $currentBook['name'] ?? '';

// -------- 利润表配置（完整版） --------
$income_rows = [
    ['idx'=>1,  'label'=>'一、营业收入',      'accounts'=>['5001','5051'], 'type'=>'credit'],
    ['idx'=>2,  'label'=>'减：营业成本',      'accounts'=>['5401','5402'], 'type'=>'debit'],
    ['idx'=>3,  'label'=>'税金及附加',        'accounts'=>['5403'], 'type'=>'debit'],
    ['idx'=>4,  'label'=>'其中：消费税',      'accounts'=>[], 'type'=>'debit'],
    ['idx'=>5,  'label'=>'营业税',            'accounts'=>[], 'type'=>'debit'],
    ['idx'=>6,  'label'=>'城市维护建设税',    'accounts'=>[], 'type'=>'debit'],
    ['idx'=>7,  'label'=>'资源税',            'accounts'=>[], 'type'=>'debit'],
    ['idx'=>8,  'label'=>'土地增值税',        'accounts'=>[], 'type'=>'debit'],
    ['idx'=>9,  'label'=>'城镇土地使用税、房产税、车船税、印花税', 'accounts'=>[], 'type'=>'debit'],
    ['idx'=>10, 'label'=>'教育费附加、矿产资源补偿费、排污费',   'accounts'=>[], 'type'=>'debit'],
    ['idx'=>11, 'label'=>'销售费用',          'accounts'=>['5601'], 'type'=>'debit'],
    ['idx'=>12, 'label'=>'其中：商品维修费',  'accounts'=>[], 'type'=>'debit'],
    ['idx'=>13, 'label'=>'广告费和业务宣传费','accounts'=>[], 'type'=>'debit'],
    ['idx'=>14, 'label'=>'管理费用',          'accounts'=>['5602'], 'type'=>'debit'],
    ['idx'=>15, 'label'=>'其中：开办费',      'accounts'=>[], 'type'=>'debit'],
    ['idx'=>16, 'label'=>'业务招待费',        'accounts'=>[], 'type'=>'debit'],
    ['idx'=>17, 'label'=>'研究费用',          'accounts'=>[], 'type'=>'debit'],
    ['idx'=>18, 'label'=>'财务费用',          'accounts'=>['5603'], 'type'=>'debit'],
    ['idx'=>19, 'label'=>'其中：利息费用（收入以“-”号填列）','accounts'=>[], 'type'=>'debit'],
    ['idx'=>20, 'label'=>'加：投资收益（损失以“-”号填列）','accounts'=>['5111'], 'type'=>'credit'],
    ['idx'=>21, 'label'=>'二、营业利润（亏损以“-”号填列）','formula'=>'(1)-(2)-(3)-(11)-(14)-(18)+(20)'],
    ['idx'=>22, 'label'=>'加：营业外收入', 'accounts'=>['5301'], 'type'=>'credit'],
    ['idx'=>23, 'label'=>'其中：政府补助', 'accounts'=>[], 'type'=>'credit'],
    ['idx'=>24, 'label'=>'减：营业外支出', 'accounts'=>['5711'], 'type'=>'debit'],
    ['idx'=>25, 'label'=>'其中：坏账损失', 'accounts'=>[], 'type'=>'debit'],
    ['idx'=>26, 'label'=>'无法收回的长期债券投资损失','accounts'=>[], 'type'=>'debit'],
    ['idx'=>27, 'label'=>'无法收回的长期股权投资损失','accounts'=>[], 'type'=>'debit'],
    ['idx'=>28, 'label'=>'自然灾害等不可抗力因素造成的损失','accounts'=>[], 'type'=>'debit'],
    ['idx'=>29, 'label'=>'税收滞纳金',      'accounts'=>[], 'type'=>'debit'],
    ['idx'=>30, 'label'=>'三、利润总额（亏损总额以“-”号填列）','formula'=>'(21)+(22)-(24)'],
    ['idx'=>31, 'label'=>'减：所得税费用',   'accounts'=>['5801'], 'type'=>'debit'],
    ['idx'=>32, 'label'=>'四、净利润（净亏损以“-”号填列）','formula'=>'(30)-(31)'],
];

// 查询损益发生额，只统计非结转凭证的本期贷方/借方发生额
function get_income_item_amount($accounts, $type, $date1, $date2, $book_id) {
    global $db;
    if (!$accounts) return 0.0;
    // 5603财务费用特殊处理
    if (count($accounts) === 1 && (strval($accounts[0]) == '5603')) {
        // 借方
        $sql_debit = "SELECT SUM(vi.debit) FROM voucher_items vi 
            JOIN vouchers v ON v.id=vi.voucher_id 
            WHERE vi.account_code = ? AND v.book_id=? 
            AND v.date>=? AND v.date<=?
            AND (vi.summary IS NULL OR vi.summary NOT IN ('结转本月损益','结转本年利润'))";
        $stmt_debit = $db->prepare($sql_debit);
        $stmt_debit->execute(['5603', $book_id, $date1, $date2]);
        $debit = $stmt_debit->fetchColumn();
        $debit = is_null($debit) ? 0 : floatval($debit);

        // 贷方
        $sql_credit = "SELECT SUM(vi.credit) FROM voucher_items vi 
            JOIN vouchers v ON v.id=vi.voucher_id 
            WHERE vi.account_code = ? AND v.book_id=? 
            AND v.date>=? AND v.date<=?
            AND (vi.summary IS NULL OR vi.summary NOT IN ('结转本月损益','结转本年利润'))";
        $stmt_credit = $db->prepare($sql_credit);
        $stmt_credit->execute(['5603', $book_id, $date1, $date2]);
        $credit = $stmt_credit->fetchColumn();
        $credit = is_null($credit) ? 0 : floatval($credit);

        return $debit - $credit;
    }
    // 其他科目
    if (!$accounts) return 0.0;
    $placeholders = implode(',', array_fill(0, count($accounts), '?'));
    $sql_where = "vi.account_code IN ($placeholders) AND v.book_id=? AND v.date>=? AND v.date<=? AND (vi.summary IS NULL OR vi.summary NOT IN ('结转本月损益','结转本年利润'))";
    if ($type == 'credit') {
        $sql = "SELECT SUM(vi.credit) FROM voucher_items vi JOIN vouchers v ON v.id=vi.voucher_id WHERE $sql_where";
    } else {
        $sql = "SELECT SUM(vi.debit) FROM voucher_items vi JOIN vouchers v ON v.id=vi.voucher_id WHERE $sql_where";
    }
    $params = array_merge($accounts, [$book_id, $date1, $date2]);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $amount = $stmt->fetchColumn();
    return is_null($amount) ? 0 : floatval($amount);
}

// 自动公式
function calc_income_row($row, $col, $date1, $date2, $q_start, $q_end, &$cache, $book_id) {
    if (!empty($row['formula'])) {
        preg_match_all('/\((\d+)\)/', $row['formula'], $m);
        $nums = $m[1];
        $exp = $row['formula'];
        foreach($nums as $n) {
            $val = $cache[$n][$col] ?? 0.0;
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
    } else {
        // col=='year'统计年累计，col=='quarter'统计本季度
        $res = get_income_item_amount(
            $row['accounts'],
            $row['type'] ?? 'credit',
            $col == 'year' ? $date1 : $q_start,
            $col == 'year' ? $date2 : $q_end,
            $book_id
        );
        return $res;
    }
}

// --------- 导出CSV ---------
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment;filename=季度利润表.csv');
    $fp = fopen('php://output', 'w');
    fwrite($fp, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($fp, [$report_title]);
    fputcsv($fp, ["核算单位：$company", '', '', $report_date, "单位：$report_unit"]);
    fputcsv($fp, ['项目', '行次', '本年累计金额', '本季度金额']);
    $cache = [];
    foreach($income_rows as $row) {
        $year_val = calc_income_row($row, 'year', $year_start, $year_end, $quarter_start, $quarter_end, $cache, $currentBookId);
        $quarter_val = calc_income_row($row, 'quarter', $year_start, $year_end, $quarter_start, $quarter_end, $cache, $currentBookId);
        $cache[$row['idx']] = ['year'=>$year_val, 'quarter'=>$quarter_val];
        fputcsv($fp, [
            $row['label'],
            $row['idx'],
            number_format($year_val,2,'.',''),
            number_format($quarter_val,2,'.','')
        ]);
    }
    fclose($fp);
    exit;
}

// --------- 页面展示 ---------
include 'templates/header.php';
?>
<style>
.report-center-wrap {
    max-width: 950px;
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
@media (max-width: 1050px) {
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
        <form method="get" action="" style="display:inline;margin-left:30px;">
            年份：
            <select name="period_year" onchange="this.form.submit()" style="font-size:15px;">
            <?php for($y=date('Y')-5;$y<=date('Y')+1;$y++): ?>
                <option value="<?=$y?>" <?=$sel_year==$y?'selected':''?>><?=$y?></option>
            <?php endfor;?>
            </select>
            季度：
            <select name="quarter" onchange="this.form.submit()" style="font-size:15px;">
                <option value="1" <?=$sel_quarter==1?'selected':''?>>第一季度</option>
                <option value="2" <?=$sel_quarter==2?'selected':''?>>第二季度</option>
                <option value="3" <?=$sel_quarter==3?'selected':''?>>第三季度</option>
                <option value="4" <?=$sel_quarter==4?'selected':''?>>第四季度</option>
            </select>
        </form>
    </div>
    <div id="print-area">
        <div style="width:100%;margin-bottom:8px;">
            <div style="text-align:center;font-weight:bold;font-size:22px;"><?= htmlspecialchars($report_title) ?></div>
            <table style="width:100%;border:none;font-size:15px;margin-top:3px;margin-bottom:8px;">
                <tr>
                    <td style="border:none;padding:0;" colspan="2">核算单位：<?= htmlspecialchars($company) ?></td>
                    <td style="border:none;padding:0;text-align:right;" colspan="2"><?= htmlspecialchars($report_date) ?>&nbsp;&nbsp;单位：<?= htmlspecialchars($report_unit) ?></td>
                </tr>
            </table>
        </div>
        <table border="1" cellspacing="0" cellpadding="4" style="width:100%;border-collapse:collapse;font-size:15px;">
            <tr style="background:#f6f6f6;">
                <th style="width:40%;">项目</th>
                <th style="width:8%;">行次</th>
                <th style="width:26%;">本年累计金额</th>
                <th style="width:26%;">本季度金额</th>
            </tr>
            <?php
            $cache = [];
            foreach($income_rows as $row):
                $year_val = calc_income_row($row, 'year', $year_start, $year_end, $quarter_start, $quarter_end, $cache, $currentBookId);
                $quarter_val = calc_income_row($row, 'quarter', $year_start, $year_end, $quarter_start, $quarter_end, $cache, $currentBookId);
                $cache[$row['idx']] = ['year'=>$year_val, 'quarter'=>$quarter_val];
            ?>
            <tr>
                <td><?= htmlspecialchars($row['label']) ?></td>
                <td align="center"><?= $row['idx'] ?></td>
                <td align="right"><?= number_format($year_val,2) ?></td>
                <td align="right"><?= number_format($quarter_val,2) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php include 'templates/footer.php'; ?>