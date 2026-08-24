<?php
/**
 * Jay影视 - 核心初始化文件
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config_file = __DIR__ . '/config.php';
if (!file_exists($config_file)) {
    if (php_sapi_name() !== 'cli') {
        header('Location: install.php');
        exit;
    }
    die('Configuration file not found. Please run install.php first.');
}

require_once $config_file;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/functions.php';

$db = Database::getInstance();
$settings = get_settings();

date_default_timezone_set('Asia/Shanghai');

if (!defined('THEME_COLOR')) {
    define('THEME_COLOR', $settings['theme_color'] ?: '#e94560');
}

$current_user = null;
if (isset($_SESSION['user_id'])) {
    $current_user = get_user($_SESSION['user_id']);
    if ($current_user && $current_user['is_banned']) {
        if ($current_user['ban_end'] && strtotime($current_user['ban_end']) < time()) {
            $db->query("UPDATE users SET is_banned = 0, ban_reason = NULL, ban_start = NULL, ban_end = NULL WHERE id = ?", [$current_user['id']]);
            $current_user['is_banned'] = 0;
        }
    }
}

function is_admin() {
    global $current_user;
    return $current_user && $current_user['is_admin'];
}

function is_logged_in() {
    global $current_user;
    return $current_user !== null;
}

function require_login() {
    if (!is_logged_in()) {
        $msg = '需要登录才可以观看哦，如没有账号请注册！';
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['code' => 401, 'msg' => $msg]);
            exit;
        }
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']) . '&msg=' . urlencode($msg));
        exit;
    }
}

function require_admin() {
    if (!is_admin()) {
        header('Location: index.php');
        exit;
    }
}
