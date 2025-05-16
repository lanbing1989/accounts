<?php
require_once 'inc/functions.php';
require_once 'inc/closing_utils.php';
checkLogin();
global $db;

// -------- 账套和期间 --------
$books = getBooks();
$currentBookId = isset($_SESSION['book_id']) ? intval($_SESSION['book_id']) : (isset($books[0]['id']) ? intval($books[0]['id']) : 0);
foreach ($books as $b) if ($b['id'] == $currentBookId) { $currentBook = $b; break; }
$company = $currentBook['name'] ?? '';
$taxpayer_no = $currentBook['taxpayer_no'] ?? '';
$sel_year  = isset($_SESSION['period_year']) ? intval($_SESSION['period_year']) : date('Y');
$sel_month = isset($_SESSION['period_month']) ? intval($_SESSION['period_month']) : date('n');
if ($sel_month < 1 || $sel_month > 12) $sel_month = date('n');

// 计算季度信息
function get_quarter_start_end($year, $month) {
    $quarter = ceil($month / 3);
    $start_month = ($quarter - 1) * 3 + 1;
    $end_month = $quarter * 3;
    $quarter_start = date('Y-m-01', strtotime("$year-$start_month-01"));
    $quarter_end   = date('Y-m-t', strtotime("$year-$end_month-01"));
    return [$quarter, $quarter_start, $quarter_end];
}
list($cur_quarter, $quarter1, $quarter2) = get_quarter_start_end($sel_year, $sel_month);
$report_title = "现金流量表（第{$cur_quarter}季度）";
$report_date = "$quarter1 至 $quarter2";
$report_unit = "元";
$report_template = "会小企03表";

// 年度区间
$date1 = sprintf('%04d-01-01', $sel_year);
$date2 = $quarter2;

// 现金等价物科目
function cash_eq_codes() {
    return ['1001','1002','1012'];
}

// 明确经营活动现金流入科目
function operating_cash_in_codes() {
    return [
        '1121','1122','1221','2203','1131','1132',
        '2241',      // 其他应付款
        '5001','5051','6001','6051', // 主营/其他/劳务收入
        '5301',      // 营业外收入
        '5603'
    ];
}

// 现金流量表项目配置
$cashflow_items = [
    ['group'=>'一、经营活动产生的现金流量：'],
    ['idx'=>1, 'label'=>'销售产成品、商品、提供劳务收到的现金', 'type'=>'in', 'main'=>['5001','5051','6001','6051']],
    ['idx'=>2, 'label'=>'收到其他与经营活动有关的现金', 'type'=>'in', 'other_in'=>true],
    ['idx'=>3, 'label'=>'购买原材料、商品、接受劳务支付的现金', 'type'=>'out', 'main'=>['1403','1405','1601','1602','1603','1604','1605','1606','1607','1608','1609','1610','1611','1612','1613','1614','1615','1616']],
    ['idx'=>4, 'label'=>'支付的职工薪酬', 'type'=>'out', 'main'=>['2211']],
    ['idx'=>5, 'label'=>'支付的税费', 'type'=>'out', 'main'=>['2221']],
    ['idx'=>6, 'label'=>'支付其他与经营活动有关的现金', 'type'=>'out', 'other_out'=>true],
    ['idx'=>7, 'label'=>'经营活动产生的现金流量净额', 'formula'=>'(1)+(2)-(3)-(4)-(5)-(6)'],
    ['group'=>'二、投资活动产生的现金流量：'],
    ['idx'=>8, 'label'=>'收回短期投资、长期债券投资和长期股权投资收到的现金', 'type'=>'in', 'main'=>['1102','1501','1502','1511','1512']],
    ['idx'=>9, 'label'=>'取得投资收益收到的现金', 'type'=>'in', 'main'=>['6111','6112']],
    ['idx'=>10, 'label'=>'处置固定资产、无形资产和其他非流动资产收回的现金净额', 'type'=>'in', 'main'=>['1601','1604','1701','1801','1802']],
    ['idx'=>11, 'label'=>'短期投资、长期债券投资和长期股权投资支付的现金', 'type'=>'out', 'main'=>['1102','1501','1502','1511','1512']],
    ['idx'=>12, 'label'=>'购建固定资产、无形资产和其他非流动资产支付的现金', 'type'=>'out', 'main'=>['1601','1604','1701','1801','1802','5002','5003']],
    ['idx'=>13, 'label'=>'投资活动产生的现金流量净额', 'formula'=>'(8)+(9)+(10)-(11)-(12)'],
    ['group'=>'三、筹资活动产生的现金流量：'],
    ['idx'=>14, 'label'=>'取得借款收到的现金', 'type'=>'in', 'main'=>['2001','2002']],
    ['idx'=>15, 'label'=>'吸收投资者投资收到的现金', 'type'=>'in', 'main'=>['3001','3002']],
    ['idx'=>16, 'label'=>'偿还借款本金支付的现金', 'type'=>'out', 'main'=>['2001','2002']],
    ['idx'=>17, 'label'=>'偿还借款利息支付的现金', 'type'=>'out', 'main'=>['2231']],
    ['idx'=>18, 'label'=>'分配利润支付的现金', 'type'=>'out', 'main'=>['2232']],
    ['idx'=>19, 'label'=>'筹资活动产生的现金流量净额', 'formula'=>'(14)+(15)-(16)-(17)-(18)'],
    ['group'=>'四、现金净增加额'],
    ['idx'=>20, 'label'=>'现金净增加额', 'formula'=>'(7)+(13)+(19)'],
    ['group'=>'加：期初现金余额'],
    ['idx'=>21, 'label'=>'期初现金余额', 'custom_balance'=>'start'],
    ['group'=>'五、期末现金余额'],
    ['idx'=>22, 'label'=>'期末现金余额', 'formula'=>'(20)+(21)'],
];

// 期初/末余额
function get_cash_balance($date, $book_id) {
    global $db;
    $codes = cash_eq_codes();
    $codestr = "'" . implode("','", $codes) . "'";
    $sql = "SELECT SUM(vi.debit-vi.credit) FROM voucher_items vi JOIN vouchers v ON v.id=vi.voucher_id WHERE vi.account_code IN ($codestr) AND v.book_id=? AND v.date<=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$book_id, $date]);
    $val = $stmt->fetchColumn();
    return $val ? floatval($val) : 0.0;
}

// 现金流量归集函数
function get_cashflow_main($main_codes, $type, $from, $to, $book_id) {
    global $db;
    $cash_codes = cash_eq_codes();
    $col = $type=='in' ? 'debit' : 'credit';
    $cash_str = "'" . implode("','", $cash_codes) . "'";
    $main_str = "'" . implode("','", $main_codes) . "'";
    $sql = "SELECT SUM(vi.$col) FROM voucher_items vi 
        JOIN voucher_items vj ON vi.voucher_id = vj.voucher_id
        JOIN vouchers v ON v.id=vi.voucher_id
        WHERE vi.account_code IN ($cash_str) AND vi.$col > 0
        AND vj.account_code IN ($main_str)
        AND v.book_id = ?
        AND v.date >= ? AND v.date <= ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$book_id, $from, $to]);
    $val = $stmt->fetchColumn();
    return $val ? floatval($val) : 0.0;
}

// 经营活动现金流入总额（只归集明确经营活动相关对方科目）
function get_operating_cash_in($from, $to, $book_id) {
    global $db;
    $cash_codes = cash_eq_codes();
    $cash_str = "'" . implode("','", $cash_codes) . "'";
    $codes = operating_cash_in_codes();
    $codes_str = "'" . implode("','", $codes) . "'";
    $sql = "SELECT SUM(vi.debit) FROM voucher_items vi
        JOIN voucher_items vj ON vi.voucher_id = vj.voucher_id
        JOIN vouchers v ON v.id=vi.voucher_id
        WHERE vi.account_code IN ($cash_str)
        AND vi.debit > 0
        AND vj.account_code IN ($codes_str)
        AND v.book_id = ?
        AND v.date >= ? AND v.date <= ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$book_id, $from, $to]);
    $val = $stmt->fetchColumn();
    return $val ? floatval($val) : 0.0;
}

// 所有现金流出（经营+投资+筹资）
function get_total_cash_out($from, $to, $book_id) {
    global $db;
    $cash_codes = cash_eq_codes();
    $cash_str = "'" . implode("','", $cash_codes) . "'";
    $sql = "SELECT SUM(vi.credit) FROM voucher_items vi 
        JOIN vouchers v ON v.id=vi.voucher_id 
        WHERE vi.account_code IN ($cash_str) AND vi.credit>0 AND v.book_id=? AND v.date>=? AND v.date<=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$book_id, $from, $to]);
    $val = $stmt->fetchColumn();
    return $val ? floatval($val) : 0.0;
}

// 其他经营活动现金流入 = 经营活动现金流入 - “销售产成品、商品、提供劳务收到的现金”
function get_other_cash_in($from, $to, $book_id, $cache) {
    $total = get_operating_cash_in($from, $to, $book_id);
    $used = isset($cache[1]['in']) ? $cache[1]['in'] : 0.0;
    return $total - $used;
}
// 其他经营活动现金流出 = 流出总额 - 已归集到除6以外所有out项目
function get_other_cash_out($from, $to, $book_id, $cache) {
    $total = get_total_cash_out($from, $to, $book_id);
    $used = 0.0;
    foreach ([3,4,5,11,12,16,17,18] as $idx) if (isset($cache[$idx]['out'])) $used += $cache[$idx]['out'];
    return $total - $used;
}

// 单项金额（累计/季度），并缓存
function calc_cashflow_item($item, $col, $date1, $date2, $quarter1, $quarter2, $book_id, &$cache) {
    // col: 'year' or 'quarter'
    if (!empty($item['formula'])) {
        $exp = $item['formula'];
        preg_match_all('/\((\d+)\)/', $exp, $m);
        foreach ($m[1] as $n) $exp = preg_replace('/\('.$n.'\)/', $cache[$n][$col]??0, $exp, 1);
        if ($exp === '' || !preg_match('/[0-9]/', $exp)) return 0.0;
        if (!preg_match('/^[\d\.\+\-\*\/\(\)]+$/', $exp)) return 0.0;
        return @eval("return $exp;");
    } elseif (!empty($item['custom_balance'])) {
        $dt = $col=='year' ? date('Y-m-d', strtotime("$date1 -1 day")) : date('Y-m-d', strtotime("$quarter1 -1 day"));
        return get_cash_balance($dt, $book_id);
    } elseif (!empty($item['main'])) {
        if ($col == 'year') {
            $from = $date1;
            $to = $date2;
        } else {
            $from = $quarter1;
            $to = $quarter2;
        }
        $val = get_cashflow_main($item['main'], $item['type'], $from, $to, $book_id);
        $cache[$item['idx']][$col] = $val;
        return $val;
    } elseif (!empty($item['other_in'])) {
        if ($col == 'year') {
            $from = $date1;
            $to = $date2;
        } else {
            $from = $quarter1;
            $to = $quarter2;
        }
        $val = get_other_cash_in($from, $to, $book_id, $cache);
        $cache[$item['idx']][$col] = $val;
        return $val;
    } elseif (!empty($item['other_out'])) {
        if ($col == 'year') {
            $from = $date1;
            $to = $date2;
        } else {
            $from = $quarter1;
            $to = $quarter2;
        }
        $val = get_other_cash_out($from, $to, $book_id, $cache);
        $cache[$item['idx']][$col] = $val;
        return $val;
    }
    return '';
}

// --------- 导出CSV ---------
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment;filename=现金流量表_第'.$cur_quarter.'季度.csv');
    $fp = fopen('php://output', 'w');
    fwrite($fp, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($fp, [$report_title]);
    fputcsv($fp, ["核算单位：$company", '', '', '', $report_date, "单位：$report_unit"]);
    fputcsv($fp, ['项目','行次','本年累计金额','本季度金额']);
    $cache = [];
    foreach($cashflow_items as $item) {
        if (!empty($item['group'])) {
            fputcsv($fp, [$item['group']]);
            continue;
        }
        $year_val = calc_cashflow_item($item, 'year', $date1, $date2, $quarter1, $quarter2, $currentBookId, $cache);
        $quarter_val = calc_cashflow_item($item, 'quarter', $date1, $date2, $quarter1, $quarter2, $currentBookId, $cache);
        $row = [
            $item['label']??'', $item['idx']??'',
            $year_val!=='' ? number_format($year_val,2,'.','') : '',
            $quarter_val!=='' ? number_format($quarter_val,2,'.','') : ''
        ];
        fputcsv($fp, $row);
    }
    fclose($fp);
    exit;
}

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
.group { font-weight: bold; background: #f8fbfd; }
.bold { font-weight: bold; }
.padl { padding-left: 18px; }
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
                <th style="width:34%;">项目</th>
                <th style="width:8%;">行次</th>
                <th style="width:26%;">本年累计金额</th>
                <th style="width:26%;">本季度金额</th>
            </tr>
            <?php
            $cache = [];
            foreach($cashflow_items as $item) {
                if (!empty($item['group'])) {
                    echo "<tr><td class='group' colspan='4'>{$item['group']}</td></tr>";
                    continue;
                }
                $year_val = calc_cashflow_item($item, 'year', $date1, $date2, $quarter1, $quarter2, $currentBookId, $cache);
                $quarter_val = calc_cashflow_item($item, 'quarter', $date1, $date2, $quarter1, $quarter2, $currentBookId, $cache);
                $cache[$item['idx']]['year'] = $year_val;
                $cache[$item['idx']]['quarter'] = $quarter_val;
                $is_formula = isset($item['formula']);
                $show_year = $year_val!=='' ? number_format($year_val,2) : '';
                $show_quarter = $quarter_val!=='' ? number_format($quarter_val,2) : '';
                $bold = $is_formula ? "bold" : "";
                $pad = (in_array($item['idx'],[4,21,22])) ? "padl" : "";
                echo "<tr class='$bold'>";
                echo "<td class='$pad'>{$item['label']}</td>";
                echo "<td>".($item['idx']??'')."</td>";
                echo "<td align='right'>$show_year</td>";
                echo "<td align='right'>$show_quarter</td>";
                echo "</tr>";
            }
            ?>
        </table>
    </div>
</div>
<?php include 'templates/footer.php'; ?>