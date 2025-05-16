<?php
if (session_status() == PHP_SESSION_NONE) session_start();
$user = isset($_SESSION['username']) ? $_SESSION['username'] : null;
$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$current_book = isset($_SESSION['book_id']) ? $_SESSION['book_id'] : null;

// 账套和所属期全局选择
require_once __DIR__ . '/../inc/functions.php';
$books = getBooks();
$book_opts = [];
foreach($books as $b) $book_opts[$b['id']] = $b;

$current_book_row = $current_book && isset($book_opts[$current_book]) ? $book_opts[$current_book] : null;
$global_periods = [];
if ($current_book_row) {
    // 支持扩展到未来6个月，确保推进期间后下拉可选
    $global_periods = get_all_periods($current_book_row['id'], $current_book_row, 6);
    if (!isset($_SESSION['global_period'])) {
        $_SESSION['global_period'] = $global_periods ? $global_periods[count($global_periods)-1]['val'] : '';
    }
    $current_global_period = $_SESSION['global_period'];
    // 如果推进期间后global_period不在$global_periods，则自动补进去
    if ($current_global_period && !in_array($current_global_period, array_column($global_periods, 'val'))) {
        $global_periods[] = [
            'val' => $current_global_period,
            'label' => preg_replace('/^(\d{4})-(\d{1,2})$/', '$1年$2月', $current_global_period)
        ];
    }
} else {
    $current_global_period = '';
}

// 解析期间
if (preg_match('/^(\d{4})-(\d{1,2})$/', $current_global_period, $m)) {
    $global_year = intval($m[1]);
    $global_month = intval($m[2]);
} else {
    $global_year = 0;
    $global_month = 0;
}

// 切换账套（重置账套并自动切换到该账套的最后一个期间）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_book'])) {
    $_SESSION['book_id'] = $_POST['switch_book'];
    // 查账套最新期间
    $books = getBooks();
    $new_book = $_POST['switch_book'];
    $book_row = null;
    foreach($books as $b) if ($b['id'] == $new_book) { $book_row = $b; break; }
    if ($book_row) {
        $global_periods = get_all_periods($book_row['id'], $book_row, 6);
        $_SESSION['global_period'] = $global_periods ? $global_periods[count($global_periods)-1]['val'] : '';
    } else {
        $_SESSION['global_period'] = '';
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 切换期间
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_period'])) {
    $_SESSION['global_period'] = $_POST['switch_period'];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 二级菜单定义
$second_navs = [
    'reports.php' => [
        ['text'=>'科目余额表','url'=>'/balance_sheet.php'],
        ['text'=>'总账','url'=>'/ledger.php'],
        ['text'=>'明细账','url'=>'/ledger_detail.php'],
        ['text'=>'凭证序时簿','url'=>'/voucher_journal.php'],
        ['text'=>'多栏账','url'=>'/ledger_multicolumn.php'],
    ],
    // 可为其他主页面添加二级菜单
];
$current_file = basename($_SERVER['PHP_SELF']);
$current_second_nav = $second_navs[$current_file] ?? ($second_navs['reports.php'] ?? []);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>高端会计系统</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/static/main.css">
    <style>
        body { margin:0; padding:0; font-family: "Segoe UI", "Microsoft YaHei", Arial, sans-serif; background: #f6f8fc; }
        .header-bar {
            background: linear-gradient(90deg, #2676f5 0%, #49b1f5 100%);
            color: #fff;
            padding: 0 32px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 6px rgba(38,118,245,0.08);
        }
        .header-left { font-weight: bold; font-size: 1.22em; letter-spacing: 2px; }
        .header-nav {
            display: flex;
            gap: 28px;
            margin-left: 32px;
        }
        .header-nav a {
            color: #fff;
            text-decoration: none;
            font-size: 1.04em;
            position:relative;
            padding: 4px 2px;
            opacity:0.95;
            transition: border-bottom 0.2s, opacity 0.2s;
        }
        .header-nav a:hover, .header-nav a.active {
            border-bottom: 2px solid #fff;
            opacity:1;
        }
        .header-user {
            display: flex;
            align-items: center;
            gap: 18px;
            font-size: 1em;
        }
        .header-user .user-badge {
            background: #fff;
            color: #2676f5;
            border-radius: 12px;
            padding: 3px 14px;
            margin-right: 2px;
            font-weight: bold;
            font-size: 0.98em;
            box-shadow: 0 1px 4px rgba(38,118,245,0.10);
        }
        .header-user .logout-link {
            color: #fff;
            text-decoration: underline dotted;
            margin-left: 12px;
            font-size: 0.98em;
            opacity:0.85;
            transition: opacity 0.2s;
        }
        .header-user .logout-link:hover {
            opacity:1;
            text-decoration: underline solid;
        }
        .header-toolbar {
            background: #f3f7fb;
            padding: 9px 32px 4px 32px;
            display: flex;
            align-items: center;
            gap: 24px;
            min-height: 40px;
            border-bottom: 1px solid #e8eef8;
        }
        .header-toolbar form { display: inline-block; margin:0; }
        .header-toolbar select {
            border: 1px solid #c2d2ea; border-radius: 5px; font-size: 15px; padding: 5px 14px;
            margin-right: 7px; min-width: 110px;
        }
        .header-toolbar label { color:#2676f5; font-weight:bold; font-size: 15px; margin-right:8px;}
        .header-toolbar .sep { color:#b6c7d2; margin: 0 8px;}
        .second-nav-bar {
            background: #fff;
            padding: 0 32px;
            min-height: 44px;
            border-bottom: 1px solid #e8eef8;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 6px #f6f8fc;
        }
        .second-nav-bar a {
            color: #2676f5;
            font-size: 1.06em;
            text-decoration: none;
            margin-right: 16px;
            padding: 0 8px;
            border-radius: 7px 7px 0 0;
            transition: background 0.14s;
        }
        .second-nav-bar a.active, .second-nav-bar a:hover {
            background: #e2edfd;
            font-weight: bold;
        }
        @media(max-width: 720px){
            .header-bar { flex-direction:column; height:auto; padding:14px; align-items:flex-start; }
            .header-left { font-size:1.1em; margin-bottom: 7px;}
            .header-nav { gap:12px; margin-left:0; margin-bottom:5px;}
            .header-user { font-size:0.98em; gap:10px;}
            .header-toolbar { padding:10px 8px 4px 8px; flex-wrap:wrap; gap:10px; }
            .header-toolbar select { min-width: 94px;}
            .second-nav-bar { padding:0 8px; gap:7px;}
            .second-nav-bar a { margin-right:7px; }
        }
    </style>
</head>
<body>
<div class="header-bar">
    <div class="header-left">
        <a href="/index.php" style="color:#fff;text-decoration:none;">
            <span style="font-weight:bold;">高端会计系统</span>
        </a>
    </div>
    <nav class="header-nav">
        <a href="/index.php" class="<?=basename($_SERVER['PHP_SELF'])=='index.php'?'active':''?>">首页</a>
        <a href="/books.php" class="<?=basename($_SERVER['PHP_SELF'])=='books.php'?'active':''?>">账套管理</a>
        <a href="/accounts.php" class="<?=basename($_SERVER['PHP_SELF'])=='accounts.php'?'active':''?>">科目管理</a>
        <a href="/vouchers.php" class="<?=basename($_SERVER['PHP_SELF'])=='vouchers.php'?'active':''?>">凭证管理</a>
        <a href="/reports.php" class="<?=basename($_SERVER['PHP_SELF'])=='reports.php'?'active':''?>">报表中心</a>
        <a href="/settings.php" class="<?=basename($_SERVER['PHP_SELF'])=='settings.php'?'active':''?>">系统设置</a>
    </nav>
    <div class="header-user">
        <?php if ($user): ?>
            <span class="user-badge"><?=$user?></span>
            <?php if($current_book && isset($book_opts[$current_book])): ?>
                <span style="font-size:0.97em;">
                    账套：<?=htmlspecialchars($book_opts[$current_book]['name'])?>
                </span>
            <?php endif; ?>
            <a href="/logout.php" class="logout-link">退出</a>
        <?php else: ?>
            <a href="/login.php" class="user-badge">登录</a>
        <?php endif; ?>
    </div>
</div>
<!-- 全局账套、期间二级菜单 -->
<div class="header-toolbar">
    <form method="post" style="display:inline;">
        <label for="switch_book">账套</label>
        <select name="switch_book" id="switch_book" onchange="this.form.submit()">
            <?php foreach($book_opts as $bid=>$b): ?>
                <option value="<?=$bid?>" <?=$current_book==$bid?'selected':''?>><?=htmlspecialchars($b['name'])?></option>
            <?php endforeach;?>
        </select>
    </form>
    <?php if($current_book_row && $global_periods): ?>
        <span class="sep">|</span>
        <form method="post" style="display:inline;">
            <label for="switch_period">所属期</label>
            <select name="switch_period" id="switch_period" onchange="this.form.submit()">
                <?php foreach($global_periods as $p): ?>
                    <option value="<?=$p['val']?>" <?=$current_global_period==$p['val']?'selected':''?>><?=$p['label']?></option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif;?>
</div>
<div style="height:18px;"></div>