<?php
require_once __DIR__ . '/inc/auth.php';
session_unset();
session_destroy();
header('Location: /be/login.php');
exit;
