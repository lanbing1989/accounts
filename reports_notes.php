<?php
require_once 'inc/functions.php';
checkLogin();
$book = getCurrentBook();
if (!$book) { header('Location: books_add.php'); exit; }
$book_id = $book['id'];
global $db;

// 科目映射
$map = [
    '货币资金' => ['1001', '1002'],
    '应收账款' => ['1122'],
    '其他应收款' => ['1221'],
    '固定资产' => ['1601'],
    '累计折旧' => ['1602'],
    '应付账款' => ['2202'],
    '应付职工薪酬' => ['2211'],
    '应交税费' => ['2221'],
    '其他应付款' => ['2241'],
    '实收资本' => ['3001'],
    '未分配利润' => ['3103'],
];

// 获取期间
$periods = get_all_periods($book_id, $book);
$period_end = $_GET['period_end'] ?? $periods[count($periods)-1]['val'];
$period_start = $_GET['period_start'] ?? $periods[0]['val'];
$end_year = intval(substr($period_end,0,4)); $end_month = intval(substr($period_end,4,2));
$start_year = intval(substr($period_start,0,4)); $start_month = intval(substr($period_start,4,2));
$date_start = sprintf('%04d-%02d-01', $start_year, $start_month);
$date_end = date('Y-m-t', strtotime("$end_year-$end_month-01"));

// 获取期初余额
function getInitBalance($book_id, $code, $year, $month) {
    global $db;
    $stmt = $db->prepare("SELECT amount FROM balances WHERE book_id=? AND account_code=? AND year=? AND month=?");
    $stmt->execute([$book_id, $code, $year, $month]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? floatval($row['amount']) : 0.0;
}

// 获取本期发生额
function getOccur($book_id, $code, $date_start, $date_end) {
    global $db;
    $sql = "SELECT SUM(vi.debit) as dr, SUM(vi.credit) as cr
            FROM voucher_items vi
            JOIN vouchers v ON vi.voucher_id=v.id
            WHERE v.book_id=? AND vi.account_code=? AND v.date>=? AND v.date<=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$book_id, $code, $date_start, $date_end]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return [
        'debit' => floatval($row['dr']),
        'credit' => floatval($row['cr'])
    ];
}

// 查询科目类别借贷方向
function getAccountDirection($book_id, $code) {
    global $db;
    $stmt = $db->prepare("SELECT direction FROM accounts WHERE book_id=? AND code=?");
    $stmt->execute([$book_id, $code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['direction'] : '借';
}

// 查询期末余额
function getEndBalance($book_id, $code, $start_year, $start_month, $date_start, $date_end) {
    $init = getInitBalance($book_id, $code, $start_year, $start_month);
    $occur = getOccur($book_id, $code, $date_start, $date_end);
    $direction = getAccountDirection($book_id, $code);
    if ($direction == '借') {
        return $init + $occur['debit'] - $occur['credit'];
    } else {
        return $init - $occur['debit'] + $occur['credit'];
    }
}

// ================== 自动抓取 ========================
$notes = [];

// 1.货币资金
$note1 = [
    'title' => '1.货币资金',
    'headers' => ['项目', '期末余额', '期初余额'],
    'rows' => []
];
foreach(['库存现金'=>'1001','银行存款'=>'1002'] as $label=>$code) {
    $start_bal = getInitBalance($book_id, $code, $start_year, $start_month);
    $end_bal = getEndBalance($book_id, $code, $start_year, $start_month, $date_start, $date_end);
    $note1['rows'][] = [$label, number_format($end_bal,2), number_format($start_bal,2)];
}
$sum_end = 0; $sum_start = 0;
foreach($map['货币资金'] as $code) {
    $sum_start += getInitBalance($book_id, $code, $start_year, $start_month);
    $sum_end += getEndBalance($book_id, $code, $start_year, $start_month, $date_start, $date_end);
}
$note1['rows'][] = ['合计', number_format($sum_end,2), number_format($sum_start,2)];
$notes[] = $note1;

// 2.应收账款
$start_bal = getInitBalance($book_id, '1122', $start_year, $start_month);
$end_bal = getEndBalance($book_id, '1122', $start_year, $start_month, $date_start, $date_end);
$note2 = [
    'title'=>'2.应收账款',
    'headers'=>['项目','期末余额','期初余额'],
    'rows'=>[['应收账款',number_format($end_bal,2),number_format($start_bal,2)],
             ['合计',number_format($end_bal,2),number_format($start_bal,2)]]
];
$notes[] = $note2;

// 3.其他应收款
$start_bal = getInitBalance($book_id, '1221', $start_year, $start_month);
$end_bal = getEndBalance($book_id, '1221', $start_year, $start_month, $date_start, $date_end);
$note3 = [
    'title'=>'3.其他应收款',
    'headers'=>['项目','期末余额','期初余额'],
    'rows'=>[['其他应收款',number_format($end_bal,2),number_format($start_bal,2)],
             ['合计',number_format($end_bal,2),number_format($start_bal,2)]]
];
$notes[] = $note3;

// 4.固定资产
$fixed_start = getInitBalance($book_id, '1601', $start_year, $start_month);
$fixed_end = getEndBalance($book_id, '1601', $start_year, $start_month, $date_start, $date_end);
$depr_start = getInitBalance($book_id, '1602', $start_year, $start_month);
$depr_end = getEndBalance($book_id, '1602', $start_year, $start_month, $date_start, $date_end);
$note4 = [
    'title'=>'4.固定资产',
    'headers'=>['项目','期末余额','期初余额'],
    'rows'=>[
        ['固定资产',number_format($fixed_end,2),number_format($fixed_start,2)],
        ['减：累计折旧',number_format($depr_end,2),number_format($depr_start,2)],
        ['固定资产净值',number_format($fixed_end-$depr_end,2),number_format($fixed_start-$depr_start,2)]
    ]
];
$notes[] = $note4;

// 5.应付账款
$start_bal = getInitBalance($book_id, '2202', $start_year, $start_month);
$end_bal = getEndBalance($book_id, '2202', $start_year, $start_month, $date_start, $date_end);
$note5 = [
    'title'=>'5.应付账款',
    'headers'=>['项目','期末余额','期初余额'],
    'rows'=>[['应付账款',number_format($end_bal,2),number_format($start_bal,2)],
             ['合计',number_format($end_bal,2),number_format($start_bal,2)]]
];
$notes[] = $note5;

// 6.应付职工薪酬
$start_bal = getInitBalance($book_id, '2211', $start_year, $start_month);
$end_bal = getEndBalance($book_id, '2211', $start_year, $start_month, $date_start, $date_end);
$note6 = [
    'title'=>'6.应付职工薪酬',
    'headers'=>['项目','期末余额','期初余额'],
    'rows'=>[['应付职工薪酬',number_format($end_bal,2),number_format($start_bal,2)],
             ['合计',number_format($end_bal,2),number_format($start_bal,2)]]
];
$notes[] = $note6;

// 7.应交税费
$start_bal = getInitBalance($book_id, '2221', $start_year, $start_month);
$end_bal = getEndBalance($book_id, '2221', $start_year, $start_month, $date_start, $date_end);
$note7 = [
    'title'=>'7.应交税费',
    'headers'=>['项目','期末余额','期初余额'],
    'rows'=>[['应交税费',number_format($end_bal,2),number_format($start_bal,2)],
             ['合计',number_format($end_bal,2),number_format($start_bal,2)]]
];
$notes[] = $note7;

// 8.其他应付款
$start_bal = getInitBalance($book_id, '2241', $start_year, $start_month);
$end_bal = getEndBalance($book_id, '2241', $start_year, $start_month, $date_start, $date_end);
$note8 = [
    'title'=>'8.其他应付款',
    'headers'=>['项目','期末余额','期初余额'],
    'rows'=>[['其他应付款',number_format($end_bal,2),number_format($start_bal,2)],
             ['合计',number_format($end_bal,2),number_format($start_bal,2)]]
];
$notes[] = $note8;

// 9.实收资本
$start_bal = getInitBalance($book_id, '3001', $start_year, $start_month);
$end_bal = getEndBalance($book_id, '3001', $start_year, $start_month, $date_start, $date_end);
$note9 = [
    'title'=>'9.实收资本',
    'headers'=>['项目','期末余额','期初余额'],
    'rows'=>[['实收资本',number_format($end_bal,2),number_format($start_bal,2)],
             ['合计',number_format($end_bal,2),number_format($start_bal,2)]]
];
$notes[] = $note9;

// 10.未分配利润
$start_bal = getInitBalance($book_id, '3103', $start_year, $start_month);
$end_bal = getEndBalance($book_id, '3103', $start_year, $start_month, $date_start, $date_end);
$note10 = [
    'title'=>'10.未分配利润',
    'headers'=>['项目','期末余额','期初余额'],
    'rows'=>[['未分配利润',number_format($end_bal,2),number_format($start_bal,2)],
             ['合计',number_format($end_bal,2),number_format($start_bal,2)]]
];
$notes[] = $note10;

// 11.营业收入和营业成本（主营业务收入5001，主营业务成本5401）
$income = getOccur($book_id, '5001', $date_start, $date_end)['credit'];
$cost = getOccur($book_id, '5401', $date_start, $date_end)['debit'];
$note11 = [
    'title'=>'11.营业收入和营业成本',
    'headers'=>['项目','2024年度收入','2024年度成本'],
    'rows'=>[
        ['主营业务', number_format($income,2), number_format($cost,2)],
        ['合计', number_format($income,2), number_format($cost,2)]
    ]
];
$notes[] = $note11;

// 12.税金及附加（5403）
$tax = getOccur($book_id, '5403', $date_start, $date_end)['debit'];
$note12 = [
    'title'=>'12.税金及附加',
    'headers'=>['项目','2024年度'],
    'rows'=>[['税金及附加', number_format($tax,2)],
             ['合计', number_format($tax,2)]]
];
$notes[] = $note12;

// 13.管理费用（5602）
$admin = getOccur($book_id, '5602', $date_start, $date_end)['debit'];
$note13 = [
    'title'=>'13.管理费用',
    'headers'=>['项目','2024年度'],
    'rows'=>[['管理费用', number_format($admin,2)],
             ['合计', number_format($admin,2)]]
];
$notes[] = $note13;

// 14.财务费用（5603）
$finance = getOccur($book_id, '5603', $date_start, $date_end)['debit'];
$note14 = [
    'title'=>'14.财务费用',
    'headers'=>['项目','2024年度'],
    'rows'=>[['财务费用', number_format($finance,2)],
             ['合计', number_format($finance,2)]]
];
$notes[] = $note14;

// 15.营业外收入（5301）
$other_income = getOccur($book_id, '5301', $date_start, $date_end)['credit'];
$note15 = [
    'title'=>'15.营业外收入',
    'headers'=>['项目','2024年度'],
    'rows'=>[['营业外收入', number_format($other_income,2)],
             ['合计', number_format($other_income,2)]]
];
$notes[] = $note15;

// 16.营业外支出（5711）
$other_exp = getOccur($book_id, '5711', $date_start, $date_end)['debit'];
$note16 = [
    'title'=>'16.营业外支出',
    'headers'=>['项目','2024年度'],
    'rows'=>[['营业外支出', number_format($other_exp,2)],
             ['合计', number_format($other_exp,2)]]
];
$notes[] = $note16;

// 17.所得税费用（5801）
$tax_exp = getOccur($book_id, '5801', $date_start, $date_end)['debit'];
$note17 = [
    'title'=>'17.所得税费用',
    'headers'=>['项目','2024年度'],
    'rows'=>[['所得税费用', number_format($tax_exp,2)],
             ['合计', number_format($tax_exp,2)]]
];
$notes[] = $note17;

// 18.净利润（主营业务收入-主营业务成本-税金及附加-管理费用-财务费用-所得税费用+营业外收入-营业外支出）
$net_profit = $income - $cost - $tax - $admin - $finance - $tax_exp + $other_income - $other_exp;
$note18 = [
    'title'=>'18.净利润',
    'headers'=>['项目','2024年度'],
    'rows'=>[['净利润', number_format($net_profit,2)],
             ['合计', number_format($net_profit,2)]]
];
$notes[] = $note18;

// =================== 展示 ===========================
include 'templates/header.php';
?>
<style>
.notes-section {max-width:900px;margin:36px auto;background:#fff;padding:36px 32px 50px 32px;border-radius:15px;box-shadow:0 2px 16px #e4edfa;}
.notes-title {font-size:24px;color:#2676f5;font-weight:700;margin-bottom:20px;letter-spacing:2px;text-align:center;}
.notes-table {width:100%;margin-bottom:14px;border-collapse:collapse;}
.notes-table th, .notes-table td {border:1px solid #e4edfa;padding:8px 14px;font-size:15px;}
.notes-table th {background:#f3f8ff;color:#2676f5;font-weight:700;}
.notes-subtitle {font-size:17px;color:#2676f5;font-weight:600;margin:30px 0 8px 0;}
.notes-detail-title {font-size:15px;color:#666;margin-bottom:5px;margin-top:6px;}
.notes-table td {background:#fcfcff;}
@media(max-width:700px){.notes-section{padding:7px 2px;}.notes-title{font-size:18px;}}
</style>
<div class="notes-section">
    <div class="notes-title">财务报表注释</div>
    <?php foreach($notes as $note): ?>
        <div class="notes-subtitle"><?=htmlspecialchars($note['title'])?></div>
        <table class="notes-table">
            <tr>
                <?php foreach($note['headers'] as $h): ?>
                    <th><?=htmlspecialchars($h)?></th>
                <?php endforeach; ?>
            </tr>
            <?php foreach($note['rows'] as $row): ?>
                <tr>
                <?php foreach($row as $cell): ?>
                    <td><?=htmlspecialchars($cell)?></td>
                <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>
</div>
<?php include 'templates/footer.php'; ?>