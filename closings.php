<?php
require_once 'inc/functions.php';
require_once 'inc/closing_utils.php';
checkLogin();

// 获取当前账套
$book = getCurrentBook();
if (!$book) {
    header('Location: books_add.php');
    exit;
}
$book_id = intval($book['id']);
$start_year = intval($book['start_year']);
$start_month = intval($book['start_month']);

// 统一使用 global_period
if (isset($_SESSION['global_period']) && preg_match('/^(\d{4})-(\d{1,2})$/', $_SESSION['global_period'], $m)) {
    $global_year = intval($m[1]);
    $global_month = intval($m[2]);
} else {
    $global_year = 0;
    $global_month = 0;
}

// 只查当前账套的结账历史
$stmt = $db->prepare("SELECT * FROM closings WHERE book_id = ? ORDER BY year ASC, month ASC");
$stmt->execute([$book_id]);
$closings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 结账历史索引
$closings_map = [];
foreach ($closings as $c) {
    $closings_map[$c['year'].'-'.$c['month']] = $c;
}

// 默认结账期
if ($global_year && $global_month) {
    $default_year = $global_year;
    $default_month = $global_month;
} elseif (!empty($closings)) {
    $last = end($closings);
    $default_year = $last['year'];
    $default_month = $last['month'] + 1;
    if ($default_month > 12) {
        $default_year++;
        $default_month = 1;
    }
} else {
    $default_year = $book['start_year'];
    $default_month = $book['start_month'];
}

$user = getUserByName($_SESSION['username']);
if ($user['role'] !== '超级管理员' && $user['role'] !== '管理员') {
    die('无权限');
}

$msg = '';

// 允许未来结账的最大月份，比如未来6个月
$future_months = 6;
$max_closed_year = $start_year;
$max_closed_month = $start_month;
if (!empty($closings)) {
    $lastClosed = end($closings);
    $max_closed_year = $lastClosed['year'];
    $max_closed_month = $lastClosed['month'];
}
$current_year = intval(date('Y'));
$current_month = intval(date('n'));

// 计算期间列表的结束时间
if (!empty($closings)) {
    $period_end_year = $max_closed_year;
    $period_end_month = $max_closed_month + $future_months;
    $period_end_year += intval(($period_end_month - 1) / 12);
    $period_end_month = (($period_end_month - 1) % 12) + 1;
} else {
    $period_end_year = $current_year;
    $period_end_month = $current_month + $future_months;
    $period_end_year += intval(($period_end_month - 1) / 12);
    $period_end_month = (($period_end_month - 1) % 12) + 1;
}

// 生成所有会计期间
$periods = [];
$t = strtotime($start_year.'-'.$start_month.'-01');
$end = strtotime($period_end_year.'-'.$period_end_month.'-01');
while ($t <= $end) {
    $y = intval(date('Y', $t));
    $m = intval(date('n', $t));
    $isClosed = isMonthClosed($book_id, $y, $m);
    $closedAt = isset($closings_map[$y.'-'.$m]) ? $closings_map[$y.'-'.$m]['closed_at'] : null;
    $periods[] = [
        'year'=>$y,
        'month'=>$m,
        'closed'=>$isClosed,
        'closed_at'=>$closedAt
    ];
    $t = strtotime('+1 month', $t);
}

// 只能结账“最早未结账期间”
$can_close_year = null;
$can_close_month = null;
foreach ($periods as $p) {
    if (!$p['closed']) {
        $can_close_year = $p['year'];
        $can_close_month = $p['month'];
        break;
    }
}

// ========== 判断凭证和损益结转相关 ==========

// 已有结转损益凭证
function hasProfitLossVoucher($book_id, $year, $month) {
    global $db;
    $sql = "SELECT COUNT(*) FROM vouchers v
            JOIN voucher_items vi ON v.id = vi.voucher_id
            WHERE v.book_id=? AND strftime('%Y',v.date)=? AND strftime('%m',v.date)=?
                AND vi.summary LIKE '%结转本月损益%'";
    $stmt = $db->prepare($sql);
    $stmt->execute([$book_id, $year, sprintf('%02d', $month)]);
    return $stmt->fetchColumn() > 0;
}

// 新增：获取某科目本年度累计余额
function getAccountYearBalance($book_id, $account_code, $year) {
    global $db;
    $start = date('Y-01-01', strtotime("$year-01-01"));
    $end = date('Y-12-31', strtotime("$year-12-31"));
    $sql = "SELECT 
        SUM(vi.debit) as total_debit,
        SUM(vi.credit) as total_credit
        FROM vouchers v
        JOIN voucher_items vi ON v.id=vi.voucher_id
        WHERE v.book_id=? AND vi.account_code=? AND v.date>=? AND v.date<=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$book_id, $account_code, $start, $end]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return round(floatval($row['total_debit']) - floatval($row['total_credit']), 2);
}

// 判断本年利润是否已全额结转
function isYearProfitFullyTransfered($book_id, $year) {
    global $db;
    $profit_code = '3103';
    $undist_code = '310401';
    // 查看年度累计余额
    $profit_balance = getAccountYearBalance($book_id, $profit_code, $year);
    if (abs($profit_balance) <= 0.01) {
        // 已全额结转
        return true;
    }
    // 检查12月所有结转本年利润凭证的金额总和
    $sql = "SELECT vi.account_code, SUM(vi.debit) as sum_debit, SUM(vi.credit) as sum_credit
            FROM vouchers v
            JOIN voucher_items vi ON v.id = vi.voucher_id
            WHERE v.book_id=? AND strftime('%Y',v.date)=? AND strftime('%m',v.date)='12'
              AND vi.summary LIKE '%结转本年利润%'
              AND (vi.account_code=? OR vi.account_code=?)
            GROUP BY vi.account_code";
    $stmt = $db->prepare($sql);
    $stmt->execute([$book_id, $year, $profit_code, $undist_code]);
    $codes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $codes[$row['account_code']] = [
            'debit' => floatval($row['sum_debit']),
            'credit' => floatval($row['sum_credit'])
        ];
    }
    $debit_undist = isset($codes[$undist_code]) ? $codes[$undist_code]['debit'] : 0;
    $credit_profit = isset($codes[$profit_code]) ? $codes[$profit_code]['credit'] : 0;
    // 必须借未分配利润=abs(累计余额)，贷本年利润=abs(累计余额)，并且余额为0才算全额结转
    return (abs($debit_undist - abs($profit_balance)) <= 0.01)
        && (abs($credit_profit - abs($profit_balance)) <= 0.01)
        && (abs($profit_balance) <= 0.01);
}

// 获取所有损益科目
function getProfitLossAccounts($book_id) {
    global $db;
    $stmt = $db->prepare("SELECT code FROM accounts WHERE book_id=? AND category='损益'");
    $stmt->execute([$book_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
// 获取某科目本期余额
function getAccountBalance($book_id, $account_code, $year, $month) {
    global $db;
    $start = date('Y-m-01', strtotime("$year-$month-01"));
    $end = date('Y-m-t', strtotime("$year-$month-01"));
    $sql = "SELECT 
        SUM(vi.debit) as total_debit,
        SUM(vi.credit) as total_credit
        FROM vouchers v
        JOIN voucher_items vi ON v.id=vi.voucher_id
        WHERE v.book_id=? AND vi.account_code=?
        AND v.date>=? AND v.date<=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$book_id, $account_code, $start, $end]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return round(floatval($row['total_debit']) - floatval($row['total_credit']), 2);
}
// 所有损益余额是否为零
function allProfitLossAccountsCleared($book_id, $year, $month) {
    $accounts = getProfitLossAccounts($book_id);
    foreach($accounts as $code) {
        $balance = getAccountBalance($book_id, $code, $year, $month);
        if (abs($balance) > 0.01) return false;
    }
    return true;
}

// 判断是否需重新结转
function needReProfitLossVoucher($book_id, $year, $month) {
    return hasProfitLossVoucher($book_id, $year, $month) && !allProfitLossAccountsCleared($book_id, $year, $month);
}

// 删除本月结转损益凭证
function deleteProfitLossVoucher($book_id, $year, $month) {
    global $db;
    // 查询所有本月结转损益凭证ID
    $sql = "SELECT v.id FROM vouchers v
            JOIN voucher_items vi ON v.id = vi.voucher_id
            WHERE v.book_id=? AND strftime('%Y',v.date)=? AND strftime('%m',v.date)=?
            AND vi.summary LIKE '%结转本月损益%'";
    $stmt = $db->prepare($sql);
    $stmt->execute([$book_id, $year, sprintf('%02d', $month)]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        foreach ($ids as $vid) {
            $db->prepare("DELETE FROM voucher_items WHERE voucher_id=?")->execute([$vid]);
            $db->prepare("DELETE FROM vouchers WHERE id=?")->execute([$vid]);
        }
    }
}

// 判断当月凭证数量
function countVouchers($book_id, $year, $month) {
    global $db;
    $sql = "SELECT COUNT(*) FROM vouchers WHERE book_id=? AND strftime('%Y',date)=? AND strftime('%m',date)=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$book_id, $year, sprintf('%02d',$month)]);
    return $stmt->fetchColumn();
}

// 判断当月普通凭证数量（不含年度结转凭证）
function countNormalVouchers($book_id, $year, $month) {
    global $db;
    // 普通凭证定义为：没有任何分录摘要为“结转本年利润”
    $sql = "SELECT COUNT(*) FROM vouchers v
        WHERE v.book_id=? AND strftime('%Y',v.date)=? AND strftime('%m',v.date)=?
        AND NOT EXISTS (
          SELECT 1 FROM voucher_items vi
            WHERE vi.voucher_id = v.id AND vi.summary LIKE '%结转本年利润%'
        )";
    $stmt = $db->prepare($sql);
    $stmt->execute([$book_id, $year, sprintf('%02d',$month)]);
    return $stmt->fetchColumn();
}

// ========== 处理生成结转凭证/结账请求 ==========
if (isset($_POST['action'])) {
    $year = intval($_POST['year']);
    $month = intval($_POST['month']);
    if ($_POST['action'] == 'gen_pl') {
        // 生成结转损益凭证
        $voucher_items = generateProfitAndLossEntries($book_id, $year, $month);
        if (!empty($voucher_items)) {
            $_SESSION['auto_voucher_items'] = $voucher_items;
            $_SESSION['auto_voucher_date'] = date('Y-m-t', strtotime("$year-$month-01"));
            $_SESSION['auto_voucher_summary'] = '结转本月损益';
            $_SESSION['auto_voucher_book_id'] = $book_id;
            $_SESSION['auto_voucher_year'] = $year;
            $_SESSION['auto_voucher_month'] = $month;
            $_SESSION['auto_voucher_back_to_close'] = true;
            header("Location: voucher_edit.php?auto=1");
            exit;
        } else {
            $msg = "无损益类余额，无需结转";
        }
    } elseif ($_POST['action'] == 'regen_pl') {
        // 重新结转本月损益
        deleteProfitLossVoucher($book_id, $year, $month);
        $voucher_items = generateProfitAndLossEntries($book_id, $year, $month);
        if (!empty($voucher_items)) {
            $_SESSION['auto_voucher_items'] = $voucher_items;
            $_SESSION['auto_voucher_date'] = date('Y-m-t', strtotime("$year-$month-01"));
            $_SESSION['auto_voucher_summary'] = '结转本月损益';
            $_SESSION['auto_voucher_book_id'] = $book_id;
            $_SESSION['auto_voucher_year'] = $year;
            $_SESSION['auto_voucher_month'] = $month;
            $_SESSION['auto_voucher_back_to_close'] = true;
            header("Location: voucher_edit.php?auto=1");
            exit;
        } else {
            $msg = "无损益类余额，无需结转";
        }
    } elseif ($_POST['action'] == 'gen_yr') {
        // 生成本年利润年度结转凭证（全年累计余额）
        $profit_code = '3103';
        $undist_code = '310401';
        $profit_balance = getAccountYearBalance($book_id, $profit_code, $year);
        if (abs($profit_balance) > 0.01) {
            $voucher_items = [
                [
                    'summary' => '结转本年利润',
                    'account_code' => $undist_code,
                    'debit' => abs($profit_balance),
                    'credit' => 0
                ],
                [
                    'summary' => '结转本年利润',
                    'account_code' => $profit_code,
                    'debit' => 0,
                    'credit' => abs($profit_balance)
                ]
            ];
            $_SESSION['auto_voucher_items'] = $voucher_items;
            $_SESSION['auto_voucher_date'] = date('Y-m-t', strtotime("$year-12-01"));
            $_SESSION['auto_voucher_summary'] = '结转本年利润';
            $_SESSION['auto_voucher_book_id'] = $book_id;
            $_SESSION['auto_voucher_year'] = $year;
            $_SESSION['auto_voucher_month'] = 12;
            $_SESSION['auto_voucher_back_to_close'] = true;
            header("Location: voucher_edit.php?auto=1");
            exit;
        } else {
            $msg = "本年利润余额为零，无需年度结转";
        }
    } elseif ($_POST['action'] == 'close') {
        $voucher_count = countVouchers($book_id, $year, $month);
        $normal_voucher_count = countNormalVouchers($book_id, $year, $month);
        $is12 = ($month == 12);

        if ($normal_voucher_count > 0) {
            // 有普通凭证时，必须走结转损益凭证、余额清零等校验
            if (!hasProfitLossVoucher($book_id, $year, $month)) {
                $msg = "请先完成本期结转损益凭证";
            } elseif ($is12 && !isYearProfitFullyTransfered($book_id, $year)) {
                $msg = "请先完成本年利润全额年度结转凭证，且金额必须等于本年利润全年累计余额";
            } elseif (isMonthClosed($book_id, $year, $month)) {
                $msg = "本月已结账";
            } elseif (!trialBalance($book_id, $year, $month)) {
                $msg = "本月借贷不平衡，不能结账！";
            } elseif (!allProfitLossAccountsCleared($book_id, $year, $month)) {
                $msg = "损益类科目余额未清零，需重新结转损益，否则无法结账！";
            } else {
                closeMonth($book_id, $year, $month);
                $next_year = $year;
                $next_month = $month + 1;
                if ($next_month > 12) {
                    $next_year++;
                    $next_month = 1;
                }
                $_SESSION['global_period'] = $next_year . '-' . ($next_month < 10 ? '0' : '') . $next_month;
                $msg = "结账成功";
            }
        } else {
            // 无普通凭证时允许直接结账，12月需校验全年本年利润余额
            if (isMonthClosed($book_id, $year, $month)) {
                $msg = "本月已结账";
            } else {
                if ($is12) {
                    if (!isYearProfitFullyTransfered($book_id, $year)) {
                        $profit_code = '3103';
                        $profit_balance = getAccountYearBalance($book_id, $profit_code, $year);
                        $msg = "本年利润还有借贷余额（$profit_balance），请先完成本年利润全额结转（借：未分配利润310401，贷：本年利润3103），否则不能结账！";
                    } else {
                        closeMonth($book_id, $year, $month);
                        $next_year = $year + 1;
                        $next_month = 1;
                        $_SESSION['global_period'] = $next_year . '-' . ($next_month < 10 ? '0' : '') . $next_month;
                        $msg = "本月无凭证，已直接结账";
                    }
                } else {
                    closeMonth($book_id, $year, $month);
                    $next_year = $year;
                    $next_month = $month + 1;
                    if ($next_month > 12) {
                        $next_year++;
                        $next_month = 1;
                    }
                    $_SESSION['global_period'] = $next_year . '-' . ($next_month < 10 ? '0' : '') . $next_month;
                    $msg = "本月无凭证，已直接结账";
                }
            }
        }
    } elseif ($_POST['action'] == 'unclose') {
        if (isMonthClosed($book_id, $year, $month)) {
            $found = false;
            foreach ($periods as $p) {
                $py = $p['year'];
                $pm = $p['month'];
                if (!$found && $py == $year && $pm == $month) {
                    $found = true;
                }
                if ($found && $p['closed']) {
                    uncloseMonth($book_id, $py, $pm);
                }
            }
            $_SESSION['global_period'] = $year . '-' . ($month < 10 ? '0' : '') . $month;
            $msg = "反结账成功（该月及之后月份全部反结账）";
        } else {
            $msg = "本月未结账";
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ====== 中国时间显示函数 ======
function toChinaTime($datetime) {
    if (!$datetime) return '';
    $dt = new DateTime($datetime, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Asia/Shanghai'));
    return $dt->format('Y-m-d H:i:s');
}

include 'templates/header.php';
?>
<style>
.period-list-table { width:100%; border-collapse:collapse; margin-top:18px; background:#fff; }
.period-list-table th, .period-list-table td { border:1px solid #eee; padding:9px 20px; text-align:center; }
.period-list-table th { background:#f8f8fa; color:#3386f1; font-weight:normal; }
.btn-close, .btn-unclose {
    background: #3386f1;
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 5px 20px;
    cursor: pointer;
    margin-right: 5px;
    font-size: 16px;
}
.btn-unclose { background: #e55d53; }
.btn-close:disabled { background: #d7e6fa; color: #a0b5d7; cursor:not-allowed; }
.btn-unclose:disabled { background: #f5d7d7; color: #b27b7b; cursor:not-allowed; }
.btn-close:hover:not(:disabled) { background: #2564ad; }
.btn-unclose:hover:not(:disabled) { background: #b21d13; }
.period-status-closed {color:green;}
.period-status-unclosed {color:#b3830e;}
.period-list-table th, .period-list-table td { font-size: 18px; }
@media (min-width: 1100px) {
    .period-list-table th, .period-list-table td {
        padding: 10px 30px;
    }
}
</style>
<div style="max-width:1100px;margin:40px auto 0;background:#f8fafc;padding:32px 22px 22px 22px;border-radius:10px;box-shadow:0 4px 14px rgba(0,0,0,0.07);">
    <h2 style="margin-bottom:22px;">结账/反结账管理 - <?=htmlspecialchars($book['name'])?></h2>
    <?php if($msg): ?>
        <div style="padding:9px 15px;background:#e9f7e9;color:#276c27;border-radius:4px;margin-bottom:18px;"><?=htmlspecialchars($msg)?></div>
    <?php endif;?>
    <table class="period-list-table">
        <thead>
            <tr>
                <th style="width:20%;">期间</th>
                <th style="width:30%;">结账状态</th>
                <th style="width:50%;">操作</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($periods as $p):
            $is12 = $p['month'] == 12;
            $has_pl = hasProfitLossVoucher($book_id, $p['year'], $p['month']);
            $has_yr = $is12 ? isYearProfitFullyTransfered($book_id, $p['year']) : true;
            $need_re_pl = needReProfitLossVoucher($book_id, $p['year'], $p['month']);
            $voucher_count = countVouchers($book_id, $p['year'], $p['month']);
            $normal_voucher_count = countNormalVouchers($book_id, $p['year'], $p['month']);
            $can_close = ($normal_voucher_count == 0) ||
                ($has_pl && $has_yr && !$p['closed'] && allProfitLossAccountsCleared($book_id, $p['year'], $p['month']));
        ?>
            <tr>
                <td><?=htmlspecialchars($p['year'].'年'.$p['month'].'月')?></td>
                <td>
                    <?php if($p['closed']): ?>
                        <span class="period-status-closed">已结账</span>
                        <?php if($p['closed_at']): ?>
                            <span style="font-size:13px;color:#aaa;">（<?=htmlspecialchars(toChinaTime($p['closed_at']))?>）</span>
                        <?php endif;?>
                    <?php else: ?>
                        <span class="period-status-unclosed">未结账</span>
                    <?php endif;?>
                </td>
                <td>
                    <?php if($p['closed']): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="year" value="<?=$p['year']?>">
                            <input type="hidden" name="month" value="<?=$p['month']?>">
                            <button type="submit" name="action" value="unclose" class="btn-unclose"
                              onclick="return confirm('确定要反结账 <?=htmlspecialchars($p['year'].'年'.$p['month'].'月')?> 吗？\n（此操作会将该月及之后所有已结账期间全部反结账！）');">反结账</button>
                        </form>
                    <?php else: ?>
                        <?php if($p['year'] == $can_close_year && $p['month'] == $can_close_month): ?>
                            <?php if($normal_voucher_count == 0): ?>
                                <button class="btn-close" disabled>结转损益</button>
                                <?php if($is12): ?>
                                    <?php if(!$has_yr): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="year" value="<?=$p['year']?>">
                                            <input type="hidden" name="month" value="<?=$p['month']?>">
                                            <button type="submit" name="action" value="gen_yr" class="btn-close"
                                              onclick="return confirm('是否自动生成 <?=htmlspecialchars($p['year'].'年')?> 的本年利润结转凭证？');">年度结转</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:green;margin-right:10px;">已年度结转</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="year" value="<?=$p['year']?>">
                                    <input type="hidden" name="month" value="<?=$p['month']?>">
                                    <button type="submit" name="action" value="close" class="btn-close"
                                      onclick="return confirm('本月没有任何凭证，确定要结账 <?=htmlspecialchars($p['year'].'年'.$p['month'].'月')?> 吗？');"
                                    >结账</button>
                                </form>
                            <?php else: ?>
                                <?php if(!$has_pl): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="year" value="<?=$p['year']?>">
                                        <input type="hidden" name="month" value="<?=$p['month']?>">
                                        <button type="submit" name="action" value="gen_pl" class="btn-close"
                                          onclick="return confirm('是否自动生成 <?=htmlspecialchars($p['year'].'年'.$p['month'].'月')?> 的结转损益凭证？');">结转损益</button>
                                    </form>
                                <?php elseif($need_re_pl): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="year" value="<?=$p['year']?>">
                                        <input type="hidden" name="month" value="<?=$p['month']?>">
                                        <button type="submit" name="action" value="regen_pl" class="btn-close"
                                          onclick="return confirm('检测到本月损益结转凭证已失效，是否重新生成？原有结转凭证将被覆盖！');">重新结转损益</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:green;margin-right:10px;">已结转损益</span>
                                <?php endif; ?>

                                <?php if($is12): ?>
                                    <?php if(!$has_yr): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="year" value="<?=$p['year']?>">
                                            <input type="hidden" name="month" value="<?=$p['month']?>">
                                            <button type="submit" name="action" value="gen_yr" class="btn-close"
                                              onclick="return confirm('是否自动生成 <?=htmlspecialchars($p['year'].'年')?> 的本年利润结转凭证？');">年度结转</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:green;margin-right:10px;">已年度结转</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="year" value="<?=$p['year']?>">
                                    <input type="hidden" name="month" value="<?=$p['month']?>">
                                    <button type="submit" name="action" value="close" class="btn-close"
                                      <?= $can_close ? '' : 'disabled style="opacity:.6;"' ?>
                                      onclick="return confirm('确定要结账 <?=htmlspecialchars($p['year'].'年'.$p['month'].'月')?> 吗？');"
                                    >结账</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn-close" disabled>结转损益</button>
                            <?php if($is12): ?>
                                <button class="btn-close" disabled>年度结转</button>
                            <?php endif; ?>
                            <button class="btn-close" disabled>结账</button>
                        <?php endif; ?>
                    <?php endif;?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include 'templates/footer.php'; ?>