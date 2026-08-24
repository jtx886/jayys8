<?php
if (!defined('IN_SITE')) {
    define('IN_SITE', true);
}
$admin_page = $admin_page ?? $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= isset($page_title) ? esc($page_title) . ' - ' : '' ?><?= esc(setting('site_name')) ?> 管理后台</title>
    <style>:root { --theme: <?= THEME_COLOR ?>; }</style>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background: var(--bg); }
        .admin-header .logo { font-size: 20px; }
    </style>
</head>
<body>

<header class="header">
    <div class="container">
        <nav class="nav">
            <a href="index.php" class="logo">
                <span class="logo-icon">⚙️</span>
                <span>管理后台</span>
            </a>
            
            <div class="nav-right">
                <a href="../index.php" class="btn btn-ghost btn-sm">返回前台</a>
                <div class="user-avatar">
                    <?= mb_substr($current_user['username'], 0, 1) ?>
                    <span class="dev-badge">开发者</span>
                </div>
                <span style="font-size:14px;"><?= esc($current_user['username']) ?></span>
            </div>
        </nav>
    </div>
</header>

<div class="admin-layout">
    <aside class="admin-sidebar">
        <nav class="admin-menu">
            <a href="?page=dashboard" class="<?= $admin_page === 'dashboard' ? 'active' : '' ?>">
                <span>📊</span> 仪表盘
            </a>
            <a href="?page=users" class="<?= $admin_page === 'users' ? 'active' : '' ?>">
                <span>👥</span> 用户管理
            </a>
            <a href="?page=history" class="<?= $admin_page === 'history' ? 'active' : '' ?>">
                <span>🕐</span> 观看历史
            </a>
            <a href="?page=favorites" class="<?= $admin_page === 'favorites' ? 'active' : '' ?>">
                <span>❤️</span> 用户收藏
            </a>
            <a href="?page=sources" class="<?= $admin_page === 'sources' ? 'active' : '' ?>">
                <span>📺</span> 播放源管理
            </a>
            <a href="?page=mail" class="<?= $admin_page === 'mail' ? 'active' : '' ?>">
                <span>📧</span> 邮件推送
            </a>
            <a href="?page=announcement" class="<?= $admin_page === 'announcement' ? 'active' : '' ?>">
                <span>📢</span> 网站公告
            </a>
            <a href="?page=settings" class="<?= $admin_page === 'settings' ? 'active' : '' ?>">
                <span>🎨</span> 网站设置
            </a>
            <a href="?page=feedbacks" class="<?= $admin_page === 'feedbacks' ? 'active' : '' ?>">
                <span>💬</span> 反馈管理
            </a>
            <a href="../logout.php" style="color:var(--danger);">
                <span>🚪</span> 退出登录
            </a>
        </nav>
    </aside>
