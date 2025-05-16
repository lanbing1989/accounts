<?php
require_once 'inc/functions.php';
checkLogin();

if (!defined('DB_FILE') || !is_file(DB_FILE)) {
    die("找不到数据库文件：" . DB_FILE);
}

$backup_name = "backup_" . date("Ymd_His") . ".sqlite";
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.$backup_name.'"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize(DB_FILE));
readfile(DB_FILE);
exit;
?>