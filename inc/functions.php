<?php
require_once __DIR__ . '/db.php';

function checkLogin() {
    if (empty($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

// 科目
function getAccounts() {
    global $db;
    $stmt = $db->query("SELECT * FROM accounts ORDER BY code");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getAccountByCode($code) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM accounts WHERE code=?");
    $stmt->execute([$code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 用户
function getUserByName($username) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM users WHERE username=?");
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function getUsers() {
    global $db;
    $stmt = $db->query("SELECT id, username, role FROM users ORDER BY id");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 凭证
function getVouchers() {
    global $db;
    $stmt = $db->query("SELECT * FROM vouchers ORDER BY date DESC, id DESC");
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // 附加items字段（可选）
    foreach ($list as &$v) {
        $v['items'] = getVoucherItems($v['id']);
    }
    return $list;
}
function getVoucherItems($voucher_id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM voucher_items WHERE voucher_id=?");
    $stmt->execute([$voucher_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getVoucher($voucher_id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM vouchers WHERE id=?");
    $stmt->execute([$voucher_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function addVoucher($date, $desc, $items) {
    global $db;
    $db->beginTransaction();
    $db->prepare("INSERT INTO vouchers (date, description, user_id) VALUES (?, ?, ?)")
        ->execute([$date, $desc, $_SESSION['user_id']]);
    $vid = $db->lastInsertId();
    $vi = $db->prepare("INSERT INTO voucher_items (voucher_id, account_code, debit, credit, summary) VALUES (?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $vi->execute([$vid, $item['account_code'], $item['debit'], $item['credit'], $item['summary']]);
    }
    $db->commit();
    return $vid;
}

// 修改凭证
function updateVoucher($id, $date, $desc, $items) {
    global $db;
    $db->beginTransaction();
    $db->prepare("UPDATE vouchers SET date=?, description=? WHERE id=?")
        ->execute([$date, $desc, $id]);
    // 删除原明细
    $db->prepare("DELETE FROM voucher_items WHERE voucher_id=?")->execute([$id]);
    // 插入新明细
    $vi = $db->prepare("INSERT INTO voucher_items (voucher_id, account_code, debit, credit, summary) VALUES (?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $vi->execute([$id, $item['account_code'], $item['debit'], $item['credit'], $item['summary']]);
    }
    $db->commit();
}

// 删除凭证
function deleteVoucher($id) {
    global $db;
    $db->beginTransaction();
    $db->prepare("DELETE FROM voucher_items WHERE voucher_id=?")->execute([$id]);
    $db->prepare("DELETE FROM vouchers WHERE id=?")->execute([$id]);
    $db->commit();
}

// 账簿
function getLedger($account_code, $date1 = null, $date2 = null) {
    global $db;
    $sql = "SELECT v.date, vi.summary, vi.debit, vi.credit FROM voucher_items vi JOIN vouchers v ON v.id=vi.voucher_id WHERE vi.account_code = ?";
    $params = [$account_code];
    if ($date1) {
        $sql .= " AND v.date>=?";
        $params[] = $date1;
    }
    if ($date2) {
        $sql .= " AND v.date<=?";
        $params[] = $date2;
    }
    $sql .= " ORDER BY v.date, vi.id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>