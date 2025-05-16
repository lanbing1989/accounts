<?php
require_once 'inc/functions.php';
checkLogin();
global $db;
session_start();
$book_id = $_SESSION['book_id'] ?? 0;

$code = isset($_GET['code']) ? trim($_GET['code']) : '';
if (!$code) die('参数错误');

$stmt = $db->prepare("SELECT * FROM accounts WHERE book_id=? AND code=?");
$stmt->execute([$book_id, $code]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$account) die('科目不存在');

// 不能删除有下级的科目
$stmt2 = $db->prepare("SELECT COUNT(*) FROM accounts WHERE book_id=? AND parent_code=?");
$stmt2->execute([$book_id, $code]);
if ($stmt2->fetchColumn() > 0) {
    header("Location: accounts.php?msg=haschild");
    exit;
}

// 不能删除有凭证的科目
$stmt3 = $db->prepare("SELECT COUNT(*) FROM voucher_items WHERE account_code=?");
$stmt3->execute([$code]);
if ($stmt3->fetchColumn() > 0) {
    header("Location: accounts.php?msg=hasvoucher");
    exit;
}
// 不能删除有余额的科目
$stmt4 = $db->prepare("SELECT COUNT(*) FROM balances WHERE account_code=?");
$stmt4->execute([$code]);
if ($stmt4->fetchColumn() > 0) {
    header("Location: accounts.php?msg=hasbalance");
    exit;
}

// 删除
$stmt = $db->prepare("DELETE FROM accounts WHERE book_id=? AND code=?");
$stmt->execute([$book_id, $code]);
header("Location: accounts.php?msg=delok");
exit;