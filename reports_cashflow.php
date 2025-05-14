<?php
require_once 'inc/functions.php';
checkLogin();
global $db;

/**
 * 现金流量表项目配置
 */
$cashflow_config = [
    ['group'=>'一、经营活动产生的现金流量：'],
    ['idx'=>1, 'label'=>'销售产成品、商品、提供劳务收到的现金', 'type'=>'in', 'opposite_accounts'=>['5001','5051','6001','6051']],
    ['idx'=>2, 'label'=>'收到其他与经营活动有关的现金', 'type'=>'in', 'exclude_opposite_accounts'=>['5001','5051','6001','6051']],
    ['idx'=>3, 'label'=>'购买原材料、商品、接受劳务支付的现金', 'type'=>'out', 'opposite_accounts'=>['1403','1405','1601','1602','1603','1604','1605','1606','1607','1608','1609','1610','1611','1612','1613','1614','1615','1616']],
    ['idx'=>4, 'label'=>'支付的职工薪酬', 'type'=>'out', 'opposite_accounts'=>['2211']],
    ['idx'=>5, 'label'=>'支付的税费', 'type'=>'out', 'opposite_accounts'=>['2221']],
    ['idx'=>6, 'label'=>'支付其他与经营活动有关的现金', 'type'=>'out', 'exclude_opposite_accounts'=>['1403','1405','1601','1602','1603','1604','1605','1606','1607','1608','1609','1610','1611','1612','1613','1614','1615','1616','2211','2221']],
    ['idx'=>7, 'label'=>'经营活动产生的现金流量净额', 'formula'=>'(1)+(2)-(3)-(4)-(5)-(6)'],

    ['group'=>'二、投资活动产生的现金流量：'],
    ['idx'=>8, 'label'=>'收回短期投资、长期债券投资和长期股权投资收到的现金', 'type'=>'in', 'opposite_accounts'=>['1102','1501','1502','1511','1512']],
    ['idx'=>9, 'label'=>'取得投资收益收到的现金', 'type'=>'in', 'opposite_accounts'=>['6111','6112']],
    ['idx'=>10, 'label'=>'处置固定资产、无形资产和其他非流动资产收回的现金净额', 'type'=>'in', 'opposite_accounts'=>['1601','1604','1701','1801','1802']],
    ['idx'=>11, 'label'=>'短期投资、长期债券投资和长期股权投资支付的现金', 'type'=>'out', 'opposite_accounts'=>['1102','1501','1502','1511','1512']],
    ['idx'=>12, 'label'=>'购建固定资产、无形资产和其他非流动资产支付的现金', 'type'=>'out', 'opposite_accounts'=>['1601','1604','1701','1801','1802']],
    ['idx'=>13, 'label'=>'投资活动产生的现金流量净额', 'formula'=>'(8)+(9)+(10)-(11)-(12)'],

    ['group'=>'三、筹资活动产生的现金流量：'],
    ['idx'=>14, 'label'=>'取得借款收到的现金', 'type'=>'in', 'opposite_accounts'=>['2001','2002']],
    ['idx'=>15, 'label'=>'吸收投资者投资收到的现金', 'type'=>'in', 'opposite_accounts'=>['4001','4002']],
    ['idx'=>16, 'label'=>'偿还借款本金支付的现金', 'type'=>'out', 'opposite_accounts'=>['2001','2002']],
    ['idx'=>17, 'label'=>'偿还借款利息支付的现金', 'type'=>'out', 'opposite_accounts'=>['2202']],
    ['idx'=>18, 'label'=>'分配利润支付的现金', 'type'=>'out', 'opposite_accounts'=>['2213']],
    ['idx'=>19, 'label'=>'筹资活动产生的现金流量净额', 'formula'=>'(14)+(15)-(16)-(17)-(18)'],

    ['group'=>'四、现金净增加额'],
    ['idx'=>20, 'label'=>'现金净增加额', 'formula'=>'(7)+(13)+(19)'],

    ['group'=>'加：期初现金余额'],
    ['idx'=>21, 'label'=>'期初现金余额', 'custom_balance'=>'start'],

    ['group'=>'五、期末现金余额'],
    ['idx'=>22, 'label'=>'期末现金余额', 'formula'=>'(20)+(21)'],
];

// 查询期初/期末余额
function get_cash_balance($date) {
    global $db;
    $codes = ['1001','1002','1012'];
    $codestr = "'" . implode("','", $codes) . "'";
    $sql = "SELECT SUM(debit-credit) FROM voucher_items vi JOIN vouchers v ON v.id=vi.voucher_id WHERE vi.account_code IN ($codestr) AND v.date<=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$date]);
    return floatval($stmt->fetchColumn());
}

// 现金流量归集核心函数
function get_cashflow_amount($row, $col, $date1, $date2, $month1, $month2) {
    global $db;
    $accounts = ['1001','1002','1012'];
    $accstr = "'" . implode("','", $accounts) . "'";
    $date_from = $col == 'year' ? $date1 : $month1;
    $date_to = $col == 'year' ? $date2 : $month2;

    if (!empty($row['opposite_accounts'])) {
        $oppstr = "'" . implode("','", $row['opposite_accounts']) . "'";
        if ($row['type'] == 'in') {
            $sql = "SELECT SUM(vi.debit) FROM voucher_items vi 
                JOIN voucher_items vj ON vi.voucher_id = vj.voucher_id 
                JOIN vouchers v ON v.id = vi.voucher_id
                WHERE vi.account_code IN ($accstr) AND vi.debit > 0
                    AND vj.account_code IN ($oppstr)
                    AND v.date >= ? AND v.date <= ?";
        } else {
            $sql = "SELECT SUM(vi.credit) FROM voucher_items vi 
                JOIN voucher_items vj ON vi.voucher_id = vj.voucher_id 
                JOIN vouchers v ON v.id = vi.voucher_id
                WHERE vi.account_code IN ($accstr) AND vi.credit > 0
                    AND vj.account_code IN ($oppstr)
                    AND v.date >= ? AND v.date <= ?";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$date_from, $date_to]);
        return floatval($stmt->fetchColumn());
    }
    elseif (!empty($row['exclude_opposite_accounts'])) {
        $oppstr = "'" . implode("','", $row['exclude_opposite_accounts']) . "'";
        if ($row['type'] == 'in') {
            $sql = "SELECT SUM(vi.debit) FROM voucher_items vi
                    JOIN vouchers v ON v.id = vi.voucher_id
                    WHERE vi.account_code IN ($accstr) AND vi.debit > 0
                        AND v.date >= ? AND v.date <= ?
                        AND NOT EXISTS (
                            SELECT 1 FROM voucher_items vj
                            WHERE vj.voucher_id = vi.voucher_id
                                AND vj.account_code IN ($oppstr)
                        )";
        } else {
            $sql = "SELECT SUM(vi.credit) FROM voucher_items vi
                    JOIN vouchers v ON v.id = vi.voucher_id
                    WHERE vi.account_code IN ($accstr) AND vi.credit > 0
                        AND v.date >= ? AND v.date <= ?
                        AND NOT EXISTS (
                            SELECT 1 FROM voucher_items vj
                            WHERE vj.voucher_id = vi.voucher_id
                                AND vj.account_code IN ($oppstr)
                        )";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$date_from, $date_to]);
        return floatval($stmt->fetchColumn());
    }
    return 0.0;
}

// 期间参数（月末模式）
if (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
    $month = $_GET['month'];
} else {
    $month = date('Y-m');
}
// 本月1号、本月最后一天
$month1 = date('Y-m-01', strtotime($month . '-01'));
$month2 = date('Y-m-t', strtotime($month . '-01'));
// 本年1号、本月最后一天
$date1 = date('Y-01-01', strtotime($month . '-01'));
$date2 = $month2;

// 生成数据
function calc_cashflow_row($row, $col, $date1, $date2, $month1, $month2, &$cache) {
    if (!empty($row['formula'])) {
        $exp = $row['formula'];
        preg_match_all('/\((\d+)\)/', $exp, $m);
        $nums = $m[1];
        foreach($nums as $n) {
            $val = isset($cache[$n][$col]) ? $cache[$n][$col] : 0.0;
            $exp = preg_replace('/\('.$n.'\)/', $val, $exp, 1);
        }
        $exp = str_replace(' ', '', $exp);
        if ($exp === '' || $exp === ';' || $exp === 'return ;' || !preg_match('/[0-9]/', $exp)) return 0.0;
        if (!preg_match('/^[\d\.\+\-\*\/\(\)]+$/', $exp)) return 0.0;
        $result = @eval("return $exp;");
        return $result;
    } elseif (!empty($row['custom_balance'])) {
        if ($row['custom_balance']=='start') {
            $dt = $col=='year' ? date('Y-m-d', strtotime("$date1 -1 day")) : date('Y-m-d', strtotime("$month1 -1 day"));
            return get_cash_balance($dt);
        }
    } elseif (!empty($row['type'])) {
        return get_cashflow_amount($row, $col, $date1, $date2, $month1, $month2);
    }
    return '';
}

// 页面
include 'templates/header.php';
?>
<h2>现金流量表</h2>
<form>
    会计期间：<input type="month" name="month" value="<?=htmlspecialchars($month)?>">
    <button class="btn" type="submit">查询</button>
</form>
<table border="1" cellspacing="0" cellpadding="4" style="width:100%;border-collapse:collapse;font-size:16px;">
    <tr style="background:#f6f6f6;">
        <th style="width:38%;">项目</th>
        <th style="width:10%;">行次</th>
        <th style="width:26%;">本年累计金额</th>
        <th style="width:26%;">本月金额</th>
    </tr>
<?php
$cache = [];
foreach($cashflow_config as $row) {
    if (!empty($row['group'])) {
        echo "<tr><td colspan='4' style='font-weight:bold;'>{$row['group']}</td></tr>";
        continue;
    }
    $year_val = calc_cashflow_row($row, 'year', $date1, $date2, $month1, $month2, $cache);
    $month_val = calc_cashflow_row($row, 'month', $date1, $date2, $month1, $month2, $cache);
    $cache[$row['idx']] = ['year'=>$year_val, 'month'=>$month_val];
    $is_formula = isset($row['formula']) || in_array($row['idx'],[7,13,19,20,22]);
    $show_year = $year_val!=='' ? number_format($year_val,2) : '';
    $show_month= $month_val!=='' ? number_format($month_val,2) : '';
    $bold = $is_formula ? "font-weight:bold;" : "";
    $pad = (in_array($row['idx'],[4,21,22])) ? "padding-left:2em;" : "";
    echo "<tr style='$bold'>";
    echo "<td style='$pad'>{$row['label']}</td>";
    echo "<td>".($row['idx']??'')."</td>";
    echo "<td align='right'>$show_year</td>";
    echo "<td align='right'>$show_month</td>";
    echo "</tr>";
}
?>
</table>
<br>
<?php include 'templates/footer.php'; ?>