<?php
require_once 'inc/functions.php';
checkLogin();

// ==== 1. 存储基础信息到系统参数表（建议用一张settings表，只有一条记录） ====
// 你可以用一本地SQLite实现如下表：CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT)
function save_setting($key, $value) {
    global $db;
    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (k, v) VALUES (?, ?)");
    $stmt->execute([$key, $value]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 基本信息
    save_setting('company_name', $_POST['company_name'] ?? '');
    save_setting('contact', $_POST['contact'] ?? '');
    save_setting('contact_phone', $_POST['contact_phone'] ?? '');

    // logo上传
    if (!empty($_FILES['company_logo']['name']) && $_FILES['company_logo']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
        $allow = ['png','jpg','jpeg','gif','bmp'];
        if (in_array($ext, $allow)) {
            $filename = "upload/logo." . $ext;
            if (!is_dir('upload')) mkdir('upload', 0777, true);
            move_uploaded_file($_FILES['company_logo']['tmp_name'], $filename);
            save_setting('company_logo', $filename);
        }
    }

    // 报表参数
    save_setting('default_report_period', $_POST['default_report_period'] ?? 'month');
    save_setting('hide_zero_account', !empty($_POST['hide_zero_account']) ? '1' : '0');
    save_setting('default_export_format', $_POST['default_export_format'] ?? 'excel');

    // 凭证与账务参数
    save_setting('voucher_prefix', $_POST['voucher_prefix'] ?? '');
    save_setting('voucher_serial_start', $_POST['voucher_serial_start'] ?? '');
    save_setting('enable_voucher_audit', !empty($_POST['enable_voucher_audit']) ? '1' : '0');
    save_setting('allow_unclose', !empty($_POST['allow_unclose']) ? '1' : '0');

    // 修改密码
    if (!empty($_POST['new_password'])) {
        $newpw = trim($_POST['new_password']);
        if (strlen($newpw) < 4) {
            header("Location: settings.php?err=密码至少4位");
            exit;
        }
        $hash = password_hash($newpw, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password=? WHERE username=?");
        $stmt->execute([$hash, $_SESSION['username']]);
    }

    // 其他功能项如期初余额、结账、用户管理等，交由专门页面处理

    header("Location: settings.php?ok=1");
    exit;
}

header("Location: settings.php");