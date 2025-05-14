<?php
$db = new PDO('sqlite:' . dirname(__DIR__) . '/db/accounting.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
session_start();
?>