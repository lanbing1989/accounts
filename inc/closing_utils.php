<?php
require_once __DIR__ . '/functions.php';

/**
 * 判断某账套某年某月是否已结账
 */
function isMonthClosed($book_id, $year, $month) {
    global $db;
    $stmt = $db->prepare("SELECT 1 FROM closings WHERE book_id = ? AND year = ? AND month = ?");
    $stmt->execute([$book_id, $year, $month]);
    return $stmt->fetchColumn() ? true : false;
}

/**
 * 检查本账套本月借贷是否平衡
 */
function trialBalance($book_id, $year, $month) {
    global $db;
    $sql = "SELECT SUM(vi.debit) AS total_debit, SUM(vi.credit) AS total_credit
            FROM voucher_items vi
            JOIN vouchers v ON vi.voucher_id = v.id
            WHERE v.book_id = ? AND strftime('%Y', v.date) = ? AND strftime('%m', v.date) = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$book_id, $year, sprintf('%02d', $month)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return abs(floatval($row['total_debit']) - floatval($row['total_credit'])) < 0.01;
}

/**
 * 生成结转损益分录
 * - 所有损益类科目（无论方向、正负余额）都结转
 * - 余额为正：借本年利润，贷损益科目
 * - 余额为负：借损益科目，贷本年利润
 * - 借贷金额都为正数
 * - 本年利润分录始终在最前
 */
function generateProfitAndLossEntries($book_id, $year, $month) {
    global $db;
    $accounts = $db->prepare("SELECT code, direction FROM accounts WHERE book_id = ? AND category = '损益'");
    $accounts->execute([$book_id]);
    $accounts = $accounts->fetchAll(PDO::FETCH_ASSOC);

    $detail_items = [];
    $profit = 0;

    foreach ($accounts as $acc) {
        $sql = "SELECT SUM(vi.debit) AS deb, SUM(vi.credit) AS cre
                FROM voucher_items vi
                JOIN vouchers v ON v.id = vi.voucher_id
                WHERE v.book_id = ? AND vi.account_code = ? AND strftime('%Y', v.date) = ? AND strftime('%m', v.date) = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$book_id, $acc['code'], $year, sprintf('%02d', $month)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $debit = floatval($row['deb']);
        $credit = floatval($row['cre']);
        $balance = $debit - $credit;

        if (abs($balance) > 0.00001) {
            if ($balance > 0) {
                // 余额为正：借本年利润，贷损益科目
                $detail_items[] = [
                    'summary'      => '结转本月损益',
                    'account_code' => $acc['code'],
                    'debit'        => 0,
                    'credit'       => abs($balance)
                ];
                $profit -= abs($balance);
            } else {
                // 余额为负：借损益科目，贷本年利润
                $detail_items[] = [
                    'summary'      => '结转本月损益',
                    'account_code' => $acc['code'],
                    'debit'        => abs($balance),
                    'credit'       => 0
                ];
                $profit += abs($balance);
            }
        }
    }
    // 本年利润分录置首
    $voucher_items = [];
    if (abs($profit) > 0.00001) {
        if ($profit > 0) {
            // 贷方余额，贷：本年利润
            $voucher_items[] = [
                'summary'      => '结转本月损益',
                'account_code' => '3103',
                'debit'        => 0,
                'credit'       => abs($profit)
            ];
        } else {
            // 借方余额，借：本年利润
            $voucher_items[] = [
                'summary'      => '结转本月损益',
                'account_code' => '3103',
                'debit'        => abs($profit),
                'credit'       => 0
            ];
        }
    }
    // 顺序：先本年利润，再明细
    return array_merge($voucher_items, $detail_items);
}

/**
 * 年终（12月）结转本年利润到利润分配-未分配利润
 * - 只在结账12月时调用
 * - 本年利润余额为正：借本年利润，贷利润分配-未分配利润
 * - 本年利润余额为负：借利润分配-未分配利润，贷本年利润
 */
function generateYearProfitDistributionEntries($book_id, $year) {
    global $db;
    // 查询12月“本年利润”余额
    $sql = "SELECT SUM(vi.debit) AS deb, SUM(vi.credit) AS cre
            FROM voucher_items vi
            JOIN vouchers v ON v.id = vi.voucher_id
            WHERE v.book_id = ? AND vi.account_code = '3103' AND strftime('%Y', v.date) = ? AND strftime('%m', v.date) = '12'";
    $stmt = $db->prepare($sql);
    $stmt->execute([$book_id, $year]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $debit = floatval($row['deb']);
    $credit = floatval($row['cre']);
    $balance = $debit - $credit;
    $entries = [];
    if (abs($balance) > 0.00001) {
        if ($balance > 0) {
            // 借：利润分配-未分配利润，贷：本年利润
            $entries[] = [
                'summary'      => '结转本年利润',
                'account_code' => '310401',
                'debit'        => abs($balance),
                'credit'       => 0
            ];
            $entries[] = [
                'summary'      => '结转本年利润',
                'account_code' => '3103',
                'debit'        => 0,
                'credit'       => abs($balance)
            ];
        } else {
            // 借：利润分配-未分配利润，贷：本年利润
            $entries[] = [
                'summary'      => '结转本年利润',
                'account_code' => '310401',
                'debit'        => abs($balance),
                'credit'       => 0
            ];
            $entries[] = [
                'summary'      => '结转本年利润',
                'account_code' => '3103',
                'debit'        => 0,
                'credit'       => abs($balance)
            ];
        }
    }
    return $entries;
}

/**
 * 标记结账
 */
function closeMonth($book_id, $year, $month) {
    global $db;
    if (isMonthClosed($book_id, $year, $month)) return false;
    $user = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_SESSION['username'];
    $stmt = $db->prepare("INSERT INTO closings (book_id, year, month, closed_at, user_id) VALUES (?, ?, ?, datetime('now'), ?)");
    return $stmt->execute([$book_id, $year, $month, $user]);
}

/**
 * 反结账
 */
function uncloseMonth($book_id, $year, $month) {
    global $db;
    $stmt = $db->prepare("DELETE FROM closings WHERE book_id = ? AND year = ? AND month = ?");
    return $stmt->execute([$book_id, $year, $month]);
}
?>