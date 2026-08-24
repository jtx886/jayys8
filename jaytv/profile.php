<?php
define('IN_SITE', true);
require_once __DIR__ . '/includes/init.php';
require_login();

$tab = $_GET['tab'] ?? 'profile';
$page_title = '个人中心';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username'] ?? '');
        if ($username && mb_strlen($username) >= 2) {
            $existing = get_user_by_username($username);
            if (!$existing || $existing['id'] == $current_user['id']) {
                $db->update('users', ['username' => $username], 'id = ?', [$current_user['id']]);
                $message = '资料更新成功';
                $current_user = get_user($current_user['id']);
            } else {
                $error = '用户名已被使用';
            }
        }
    }
}

$favorites = $db->fetchAll("SELECT * FROM favorites WHERE user_id = ? ORDER BY created_at DESC", [$current_user['id']]);
$history = $db->fetchAll("SELECT * FROM watch_history WHERE user_id = ? ORDER BY watched_at DESC LIMIT 100", [$current_user['id']]);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="profile-layout">
        <div class="profile-sidebar">
            <div style="text-align: center; padding-bottom: 20px; border-bottom: 1px solid var(--border);">
                <div style="position: relative; width: 80px; height: 80px; margin: 0 auto 12px;">
                    <div class="user-avatar" style="width:80px; height:80px; font-size:28px;" id="avatarContainer">
                        <?php if ($current_user['avatar'] && $current_user['avatar'] !== 'default.png' && file_exists(UPLOAD_PATH . 'avatars/' . $current_user['avatar'])): ?>
                            <img src="uploads/avatars/<?= esc($current_user['avatar']) ?>" id="avatarImg" alt="">
                        <?php else: ?>
                            <span id="avatarText"><?= mb_substr($current_user['username'], 0, 1) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <h3 style="margin-bottom: 4px;"><?= esc($current_user['username']) ?></h3>
                <p style="color: var(--text3); font-size: 13px;"><?= esc($current_user['email']) ?></p>
                <form id="avatarForm" style="margin-top: 12px;">
                    <label class="btn btn-outline btn-sm" style="cursor:pointer;">
                        更换头像
                        <input type="file" accept="image/*" style="display:none;" id="avatarInput">
                    </label>
                </form>
            </div>
            <nav class="profile-menu" style="margin-top: 10px;">
                <a href="profile.php?tab=profile" class="<?= $tab === 'profile' ? 'active' : '' ?>">
                    <span class="icon">👤</span> 个人资料
                </a>
                <a href="profile.php?tab=favorites" class="<?= $tab === 'favorites' ? 'active' : '' ?>">
                    <span class="icon">❤️</span> 我的收藏
                    (<?= count($favorites) ?>)
                </a>
                <a href="profile.php?tab=history" class="<?= $tab === 'history' ? 'active' : '' ?>">
                    <span class="icon">🕐</span> 观看历史
                    (<?= count($history) ?>)
                </a>
                <a href="feedback.php" class="">
                    <span class="icon">💬</span> 意见反馈
                </a>
                <?php if (is_admin()): ?>
                <a href="admin/">
                    <span class="icon">⚙️</span> 管理后台
                </a>
                <?php endif; ?>
                <a href="logout.php">
                    <span class="icon">🚪</span> 退出登录
                </a>
            </nav>
        </div>
        
        <div class="profile-content">
            <?php if ($message): ?>
            <div class="alert alert-success" style="padding:12px 16px;background:rgba(74,222,128,0.1);border:1px solid rgba(74,222,128,0.3);color:#86efac;border-radius:10px;margin-bottom:20px;"><?= esc($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-error" style="padding:12px 16px;background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.3);color:#fca5a5;border-radius:10px;margin-bottom:20px;"><?= esc($error) ?></div>
            <?php endif; ?>
            
            <?php if ($tab === 'profile'): ?>
            <h2 style="margin-bottom: 24px;">个人资料</h2>
            <form method="post" style="max-width: 400px;">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" name="username" class="form-control" value="<?= esc($current_user['username']) ?>" required>
                </div>
                <div class="form-group">
                    <label>邮箱</label>
                    <input type="email" class="form-control" value="<?= esc($current_user['email']) ?>" disabled>
                    <p class="form-hint">邮箱不可修改</p>
                </div>
                <div class="form-group">
                    <label>注册时间</label>
                    <input type="text" class="form-control" value="<?= $current_user['created_at'] ?>" disabled>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary">保存修改</button>
            </form>
            
            <?php elseif ($tab === 'favorites'): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h2>我的收藏</h2>
                <?php if (!empty($favorites)): ?>
                <button class="btn btn-ghost btn-sm" onclick="clearAll('favorites')">清空全部</button>
                <?php endif; ?>
            </div>
            <?php if (empty($favorites)): ?>
            <div class="empty-state">
                <div class="icon">💔</div>
                <p>还没有收藏任何影视</p>
                <a href="index.php" class="btn btn-primary" style="margin-top: 16px;">去发现好看的</a>
            </div>
            <?php else: ?>
            <div class="media-grid">
                <?php foreach ($favorites as $fav): ?>
                <div style="position: relative;">
                    <a href="detail.php?id=<?= $fav['media_id'] ?>&type=<?= esc($fav['media_type']) ?>" class="media-card">
                        <div class="poster">
                            <?php if ($fav['poster']): ?>
                            <img src="<?= esc($fav['poster']) ?>" alt="<?= esc($fav['title']) ?>" loading="lazy">
                            <?php else: ?>
                            <div style="width:100%;height:100%;background:var(--bg3);display:flex;align-items:center;justify-content:center;font-size:50px;">🎬</div>
                            <?php endif; ?>
                            <span class="play-btn"></span>
                        </div>
                        <div class="info">
                            <div class="title"><?= esc($fav['title']) ?></div>
                            <div class="meta"><?= time_ago($fav['created_at']) ?>收藏</div>
                        </div>
                    </a>
                    <button class="favorite-btn active" style="position:absolute;top:10px;right:10px;" onclick="deleteFav(<?= $fav['id'] ?>, this)">
                        <span class="heart-icon"></span>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php elseif ($tab === 'history'): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h2>观看历史</h2>
                <?php if (!empty($history)): ?>
                <button class="btn btn-ghost btn-sm" onclick="clearAll('history')">清空全部</button>
                <?php endif; ?>
            </div>
            <?php if (empty($history)): ?>
            <div class="empty-state">
                <div class="icon">📺</div>
                <p>还没有观看记录</p>
                <a href="index.php" class="btn btn-primary" style="margin-top: 16px;">去发现好看的</a>
            </div>
            <?php else: ?>
            <div class="media-grid">
                <?php foreach ($history as $h): ?>
                <div style="position: relative;">
                    <a href="detail.php?id=<?= $h['media_id'] ?>&type=<?= esc($h['media_type']) ?>" class="media-card">
                        <div class="poster">
                            <?php if ($h['poster']): ?>
                            <img src="<?= esc($h['poster']) ?>" alt="<?= esc($h['title']) ?>" loading="lazy">
                            <?php else: ?>
                            <div style="width:100%;height:100%;background:var(--bg3);display:flex;align-items:center;justify-content:center;font-size:50px;">🎬</div>
                            <?php endif; ?>
                            <span class="play-btn"></span>
                        </div>
                        <div class="info">
                            <div class="title"><?= esc($h['title']) ?></div>
                            <div class="meta">
                                看过 · <?= time_ago($h['watched_at']) ?>
                                <?php if ($h['episode']): ?> · 第<?= $h['episode'] ?>集<?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <button class="icon-btn" style="position:absolute;top:10px;right:10px;width:28px;height:28px;font-size:14px;background:rgba(0,0,0,0.6);" onclick="deleteHistory(<?= $h['id'] ?>, this)">✕</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const avatarInput = document.getElementById('avatarInput');
if (avatarInput) {
    avatarInput.addEventListener('change', async function() {
        if (!this.files[0]) return;
        const formData = new FormData();
        formData.append('avatar', this.files[0]);
        formData.append('action', 'upload_avatar');
        const res = await fetch('ajax.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.code === 200) {
            JayTV.toast('头像上传成功');
            setTimeout(() => location.reload(), 800);
        } else {
            JayTV.toast(data.msg || '上传失败', 'error');
        }
    });
}

async function deleteFav(id, btn) {
    const formData = new FormData();
    formData.append('action', 'delete_favorite');
    formData.append('id', id);
    const res = await fetch('ajax.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.code === 200) {
        btn.closest('div[style*="position: relative"]').style.opacity = '0';
        setTimeout(() => location.reload(), 300);
    }
}

async function deleteHistory(id, btn) {
    const formData = new FormData();
    formData.append('action', 'delete_history');
    formData.append('id', id);
    const res = await fetch('ajax.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.code === 200) {
        btn.closest('div[style*="position: relative"]').style.opacity = '0';
        setTimeout(() => location.reload(), 300);
    }
}

async function clearAll(type) {
    if (!confirm('确定要清空全部' + (type === 'favorites' ? '收藏' : '历史') + '吗？')) return;
    const formData = new FormData();
    formData.append('action', type === 'favorites' ? 'delete_favorite' : 'delete_history');
    const res = await fetch('ajax.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.code === 200) location.reload();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
