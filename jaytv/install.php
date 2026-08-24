<?php
/**
 * Jay影视 - 安装向导
 * 首次访问运行，安装完成后自动销毁入口
 */

session_start();

mysqli_report(MYSQLI_REPORT_OFF);

$lock_file = __DIR__ . '/install.lock';
$config_file = __DIR__ . '/includes/config.php';

if (file_exists($lock_file) && file_exists($config_file) && !isset($_GET['force'])) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>系统已安装</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { background: #1a1a2e; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #fff; padding: 20px; }
            .box { text-align: center; padding: 40px; background: #16213e; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 400px; width: 100%; }
            h1 { font-size: 24px; margin-bottom: 16px; color: #e94560; }
            p { color: #a0a0b0; margin-bottom: 24px; line-height: 1.6; }
            a { color: #fff; background: #e94560; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block; transition: transform 0.3s; }
            a:hover { transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>🚫 系统已安装</h1>
            <p>Jay影视已完成安装。如需重新安装，请删除 includes/config.php 和 install.lock 文件后重试。</p>
            <a href="index.php">返回首页</a>
        </div>
    </body>
    </html>');
}

@mkdir(__DIR__ . '/uploads/avatars', 0755, true);
@mkdir(__DIR__ . '/uploads/cache', 0755, true);

$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$error = '';
$success = '';
$errors = [];

function check_writeable($dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (is_dir($dir)) {
        $test_file = $dir . '/test_write.tmp';
        $written = @file_put_contents($test_file, 'test');
        if ($written !== false) {
            @unlink($test_file);
            return true;
        }
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        $db_host = trim($_POST['db_host'] ?? 'localhost');
        $db_name = trim($_POST['db_name'] ?? '');
        $db_user = trim($_POST['db_user'] ?? '');
        $db_pass = $_POST['db_pass'] ?? '';
        
        if (!$db_host) $errors[] = '请输入数据库主机';
        if (!$db_name) $errors[] = '请输入数据库名称';
        if ($db_user === '') $errors[] = '请输入数据库用户名';
        
        if (empty($errors)) {
            $conn = @new mysqli($db_host, $db_user, $db_pass);
            if ($conn->connect_errno) {
                $errors[] = '数据库连接失败: ' . $conn->connect_error;
            } else {
                $conn->set_charset('utf8mb4');
                
                if (!$conn->select_db($db_name)) {
                    $create_db = @$conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    if (!$create_db) {
                        $errors[] = '数据库不存在且无权限创建，请手动创建数据库';
                    } else {
                        $conn->select_db($db_name);
                    }
                }
                
                if (empty($errors) && !$conn->select_db($db_name)) {
                    $errors[] = '无法选择数据库: ' . $conn->error;
                }
                
                if (empty($errors)) {
                    $_SESSION['install_db'] = [
                        'host' => $db_host,
                        'name' => $db_name,
                        'user' => $db_user,
                        'pass' => $db_pass
                    ];
                    $step = 3;
                    $success = '数据库连接成功，请继续配置站点信息';
                }
                $conn->close();
            }
        }
        
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        }
    } elseif ($step === 3) {
        $db = $_SESSION['install_db'] ?? [];
        $site_name = trim($_POST['site_name'] ?? 'Jay影视');
        $admin_user = trim($_POST['admin_user'] ?? '杰同学');
        $admin_pass = $_POST['admin_pass'] ?? '101113';
        $admin_email = trim($_POST['admin_email'] ?? '');
        $tmdb_key = trim($_POST['tmdb_key'] ?? '');
        
        if (!$site_name) $errors[] = '请输入网站名称';
        if (!$admin_user || mb_strlen($admin_user) < 2) $errors[] = '请输入管理员用户名';
        if (!$admin_pass || strlen($admin_pass) < 6) $errors[] = '管理员密码至少6位';
        if (!$admin_email || !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) $errors[] = '请输入有效的管理员邮箱';
        
        if (empty($errors)) {
            if (!is_writable(__DIR__ . '/includes')) {
                if (!check_writeable(__DIR__ . '/includes')) {
                    $errors[] = 'includes目录不可写，请设置目录权限为755或777';
                }
            }
        }
        
        if (empty($errors)) {
            $conn = @new mysqli($db['host'], $db['user'], $db['pass'], $db['name']);
            if ($conn->connect_errno) {
                $errors[] = '数据库连接失败: ' . $conn->connect_error;
            } else {
                $conn->set_charset('utf8mb4');
                
                $sql_file = __DIR__ . '/includes/database.sql';
                if (!file_exists($sql_file)) {
                    $errors[] = '数据库结构文件不存在: includes/database.sql';
                } else {
                    $sql = file_get_contents($sql_file);
                    if ($sql === false) {
                        $errors[] = '无法读取数据库结构文件';
                    } else {
                        $conn->begin_transaction();
                        try {
                            $queries = array_filter(array_map('trim', explode(';', $sql)));
                            foreach ($queries as $q) {
                                if (empty($q) || strpos($q, '--') === 0) continue;
                                if (!$conn->query($q)) {
                                    throw new Exception('SQL执行错误: ' . $conn->error . ' SQL: ' . substr($q, 0, 100));
                                }
                            }
                            
                            $admin_hash = password_hash($admin_pass, PASSWORD_DEFAULT);
                            $default_source = 'https://api.yyzy-tv.vip/inc/apijson.php';
                            $parse_url = 'https://svip.ffzyplay.com/?url=';
                            $theme_color = '#e94560';
                            
                            $conn->query("DELETE FROM settings");
                            $stmt = $conn->prepare("INSERT INTO settings (site_name, theme_color, tmdb_api_key, default_source, parse_url) VALUES (?, ?, ?, 1, ?)");
                            if ($stmt) {
                                $stmt->bind_param("ssss", $site_name, $theme_color, $tmdb_key, $parse_url);
                                $stmt->execute();
                                $stmt->close();
                            }
                            
                            $conn->query("DELETE FROM users WHERE is_admin = 1");
                            $stmt = $conn->prepare("INSERT INTO users (username, email, password, is_admin, avatar, created_at) VALUES (?, ?, ?, 1, 'default.png', NOW())");
                            if ($stmt) {
                                $stmt->bind_param("sss", $admin_user, $admin_email, $admin_hash);
                                $stmt->execute();
                                $stmt->close();
                            }
                            
                            $conn->query("DELETE FROM play_sources WHERE is_default = 1");
                            $stmt = $conn->prepare("INSERT INTO play_sources (name, url, is_default) VALUES ('默认源', ?, 1)");
                            if ($stmt) {
                                $stmt->bind_param("s", $default_source);
                                $stmt->execute();
                                $stmt->close();
                            }
                            
                            $conn->commit();
                            
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

define('SITE_URL', (isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] ? 'https' : 'http') . '://' . \$_SERVER['HTTP_HOST'] . rtrim(dirname(\$_SERVER['SCRIPT_NAME']), '/\\\\'));
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');
";
                            
                            $write_result = @file_put_contents($config_file, $config_content);
                            if ($write_result === false) {
                                throw new Exception('无法写入配置文件 includes/config.php，请检查目录权限');
                            }
                            
                            $lock_content = "Jay影视安装锁定\n安装时间: " . date('Y-m-d H:i:s') . "\n站点: " . $site_name . "\n管理员: " . $admin_user;
                            @file_put_contents($lock_file, $lock_content);
                            
                            unset($_SESSION['install_db']);
                            
                            $step = 4;
                            $success = '安装成功！';
                        } catch (Exception $e) {
                            $conn->rollback();
                            $errors[] = '安装失败: ' . $e->getMessage();
                            @unlink($config_file);
                        }
                    }
                }
                $conn->close();
            }
        }
        
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        }
    }
}

$php_version = phpversion();
$can_continue = true;
$env_checks = [
    'PHP版本 >= 7.4' => version_compare($php_version, '7.4.0', '>='),
    'MySQLi扩展' => extension_loaded('mysqli'),
    'cURL扩展' => extension_loaded('curl'),
    'JSON支持' => function_exists('json_encode'),
    'Session支持' => function_exists('session_start'),
    'includes目录可写' => check_writeable(__DIR__ . '/includes'),
    'uploads目录可写' => check_writeable(__DIR__ . '/uploads'),
];

foreach ($env_checks as $check => $ok) {
    if (!$ok) $can_continue = false;
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', 'PingFang SC', 'Microsoft YaHei', sans-serif;
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
        .header h1 { font-size: 28px; font-weight: 700; margin-bottom: 8px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .steps {
            display: flex;
            justify-content: center;
            gap: 12px;
            padding: 20px;
            background: rgba(0,0,0,0.2);
            flex-wrap: wrap;
        }
        .step { display: flex; align-items: center; gap: 6px; color: #555; font-size: 13px; }
        .step.active { color: #e94560; }
        .step.done { color: #4ade80; }
        .step-num {
            width: 26px; height: 26px; border-radius: 50%;
            background: #333; display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 12px;
        }
        .step.active .step-num { background: #e94560; }
        .step.done .step-num { background: #4ade80; }
        .content { padding: 30px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: 500; color: #ccc; font-size: 14px; }
        input {
            width: 100%; padding: 12px 14px;
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 10px; color: #fff; font-size: 14px;
            transition: all 0.3s;
        }
        input:focus { outline: none; border-color: #e94560; background: rgba(255,255,255,0.08); }
        .btn {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, #e94560 0%, #d63850 100%);
            border: none; border-radius: 10px; color: #fff; font-size: 15px;
            font-weight: 600; cursor: pointer; transition: all 0.3s;
        }
        .btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(233, 69, 96, 0.4); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 18px; font-size: 14px; line-height: 1.6; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; }
        .alert-success { background: rgba(74, 222, 128, 0.15); border: 1px solid rgba(74, 222, 128, 0.3); color: #86efac; }
        .success-box { text-align: center; padding: 20px 0; }
        .success-icon {
            width: 70px; height: 70px; border-radius: 50%; background: #4ade80;
            display: flex; align-items: center; justify-content: center; font-size: 36px;
            margin: 0 auto 20px; animation: popIn 0.5s ease-out;
        }
        @keyframes popIn { 0% { transform: scale(0); } 70% { transform: scale(1.1); } 100% { transform: scale(1); } }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 480px) { .grid-2 { grid-template-columns: 1fr; } }
        .hint { font-size: 12px; color: #777; margin-top: 5px; }
        .check-list { margin-bottom: 20px; }
        .check-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 14px;
        }
        .check-ok { color: #4ade80; }
        .check-fail { color: #f87171; }
        .php-ver { color: var(--text2, #a0a0b0); font-size: 12px; margin-left: 8px; }
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
                <span>数据库</span>
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
                <div class="alert alert-error">⚠️ <?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= $success ?></div>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
                <h3 style="margin-bottom: 16px; font-size: 18px;">环境检测</h3>
                <p style="color: #a0a0b0; font-size: 13px; margin-bottom: 16px;">PHP版本: <strong><?= $php_version ?></strong></p>
                <div class="check-list">
                    <?php foreach ($env_checks as $name => $ok): ?>
                    <div class="check-item">
                        <span><?= $name ?></span>
                        <span class="<?= $ok ? 'check-ok' : 'check-fail' ?>">
                            <?= $ok ? '✓ 通过' : '✗ 不通过' ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!$can_continue): ?>
                <div class="alert alert-error">
                    ⚠️ 请先修复以上环境问题后再继续安装。<br>
                    如果是目录权限问题，请将 includes 和 uploads 目录权限设置为 755 或 777。
                </div>
                <?php else: ?>
                <form method="post">
                    <input type="hidden" name="step" value="2">
                    <button type="submit" class="btn">下一步：配置数据库</button>
                </form>
                <?php endif; ?>
                
            <?php elseif ($step === 2): ?>
                <h3 style="margin-bottom: 18px; font-size: 18px;">数据库配置</h3>
                <p style="color: #a0a0b0; font-size: 13px; margin-bottom: 18px;">请填写您的MySQL数据库信息（InfinityFree等免费主机请在主机后台查看）</p>
                <form method="post" onsubmit="return validateDbForm()">
                    <input type="hidden" name="step" value="2">
                    <div class="form-group">
                        <label>数据库主机</label>
                        <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required placeholder="通常是 localhost">
                    </div>
                    <div class="form-group">
                        <label>数据库名称</label>
                        <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required placeholder="例如：jaytv 或 epiz_xxxxx_jaytv">
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>数据库用户名</label>
                            <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>" required placeholder="数据库用户名">
                        </div>
                        <div class="form-group">
                            <label>数据库密码</label>
                            <input type="password" name="db_pass" placeholder="数据库密码（本地可能为空）">
                        </div>
                    </div>
                    <p class="hint">💡 提示：如果数据库不存在，程序会尝试自动创建（需要用户有CREATE权限）</p>
                    <button type="submit" class="btn" style="margin-top: 10px;">下一步：站点设置</button>
                </form>
                
            <?php elseif ($step === 3): ?>
                <h3 style="margin-bottom: 18px; font-size: 18px;">站点设置</h3>
                <form method="post">
                    <input type="hidden" name="step" value="3">
                    <div class="form-group">
                        <label>网站名称</label>
                        <input type="text" name="site_name" value="<?= htmlspecialchars($_POST['site_name'] ?? 'Jay影视') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>TMDB API Key</label>
                        <input type="text" name="tmdb_key" value="<?= htmlspecialchars($_POST['tmdb_key'] ?? '') ?>" placeholder="用于获取影视元数据（可选，可安装后在后台设置）">
                        <p class="hint">申请地址：themoviedb.org → Settings → API → API Key (v3 auth)<br>如暂时没有可留空，安装后在后台 → 网站设置中填写</p>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>管理员用户名</label>
                            <input type="text" name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? '杰同学') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>管理员密码</label>
                            <input type="password" name="admin_pass" value="101113" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>管理员邮箱</label>
                        <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? 'jtxnb886@163.com') ?>" required>
                    </div>
                    <button type="submit" class="btn" style="margin-top: 10px;">开始安装</button>
                </form>
                
            <?php elseif ($step === 4): ?>
                <div class="success-box">
                    <div class="success-icon">🎉</div>
                    <h2 style="margin-bottom: 12px; font-size: 22px;">安装成功！</h2>
                    <p style="color: #a0a0b0; margin-bottom: 25px; font-size: 14px; line-height: 1.6;">
                        Jay影视已成功安装，点击下方按钮进入首页<br>
                        默认管理员：<strong>杰同学</strong> / <strong>101113</strong>
                    </p>
                    <a href="index.php" class="btn" style="text-decoration: none; display: block; text-align: center;">进入网站</a>
                    <p style="margin-top: 16px; font-size: 12px; color: #666; line-height: 1.8;">
                        ✅ install.lock 已创建，安装入口已锁定<br>
                        💡 建议删除 install.php 文件以提高安全性
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
    function validateDbForm() {
        var host = document.querySelector('input[name="db_host"]').value.trim();
        var name = document.querySelector('input[name="db_name"]').value.trim();
        var user = document.querySelector('input[name="db_user"]').value;
        if (!host) { alert('请输入数据库主机'); return false; }
        if (!name) { alert('请输入数据库名称'); return false; }
        if (user === '') { alert('请输入数据库用户名'); return false; }
        return true;
    }
    </script>
</body>
</html>