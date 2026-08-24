<?php
if (!defined('IN_SITE')) define('IN_SITE', true);
require_once __DIR__ . '/init.php';
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= isset($page_title) ? esc($page_title) . ' - ' : '' ?><?= esc(setting('site_name')) ?></title>
    <style>:root { --theme: <?= THEME_COLOR ?>; }</style>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="header">
    <div class="container">
        <nav class="nav">
            <a href="index.php" class="logo">
                <span class="logo-icon">🎬</span>
                <span><?= esc(setting('site_name')) ?></span>
            </a>
            
            <div class="nav-menu" id="navMenu">
                <a href="index.php" class="<?= $current_page === 'index' ? 'active' : '' ?>">首页</a>
                <a href="list.php?type=movie" class="<?= isset($type) && $type === 'movie' ? 'active' : '' ?>">电影</a>
                <a href="list.php?type=tv" class="<?= isset($type) && $type === 'tv' ? 'active' : '' ?>">电视剧</a>
                <a href="list.php?type=综艺" class="<?= isset($type) && $type === '综艺' ? 'active' : '' ?>">综艺</a>
                <a href="list.php?type=动漫" class="<?= isset($type) && $type === '动漫' ? 'active' : '' ?>">动漫</a>
                <a href="feedback.php" class="<?= $current_page === 'feedback' ? 'active' : '' ?>">反馈</a>
            </div>
            
            <div class="nav-right">
                <div class="search-box">
                    <span class="search-icon"></span>
                    <input type="text" placeholder="搜索影视..." id="searchInput" value="<?= isset($_GET['q']) ? esc($_GET['q']) : '' ?>">
                </div>
                
                <?php if (is_logged_in()): ?>
                    <?php if (is_admin()): ?>
                        <a href="admin/" class="icon-btn" title="管理后台">⚙️</a>
                    <?php endif; ?>
                    <a href="profile.php" class="user-avatar" title="<?= esc($current_user['username']) ?>">
                        <?php if ($current_user['avatar'] && $current_user['avatar'] !== 'default.png' && file_exists(UPLOAD_PATH . 'avatars/' . $current_user['avatar'])): ?>
                            <img src="uploads/avatars/<?= esc($current_user['avatar']) ?>" alt="">
                        <?php else: ?>
                            <?= mb_substr($current_user['username'], 0, 1) ?>
                        <?php endif; ?>
                        <?php if (is_admin()): ?>
                            <span class="dev-badge">开发者</span>
                        <?php endif; ?>
                    </a>
                <?php else: ?>
                    <button class="btn btn-primary btn-sm" data-modal="login-modal">登录</button>
                <?php endif; ?>
                
                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </nav>
    </div>
</header>

<?php if (!is_logged_in()): ?>
<div class="modal-overlay" id="login-modal">
    <div class="modal">
        <div class="modal-header">
            <h3>欢迎回来</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="auth-tabs">
                <div class="auth-tab active" data-tab="login">登录</div>
                <div class="auth-tab" data-tab="register">注册</div>
            </div>
            
            <form id="loginForm">
                <div class="form-group">
                    <label>邮箱/用户名</label>
                    <input type="text" name="account" class="form-control" required placeholder="请输入邮箱或用户名">
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="password" class="form-control" required placeholder="请输入密码">
                </div>
                <input type="hidden" name="redirect" value="<?= isset($_GET['redirect']) ? esc($_GET['redirect']) : (isset($_SERVER['REQUEST_URI']) ? esc($_SERVER['REQUEST_URI']) : '') ?>">
                <button type="submit" class="btn btn-primary btn-block btn-lg">登录</button>
            </form>
            
            <form id="registerForm" style="display:none;">
                <div class="form-group">
                    <label>邮箱</label>
                    <input type="email" name="email" class="form-control" required placeholder="请输入邮箱">
                </div>
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" name="username" class="form-control" required placeholder="请输入用户名">
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="password" class="form-control" required placeholder="请输入密码">
                </div>
                <div class="form-group">
                    <label>确认密码</label>
                    <input type="password" name="password2" class="form-control" required placeholder="请再次输入密码">
                </div>
                <div class="form-group">
                    <label>邮箱验证码</label>
                    <div class="form-row">
                        <input type="text" name="code" class="form-control" required placeholder="请输入验证码">
                        <button type="button" class="btn btn-outline btn-sm" id="sendCodeBtn">发送验证码</button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">注册</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<main>
