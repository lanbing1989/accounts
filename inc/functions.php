<?php
define('DB_FILE', dirname(__DIR__) . '/db/accounting.db');
if (!isset($db)) {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/accounting.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

function checkLogin() {
    session_start();
    if (!isset($_SESSION['username'])) {
        header('Location: login.php');
        exit;
    }
}

function getUserByName($username) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM users WHERE username=?");
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAccounts($book_id = null) {
    global $db;
    if ($book_id === null && isset($_SESSION['book_id'])) $book_id = $_SESSION['book_id'];
    if ($book_id === null) return [];
    $stmt = $db->prepare("SELECT * FROM accounts WHERE book_id=? ORDER BY code");
    $stmt->execute([$book_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAccount($code, $book_id = null) {
    global $db;
    if ($book_id === null && isset($_SESSION['book_id'])) $book_id = $_SESSION['book_id'];
    if ($book_id === null) return null;
    $stmt = $db->prepare("SELECT * FROM accounts WHERE book_id=? AND code=?");
    $stmt->execute([$book_id, $code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getVouchers($book_id = null) {
    global $db;
    if ($book_id === null && isset($_SESSION['book_id'])) $book_id = $_SESSION['book_id'];
    if ($book_id === null) return [];
    $stmt = $db->prepare("SELECT * FROM vouchers WHERE book_id=? ORDER BY date DESC, id DESC");
    $stmt->execute([$book_id]);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($list as &$v) {
        $v['items'] = getVoucherItems($v['id']);
    }
    return $list;
}

function getVouchersByDate($date1, $date2, $book_id = null) {
    global $db;
    if ($book_id === null && isset($_SESSION['book_id'])) $book_id = $_SESSION['book_id'];
    if ($book_id === null) return [];
    $stmt = $db->prepare("SELECT * FROM vouchers WHERE book_id=? AND date>=? AND date<=? ORDER BY date DESC, id DESC");
    $stmt->execute([$book_id, $date1, $date2]);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($list as &$v) {
        $v['items'] = getVoucherItems($v['id']);
    }
    return $list;
}

function getVoucher($id, $book_id = null) {
    global $db;
    if ($book_id === null && isset($_SESSION['book_id'])) $book_id = $_SESSION['book_id'];
    if ($book_id === null) return null;
    $stmt = $db->prepare("SELECT * FROM vouchers WHERE id=? AND book_id=?");
    $stmt->execute([$id, $book_id]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($v) $v['items'] = getVoucherItems($id);
    return $v;
}

function getVoucherItems($voucher_id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM voucher_items WHERE voucher_id=? ORDER BY id ASC");
    $stmt->execute([$voucher_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/closing_utils.php';

function addVoucher($date, $desc, $items, $book_id = null) {
    global $db;
    if ($book_id === null && isset($_SESSION['book_id'])) $book_id = $_SESSION['book_id'];
    if ($book_id === null) throw new Exception("账套未指定！");
    $year = date('Y', strtotime($date));
    $month = date('n', strtotime($date));
    if (isMonthClosed($book_id, $year, $month)) {
        throw new Exception("该月份已结账，不允许新增凭证！");
    }
    $db->beginTransaction();
    // 自动编号（同一账套、同一年月内递增）
    $stmt = $db->prepare("SELECT MAX(number) FROM vouchers WHERE book_id = ? AND strftime('%Y-%m', date) = ?");
    $stmt->execute([$book_id, date('Y-m', strtotime($date))]);
    $max_number = $stmt->fetchColumn();
    $number = $max_number ? $max_number + 1 : 1;

    $stmt = $db->prepare("INSERT INTO vouchers(book_id, date, description, number, creator, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))");
    $stmt->execute([$book_id, $date, $desc, $number, $_SESSION['username']]);
    $voucher_id = $db->lastInsertId();
    $itemstmt = $db->prepare("INSERT INTO voucher_items(voucher_id, summary, account_code, debit, credit) VALUES (?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $itemstmt->execute([
            $voucher_id,
            $item['summary'],
            $item['account_code'],
            floatval($item['debit']),
            floatval($item['credit'])
        ]);
    }
    $db->commit();
    return $voucher_id;
}

function updateVoucher($id, $date, $desc, $items, $book_id = null) {
    global $db;
    if ($book_id === null && isset($_SESSION['book_id'])) $book_id = $_SESSION['book_id'];
    if ($book_id === null) throw new Exception("账套未指定！");
    $year = date('Y', strtotime($date));
    $month = date('n', strtotime($date));
    if (isMonthClosed($book_id, $year, $month)) {
        throw new Exception("该月份已结账，不允许修改凭证！");
    }
    $db->beginTransaction();
    $stmt = $db->prepare("UPDATE vouchers SET date=?, description=?, creator=?, updated_at=datetime('now') WHERE id=? AND book_id=?");
    $stmt->execute([$date, $desc, $_SESSION['username'], $id, $book_id]);
    $db->prepare("DELETE FROM voucher_items WHERE voucher_id=?")->execute([$id]);
    $itemstmt = $db->prepare("INSERT INTO voucher_items(voucher_id, summary, account_code, debit, credit) VALUES (?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $itemstmt->execute([
            $id,
            $item['summary'],
            $item['account_code'],
            floatval($item['debit']),
            floatval($item['credit'])
        ]);
    }
    $db->commit();
    return true;
}

function deleteVoucher($id, $book_id = null) {
    global $db;
    if ($book_id === null && isset($_SESSION['book_id'])) $book_id = $_SESSION['book_id'];
    if ($book_id === null) return false;
    $voucher = getVoucher($id, $book_id);
    if (!$voucher) return false;
    $year = date('Y', strtotime($voucher['date']));
    $month = date('n', strtotime($voucher['date']));
    if (isMonthClosed($book_id, $year, $month)) {
        throw new Exception("该月份已结账，不允许删除凭证！");
    }
    $db->beginTransaction();
    $db->prepare("DELETE FROM voucher_items WHERE voucher_id=?")->execute([$id]);
    $db->prepare("DELETE FROM vouchers WHERE id=? AND book_id=?")->execute([$id, $book_id]);
    $db->commit();
    return true;
}

function getUsers() {
    global $db;
    $stmt = $db->query("SELECT * FROM users ORDER BY id");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addUser($username, $password, $role) {
    global $db;
    $stmt = $db->prepare("INSERT INTO users(username, password, role) VALUES (?, ?, ?)");
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
    return $db->lastInsertId();
}

function updateUser($id, $data) {
    global $db;
    $set = [];
    $params = [];
    if (isset($data['password']) && $data['password'] !== '') {
        $set[] = "password=?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    if (isset($data['role'])) {
        $set[] = "role=?";
        $params[] = $data['role'];
    }
    if (!$set) return false;
    $params[] = $id;
    $stmt = $db->prepare("UPDATE users SET " . implode(',', $set) . " WHERE id=?");
    return $stmt->execute($params);
}

function deleteUser($id) {
    global $db;
    $stmt = $db->prepare("DELETE FROM users WHERE id=?");
    return $stmt->execute([$id]);
}

function getBooks() {
    global $db;
    $stmt = $db->query("SELECT * FROM books ORDER BY id");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function switchBook($book_id) {
    $_SESSION['book_id'] = $book_id;
}

function get_all_periods($book_id, $book) {
    global $db;
    $period_set = [];
    // 账套起始
    $start_year = intval($book['start_year']);
    $start_month = intval($book['start_month']);
    $period_set[$start_year.sprintf('%02d',$start_month)] = "{$start_year}年{$start_month}月";

    // 凭证期间
    $stmt = $db->prepare("SELECT MIN(date) as mindate, MAX(date) as maxdate FROM vouchers WHERE book_id=?");
    $stmt->execute([$book_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['mindate']) {
        $min = strtotime(substr($row['mindate'],0,7).'-01');
        $max = strtotime(substr($row['maxdate'],0,7).'-01');
        for ($t = $min; $t <= $max; $t = strtotime("+1 month", $t)) {
            $y = date('Y', $t);
            $m = date('n', $t);
            $period_set[$y.sprintf('%02d',$m)] = "{$y}年{$m}月";
        }
    }

    // 结账期间
    $stmt = $db->prepare("SELECT MIN(year) as miny, MIN(month) as minm, MAX(year) as maxy, MAX(month) as maxm FROM closings WHERE book_id=?");
    $stmt->execute([$book_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['miny']) {
        $min = strtotime("{$row['miny']}-{$row['minm']}-01");
        $max = strtotime("{$row['maxy']}-{$row['maxm']}-01");
        for ($t = $min; $t <= $max; $t = strtotime("+1 month", $t)) {
            $y = date('Y', $t);
            $m = date('n', $t);
            $period_set[$y.sprintf('%02d',$m)] = "{$y}年{$m}月";
        }
    }

    // 余额期间
    $stmt = $db->prepare("SELECT MIN(year) as miny, MIN(month) as minm, MAX(year) as maxy, MAX(month) as maxm FROM balances WHERE book_id=?");
    $stmt->execute([$book_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['miny']) {
        $min = strtotime("{$row['miny']}-{$row['minm']}-01");
        $max = strtotime("{$row['maxy']}-{$row['maxm']}-01");
        for ($t = $min; $t <= $max; $t = strtotime("+1 month", $t)) {
            $y = date('Y', $t);
            $m = date('n', $t);
            $period_set[$y.sprintf('%02d',$m)] = "{$y}年{$m}月";
        }
    }

    // 排序
    ksort($period_set);
    $all_periods = [];
    foreach($period_set as $val=>$label) {
        $all_periods[] = [
            'val'=>$val,
            'year'=>intval(substr($val,0,4)),
            'month'=>intval(substr($val,4,2)),
            'label'=>$label
        ];
    }
    return $all_periods;
}

function getBalanceBefore($book_id, $account_code, $year, $month) {
    // 查询某账套、科目在指定年月之前的最新余额
    global $db;
    $stmt = $db->prepare("SELECT amount FROM balances WHERE book_id=? AND account_code=? AND ((year < ?) OR (year = ? AND month < ?)) ORDER BY year DESC, month DESC LIMIT 1");
    $stmt->execute([$book_id, $account_code, $year, $year, $month]);
    $val = $stmt->fetchColumn();
    return $val === false ? 0 : floatval($val);
}

function getHappenThisPeriod($book_id, $account_code, $date1, $date2) {
    // 查询本期发生额（本期借方、贷方合计）
    global $db;
    $stmt = $db->prepare("SELECT SUM(vi.debit) AS debit, SUM(vi.credit) AS credit 
        FROM voucher_items vi 
        JOIN vouchers v ON vi.voucher_id = v.id 
        WHERE v.book_id=? AND vi.account_code=? AND v.date>=? AND v.date<=?");
    $stmt->execute([$book_id, $account_code, $date1, $date2]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return [
        'debit' => $row['debit'] === null ? 0 : floatval($row['debit']),
        'credit' => $row['credit'] === null ? 0 : floatval($row['credit'])
    ];
}

function getCurrentBook() {
    global $db;
    if (!isset($_SESSION['book_id'])) return null;
    $stmt = $db->prepare("SELECT * FROM books WHERE id=?");
    $stmt->execute([$_SESSION['book_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getSystemInfo() {
    global $db;
    $stmt = $db->query("SELECT k, v FROM settings");
    $arr = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $arr[$row['k']] = $row['v'];
    }
    return $arr;
}
function getAllUsers() {
    global $db;
    $stmt = $db->query("SELECT id, username, role FROM users ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>