<?php
require_once 'inc/functions.php';
checkLogin();
global $db;

// 资产配置
$asset_config = [
    ['label'=>'流动资产:',               'idx'=>'', 'accounts'=>[]],
    ['label'=>'库存现金',                'idx'=>1,  'accounts'=>['1001']],
    ['label'=>'银行存款',                'idx'=>2,  'accounts'=>['1002']],
    ['label'=>'短期投资',                'idx'=>3,  'accounts'=>['1101']],
    ['label'=>'应收票据',                'idx'=>4,  'accounts'=>['1121']],
    ['label'=>'应收账款',                'idx'=>5,  'accounts'=>['1122'], 'balance_type'=>'debit'],
    ['label'=>'预付账款',                'idx'=>6,  'accounts'=>['1123'], 'balance_type'=>'debit'],
    ['label'=>'应收利息',                'idx'=>7,  'accounts'=>['1132']],
    ['label'=>'其他应收款',              'idx'=>8,  'accounts'=>['1221']],
    ['label'=>'存货',                    'idx'=>9,  'accounts'=>['1401','1402','1403','1404','1405','1407','1408','1411','1421']],
    ['label'=>'　其中：原材料',           'idx'=>10, 'accounts'=>['1403']],
    ['label'=>'　　　在产品',             'idx'=>11, 'accounts'=>['1408']],
    ['label'=>'　　　库存商品',           'idx'=>12, 'accounts'=>['1405']],
    ['label'=>'　　　周转材料',           'idx'=>13, 'accounts'=>['1411']],
    ['label'=>'其他流动资产',             'idx'=>14, 'accounts'=>[]],
    ['label'=>'流动资产合计',             'idx'=>15, 'formula'=>'(2)+(3)+(4)+(5)+(6)+(7)+(8)+(9)+(14)'],
    ['label'=>'非流动资产:',              'idx'=>'', 'accounts'=>[]],
    ['label'=>'长期债券投资',             'idx'=>16, 'accounts'=>['1501']],
    ['label'=>'长期股权投资',             'idx'=>17, 'accounts'=>['1511']],
    ['label'=>'固定资产原价',             'idx'=>18, 'accounts'=>['1601']],
    ['label'=>'减：累计折旧',             'idx'=>19, 'accounts'=>['1602'], 'is_minus'=>true],
    ['label'=>'固定资产账面价值',         'idx'=>20, 'formula'=>'(18)-(19)'],
    ['label'=>'在建工程',                 'idx'=>21, 'accounts'=>['1604']],
    ['label'=>'工程物资',                 'idx'=>22, 'accounts'=>['1605']],
    ['label'=>'固定资产清理',             'idx'=>23, 'accounts'=>['1606']],
    ['label'=>'生产性生物资产',           'idx'=>24, 'accounts'=>['1621','1622']],
    ['label'=>'无形资产',                 'idx'=>25, 'accounts'=>['1701','1702']],
    ['label'=>'开发支出',                 'idx'=>26, 'accounts'=>['4301']],
    ['label'=>'长期待摊费用',             'idx'=>27, 'accounts'=>['1801']],
    ['label'=>'其他非流动资产',           'idx'=>28, 'accounts'=>[]],
    ['label'=>'非流动资产合计',           'idx'=>29, 'formula'=>'(16)+(17)+(20)+(21)+(22)+(23)+(24)+(25)+(26)+(27)+(28)'],
    ['label'=>'资产总计',                 'idx'=>30, 'formula'=>'(15)+(29)'],
];

// 负债和所有者权益配置
$liab_config = [
    ['label'=>'流动负债:',               'idx'=>'', 'accounts'=>[]],
    ['label'=>'短期借款',                'idx'=>31, 'accounts'=>['2001']],
    ['label'=>'应付票据',                'idx'=>32, 'accounts'=>['2201']],
    ['label'=>'应付账款',                'idx'=>33, 'accounts'=>['2202'], 'balance_type'=>'credit'],
    ['label'=>'预收账款',                'idx'=>34, 'accounts'=>['2203'], 'balance_type'=>'credit'],
    ['label'=>'应付职工薪酬',            'idx'=>35, 'accounts'=>['2211']],
    ['label'=>'应交税费',                'idx'=>36, 'accounts'=>['2221'], 'balance_type'=>'credit'],
    ['label'=>'应付利息',                'idx'=>37, 'accounts'=>['2231']],
    ['label'=>'应付利润',                'idx'=>38, 'accounts'=>['2232']],
    ['label'=>'其他应付款',              'idx'=>39, 'accounts'=>['2241']],
    ['label'=>'其他流动负债',            'idx'=>40, 'accounts'=>[]],
    ['label'=>'流动负债合计',            'idx'=>41, 'formula'=>'(31)+(32)+(33)+(34)+(35)+(36)+(37)+(38)+(39)+(40)'],
    ['label'=>'非流动负债:',             'idx'=>'', 'accounts'=>[]],
    ['label'=>'长期借款',                'idx'=>42, 'accounts'=>['2501']],
    ['label'=>'长期应付款',              'idx'=>43, 'accounts'=>['2701']],
    ['label'=>'递延收益',                'idx'=>44, 'accounts'=>['2401']],
    ['label'=>'其他非流动负债',          'idx'=>45, 'accounts'=>[]],
    ['label'=>'非流动负债合计',          'idx'=>46, 'formula'=>'(42)+(43)+(44)+(45)'],
    ['label'=>'负债合计',                'idx'=>47, 'formula'=>'(41)+(46)'],
    ['label'=>'所有者权益:',             'idx'=>'', 'accounts'=>[]],
    ['label'=>'实收资本（或股本）',      'idx'=>48, 'accounts'=>['3001']],
    ['label'=>'资本公积',                'idx'=>49, 'accounts'=>['3002']],
    ['label'=>'盈余公积',                'idx'=>50, 'accounts'=>['3101']],
    ['label'=>'未分配利润',              'idx'=>51, 'special'=>'unallocated_profit'],
    ['label'=>'所有者权益合计',          'idx'=>52, 'formula'=>'(48)+(49)+(50)+(51)'],
    ['label'=>'负债和所有者权益合计',    'idx'=>53, 'formula'=>'(47)+(52)'],
];

// 查询科目余额
function get_account_balance($accounts, $date, $is_minus = false, $balance_type = null) {
    global $db;
    if (!$accounts) return 0.0;
    $sum = 0.0;
    foreach($accounts as $code) {
        $category = $db->query("SELECT category FROM accounts WHERE code='$code'")->fetchColumn();
        $sql = ($category=='资产' || $category=='成本') ?
            "SELECT SUM(debit)-SUM(credit) FROM voucher_items vi JOIN vouchers v ON v.id=vi.voucher_id WHERE vi.account_code=? AND v.date<=?" :
            "SELECT SUM(credit)-SUM(debit) FROM voucher_items vi JOIN vouchers v ON v.id=vi.voucher_id WHERE vi.account_code=? AND v.date<=?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$code, $date]);
        $val = floatval($stmt->fetchColumn());
        if ($balance_type == 'debit' && $val < 0) $val = 0;
        if ($balance_type == 'credit' && $val > 0) $val = 0;
        if ($is_minus) $val = -$val;
        $sum += $val;
    }
    return $sum;
}

// 查询本年净利润（兼容SQLite，分步归集）
function get_net_profit($date) {
    global $db;
    $year_start = date('Y-01-01', strtotime($date));
    $income = $db->query("SELECT SUM(credit)-SUM(debit) FROM voucher_items vi 
        JOIN vouchers v ON v.id=vi.voucher_id 
        WHERE vi.account_code IN ('5001','5051','6001','6051') AND v.date>='$year_start' AND v.date<='$date'")->fetchColumn();
    $expense = $db->query("SELECT SUM(debit)-SUM(credit) FROM voucher_items vi 
        JOIN vouchers v ON v.id=vi.voucher_id 
        WHERE vi.account_code LIKE '6%' AND v.date>='$year_start' AND v.date<='$date'")->fetchColumn();
    $income = floatval($income);
    $expense = floatval($expense);
    return $income - $expense;
}

// 查询利润分配（分红等，贷方发生额计为正）
function get_profit_distributed($date) {
    global $db;
    $year_start = date('Y-01-01', strtotime($date));
    $sql = "SELECT SUM(vi.credit) FROM voucher_items vi 
        JOIN vouchers v ON v.id=vi.voucher_id
        WHERE vi.account_code IN ('2232','3104') AND v.date>=? AND v.date<=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$year_start, $date]);
    return floatval($stmt->fetchColumn());
}

// 期间参数（月末模式）
if (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
    $month = $_GET['month'];
} else {
    $month = date('Y-m');
}
// 自动取月末
$today = date('Y-m-t', strtotime($month . '-01'));
$year = date('Y', strtotime($today));
$year_start = "$year-01-01";
$last_year = date('Y-m-d', strtotime("$year_start -1 day"));

// 计算每行（资产/负债权益）值
function calc_balance_row($row, $col, $today, $last_year, &$cache) {
    if (!empty($row['special']) && $row['special']=='unallocated_profit') {
        $date = $col=='end' ? $today : $last_year;
        $begin_unallocated = get_account_balance(['3104'], $last_year);
        $net_profit = get_net_profit($date);
        $profit_distributed = get_profit_distributed($date);
        if ($col=='end') {
            $val = $begin_unallocated + $net_profit - $profit_distributed;
        } else {
            $val = $begin_unallocated;
        }
        return $val;
    }
    if (!empty($row['formula'])) {
        preg_match_all('/\((\d+)\)/', $row['formula'], $m);
        $nums = $m[1];
        $exp = $row['formula'];
        foreach($nums as $n) {
            // 缺失行号用0兜底
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
        $val = get_account_balance($row['accounts'], $date, $row['is_minus']??false, $row['balance_type']??null);
        return $val;
    } else {
        return '';
    }
}

// 组装所有颗粒度对齐的行
$maxlen = max(count($asset_config), count($liab_config));
$rows = [];
$cache = [];
for($i=0; $i<$maxlen; $i++) {
    $a = $asset_config[$i] ?? ['label'=>'','idx'=>''];
    $l = $liab_config[$i] ?? ['label'=>'','idx'=>''];
    $end_a = $a['label']!=='' ? calc_balance_row($a, 'end', $today, $last_year, $cache) : '';
    $start_a = $a['label']!=='' ? calc_balance_row($a, 'start', $today, $last_year, $cache) : '';
    if (isset($a['idx']) && $a['idx']!=='') $cache[$a['idx']] = ['end'=>$end_a,'start'=>$start_a];
    $end_l = $l['label']!=='' ? calc_balance_row($l, 'end', $today, $last_year, $cache) : '';
    $start_l = $l['label']!=='' ? calc_balance_row($l, 'start', $today, $last_year, $cache) : '';
    if (isset($l['idx']) && $l['idx']!=='') $cache[$l['idx']] = ['end'=>$end_l,'start'=>$start_l];
    $rows[] = [
        $a['label']??'', $a['idx']??'', $end_a, $start_a,
        $l['label']??'', $l['idx']??'', $end_l, $start_l
    ];
}

// 页面
include 'templates/header.php';
?>
<h2>资产负债表</h2>
<form>
    会计期间：<input type="month" name="month" value="<?=htmlspecialchars($month)?>">
    <button class="btn" type="submit">查询</button>
</form>
<table border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;font-size:15px;width:100%;">
    <tr style="background:#f6f6f6;">
        <th style="width:20%;">资产</th>
        <th style="width:5%;">行次</th>
        <th style="width:12%;">期末余额</th>
        <th style="width:12%;">年初余额</th>
        <th style="width:20%;">负债和所有者权益</th>
        <th style="width:5%;">行次</th>
        <th style="width:12%;">期末余额</th>
        <th style="width:12%;">年初余额</th>
    </tr>
<?php foreach($rows as $row): ?>
    <tr>
        <td><?= htmlspecialchars($row[0]) ?></td>
        <td align="center"><?= $row[1] ?></td>
        <td align="right"><?= $row[2]!=='' ? number_format($row[2],2) : '' ?></td>
        <td align="right"><?= $row[3]!=='' ? number_format($row[3],2) : '' ?></td>
        <td><?= htmlspecialchars($row[4]) ?></td>
        <td align="center"><?= $row[5] ?></td>
        <td align="right"><?= $row[6]!=='' ? number_format($row[6],2) : '' ?></td>
        <td align="right"><?= $row[7]!=='' ? number_format($row[7],2) : '' ?></td>
    </tr>
<?php endforeach; ?>
</table>
<?php include 'templates/footer.php'; ?>