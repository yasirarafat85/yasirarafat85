<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::logout();
redirect('auth/login.php');
