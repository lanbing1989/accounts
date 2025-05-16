<?php
require_once 'inc/functions.php';
checkLogin();
$book = getCurrentBook();
if (!$book) { header('Location: books_add.php'); exit; }
$book_id = $book['id'];
global $db;

// 1. 区间期间下拉
$periods = get_all_periods($book_id, $book);
$period_start = $_GET['period_start'] ?? $periods[0]['val'];
$period_end = $_GET['period_end'] ?? $periods[count($periods)-1]['val'];

$start_year = intval(substr($period_start,0,4));
$start_month = intval(substr($period_start,4,2));
$end_year = intval(substr($period_end,0,4));
$end_month = intval(substr($period_end,4,2));
$date1 = sprintf('%04d-%02d-01', $start_year, $start_month);
$date2 = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $end_year, $end_month)));

// 2. 获取所有科目
$accounts = getAccounts($book_id);

// 3. 查询余额表数据
$data = [];
foreach($accounts as $a) {
    $code = $a['code'];
    // 期初余额：区间起始期的前一月
    $init = getBalanceBefore($book_id, $code, $start_year, $start_month);
    // 区间发生额
    $h = getHappenThisPeriod($book_id, $code, $date1, $date2);
    // 期末余额
    $is_debit = in_array($a['category'], ['资产', '成本']);
    $end = $is_debit
        ? $init + $h['debit'] - $h['credit']
        : $init - $h['debit'] + $h['credit'];
    // 只显示有余额/发生的科目
    if (abs($init) > 1e-6 || abs($h['debit']) > 1e-6 || abs($h['credit']) > 1e-6 || abs($end) > 1e-6) {
        $data[] = [
            'code'=>$code,
            'name'=>$a['name'],
            'init'=>$init,
            'debit'=>$h['debit'],
            'credit'=>$h['credit'],
            'end'=>$end,
            'is_bold'=>($a['category']=='资产' || $a['category']=='负债')
        ];
    }
}

// 4. 明细链接拼接函数
function detail_url($code, $period_start, $period_end) {
    return "ledger_detail.php?account_code=" . urlencode($code)
        . "&period_start=" . urlencode($period_start)
        . "&period_end=" . urlencode($period_end);
}

include 'templates/header.php';
?>
<style>
.ledger-main-wrap {
    max-width: 1200px;
    margin: 36px auto;
    padding: 28px 30px;
    background: #fff;
    border-radius: 15px;
    box-shadow: 0 2px 16px #e4edfa;
}
.ledger-title {
    font-size: 24px;
    color: #2676f5;
    font-weight: bold;
    margin-bottom: 20px;
    letter-spacing: 2px;
    text-align: center;
}
.ledger-form-bar {
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 22px;
    align-items: center;
}
.ledger-form-bar label { color: #555; font-size: 15px; font-weight: bold; }
.ledger-form-bar select, .ledger-form-bar input {
    border: 1px solid #c2d2ea; border-radius: 5px; font-size: 15px; padding: 6px 12px;
}
.ledger-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
    background: #f8fcff;
    border-radius: 8px;
    box-shadow: 0 1px 4px #f0f7fa;
}
.ledger-table th, .ledger-table td {
    padding: 12px 8px;
    font-size: 15.5px;
    text-align: right;
    border-bottom: 1px solid #e6eef8;
    background: #fff;
}
.ledger-table th {
    background: #f1f6fb;
    color: #2676f5;
    font-weight: bold;
    border-bottom: 2px solid #e0eefa;
}
.ledger-table td.left, .ledger-table th.left { text-align: left; }
.ledger-table tr:last-child td { border-bottom: none; }
.ledger-table .bold td { font-weight: bold; color: #2676f5;}
.ledger-table td a.detail-link { color: #2676f5; text-decoration: underline; }
@media (max-width: 900px) {
    .ledger-main-wrap { padding: 7px 2px;}
    .ledger-table th, .ledger-table td { font-size: 14px;}
}
</style>
<div class="ledger-main-wrap">
    <div class="ledger-title">科目余额表</div>
    <form method="get" class="ledger-form-bar">
        <label>起始期间
            <select name="period_start" onchange="this.form.submit()">
                <?php foreach($periods as $p): ?>
                    <option value="<?=$p['val']?>" <?=$period_start==$p['val']?'selected':''?>><?=$p['label']?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>结束期间
            <select name="period_end" onchange="this.form.submit()">
                <?php foreach($periods as $p): ?>
                    <option value="<?=$p['val']?>" <?=$period_end==$p['val']?'selected':''?>><?=$p['label']?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" style="padding:7px 22px;border-radius:5px;background:#2676f5;color:#fff;border:none;cursor:pointer;">查询</button>
        <a href="?period_start=<?=$period_start?>&period_end=<?=$period_end?>" style="padding:7px 22px;border-radius:5px;background:#b1cfff;color:#2676f5;border:none;cursor:pointer;text-decoration:none;margin-left:10px;" onclick="window.print();return false;">打印</a>
    </form>
    <table class="ledger-table">
        <tr>
            <th class="left">科目编码</th>
            <th class="left">科目名称</th>
            <th>期初余额</th>
            <th>本期借方</th>
            <th>本期贷方</th>
            <th>期末余额</th>
        </tr>
        <?php if(empty($data)): ?>
            <tr><td colspan="6" style="color:#aaa;text-align:center;">无数据</td></tr>
        <?php else: foreach($data as $row): ?>
        <tr class="<?= $row['is_bold'] ? 'bold' : '' ?>">
            <td class="left">
                <a href="<?=detail_url($row['code'], $period_start, $period_end)?>" class="detail-link" title="查看明细账"><?=htmlspecialchars($row['code'])?></a>
            </td>
            <td class="left"><?=htmlspecialchars($row['name'])?></td>
            <td><?=number_format($row['init'],2)?></td>
            <td><?=number_format($row['debit'],2)?></td>
            <td><?=number_format($row['credit'],2)?></td>
            <td><?=number_format($row['end'],2)?></td>
        </tr>
        <?php endforeach; endif;?>
    </table>
</div>
<?php include 'templates/footer.php'; ?>