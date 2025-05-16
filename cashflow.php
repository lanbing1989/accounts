<?php
require_once 'inc/functions.php';
require_once 'inc/closing_utils.php'; // 集成结账校验
checkLogin();
global $db;

// ...（你的配置、函数等原样保留）...

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

// 获取结账状态
$year = date('Y', strtotime($month2));
$month_num = intval(date('n', strtotime($month2)));

if (!isMonthClosed($year, $month_num)) {
    include 'templates/header.php';
    echo '<h2>现金流量表</h2>
    <form>
        会计期间：<input type="month" name="month" value="'.htmlspecialchars($month).'">
        <button class="btn" type="submit">查询</button>
    </form>
    <div style="color:#f55;font-size:17px;margin:40px 0;">
    该期间未结账，不能查看正式报表。请先完成结账。
    </div>';
    include 'templates/footer.php';
    exit;
}

// ...（生成数据和表格渲染部分与你原代码一致）...
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