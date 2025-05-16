<?php
require_once 'inc/functions.php';
checkLogin();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    header("Location: settings.php");
    exit;
}

global $db;
// 防止删除admin账户
$stmt = $db->prepare("SELECT username FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: settings.php");
    exit;
}

if ($user['username'] === 'admin') {
    echo "<div style='color:red;text-align:center;margin-top:40px'>admin账户禁止删除！<br><a href='settings.php'>返回</a></div>";
    exit;
}

// 执行删除
$stmt = $db->prepare("DELETE FROM users WHERE id=?");
$stmt->execute([$id]);

header("Location: settings.php");
exit;
?>