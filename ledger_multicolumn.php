<?php
require_once 'inc/functions.php';
checkLogin();
$book = getCurrentBook();
if (!$book) { header('Location: books_add.php'); exit; }
$book_id = $book['id'];
global $db;

// 区间期间下拉
$periods = get_all_periods($book_id, $book);
$period_start = $_GET['period_start'] ?? $periods[0]['val'];
$period_end = $_GET['period_end'] ?? $periods[count($periods)-1]['val'];
$start_year = intval(substr($period_start,0,4));
$start_month = intval(substr($period_start,4,2));
$end_year = intval(substr($period_end,0,4));
$end_month = intval(substr($period_end,4,2));
$date1 = sprintf('%04d-%02d-01', $start_year, $start_month);
$date2 = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $end_year, $end_month)));

// 科目列表和筛选
$accounts = getAccounts($book_id);
$account_code = $_GET['account_code'] ?? '';
$account_obj = null;
foreach($accounts as $a) {
    if ($a['code'] === $account_code) {
        $account_obj = $a;
        break;
    }
}
// 明细科目列表（如辅助核算、子科目），这里简单用下级科目
$sub_accounts = [];
if ($account_obj) {
    $prefix = $account_obj['code'];
    foreach($accounts as $a) {
        if ($a['code'] !== $prefix && strpos($a['code'], $prefix) === 0) {
            $sub_accounts[] = $a;
        }
    }
}

// 查询主科目本期发生和余额
function getMultiColData($book_id, $main_code, $sub_accounts, $date1, $date2, $start_year, $start_month) {
    global $db;
    $col_codes = array_map(function($a){return $a['code'];}, $sub_accounts);
    array_unshift($col_codes, $main_code); // 主科目自己也做一列
    $result = [];
    foreach($col_codes as $code) {
        // 期初余额
        $init = getBalanceBefore($book_id, $code, $start_year, $start_month);
        // 区间发生
        $h = getHappenThisPeriod($book_id, $code, $date1, $date2);
        // 期末余额
        $row = [
            'code'=>$code,
            'init'=>$init,
            'debit'=>$h['debit'],
            'credit'=>$h['credit'],
            'end'=>null
        ];
        $is_debit = false;
        foreach($sub_accounts as $a) { if ($a['code']===$code) $is_debit = in_array($a['category'], ['资产','成本']); }
        if ($code===$main_code) {
            global $accounts;
            foreach($accounts as $a){ if($a['code']===$main_code) $is_debit = in_array($a['category'], ['资产','成本']); }
        }
        $row['end'] = $is_debit
            ? $init + $h['debit'] - $h['credit']
            : $init - $h['debit'] + $h['credit'];
        $result[$code] = $row;
    }
    return $result;
}

$multi_data = [];
if ($account_obj) {
    $multi_data = getMultiColData($book_id, $account_code, $sub_accounts, $date1, $date2, $start_year, $start_month);
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
@media (max-width: 900px) {
    .ledger-main-wrap { padding: 7px 2px;}
    .ledger-table th, .ledger-table td { font-size: 14px;}
    .ledger-table th, .ledger-table td { padding: 7px 3px;}
}
</style>
<div class="ledger-main-wrap">
    <div class="ledger-title">多栏账</div>
    <form method="get" class="ledger-form-bar">
        <label>主科目
            <select name="account_code" onchange="this.form.submit()">
                <option value="">请选择</option>
                <?php foreach($accounts as $a): ?>
                    <option value="<?=$a['code']?>" <?=$account_code==$a['code']?'selected':''?>><?=$a['code'].' '.$a['name']?></option>
                <?php endforeach;?>
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
    <?php if(!$account_obj): ?>
        <div style="color:#aaa;text-align:center;">请选择主科目</div>
    <?php else: ?>
    <div style="font-weight:bold;font-size:17px;margin:14px 0;">
        主科目：<?=htmlspecialchars($account_obj['code'].' '.$account_obj['name'])?>（<?=$account_obj['category']?>类，方向：<?=in_array($account_obj['category'], ['资产','成本'])?'借':'贷'?>）
    </div>
    <table class="ledger-table">
        <tr>
            <th>科目编码</th>
            <th>期初余额</th>
            <th>本期借方</th>
            <th>本期贷方</th>
            <th>期末余额</th>
        </tr>
        <?php foreach($multi_data as $code=>$row): 
            $nm = $code==$account_obj['code'] ? $account_obj['name'] : '';
            foreach($sub_accounts as $sa) if($sa['code']===$code) $nm = $sa['name'];
        ?>
        <tr>
            <td class="left"><?=htmlspecialchars($code.' '.$nm)?></td>
            <td><?=number_format($row['init'],2)?></td>
            <td><?=number_format($row['debit'],2)?></td>
            <td><?=number_format($row['credit'],2)?></td>
            <td><?=number_format($row['end'],2)?></td>
        </tr>
        <?php endforeach;?>
    </table>
    <?php endif; ?>
</div>
<?php include 'templates/footer.php'; ?>