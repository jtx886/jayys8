<?php
$page_title = '意见反馈';
require_once __DIR__ . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    if (!is_logged_in()) {
        $error = '请先登录';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $is_public = isset($_POST['is_public']) ? 1 : 1;
        
        if (!$title || mb_strlen($title) < 2) {
            $error = '请输入标题(至少2字)';
        } elseif (!$content || mb_strlen($content) < 5) {
            $error = '请输入反馈内容(至少5字)';
        } else {
            $db->insert('feedbacks', [
                'user_id' => $current_user['id'],
                'title' => $title,
                'content' => $content,
                'is_public' => $is_public,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $success = '反馈提交成功！感谢您的宝贵意见。';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reply'])) {
    require_login();
    $feedback_id = (int)$_POST['feedback_id'];
    $reply_content = trim($_POST['reply_content'] ?? '');
    if ($feedback_id > 0 && $reply_content) {
        $db->insert('feedback_replies', [
            'feedback_id' => $feedback_id,
            'user_id' => $current_user['id'],
            'content' => $reply_content,
            'is_admin' => is_admin() ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        header('Location: feedback.php#f_' . $feedback_id);
        exit;
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$feedbacks = $db->fetchAll("SELECT f.*, u.username, u.avatar as user_avatar, u.is_admin as user_is_admin 
    FROM feedbacks f 
    JOIN users u ON f.user_id = u.id 
    WHERE f.is_public = 1
    ORDER BY f.created_at DESC 
    LIMIT $per_page OFFSET $offset");

$total = $db->fetch("SELECT COUNT(*) as cnt FROM feedbacks WHERE is_public = 1")['cnt'];
$total_pages = ceil($total / $per_page);
?>

<div class="container" style="padding-top: 30px;">
    <div class="section-header">
        <h2 class="section-title">💬 意见反馈</h2>
        <?php if (is_logged_in()): ?>
        <button class="btn btn-primary btn-sm" data-modal="feedback-modal">+ 提交反馈</button>
        <?php else: ?>
        <button class="btn btn-primary btn-sm" onclick="openModal('login-modal')">登录后反馈</button>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($success)): ?>
    <div style="padding:14px 16px;background:rgba(74,222,128,0.1);border:1px solid rgba(74,222,128,0.3);color:#86efac;border-radius:10px;margin-bottom:20px;">
        <?= esc($success) ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    <div style="padding:14px 16px;background:rgba(248,113,113,0.1);border:1px solid rgba(248,113,113,0.3);color:#fca5a5;border-radius:10px;margin-bottom:20px;">
        <?= esc($error) ?>
    </div>
    <?php endif; ?>
    
    <div class="feedback-list">
        <?php if (empty($feedbacks)): ?>
        <div class="empty-state">
            <div class="icon">💭</div>
            <p>还没有反馈，来做第一个提意见的人吧！</p>
        </div>
        <?php else: ?>
            <?php foreach ($feedbacks as $f): 
            $user_liked = false;
            $like_count = (int)$f['likes'];
            if (is_logged_in()) {
                $user_liked = (bool)$db->fetch("SELECT id FROM feedback_likes WHERE feedback_id = ? AND user_id = ?", [$f['id'], $current_user['id']]);
            }
            
            $replies = $db->fetchAll("SELECT r.*, u.username, u.avatar, u.is_admin 
                FROM feedback_replies r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.feedback_id = ? 
                ORDER BY r.is_admin DESC, r.created_at ASC", [$f['id']]);
            
            $reply_count = count($replies);
            $show_all = isset($_GET['show_' . $f['id']]);
            $visible_replies = $show_all ? $replies : array_slice($replies, 0, 3);
            $has_more = $reply_count > 3;
            ?>
            <div class="feedback-card" id="f_<?= $f['id'] ?>">
                <div class="feedback-header">
                    <div class="user-avatar" style="width:40px;height:40px;font-size:14px;flex-shrink:0;">
                        <?php if ($f['user_avatar'] && $f['user_avatar'] !== 'default.png' && file_exists(UPLOAD_PATH . 'avatars/' . $f['user_avatar'])): ?>
                            <img src="uploads/avatars/<?= esc($f['user_avatar']) ?>" alt="">
                        <?php else: ?>
                            <?= mb_substr($f['username'], 0, 1) ?>
                        <?php endif; ?>
                        <?php if ($f['user_is_admin']): ?>
                            <span class="dev-badge" style="font-size:8px;padding:0 3px;">管理员</span>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;">
                        <div class="feedback-title">
                            <?= esc($f['title']) ?>
                            <?php if ($f['user_is_admin']): ?>
                            <span class="reply-admin-badge">官方</span>
                            <?php endif; ?>
                        </div>
                        <div class="feedback-meta">
                            <span><?= esc($f['username']) ?></span>
                            <span><?= time_ago($f['created_at']) ?></span>
                            <?php if ($f['admin_reply']): ?>
                            <span style="color: var(--success);">✓ 已回复</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="feedback-content"><?= nl2br(esc($f['content'])) ?></div>
                
                <?php if ($f['admin_reply']): ?>
                <div class="reply-item admin-reply">
                    <div class="user-avatar" style="width:32px;height:32px;font-size:12px;">
                        管
                        <span class="dev-badge" style="font-size:7px;">管理</span>
                    </div>
                    <div style="flex:1;">
                        <div style="font-weight:600;margin-bottom:4px;">
                            管理员回复 <span class="reply-admin-badge">官方</span>
                        </div>
                        <div style="color:var(--text2);font-size:14px;line-height:1.7;"><?= nl2br(esc($f['admin_reply'])) ?></div>
                        <div style="font-size:12px;color:var(--text3);margin-top:4px;"><?= time_ago($f['replied_at']) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($replies)): ?>
                <div class="reply-list">
                    <?php foreach ($visible_replies as $r): ?>
                    <div class="reply-item <?= $r['is_admin'] ? 'admin-reply' : '' ?>">
                        <div class="user-avatar" style="width:32px;height:32px;font-size:12px;flex-shrink:0;">
                            <?php if ($r['avatar'] && $r['avatar'] !== 'default.png' && file_exists(UPLOAD_PATH . 'avatars/' . $r['avatar'])): ?>
                                <img src="uploads/avatars/<?= esc($r['avatar']) ?>" alt="">
                            <?php else: ?>
                                <?= mb_substr($r['username'], 0, 1) ?>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:14px;margin-bottom:2px;">
                                <strong><?= esc($r['username']) ?></strong>
                                <?php if ($r['is_admin']): ?>
                                <span class="reply-admin-badge">管理员</span>
                                <?php endif; ?>
                                <span style="color:var(--text3);font-size:12px;margin-left:8px;"><?= time_ago($r['created_at']) ?></span>
                            </div>
                            <div style="color:var(--text2);font-size:14px;line-height:1.6;"><?= nl2br(esc($r['content'])) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if ($has_more && !$show_all): ?>
                    <div class="reply-expand" onclick="location.href='?show_<?= $f['id'] ?>=1#f_<?= $f['id'] ?>'">
                        展开全部 <?= $reply_count ?> 条回复 ▼
                    </div>
                    <?php elseif ($has_more && $show_all): ?>
                    <div class="reply-expand" onclick="location.href='feedback.php#f_<?= $f['id'] ?>'">
                        收起回复 ▲
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="feedback-actions">
                    <button class="feedback-action like-btn <?= $user_liked ? 'active' : '' ?>" data-id="<?= $f['id'] ?>">
                        <span>👍</span>
                        <span class="count"><?= $like_count ?></span>
                    </button>
                    <?php if (is_logged_in()): ?>
                    <button class="feedback-action" onclick="document.getElementById('reply-form-<?= $f['id'] ?>').style.display='block'">
                        <span>💬</span> 回复
                    </button>
                    <?php else: ?>
                    <button class="feedback-action" onclick="openModal('login-modal')">
                        <span>💬</span> 回复
                    </button>
                    <?php endif; ?>
                </div>
                
                <?php if (is_logged_in()): ?>
                <form method="post" id="reply-form-<?= $f['id'] ?>" style="margin-top:12px;display:none;">
                    <input type="hidden" name="feedback_id" value="<?= $f['id'] ?>">
                    <div style="display:flex;gap:10px;">
                        <input type="text" name="reply_content" class="form-control" placeholder="写下你的回复..." required style="flex:1;">
                        <button type="submit" name="submit_reply" class="btn btn-primary btn-sm">发送</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>">‹</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $page): ?>
            <span class="active"><?= $i ?></span>
            <?php else: ?>
            <a href="?page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page + 1 ?>">›</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (is_logged_in()): ?>
<div class="modal-overlay" id="feedback-modal">
    <div class="modal">
        <div class="modal-header">
            <h3>提交反馈</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="post">
                <div class="form-group">
                    <label>标题</label>
                    <input type="text" name="title" class="form-control" placeholder="简要描述问题或建议" required>
                </div>
                <div class="form-group">
                    <label>详细内容</label>
                    <textarea name="content" class="form-control" rows="5" placeholder="请详细描述您遇到的问题或建议..." required></textarea>
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="is_public" id="is_public" checked style="width:auto;">
                    <label for="is_public" style="margin:0;cursor:pointer;font-size:14px;color:var(--text2);">公开反馈（所有人可见）</label>
                </div>
                <button type="submit" name="submit_feedback" class="btn btn-primary btn-block btn-lg">提交反馈</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
