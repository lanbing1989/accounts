<?php
require_once 'inc/functions.php';
checkLogin();
$users = getUsers();
include 'templates/header.php';
?>
<h2>用户列表</h2>
<table>
    <tr><th>ID</th><th>用户名</th><th>角色</th></tr>
    <?php foreach($users as $u): ?>
        <tr>
            <td><?=$u['id']?></td>
            <td><?=$u['username']?></td>
            <td><?=$u['role']?></td>
        </tr>
    <?php endforeach;?>
</table>
<?php include 'templates/footer.php'; ?>