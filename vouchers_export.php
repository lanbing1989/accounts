<?php
require_once 'inc/functions.php';
require_once 'vendor/autoload.php';
checkLogin();
global $db;
session_start();

$book = getCurrentBook();
$book_id = $book ? $book['id'] : 0;

// 期间过滤
if(isset($_GET['period']) && preg_match('/^(\d{4})(\d{2})$/', $_GET['period'], $m)){
    $year = intval($m[1]); $month = intval($m[2]);
    $date1 = sprintf('%04d-%02d-01', $year, $month);
    $date2 = date('Y-m-t', strtotime($date1));
    $where = "AND v.book_id=? AND v.date>=? AND v.date<=?";
    $params = [$book_id, $date1, $date2];
}else{
    $where = "AND v.book_id=?";
    $params = [$book_id];
}

$sql = "SELECT v.id, v.date, v.number, vi.summary, vi.account_code, vi.debit, vi.credit
        FROM vouchers v
        JOIN voucher_items vi ON v.id=vi.voucher_id
        WHERE 1 $where
        ORDER BY v.date, v.number, v.id, vi.id";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->fromArray(['日期','号','摘要','科目编号','借/贷','金额'],NULL,'A1');
$rowid = 2;
foreach($rows as $r){
    $dc = $r['debit']>0 ? '借' : '贷';
    $amt = $r['debit']>0 ? $r['debit'] : $r['credit'];
    $sheet->setCellValue("A{$rowid}", $r['date']);
    $sheet->setCellValue("B{$rowid}", $r['number']);
    $sheet->setCellValue("C{$rowid}", $r['summary']);
    $sheet->setCellValue("D{$rowid}", $r['account_code']);
    $sheet->setCellValue("E{$rowid}", $dc);
    $sheet->setCellValue("F{$rowid}", $amt);
    $rowid++;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="voucher_export.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;