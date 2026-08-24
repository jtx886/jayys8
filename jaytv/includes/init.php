<?php
/**
 * Jay影视 - 核心初始化文件
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

mysqli_report(MYSQLI_REPORT_OFF);

$lock_file = dirname(__DIR__) . '/install.lock';
$config_file = __DIR__ . '/config.php';
$install_script = dirname(__DIR__) . '/install.php';

$needs_install = false;

if (!file_exists($config_file) || filesize($config_file) < 50) {
    $needs_install = true;
} elseif (file_exists($install_script) && !file_exists($lock_file)) {
    $needs_install = true;
}

if ($needs_install) {
    if (php_sapi_name() !== 'cli') {
        $current_url = $_SERVER['SCRIPT_NAME'] ?? '';
        if (basename($current_url) !== 'install.php') {
            header('Location: install.php');
            exit;
        }
    } else {
        die('Configuration not found. Please run install.php via web browser first.' . PHP_EOL);
    }
}

if (!@include_once $config_file) {
    if (php_sapi_name() !== 'cli') {
        header('Location: install.php?force=1');
        exit;
    }
    die('Configuration file corrupted. Please re-run install.php.' . PHP_EOL);
}

if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
    if (php_sapi_name() !== 'cli') {
        @unlink($config_file);
        header('Location: install.php?force=1');
        exit;
    }
    die('Database constants not defined. Please re-run install.php.' . PHP_EOL);
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/functions.php';

$db = null;
$db_error = '';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    if ($conn->connect_errno) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    $db_error = $e->getMessage();
}

if (!$db || $db_error) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        $err_msg = '数据库连接失败，请检查 includes/config.php 配置是否正确。<br>错误: ' . htmlspecialchars($db_error);
        if (file_exists($install_script)) {
            $err_msg .= '<br><br><a href="install.php?force=1" style="color:#e94560;">点此重新安装</a>';
        }
        die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>数据库连接错误</title><style>body{background:#1a1a2e;color:#fff;font-family:Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}.box{background:#16213e;padding:40px;border-radius:16px;max-width:500px;text-align:center;}h1{color:#e94560;margin-bottom:20px;}a{color:#e94560;}</style></head><body><div class="box"><h1>⚠️ 数据库连接错误</h1><p style="line-height:1.8;">' . $err_msg . '</p></div></body></html>');
    }
}

$settings = null;
try {
    $settings = get_settings();
} catch (Exception $e) {
    $settings = [
        'site_name' => 'Jay影视',
        'theme_color' => '#e94560',
        'tmdb_api_key' => '',
        'announcement_id' => 0,
        'default_source' => 1,
        'parse_url' => 'https://svip.ffzyplay.com/?url='
    ];
}

date_default_timezone_set('Asia/Shanghai');

if (!defined('THEME_COLOR')) {
    define('THEME_COLOR', $settings['theme_color'] ?? '#e94560');
}

$current_user = null;
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    try {
        $current_user = get_user($_SESSION['user_id']);
        if ($current_user && $current_user['is_banned']) {
            if ($current_user['ban_end'] && strtotime($current_user['ban_end']) < time()) {
                $db->query("UPDATE users SET is_banned = 0, ban_reason = NULL, ban_start = NULL, ban_end = NULL WHERE id = ?", [$current_user['id']]);
                $current_user['is_banned'] = 0;
            }
        }
    } catch (Exception $e) {
        $current_user = null;
    }
}

function is_admin() {
    global $current_user;
    return $current_user && isset($current_user['is_admin']) && $current_user['is_admin'];
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
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/') . '&msg=' . urlencode($msg));
        exit;
    }
}

function require_admin() {
    if (!is_admin()) {
        header('Location: index.php');
        exit;
    }
}
