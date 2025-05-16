<?php
require_once 'inc/functions.php';
checkLogin();

// 导出所有表为csv压缩包
global $db;
$tables = [];
$res = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
while($row = $res->fetch(PDO::FETCH_ASSOC)) {
    $tables[] = $row['name'];
}
$tmpdir = sys_get_temp_dir() . '/exp_' . uniqid();
mkdir($tmpdir);

foreach($tables as $table) {
    $csvfile = $tmpdir . "/{$table}.csv";
    $fp = fopen($csvfile, "w");
    $rs = $db->query("SELECT * FROM {$table}");
    $cols = [];
    for ($i = 0; $i < $rs->columnCount(); $i++) {
        $meta = $rs->getColumnMeta($i);
        $cols[] = $meta['name'];
    }
    fputcsv($fp, $cols);
    while($row = $rs->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($fp, $row);
    }
    fclose($fp);
}

// 打包为zip
$zipfile = $tmpdir . ".zip";
$zip = new ZipArchive();
$zip->open($zipfile, ZipArchive::CREATE);
foreach($tables as $table) {
    $csvfile = $tmpdir . "/{$table}.csv";
    $zip->addFile($csvfile, "{$table}.csv");
}
$zip->close();

// 输出下载
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="data_export_'.date('Ymd_His').'.zip"');
header('Content-Length: ' . filesize($zipfile));
readfile($zipfile);

// 清理
foreach($tables as $table) {
    unlink($tmpdir . "/{$table}.csv");
}
rmdir($tmpdir);
unlink($zipfile);
exit;
?>