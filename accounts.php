<?php
require_once 'inc/functions.php';
checkLogin();
$accounts = getAccounts();
include 'templates/header.php';
?>
<h2>会计科目表</h2>
<table>
    <tr><th>编号</th><th>名称</th><th>类别</th><th>方向</th></tr>
    <?php foreach($accounts as $a): ?>
        <tr>
            <td><?=htmlspecialchars($a['code'])?></td>
            <td><?=htmlspecialchars($a['name'])?></td>
            <td><?=htmlspecialchars($a['category'])?></td>
            <td><?=htmlspecialchars($a['direction'])?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php include 'templates/footer.php'; ?>