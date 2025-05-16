<?php
require_once 'inc/functions.php';
require_once 'inc/closing_utils.php'; // 结账相关
checkLogin();
global $db;

// ...（利润表配置省略，与原代码一致）...

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

// 结账校验
$year = date('Y', strtotime($month2));
$month_num = intval(date('n', strtotime($month2)));
if (!isMonthClosed($year, $month_num)) {
    include 'templates/header.php';
    echo '<h2>利润表</h2>
    <form>
        会计期间：<input type="month" name="month" value="'.htmlspecialchars($month).'">
        <button class="btn" type="submit">查询</button>
    </form>
    <div style="color:#f55;font-size:17px;margin:40px 0;">
    该期间未结账，不能查看正式报表，请先结账。
    </div>';
    include 'templates/footer.php';
    exit;
}

// ...（后续利润表渲染部分与原代码一致）...
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