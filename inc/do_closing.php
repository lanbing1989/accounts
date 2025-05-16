<?php
require_once 'inc/functions.php';
require_once 'inc/closing_utils.php';

session_start();

$year = isset($_REQUEST['year']) ? intval($_REQUEST['year']) : null;
$month = isset($_REQUEST['month']) ? intval($_REQUEST['month']) : null;

if (!$year || !$month) {
    echo json_encode(['success'=>false, 'msg'=>'缺少参数']);
    exit;
}

// 调用 closing_utils.php 中的 doClosing
$result = doClosing($year, $month);

echo json_encode($result);
exit;
?>