<?php
/**
 * Jay影视 - 安装向导
 * 首次访问运行，安装完成后自动销毁
 */

session_start();

$installed = file_exists(__DIR__ . '/includes/config.php');

if ($installed && !isset($_GET['force'])) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>系统已安装</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { background: #1a1a2e; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #fff; }
            .box { text-align: center; padding: 40px; background: #16213e; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
            h1 { font-size: 28px; margin-bottom: 16px; color: #e94560; }
            p { color: #a0a0b0; margin-bottom: 24px; }
            a { color: #0f3460; background: #e94560; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block; transition: transform 0.3s; }
            a:hover { transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>🚫 系统已安装</h1>
            <p>Jay影视已完成安装，如需重新安装请删除 includes/config.php 文件</p>
            <a href="index.php">返回首页</a>
        </div>
    </body>
    </html>');
}

$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        $db_host = trim($_POST['db_host']);
        $db_name = trim($_POST['db_name']);
        $db_user = trim($_POST['db_user']);
        $db_pass = $_POST['db_pass'];
        
        try {
            $conn = @new mysqli($db_host, $db_user, $db_pass);
            if ($conn->connect_error) {
                throw new Exception('数据库连接失败: ' . $conn->connect_error);
            }
            $conn->set_charset('utf8mb4');
            
            $conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $conn->select_db($db_name);
            
            $_SESSION['install_db'] = [
                'host' => $db_host,
                'name' => $db_name,
                'user' => $db_user,
                'pass' => $db_pass
            ];
            
            $step = 3;
            $success = '数据库连接成功！';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($step === 3) {
        $db = $_SESSION['install_db'];
        $site_name = trim($_POST['site_name']);
        $admin_user = trim($_POST['admin_user']);
        $admin_pass = $_POST['admin_pass'];
        $admin_email = trim($_POST['admin_email']);
        $tmdb_key = trim($_POST['tmdb_key']);
        
        try {
            $conn = new mysqli($db['host'], $db['user'], $db['pass'], $db['name']);
            $conn->set_charset('utf8mb4');
            
            $sql = file_get_contents(__DIR__ . '/includes/database.sql');
            $conn->multi_query($sql);
            while ($conn->more_results()) {
                $conn->next_result();
            }
            
            $admin_hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $default_source = 'https://api.yyzy-tv.vip/inc/apijson.php';
            $parse_url = 'https://svip.ffzyplay.com/?url=';
            $theme_color = '#e94560';
            
            $stmt = $conn->prepare("INSERT INTO settings (site_name, theme_color, tmdb_api_key, default_source, parse_url) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $site_name, $theme_color, $tmdb_key, $default_source, $parse_url);
            $stmt->execute();
            
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, is_admin, avatar, created_at) VALUES (?, ?, ?, 1, 'default.png', NOW())");
            $stmt->bind_param("ssss", $admin_user, $admin_email, $admin_hash);
            $stmt->execute();
            
            $stmt = $conn->prepare("INSERT INTO play_sources (name, url, is_default) VALUES ('默认源', ?, 1)");
            $stmt->bind_param("s", $default_source);
            $stmt->execute();
            
            $config_content = "<?php
/**
 * Jay影视 配置文件
 * 安装时间: " . date('Y-m-d H:i:s') . "
 */

define('DB_HOST', '" . addslashes($db['host']) . "');
define('DB_NAME', '" . addslashes($db['name']) . "');
define('DB_USER', '" . addslashes($db['user']) . "');
define('DB_PASS', '" . addslashes($db['pass']) . "');

define('SMTP_HOST', 'smtp.163.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'jtxnb886@163.com');
define('SMTP_PASS', 'FLLRDtadYAfGXp9Y');
define('SMTP_FROM', 'jtxnb886@163.com');
define('SMTP_FROM_NAME', 'Jay影视');

define('SITE_URL', (isset(\$_SERVER['HTTPS']) ? 'https' : 'http') . '://' . \$_SERVER['HTTP_HOST'] . dirname(\$_SERVER['SCRIPT_NAME']));
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');
";
            
            file_put_contents(__DIR__ . '/includes/config.php', $config_content);
            @chmod(__DIR__ . '/includes/config.php', 0644);
            
            $install_lock = fopen(__DIR__ . '/install.lock', 'w');
            fwrite($install_lock, 'Installed at: ' . date('Y-m-d H:i:s'));
            fclose($install_lock);
            
            unset($_SESSION['install_db']);
            
            $step = 4;
            $success = '安装成功！';
        } catch (Exception $e) {
            $error = '安装失败: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jay影视 - 安装向导</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .installer {
            width: 100%;
            max-width: 600px;
            background: rgba(22, 33, 62, 0.95);
            border-radius: 20px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.5);
            overflow: hidden;
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .header {
            background: linear-gradient(135deg, #e94560 0%, #0f3460 100%);
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .steps {
            display: flex;
            justify-content: center;
            gap: 20px;
            padding: 20px;
            background: rgba(0,0,0,0.2);
        }
        .step {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
        }
        .step.active { color: #e94560; }
        .step.done { color: #4ade80; }
        .step-num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }
        .step.active .step-num { background: #e94560; }
        .step.done .step-num { background: #4ade80; }
        .content { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; color: #ccc; font-size: 14px; }
        input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #e94560;
            background: rgba(255,255,255,0.08);
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #e94560 0%, #d63850 100%);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(233, 69, 96, 0.4);
        }
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error { background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; }
        .alert-success { background: rgba(74, 222, 128, 0.2); border: 1px solid rgba(74, 222, 128, 0.3); color: #86efac; }
        .success-box { text-align: center; padding: 20px 0; }
        .success-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #4ade80;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
            animation: popIn 0.5s ease-out;
        }
        @keyframes popIn {
            0% { transform: scale(0); }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        @media (max-width: 480px) { .grid-2 { grid-template-columns: 1fr; } }
        .hint { font-size: 12px; color: #888; margin-top: 6px; }
    </style>
</head>
<body>
    <div class="installer">
        <div class="header">
            <h1>🎬 Jay影视</h1>
            <p>现代化影视网站安装向导</p>
        </div>
        
        <div class="steps">
            <div class="step <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>">
                <span class="step-num"><?= $step > 1 ? '✓' : '1' ?></span>
                <span>环境检测</span>
            </div>
            <div class="step <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>">
                <span class="step-num"><?= $step > 2 ? '✓' : '2' ?></span>
                <span>数据库配置</span>
            </div>
            <div class="step <?= $step >= 3 ? ($step > 3 ? 'done' : 'active') : '' ?>">
                <span class="step-num"><?= $step > 3 ? '✓' : '3' ?></span>
                <span>站点设置</span>
            </div>
            <div class="step <?= $step >= 4 ? 'active' : '' ?>">
                <span class="step-num">4</span>
                <span>完成</span>
            </div>
        </div>
        
        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
                <h3 style="margin-bottom: 20px;">环境检测</h3>
                <?php
                $checks = [
                    'PHP版本 >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
                    'MySQLi扩展' => extension_loaded('mysqli'),
                    'cURL扩展' => extension_loaded('curl'),
                    'GD扩展' => extension_loaded('gd'),
                    'uploads目录可写' => is_writable(__DIR__ . '/uploads'),
                    'includes目录可写' => is_writable(__DIR__ . '/includes'),
                ];
                $can_continue = !in_array(false, $checks, true);
                ?>
                <div style="margin-bottom: 25px;">
                    <?php foreach ($checks as $name => $ok): ?>
                        <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <span><?= $name ?></span>
                            <span style="color: <?= $ok ? '#4ade80' : '#f87171' ?>"><?= $ok ? '✓ 通过' : '✗ 不通过' ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($can_continue): ?>
                    <form method="post">
                        <input type="hidden" name="step" value="2">
                        <button type="submit" class="btn">下一步：配置数据库</button>
                    </form>
                <?php else: ?>
                    <p style="color: #f87171; text-align: center;">请先修复以上问题后再继续安装</p>
                <?php endif; ?>
                
            <?php elseif ($step === 2): ?>
                <h3 style="margin-bottom: 20px;">数据库配置</h3>
                <form method="post">
                    <input type="hidden" name="step" value="2">
                    <div class="form-group">
                        <label>数据库主机</label>
                        <input type="text" name="db_host" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label>数据库名称</label>
                        <input type="text" name="db_name" value="jaytv" required>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>数据库用户名</label>
                            <input type="text" name="db_user" value="root" required>
                        </div>
                        <div class="form-group">
                            <label>数据库密码</label>
                            <input type="password" name="db_pass">
                        </div>
                    </div>
                    <button type="submit" class="btn" style="margin-top: 10px;">下一步：站点设置</button>
                </form>
                
            <?php elseif ($step === 3): ?>
                <h3 style="margin-bottom: 20px;">站点设置</h3>
                <form method="post">
                    <input type="hidden" name="step" value="3">
                    <div class="form-group">
                        <label>网站名称</label>
                        <input type="text" name="site_name" value="Jay影视" required>
                    </div>
                    <div class="form-group">
                        <label>TMDB API Key</label>
                        <input type="text" name="tmdb_key" placeholder="用于获取影视元数据" required>
                        <p class="hint">请在 themoviedb.org 申请API密钥</p>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>管理员用户名</label>
                            <input type="text" name="admin_user" value="杰同学" required>
                        </div>
                        <div class="form-group">
                            <label>管理员密码</label>
                            <input type="password" name="admin_pass" value="101113" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>管理员邮箱</label>
                        <input type="email" name="admin_email" value="jtxnb886@163.com" required>
                    </div>
                    <button type="submit" class="btn" style="margin-top: 10px;">开始安装</button>
                </form>
                
            <?php elseif ($step === 4): ?>
                <div class="success-box">
                    <div class="success-icon">🎉</div>
                    <h2 style="margin-bottom: 12px;">安装成功！</h2>
                    <p style="color: #a0a0b0; margin-bottom: 25px;">Jay影视已成功安装，点击下方按钮进入首页</p>
                    <a href="index.php" class="btn" style="text-decoration: none; display: inline-block; width: 100%;">进入网站</a>
                    <p style="margin-top: 15px; font-size: 13px; color: #888;">
                        默认管理员：杰同学 / 101113<br>
                        建议删除 install.php 文件以提高安全性
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>