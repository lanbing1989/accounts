<?php
session_start();
if (isset($_REQUEST['year']) && isset($_REQUEST['month'])) {
    $_SESSION['period_year'] = intval($_REQUEST['year']);
    $_SESSION['period_month'] = intval($_REQUEST['month']);
}
$redirect = $_REQUEST['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $redirect);
exit;
?>