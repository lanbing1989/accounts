<?php
require_once 'inc/functions.php';
checkLogin();
$accounts = getAccounts();
$selected = $_GET['account_code'] ?? '';
$data = null;
if ($selected) $data = getLedger($selected);
include 'templates/header.php';
?>
<h2>账簿查询</h2>
<form>
    选择科目：
    <select name="account_code">
        <option value="">--请选择--</option>
        <?php foreach($accounts as $a): ?>
            <option value="<?=$a['code']?>" <?=$selected==$a['code']?'selected':''?>><?=$a['code']?> <?=$a['name']?></option>
        <?php endforeach;?>
    </select>
    <button class="btn" type="submit">查询</button>
</form>
<?php if ($data): ?>
    <h3>明细账 - <?=$selected?> <?=$accounts[array_search($selected, array_column($accounts, 'code'))]['name']?></h3>
    <table>
        <tr><th>日期</th><th>摘要</th><th>借方</th><th>贷方</th></tr>
        <?php foreach($data as $row): ?>
            <tr>
                <td><?=$row['date']?></td>
                <td><?=$row['summary']?></td>
                <td><?=number_format($row['debit'],2)?></td>
                <td><?=number_format($row['credit'],2)?></td>
            </tr>
        <?php endforeach;?>
    </table>
<?php endif; ?>
<?php include 'templates/footer.php'; ?>