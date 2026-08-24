<?php
define('IN_SITE', true);
require_once __DIR__ . '/../includes/init.php';
require_admin();

$admin_page = $_GET['page'] ?? 'dashboard';
$page_title = '管理后台';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    switch ($action) {
        case 'save_settings':
            $site_name = trim($_POST['site_name'] ?? '');
            $theme_color = trim($_POST['theme_color'] ?? '#e94560');
            $tmdb_key = trim($_POST['tmdb_key'] ?? '');
            $parse_url = trim($_POST['parse_url'] ?? '');
            if ($site_name) {
                $db->update('settings', [
                    'site_name' => $site_name,
                    'theme_color' => $theme_color,
                    'tmdb_api_key' => $tmdb_key,
                    'parse_url' => $parse_url
                ], 'id = 1');
                $message = '设置已保存';
            }
            break;
        
        case 'save_source':
            $source_id = (int)$_POST['source_id'];
            $name = trim($_POST['name'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $is_default = isset($_POST['is_default']) ? 1 : 0;
            if ($name && $url) {
                if ($is_default) {
                    $db->query("UPDATE play_sources SET is_default = 0");
                }
                if ($source_id > 0) {
                    $db->update('play_sources', ['name' => $name, 'url' => $url, 'is_default' => $is_default], 'id = ?', [$source_id]);
                } else {
                    $db->insert('play_sources', ['name' => $name, 'url' => $url, 'is_default' => $is_default]);
                }
                $message = '播放源已保存';
            }
            break;
        
        case 'publish_announcement':
            $title = trim($_POST['ann_title'] ?? '');
            $content = trim($_POST['ann_content'] ?? '');
            if ($title && $content) {
                $ann_id = $db->insert('announcements', [
                    'title' => $title,
                    'content' => $content,
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $db->update('settings', ['announcement_id' => $ann_id], 'id = 1');
                $message = '公告已发布';
            }
            break;
        
        case 'send_mail':
            $to = trim($_POST['mail_to'] ?? '');
            $subject = trim($_POST['mail_subject'] ?? '');
            $content = trim($_POST['mail_content'] ?? '');
            if ($to && $subject && $content) {
                $mailer = new Mailer();
                $html = '<div style="max-width:600px;margin:0 auto;padding:20px;font-family:Arial,sans-serif;">' . $content . '</div>';
                if ($mailer->send($to, $subject, $html)) {
                    $message = '邮件发送成功';
                } else {
                    $error = '邮件发送失败';
                }
            }
            break;
        
        case 'reply_feedback':
            $fid = (int)$_POST['feedback_id'];
            $reply = trim($_POST['admin_reply'] ?? '');
            if ($fid > 0 && $reply) {
                $db->update('feedbacks', [
                    'admin_reply' => $reply,
                    'replied_at' => date('Y-m-d H:i:s'),
                    'status' => 'replied'
                ], 'id = ?', [$fid]);
                $message = '回复已保存';
            }
            break;
    }
}

$stats = [
    'users' => $db->fetch("SELECT COUNT(*) as cnt FROM users")['cnt'],
    'feedbacks' => $db->fetch("SELECT COUNT(*) as cnt FROM feedbacks")['cnt'],
    'favorites' => $db->fetch("SELECT COUNT(*) as cnt FROM favorites")['cnt'],
    'history' => $db->fetch("SELECT COUNT(*) as cnt FROM watch_history")['cnt'],
];

$recent_users = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recent_feedbacks = $db->fetchAll("SELECT f.*, u.username FROM feedbacks f JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC LIMIT 5");
$recent_history = $db->fetchAll("SELECT h.*, u.username FROM watch_history h JOIN users u ON h.user_id = u.id ORDER BY h.watched_at DESC LIMIT 10");
$recent_favorites = $db->fetchAll("SELECT fav.*, u.username FROM favorites fav JOIN users u ON fav.user_id = u.id ORDER BY fav.created_at DESC LIMIT 10");

require_once __DIR__ . '/admin_header.php';
?>

<div class="admin-content">
    <?php if ($message): ?>
    <div style="padding:12px 16px;background:rgba(74,222,128,0.1);border:1px solid rgba(74,222,128,0.3);color:#86efac;border-radius:10px;margin-bottom:20px;"><?= esc($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div style="padding:12px 16px;background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.3);color:#fca5a5;border-radius:10px;margin-bottom:20px;"><?= esc($error) ?></div>
    <?php endif; ?>
    
    <?php if ($admin_page === 'dashboard'): ?>
    <div class="admin-header">
        <h1>仪表盘</h1>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon users">👥</div>
            <div class="stat-info">
                <h3><?= $stats['users'] ?></h3>
                <p>注册用户</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon feedbacks">💬</div>
            <div class="stat-info">
                <h3><?= $stats['feedbacks'] ?></h3>
                <p>反馈数量</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon favorites">❤️</div>
            <div class="stat-info">
                <h3><?= $stats['favorites'] ?></h3>
                <p>收藏总数</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon history">🕐</div>
            <div class="stat-info">
                <h3><?= $stats['history'] ?></h3>
                <p>观看记录</p>
            </div>
        </div>
    </div>
    
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:20px;">
        <div style="background:var(--bg2);border-radius:var(--radius-lg);padding:20px;">
            <h3 style="margin-bottom:16px;font-size:16px;">最新注册用户</h3>
            <div class="table-container" style="margin:0;">
                <table class="data-table">
                    <thead><tr><th>用户名</th><th>邮箱</th><th>注册时间</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_users as $u): ?>
                        <tr>
                            <td>
                                <span style="display:flex;align-items:center;gap:8px;">
                                    <span class="user-avatar" style="width:28px;height:28px;font-size:12px;"><?= mb_substr($u['username'], 0, 1) ?></span>
                                    <?= esc($u['username']) ?>
                                    <?php if ($u['is_admin']): ?><span class="reply-admin-badge">管理员</span><?php endif; ?>
                                </span>
                            </td>
                            <td style="font-size:13px;color:var(--text2);"><?= esc($u['email']) ?></td>
                            <td style="font-size:13px;color:var(--text3);"><?= time_ago($u['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:12px;text-align:center;">
                <a href="?page=users" class="btn btn-ghost btn-sm">查看全部用户</a>
            </div>
        </div>
        
        <div style="background:var(--bg2);border-radius:var(--radius-lg);padding:20px;">
            <h3 style="margin-bottom:16px;font-size:16px;">最新反馈</h3>
            <div class="table-container" style="margin:0;">
                <table class="data-table">
                    <thead><tr><th>标题</th><th>用户</th><th>状态</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_feedbacks as $f): ?>
                        <tr>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= esc($f['title']) ?></td>
                            <td style="font-size:13px;"><?= esc($f['username']) ?></td>
                            <td>
                                <?php if ($f['admin_reply']): ?>
                                <span class="badge badge-success">已回复</span>
                                <?php else: ?>
                                <span class="badge badge-warning">待处理</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:12px;text-align:center;">
                <a href="?page=feedbacks" class="btn btn-ghost btn-sm">查看全部反馈</a>
            </div>
        </div>
    </div>
    
    <?php elseif ($admin_page === 'users'):
    $filter_user = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
    $users = $filter_user ? $db->fetchAll("SELECT * FROM users WHERE id = ?", [$filter_user]) : $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC LIMIT 100");
    ?>
    <div class="admin-header">
        <h1>用户管理</h1>
        <form method="get" style="display:flex;gap:10px;">
            <input type="hidden" name="page" value="users">
            <input type="number" name="uid" placeholder="用户ID筛选" class="form-control" style="width:150px;padding:8px 12px;" value="<?= $filter_user ?: '' ?>">
            <button type="submit" class="btn btn-primary btn-sm">筛选</button>
        </form>
    </div>
    
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>用户</th>
                    <th>邮箱</th>
                    <th>状态</th>
                    <th>注册时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td>
                        <span style="display:flex;align-items:center;gap:8px;">
                            <span class="user-avatar" style="width:32px;height:32px;font-size:13px;"><?= mb_substr($u['username'], 0, 1) ?></span>
                            <?= esc($u['username']) ?>
                            <?php if ($u['is_admin']): ?><span class="dev-badge" style="position:static;font-size:10px;">开发者</span><?php endif; ?>
                        </span>
                    </td>
                    <td><?= esc($u['email']) ?></td>
                    <td>
                        <?php if ($u['is_banned']): ?>
                        <span class="badge badge-danger">已封禁</span>
                        <?php else: ?>
                        <span class="badge badge-success">正常</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px;"><?= $u['created_at'] ?></td>
                    <td>
                        <?php if (!$u['is_admin']): ?>
                            <?php if ($u['is_banned']): ?>
                            <button class="btn btn-outline btn-sm" onclick="unbanUser(<?= $u['id'] ?>)">解封</button>
                            <?php else: ?>
                            <button class="btn btn-ghost btn-sm" onclick="showBanModal(<?= $u['id'] ?>, '<?= esc($u['username']) ?>')">封禁</button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button class="btn btn-ghost btn-sm" onclick="location.href='?page=history&uid=<?= $u['id'] ?>'">历史</button>
                        <button class="btn btn-ghost btn-sm" onclick="location.href='?page=favorites&uid=<?= $u['id'] ?>'">收藏</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="modal-overlay" id="banModal">
        <div class="modal">
            <div class="modal-header">
                <h3>封禁用户</h3>
                <button class="modal-close" onclick="document.getElementById('banModal').classList.remove('active')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" id="banForm">
                    <p style="margin-bottom:16px;">封禁用户：<strong id="banUserName"></strong></p>
                    <div class="form-group">
                        <label>封禁原因</label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="请输入封禁原因"></textarea>
                    </div>
                    <div class="form-group">
                        <label>封禁开始时间</label>
                        <input type="datetime-local" name="ban_start" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                    <div class="form-group">
                        <label>解封时间（留空为永久封禁）</label>
                        <input type="datetime-local" name="ban_end" class="form-control">
                    </div>
                    <input type="hidden" name="user_id" id="banUserId">
                    <button type="button" class="btn btn-danger btn-block btn-lg" onclick="submitBan()" style="background:var(--danger);">确认封禁</button>
                </form>
            </div>
        </div>
    </div>
    
    <?php elseif ($admin_page === 'history'):
    $uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
    $histories = $uid ? $db->fetchAll("SELECT h.*, u.username FROM watch_history h JOIN users u ON h.user_id = u.id WHERE h.user_id = ? ORDER BY h.watched_at DESC LIMIT 200", [$uid]) : $db->fetchAll("SELECT h.*, u.username FROM watch_history h JOIN users u ON h.user_id = u.id ORDER BY h.watched_at DESC LIMIT 100");
    ?>
    <div class="admin-header">
        <h1>观看历史<?= $uid ? ' - 用户 #' . $uid : '' ?></h1>
        <?php if ($uid): ?><a href="?page=users" class="btn btn-ghost btn-sm">返回用户列表</a><?php endif; ?>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead><tr><th>用户</th><th>影片</th><th>季/集</th><th>观看时间</th></tr></thead>
            <tbody>
                <?php foreach ($histories as $h): ?>
                <tr>
                    <td><?= esc($h['username']) ?></td>
                    <td><?= esc($h['title']) ?></td>
                    <td><?= $h['season'] ? 'S' . $h['season'] . 'E' . $h['episode'] : '电影' ?></td>
                    <td><?= $h['watched_at'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php elseif ($admin_page === 'favorites'):
    $uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
    $favs = $uid ? $db->fetchAll("SELECT f.*, u.username FROM favorites f JOIN users u ON f.user_id = u.id WHERE f.user_id = ? ORDER BY f.created_at DESC", [$uid]) : $db->fetchAll("SELECT f.*, u.username FROM favorites f JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC LIMIT 200");
    ?>
    <div class="admin-header">
        <h1>用户收藏<?= $uid ? ' - 用户 #' . $uid : '' ?></h1>
        <?php if ($uid): ?><a href="?page=users" class="btn btn-ghost btn-sm">返回用户列表</a><?php endif; ?>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead><tr><th>用户</th><th>影片</th><th>类型</th><th>收藏时间</th></tr></thead>
            <tbody>
                <?php foreach ($favs as $f): ?>
                <tr>
                    <td><?= esc($f['username']) ?></td>
                    <td><?= esc($f['title']) ?></td>
                    <td><?= media_type_label($f['media_type']) ?></td>
                    <td><?= $f['created_at'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php elseif ($admin_page === 'sources'):
    $sources = get_play_sources();
    $edit_source = null;
    if (isset($_GET['edit'])) {
        $edit_id = (int)$_GET['edit'];
        $edit_source = $db->fetch("SELECT * FROM play_sources WHERE id = ?", [$edit_id]);
    }
    ?>
    <div class="admin-header">
        <h1>播放源管理</h1>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('sourceForm').style.display='block'">+ 添加播放源</button>
    </div>
    
    <div id="sourceForm" style="background:var(--bg2);border-radius:var(--radius-lg);padding:24px;margin-bottom:20px;display:<?= $edit_source ? 'block' : 'none' ?>;">
        <h3 style="margin-bottom:20px;"><?= $edit_source ? '编辑播放源' : '添加播放源' ?></h3>
        <form method="post">
            <input type="hidden" name="form_action" value="save_source">
            <input type="hidden" name="source_id" value="<?= $edit_source['id'] ?? 0 ?>">
            <div class="form-group">
                <label>名称</label>
                <input type="text" name="name" class="form-control" required value="<?= esc($edit_source['name'] ?? '') ?>" placeholder="如：默认源">
            </div>
            <div class="form-group">
                <label>接口地址</label>
                <input type="text" name="url" class="form-control" required value="<?= esc($edit_source['url'] ?? '') ?>" placeholder="https://api.example.com/inc/apijson.php">
            </div>
            <div class="form-group" style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="is_default" id="is_default" <?= ($edit_source['is_default'] ?? false) ? 'checked' : '' ?>>
                <label for="is_default" style="margin:0;cursor:pointer;">设为默认播放源</label>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary">保存</button>
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('sourceForm').style.display='none'">取消</button>
            </div>
        </form>
    </div>
    
    <div class="table-container">
        <table class="data-table">
            <thead><tr><th>ID</th><th>名称</th><th>地址</th><th>默认</th><th>操作</th></tr></thead>
            <tbody>
                <?php foreach ($sources as $s): ?>
                <tr>
                    <td><?= $s['id'] ?></td>
                    <td><?= esc($s['name']) ?></td>
                    <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;font-size:13px;"><?= esc($s['url']) ?></td>
                    <td><?= $s['is_default'] ? '<span class="badge badge-success">默认</span>' : '' ?></td>
                    <td>
                        <a href="?page=sources&edit=<?= $s['id'] ?>" class="btn btn-ghost btn-sm">编辑</a>
                        <button class="btn btn-ghost btn-sm" style="color:var(--danger);" onclick="deleteSource(<?= $s['id'] ?>)">删除</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php elseif ($admin_page === 'mail'): ?>
    <div class="admin-header">
        <h1>邮件推送</h1>
    </div>
    <div style="max-width:600px;background:var(--bg2);border-radius:var(--radius-lg);padding:30px;">
        <form method="post">
            <input type="hidden" name="form_action" value="send_mail">
            <div class="form-group">
                <label>收件人邮箱</label>
                <input type="email" name="mail_to" class="form-control" required placeholder="user@example.com">
            </div>
            <div class="form-group">
                <label>邮件主题</label>
                <input type="text" name="mail_subject" class="form-control" required placeholder="邮件标题">
            </div>
            <div class="form-group">
                <label>邮件内容（支持HTML）</label>
                <textarea name="mail_content" class="form-control" rows="10" required placeholder="<h1>您好！</h1><p>这是一封邮件...</p>"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-lg">发送邮件</button>
        </form>
    </div>
    
    <?php elseif ($admin_page === 'announcement'): ?>
    <div class="admin-header">
        <h1>网站公告</h1>
    </div>
    <div style="max-width:600px;background:var(--bg2);border-radius:var(--radius-lg);padding:30px;">
        <form method="post">
            <input type="hidden" name="form_action" value="publish_announcement">
            <div class="form-group">
                <label>公告标题</label>
                <input type="text" name="ann_title" class="form-control" required placeholder="如：网站更新通知">
            </div>
            <div class="form-group">
                <label>公告内容</label>
                <textarea name="ann_content" class="form-control" rows="6" required placeholder="请输入公告内容..."></textarea>
                <p class="form-hint">发布新公告后，所有用户进入首页时会重新弹出公告弹窗</p>
            </div>
            <button type="submit" class="btn btn-primary btn-lg">发布公告</button>
        </form>
        
        <div style="margin-top:30px;padding-top:20px;border-top:1px solid var(--border);">
            <h4 style="margin-bottom:12px;">历史公告</h4>
            <?php
            $anns = $db->fetchAll("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 10");
            foreach ($anns as $a):
            $is_active = (setting('announcement_id') == $a['id']);
            ?>
            <div style="padding:12px;background:var(--card);border-radius:var(--radius);margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <strong><?= esc($a['title']) ?></strong>
                    <span style="color:var(--text3);font-size:12px;margin-left:8px;"><?= $a['created_at'] ?></span>
                    <?php if ($is_active): ?><span class="badge badge-success" style="margin-left:8px;">当前显示</span><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php elseif ($admin_page === 'settings'):
    $s = get_settings();
    $colors = ['#e94560', '#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#06b6d4'];
    ?>
    <div class="admin-header">
        <h1>网站设置</h1>
    </div>
    <div style="max-width:600px;background:var(--bg2);border-radius:var(--radius-lg);padding:30px;">
        <form method="post">
            <input type="hidden" name="form_action" value="save_settings">
            <div class="form-group">
                <label>网站名称</label>
                <input type="text" name="site_name" class="form-control" required value="<?= esc($s['site_name']) ?>">
            </div>
            <div class="form-group">
                <label>主题颜色</label>
                <div class="color-picker" id="colorPicker">
                    <?php foreach ($colors as $c): ?>
                    <div class="color-option <?= $s['theme_color'] === $c ? 'active' : '' ?>" data-color="<?= $c ?>" style="background:<?= $c ?>;" onclick="selectColor(this, '<?= $c ?>')"></div>
                    <?php endforeach; ?>
                    <div style="width:36px;height:36px;border-radius:50%;border:2px dashed var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;">
                        <input type="color" id="customColor" style="position:absolute;inset:0;opacity:0;cursor:pointer;" onchange="selectCustomColor(this.value)">
                        <span style="font-size:18px;">+</span>
                    </div>
                </div>
                <input type="hidden" name="theme_color" id="themeColor" value="<?= esc($s['theme_color']) ?>">
                <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
                    <span style="font-size:13px;color:var(--text2);">当前：</span>
                    <span id="colorPreview" style="width:24px;height:24px;border-radius:6px;background:<?= esc($s['theme_color']) ?>;"></span>
                    <code id="colorValue" style="color:var(--text2);font-size:13px;"><?= esc($s['theme_color']) ?></code>
                </div>
            </div>
            <div class="form-group">
                <label>TMDB API Key</label>
                <input type="text" name="tmdb_key" class="form-control" value="<?= esc($s['tmdb_api_key']) ?>" placeholder="用于获取影视元数据">
            </div>
            <div class="form-group">
                <label>解析播放器地址</label>
                <input type="text" name="parse_url" class="form-control" value="<?= esc($s['parse_url']) ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-lg">保存设置</button>
        </form>
    </div>
    
    <?php elseif ($admin_page === 'feedbacks'):
    $all_feedbacks = $db->fetchAll("SELECT f.*, u.username, u.email FROM feedbacks f JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC LIMIT 100");
    ?>
    <div class="admin-header">
        <h1>反馈管理</h1>
    </div>
    
    <?php foreach ($all_feedbacks as $f):
    $replies = $db->fetchAll("SELECT r.*, u.username FROM feedback_replies r JOIN users u ON r.user_id = u.id WHERE r.feedback_id = ?", [$f['id']]);
    ?>
    <div class="feedback-card" style="margin-bottom:20px;">
        <div class="feedback-header">
            <div class="user-avatar" style="width:36px;height:36px;font-size:14px;"><?= mb_substr($f['username'], 0, 1) ?></div>
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <strong><?= esc($f['title']) ?></strong>
                    <?php if ($f['admin_reply']): ?>
                    <span class="badge badge-success">已回复</span>
                    <?php else: ?>
                    <span class="badge badge-warning">待处理</span>
                    <?php endif; ?>
                    <?php if (!$f['is_public']): ?>
                    <span class="badge badge-danger">私密</span>
                    <?php endif; ?>
                </div>
                <div class="feedback-meta">
                    <span><?= esc($f['username']) ?> (<?= esc($f['email']) ?>)</span>
                    <span><?= $f['created_at'] ?></span>
                    <span>👍 <?= $f['likes'] ?></span>
                </div>
            </div>
        </div>
        <div class="feedback-content"><?= nl2br(esc($f['content'])) ?></div>
        
        <?php if (!empty($replies)): ?>
        <div style="margin:12px 0;padding:12px;background:var(--card);border-radius:var(--radius);">
            <p style="font-size:13px;color:var(--text2);margin-bottom:8px;">用户回复 (<?= count($replies) ?>)：</p>
            <?php foreach ($replies as $r): ?>
            <div style="padding:8px 0;border-bottom:1px solid var(--border);font-size:14px;">
                <strong><?= esc($r['username']) ?><?= $r['is_admin'] ? '<span class="reply-admin-badge">管理员</span>' : '' ?></strong>
                <span style="color:var(--text3);font-size:12px;margin-left:8px;"><?= $r['created_at'] ?></span>
                <p style="color:var(--text2);margin-top:4px;"><?= esc($r['content']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <form method="post" style="margin-top:16px;">
            <input type="hidden" name="form_action" value="reply_feedback">
            <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
            <div class="form-group">
                <label><?= $f['admin_reply'] ? '修改回复' : '管理员回复' ?></label>
                <textarea name="admin_reply" class="form-control" rows="3" placeholder="输入回复内容..."><?= esc($f['admin_reply'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">发送回复</button>
        </form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
