<?php
require_once 'inc/functions.php';
checkLogin();
global $db;

// 利润表配置，含行次、公式、归集
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

// 查询损益发生额
function get_income_item_amount($accounts, $type, $date1, $date2) {
    global $db;
    if (!$accounts) return 0.0;
    $codes = implode("','", $accounts);
    $sql = "SELECT SUM(" . ($type=='credit' ? 'credit-debit' : 'debit-credit') . ") FROM voucher_items vi JOIN vouchers v ON v.id=vi.voucher_id WHERE vi.account_code IN ('$codes') AND v.date>=? AND v.date<=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$date1, $date2]);
    return floatval($stmt->fetchColumn());
}

// 期间参数（月末模式）
if (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
    $month = $_GET['month'];
} else {
    $month = date('Y-m');
}
$month1 = date('Y-m-01', strtotime($month . '-01'));
$month2 = date('Y-m-t', strtotime($month . '-01'));
$date1 = date('Y-01-01', strtotime($month . '-01'));
$date2 = $month2;

// 自动公式
function calc_income_row($row, $col, $date1, $date2, $month1, $month2, &$cache) {
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
        $res = get_income_item_amount(
            $row['accounts'],
            $row['type'] ?? 'credit',
            $col == 'year' ? $date1 : $month1,
            $col == 'year' ? $date2 : $month2
        );
        return $res;
    }
}

// 页面
include 'templates/header.php';
?>
<h2>利润表</h2>
<form>
    会计期间：<input type="month" name="month" value="<?=htmlspecialchars($month)?>">
    <button class="btn" type="submit">查询</button>
</form>
<table border="1" cellspacing="0" cellpadding="4" style="width:100%;border-collapse:collapse;font-size:16px;">
<tr>
    <th>项目</th>
    <th>行次</th>
    <th>本年累计金额</th>
    <th>本月金额</th>
</tr>
<?php
$cache = [];
foreach($income_rows as $row) {
    $year_val = calc_income_row($row, 'year', $date1, $date2, $month1, $month2, $cache);
    $month_val = calc_income_row($row, 'month', $date1, $date2, $month1, $month2, $cache);
    $cache[$row['idx']] = ['year'=>$year_val, 'month'=>$month_val];
    echo "<tr>";
    echo "<td>{$row['label']}</td>";
    echo "<td>{$row['idx']}</td>";
    echo "<td style='text-align:right'>".number_format($year_val,2)."</td>";
    echo "<td style='text-align:right'>".number_format($month_val,2)."</td>";
    echo "</tr>";
}
?>
</table>
<br>
<?php include 'templates/footer.php'; ?>