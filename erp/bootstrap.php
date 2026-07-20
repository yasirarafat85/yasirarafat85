<?php
/**
 * ---------------------------------------------------------------
 *  bootstrap.php — প্রতিটি পেজের শুরুতে একবার include করতে হবে।
 * ---------------------------------------------------------------
 *  এটি config, database layer, auth ও helper সব লোড করে,
 *  সেশন চালু করে। module থেকেও শুধু এটি include করলেই সব পাওয়া যাবে।
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
