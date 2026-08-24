<?php
define('IN_SITE', true);
require_once __DIR__ . '/includes/init.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'send_code':
        $email = trim($_POST['email'] ?? '');
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(400, '请输入有效邮箱');
        }
        if (get_user_by_email($email)) {
            json_response(400, '该邮箱已被注册');
        }
        if (send_verify_code($email)) {
            json_response(200, '验证码已发送');
        } else {
            json_response(500, '发送失败，请稍后重试');
        }
        break;
    
    case 'register':
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        $code = trim($_POST['code'] ?? '');
        
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) json_response(400, '请输入有效邮箱');
        if (!$username || mb_strlen($username) < 2 || mb_strlen($username) > 20) json_response(400, '用户名长度2-20位');
        if (!$password || strlen($password) < 6) json_response(400, '密码至少6位');
        if ($password !== $password2) json_response(400, '两次密码不一致');
        if (!$code) json_response(400, '请输入验证码');
        
        if (get_user_by_email($email)) json_response(400, '该邮箱已被注册');
        if (get_user_by_username($username)) json_response(400, '该用户名已被使用');
        
        if (!verify_code($email, $code)) json_response(400, '验证码错误或已过期');
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $uid = $db->insert('users', [
            'email' => $email,
            'username' => $username,
            'password' => $hash,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($uid) {
            $_SESSION['user_id'] = $uid;
            json_response(200, '注册成功');
        } else {
            json_response(500, '注册失败，请重试');
        }
        break;
    
    case 'login':
        $account = trim($_POST['account'] ?? '');
        $password = $_POST['password'] ?? '';
        $redirect = $_POST['redirect'] ?? '';
        
        if (!$account || !$password) json_response(400, '请输入账号密码');
        
        $user = strpos($account, '@') !== false ? get_user_by_email($account) : get_user_by_username($account);
        if (!$user) json_response(400, '账号或密码错误');
        if ($user['is_banned'] && is_banned($user)) {
            $end_text = $user['ban_end'] ? date('Y-m-d H:i', strtotime($user['ban_end'])) : '永久';
            json_response(403, "账号已被封禁，原因：{$user['ban_reason']}，解封时间：$end_text");
        }
        if (!password_verify($password, $user['password'])) json_response(400, '账号或密码错误');
        
        $_SESSION['user_id'] = $user['id'];
        json_response(200, '登录成功', ['redirect' => $redirect ?: 'index.php']);
        break;
    
    case 'logout':
        session_destroy();
        json_response(200, '已退出');
        break;
    
    case 'favorite':
        require_login();
        $id = (int)($_POST['id'] ?? 0);
        $type = $_POST['type'] ?? 'movie';
        $do = $_POST['do'] ?? 'add';
        
        if ($id <= 0) json_response(400, '参数错误');
        
        $title = '';
        $poster = '';
        $media = get_tmdb_detail($id, $type);
        if ($media) {
            $title = $media['title'] ?? $media['name'] ?? '';
            $poster = tmdb_image($media['poster_path'], 'w200');
        }
        
        if ($do === 'add') {
            if (add_favorite($current_user['id'], $id, $type, $title, $poster)) {
                json_response(200, '已添加收藏');
            } else {
                json_response(400, '已在收藏中');
            }
        } else {
            remove_favorite($current_user['id'], $id, $type);
            json_response(200, '已取消收藏');
        }
        break;
    
    case 'like_feedback':
        require_login();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) json_response(400, '参数错误');
        
        $existing = $db->fetch("SELECT id FROM feedback_likes WHERE feedback_id = ? AND user_id = ?", [$id, $current_user['id']]);
        if ($existing) {
            $db->delete('feedback_likes', 'id = ?', [$existing['id']]);
            $db->query("UPDATE feedbacks SET likes = likes - 1 WHERE id = ?", [$id]);
            $liked = false;
        } else {
            $db->insert('feedback_likes', [
                'feedback_id' => $id,
                'user_id' => $current_user['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $db->query("UPDATE feedbacks SET likes = likes + 1 WHERE id = ?", [$id]);
            $liked = true;
        }
        $f = $db->fetch("SELECT likes FROM feedbacks WHERE id = ?", [$id]);
        json_response(200, 'ok', ['liked' => $liked, 'likes' => $f['likes'] ?? 0]);
        break;
    
    case 'submit_feedback':
        require_login();
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $is_public = isset($_POST['is_public']) ? 1 : 0;
        
        if (!$title || mb_strlen($title) < 2) json_response(400, '请输入标题');
        if (!$content || mb_strlen($content) < 5) json_response(400, '请输入内容(至少5字)');
        
        $db->insert('feedbacks', [
            'user_id' => $current_user['id'],
            'title' => $title,
            'content' => $content,
            'is_public' => $is_public,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        json_response(200, '提交成功，感谢您的反馈！');
        break;
    
    case 'reply_feedback':
        require_login();
        $id = (int)($_POST['id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        
        if ($id <= 0 || !$content) json_response(400, '参数错误');
        $feedback = $db->fetch("SELECT * FROM feedbacks WHERE id = ?", [$id]);
        if (!$feedback) json_response(404, '反馈不存在');
        
        $db->insert('feedback_replies', [
            'feedback_id' => $id,
            'user_id' => $current_user['id'],
            'content' => $content,
            'is_admin' => is_admin() ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        json_response(200, '回复成功');
        break;
    
    case 'upload_avatar':
        require_login();
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            json_response(400, '请选择图片');
        }
        
        $file = $_FILES['avatar'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed)) json_response(400, '仅支持JPG/PNG/GIF/WEBP格式');
        if ($file['size'] > 2 * 1024 * 1024) json_response(400, '图片大小不超过2MB');
        
        $dir = UPLOAD_PATH . 'avatars/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        $filename = $current_user['id'] . '_' . time() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            $db->update('users', ['avatar' => $filename], 'id = ?', [$current_user['id']]);
            json_response(200, '头像上传成功', ['avatar' => 'uploads/avatars/' . $filename]);
        } else {
            json_response(500, '上传失败');
        }
        break;
    
    case 'delete_history':
        require_login();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->delete('watch_history', 'id = ? AND user_id = ?', [$id, $current_user['id']]);
        } else {
            $db->delete('watch_history', 'user_id = ?', [$current_user['id']]);
        }
        json_response(200, '已删除');
        break;
    
    case 'delete_favorite':
        require_login();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->delete('favorites', 'id = ? AND user_id = ?', [$id, $current_user['id']]);
        } else {
            $db->delete('favorites', 'user_id = ?', [$current_user['id']]);
        }
        json_response(200, '已删除');
        break;
    
    case 'admin_ban_user':
        require_admin();
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $ban_start = $_POST['ban_start'] ?? date('Y-m-d H:i:s');
        $ban_end = !empty($_POST['ban_end']) ? $_POST['ban_end'] : null;
        
        if ($id <= 0 || !$reason) json_response(400, '参数错误');
        $user = get_user($id);
        if (!$user) json_response(404, '用户不存在');
        
        $db->update('users', [
            'is_banned' => 1,
            'ban_reason' => $reason,
            'ban_start' => $ban_start,
            'ban_end' => $ban_end
        ], 'id = ?', [$id]);
        
        $mailer = new Mailer();
        $subject = '账号封禁通知 - ' . setting('site_name');
        $html = Mailer::ban_notice_template($user['username'], $reason, $ban_start, $ban_end ?: '永久');
        $mailer->send($user['email'], $subject, $html);
        
        json_response(200, '封禁成功，通知邮件已发送');
        break;
    
    case 'admin_unban_user':
        require_admin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) json_response(400, '参数错误');
        $db->update('users', [
            'is_banned' => 0,
            'ban_reason' => null,
            'ban_start' => null,
            'ban_end' => null
        ], 'id = ?', [$id]);
        json_response(200, '已解封');
        break;
    
    case 'admin_save_source':
        require_admin();
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        if (!$name || !$url) json_response(400, '请填写完整');
        
        if ($is_default) {
            $db->query("UPDATE play_sources SET is_default = 0");
        }
        
        if ($id > 0) {
            $db->update('play_sources', ['name' => $name, 'url' => $url, 'is_default' => $is_default], 'id = ?', [$id]);
        } else {
            $id = $db->insert('play_sources', ['name' => $name, 'url' => $url, 'is_default' => $is_default]);
        }
        json_response(200, '保存成功');
        break;
    
    case 'admin_delete_source':
        require_admin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) $db->delete('play_sources', 'id = ?', [$id]);
        json_response(200, '已删除');
        break;
    
    case 'admin_send_mail':
        require_admin();
        $to = trim($_POST['to'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $content = trim($_POST['content'] ?? '');
        
        if (!$to || !$subject || !$content) json_response(400, '请填写完整');
        
        $mailer = new Mailer();
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">' . $content . '</body></html>';
        
        if ($mailer->send($to, $subject, $html)) {
            json_response(200, '发送成功');
        } else {
            json_response(500, '发送失败');
        }
        break;
    
    case 'admin_save_announcement':
        require_admin();
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (!$title || !$content) json_response(400, '请填写标题和内容');
        
        $id = $db->insert('announcements', [
            'title' => $title,
            'content' => $content,
            'is_active' => $is_active,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($is_active) {
            $db->update('settings', ['announcement_id' => $id], 'id = 1');
        }
        json_response(200, '公告已发布');
        break;
    
    case 'admin_save_settings':
        require_admin();
        $site_name = trim($_POST['site_name'] ?? '');
        $theme_color = trim($_POST['theme_color'] ?? '#e94560');
        $tmdb_key = trim($_POST['tmdb_key'] ?? '');
        $parse_url = trim($_POST['parse_url'] ?? '');
        
        if (!$site_name) json_response(400, '请填写站点名称');
        
        $db->update('settings', [
            'site_name' => $site_name,
            'theme_color' => $theme_color,
            'tmdb_api_key' => $tmdb_key,
            'parse_url' => $parse_url
        ], 'id = 1');
        
        json_response(200, '设置已保存');
        break;
    
    case 'admin_reply_feedback':
        require_admin();
        $id = (int)($_POST['id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');
        
        if ($id <= 0 || !$reply) json_response(400, '请输入回复内容');
        
        $db->update('feedbacks', [
            'admin_reply' => $reply,
            'replied_at' => date('Y-m-d H:i:s'),
            'status' => 'replied'
        ], 'id = ?', [$id]);
        json_response(200, '回复成功');
        break;
    
    case 'record_history':
        require_login();
        $media_id = (int)($_POST['media_id'] ?? 0);
        $media_type = $_POST['media_type'] ?? 'movie';
        $title = trim($_POST['title'] ?? '');
        $poster = trim($_POST['poster'] ?? '');
        $season = isset($_POST['season']) ? (int)$_POST['season'] : null;
        $episode = isset($_POST['episode']) ? (int)$_POST['episode'] : null;
        
        if ($media_id > 0 && $title) {
            record_history($current_user['id'], $media_id, $media_type, $title, $poster, $season, $episode);
        }
        json_response(200, 'ok');
        break;
    
    default:
        json_response(404, '未知请求');
}
