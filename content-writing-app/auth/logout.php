<?php
// auth/logout.php — লগআউট
require_once __DIR__ . '/../includes/auth.php';
logout_user();
session_start();
set_flash('info', 'আপনি লগআউট করেছেন। আবার আসবেন! 👋');
redirect('../auth/login.php');
