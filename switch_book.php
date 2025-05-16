<?php
session_start();
$book_id = null;
if (isset($_POST['book_id'])) {
    $book_id = intval($_POST['book_id']);
} elseif (isset($_GET['book_id'])) {
    $book_id = intval($_GET['book_id']);
}
if ($book_id !== null) {
    if ($book_id > 0) {
        $_SESSION['book_id'] = $book_id;
        // 选账套时自动切换到该账套的起始期间
        require_once 'inc/functions.php';
        global $db;
        if (!isset($db)) $db = new PDO('sqlite:' . __DIR__ . '/db/accounting.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $db->prepare("SELECT * FROM books WHERE id=?");
        $stmt->execute([$book_id]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($book) {
            $_SESSION['period_year'] = $book['start_year'];
            $_SESSION['period_month'] = $book['start_month'];
        }
    } else {
        unset($_SESSION['book_id']);
    }
}
// 支持 ?redirect=xxx 指定跳转页面，否则回到来源页或首页
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : ($_SERVER['HTTP_REFERER'] ?? "index.php");
header("Location: $redirect");
exit;
?>