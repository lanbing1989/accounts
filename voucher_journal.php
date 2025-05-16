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

// 科目筛选（可选）
$accounts = getAccounts($book_id);
$account_code = $_GET['account_code'] ?? '';
$account_condition = '';
$params = [$book_id, $date1, $date2];
if ($account_code) {
    $account_condition = "AND vi.account_code=?";
    $params[] = $account_code;
}

// 构建科目代码到中文名称的映射
$account_map = [];
foreach($accounts as $a) {
    $account_map[$a['code']] = $a['name'];
}

// 查询区间内所有凭证及分录
$stmt = $db->prepare("SELECT v.id as voucher_id, v.date, v.number, v.creator, v.description,
    vi.summary, vi.account_code, vi.debit, vi.credit
    FROM vouchers v
    JOIN voucher_items vi ON v.id=vi.voucher_id
    WHERE v.book_id=? AND v.date>=? AND v.date<=? $account_condition
    ORDER BY v.date, v.number, v.id, vi.id");
$stmt->execute($params);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
.ledger-table .voucher-row td { background: #f9fdff; font-weight:bold; color:#2676f5;}
@media (max-width: 900px) {
    .ledger-main-wrap { padding: 7px 2px;}
    .ledger-table th, .ledger-table td { font-size: 14px;}
}
</style>
<div class="ledger-main-wrap">
    <div class="ledger-title">凭证序时簿</div>
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
        <label>科目
            <select name="account_code" onchange="this.form.submit()">
                <option value="">全部</option>
                <?php foreach($accounts as $a): ?>
                    <option value="<?=$a['code']?>" <?=$account_code==$a['code']?'selected':''?>><?=$a['code'].' '.$a['name']?></option>
                <?php endforeach;?>
            </select>
        </label>
        <button type="submit" style="padding:7px 22px;border-radius:5px;background:#2676f5;color:#fff;border:none;cursor:pointer;">查询</button>
        <a href="?period_start=<?=$period_start?>&period_end=<?=$period_end?>&account_code=<?=$account_code?>" style="padding:7px 22px;border-radius:5px;background:#b1cfff;color:#2676f5;border:none;cursor:pointer;text-decoration:none;margin-left:10px;" onclick="window.print();return false;">打印</a>
    </form>
    <table class="ledger-table">
        <tr>
            <th>日期</th>
            <th>凭证号</th>
            <th class="left">摘要</th>
            <th>科目</th>
            <th>借方</th>
            <th>贷方</th>
            <th>制单人</th>
            <th>操作</th>
        </tr>
        <?php if(empty($list)): ?>
            <tr><td colspan="8" style="color:#aaa;text-align:center;">无数据</td></tr>
        <?php else:
            $last_voucher_id = null;
            foreach($list as $row):
                $is_new_voucher = $row['voucher_id'] !== $last_voucher_id;
                $last_voucher_id = $row['voucher_id'];
                $account_display = htmlspecialchars($row['account_code']);
                if (isset($account_map[$row['account_code']])) {
                    $account_display .= ' ' . htmlspecialchars($account_map[$row['account_code']]);
                }
        ?>
            <tr<?= $is_new_voucher ? ' class="voucher-row"' : '' ?>>
                <td><?=htmlspecialchars($row['date'])?></td>
                <td><a href="voucher_edit.php?id=<?=$row['voucher_id']?>" style="color:#2676f5;text-decoration:underline;"><?=$row['number']?></a></td>
                <td class="left"><?=htmlspecialchars($row['summary'])?></td>
                <td><?=$account_display?></td>
                <td><?=floatval($row['debit'])?number_format($row['debit'],2):''?></td>
                <td><?=floatval($row['credit'])?number_format($row['credit'],2):''?></td>
                <td><?=htmlspecialchars($row['creator'])?></td>
                <td>
                    <a href="voucher_edit.php?id=<?=$row['voucher_id']?>" style="color:#428bca;text-decoration:none;">查看</a>
                </td>
            </tr>
        <?php endforeach; endif;?>
    </table>
</div>
<?php include 'templates/footer.php'; ?>