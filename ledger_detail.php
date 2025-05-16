<?php
require_once 'inc/functions.php';
checkLogin();
$book = getCurrentBook();
if (!$book) { header('Location: books_add.php'); exit; }
$book_id = $book['id'];
global $db;

// 获取所有区间期间，供下拉用
$periods = get_all_periods($book_id, $book);
$period_start = $_GET['period_start'] ?? $periods[0]['val'];
$period_end = $_GET['period_end'] ?? $periods[count($periods)-1]['val'];
$start_year = intval(substr($period_start,0,4));
$start_month = intval(substr($period_start,4,2));
$end_year = intval(substr($period_end,0,4));
$end_month = intval(substr($period_end,4,2));
$date1 = sprintf('%04d-%02d-01', $start_year, $start_month);
$date2 = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $end_year, $end_month)));

// 科目筛选
$accounts = getAccounts($book_id);
// 默认选中第一个资产类科目
$account_code = $_GET['account_code'] ?? ($accounts[0]['code'] ?? '');
$account_obj = null;
foreach($accounts as $a) {
    if ($a['code'] === $account_code) {
        $account_obj = $a;
        break;
    }
}

// 科目下拉渲染
function renderAccountOptions($accounts, $selected) {
    foreach($accounts as $a) {
        $sel = $selected==$a['code'] ? 'selected' : '';
        echo "<option value='".htmlspecialchars($a['code'])."' $sel>{$a['code']} {$a['name']}</option>";
    }
}

// 查询期初余额
$init_bal = 0;
$list = [];
$end_bal = 0;
$sum_debit = 0;
$sum_credit = 0;
if ($account_obj) {
    $is_debit = in_array($account_obj['category'], ['资产', '成本']);
    $init_bal = getBalanceBefore($book_id, $account_code, $start_year, $start_month);

    // 获取区间内所有分录
    $stmt = $db->prepare("SELECT v.date, v.number, v.id as voucher_id, vi.summary, vi.debit, vi.credit
        FROM voucher_items vi
        JOIN vouchers v ON vi.voucher_id = v.id
        WHERE v.book_id=? AND vi.account_code=? AND v.date>=? AND v.date<=?
        ORDER BY v.date, v.number, vi.id");
    $stmt->execute([$book_id, $account_code, $date1, $date2]);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 计算每笔后的余额
    $running_bal = $init_bal;
    foreach($list as &$row) {
        $row['pre_balance'] = $running_bal;
        $d = floatval($row['debit']);
        $c = floatval($row['credit']);
        $sum_debit += $d;
        $sum_credit += $c;
        $running_bal = $is_debit ? $running_bal + $d - $c : $running_bal - $d + $c;
        $row['balance'] = $running_bal;
    }
    unset($row);
    $end_bal = $running_bal;
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
.ledger-table .init-row td { background: #f6fbf9; color: #888; font-style: italic;}
.ledger-table .sum-row td { background: #fcf7e8; color: #b3830e; font-weight: bold;}
.ledger-table .bal-row td { background: #eafae8; color: #276c27; font-weight: bold;}
@media (max-width: 900px) {
    .ledger-main-wrap { padding: 7px 2px;}
    .ledger-table th, .ledger-table td { font-size: 14px;}
}
</style>
<div class="ledger-main-wrap">
    <div class="ledger-title">明细账</div>
    <form method="get" class="ledger-form-bar">
        <label>科目
            <select name="account_code" onchange="this.form.submit()">
                <?php renderAccountOptions($accounts, $account_code);?>
            </select>
        </label>
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
        <a href="?account_code=<?=urlencode($account_code)?>&period_start=<?=$period_start?>&period_end=<?=$period_end?>" style="padding:7px 22px;border-radius:5px;background:#b1cfff;color:#2676f5;border:none;cursor:pointer;text-decoration:none;margin-left:10px;" onclick="window.print();return false;">打印</a>
    </form>
    <?php if($account_obj): ?>
        <div style="font-weight:bold;font-size:17px;margin:14px 0;">
            科目：<?=htmlspecialchars($account_obj['code'].' '.$account_obj['name'])?>（<?=$account_obj['category']?>类，方向：<?=in_array($account_obj['category'], ['资产','成本'])?'借':'贷'?>）
        </div>
        <table class="ledger-table">
            <tr>
                <th>日期</th>
                <th>凭证号</th>
                <th class="left">摘要</th>
                <th>借方</th>
                <th>贷方</th>
                <th>余额</th>
                <th>操作</th>
            </tr>
            <tr class="init-row">
                <td colspan="5" style="text-align:right;">期初余额</td>
                <td><?=number_format($init_bal,2)?></td>
                <td></td>
            </tr>
            <?php foreach($list as $row): ?>
            <tr>
                <td><?=htmlspecialchars($row['date'])?></td>
                <td><a href="voucher_edit.php?id=<?=$row['voucher_id']?>" style="color:#2676f5;text-decoration:underline;"><?=$row['number']?></a></td>
                <td class="left"><?=htmlspecialchars($row['summary'])?></td>
                <td><?=floatval($row['debit'])?number_format($row['debit'],2):''?></td>
                <td><?=floatval($row['credit'])?number_format($row['credit'],2):''?></td>
                <td><?=number_format($row['balance'],2)?></td>
                <td>
                    <a href="voucher_edit.php?id=<?=$row['voucher_id']?>" style="color:#428bca;text-decoration:none;">查看</a>
                </td>
            </tr>
            <?php endforeach;?>
            <tr class="sum-row">
                <td colspan="3" style="text-align:right;">本期合计</td>
                <td><?=number_format($sum_debit,2)?></td>
                <td><?=number_format($sum_credit,2)?></td>
                <td></td>
                <td></td>
            </tr>
            <tr class="bal-row">
                <td colspan="5" style="text-align:right;">期末余额</td>
                <td><?=number_format($end_bal,2)?></td>
                <td></td>
            </tr>
        </table>
    <?php else: ?>
        <div style="color:#aaa;text-align:center;">请选择科目</div>
    <?php endif; ?>
</div>
<?php include 'templates/footer.php'; ?>