<?php
/**
 * Jay影视 - 公共函数库
 */

function get_settings() {
    static $settings = null;
    if ($settings === null) {
        $db = Database::getInstance();
        $row = $db->fetch("SELECT * FROM settings WHERE id = 1");
        if (!$row) {
            $db->query("INSERT INTO settings (site_name, theme_color) VALUES ('Jay影视', '#e94560')");
            $row = $db->fetch("SELECT * FROM settings WHERE id = 1");
        }
        $settings = $row;
    }
    return $settings;
}

function setting($key, $default = '') {
    $settings = get_settings();
    return isset($settings[$key]) ? $settings[$key] : $default;
}

function get_user($id) {
    $db = Database::getInstance();
    return $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
}

function get_user_by_email($email) {
    $db = Database::getInstance();
    return $db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
}

function get_user_by_username($username) {
    $db = Database::getInstance();
    return $db->fetch("SELECT * FROM users WHERE username = ?", [$username]);
}

function is_banned($user) {
    if (!$user || !$user['is_banned']) return false;
    if ($user['ban_end'] && strtotime($user['ban_end']) < time()) return false;
    return true;
}

function generate_code($length = 6) {
    return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function send_verify_code($email, $username = '') {
    $db = Database::getInstance();
    $code = generate_code();
    $expires = date('Y-m-d H:i:s', time() + 600);
    
    $db->query("DELETE FROM email_codes WHERE email = ? AND used = 0", [$email]);
    $db->insert('email_codes', [
        'email' => $email,
        'code' => $code,
        'type' => 'register',
        'expires_at' => $expires
    ]);
    
    $mailer = new Mailer();
    return $mailer->send($email, 'Jay影视 - 邮箱验证码', Mailer::verify_code_template($code, $username));
}

function verify_code($email, $code) {
    $db = Database::getInstance();
    $row = $db->fetch("SELECT * FROM email_codes WHERE email = ? AND code = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1", [$email, $code]);
    if ($row) {
        $db->update('email_codes', ['used' => 1], 'id = ?', [$row['id']]);
        return true;
    }
    return false;
}

function tmdb_api($endpoint, $params = []) {
    $api_key = setting('tmdb_api_key');
    if (!$api_key) return null;
    
    $params['api_key'] = $api_key;
    $params['language'] = 'zh-CN';
    
    $url = 'https://api.themoviedb.org/3/' . $endpoint . '?' . http_build_query($params);
    
    $cache_key = 'tmdb_' . md5($url);
    $cached = get_cache($cache_key);
    if ($cached !== null) return $cached;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'JayTV/1.0');
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        set_cache($cache_key, $data, 86400);
        return $data;
    }
    return null;
}

function tmdb_image($path, $size = 'w500') {
    if (!$path) return '';
    return 'https://image.tmdb.org/t/p/' . $size . $path;
}

function search_tmdb($query, $type = 'multi', $page = 1) {
    return tmdb_api('search/' . $type, [
        'query' => $query,
        'page' => $page,
        'include_adult' => false
    ]);
}

function get_tmdb_detail($id, $type = 'movie') {
    return tmdb_api("$type/$id", [
        'append_to_response' => 'credits,videos,similar,recommendations,seasons'
    ]);
}

function get_tmdb_season($tv_id, $season_number) {
    return tmdb_api("tv/$tv_id/season/$season_number");
}

function get_tmdb_trending($type = 'all', $time = 'week', $page = 1) {
    return tmdb_api("trending/$type/$time", ['page' => $page]);
}

function get_tmdb_popular($type = 'movie', $page = 1) {
    return tmdb_api("$type/popular", ['page' => $page]);
}

function get_play_sources() {
    $db = Database::getInstance();
    return $db->fetchAll("SELECT * FROM play_sources ORDER BY is_default DESC, id ASC");
}

function get_default_source() {
    $db = Database::getInstance();
    return $db->fetch("SELECT * FROM play_sources WHERE is_default = 1 LIMIT 1");
}

function search_videos($keyword, $source_url = null) {
    if (!$source_url) {
        $source = get_default_source();
        $source_url = $source ? $source['url'] : '';
    }
    if (!$source_url) return [];
    
    $url = $source_url;
    if (strpos($url, '?') === false) {
        $url .= '?';
    } else {
        $url .= '&';
    }
    $url .= http_build_query(['wd' => $keyword]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Referer: https://www.baidu.com/']);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['list'])) {
        if (preg_match('/\$list\s*=\s*(\[.*?\]);/s', $response, $m)) {
            $data = json_decode($m[1], true);
        }
    }
    
    $results = [];
    if (is_array($data)) {
        $list = isset($data['list']) ? $data['list'] : (isset($data[0]) ? $data : []);
        foreach ($list as $item) {
            if (isset($item['vod_name'])) {
                $results[] = [
                    'id' => $item['vod_id'] ?? 0,
                    'name' => $item['vod_name'],
                    'type' => $item['type_name'] ?? '',
                    'pic' => $item['vod_pic'] ?? '',
                    'remarks' => $item['vod_remarks'] ?? '',
                    'play_url' => $item['vod_play_url'] ?? '',
                    'director' => $item['vod_director'] ?? '',
                    'actor' => $item['vod_actor'] ?? '',
                    'blurb' => $item['vod_blurb'] ?? '',
                    'year' => $item['vod_year'] ?? '',
                    'area' => $item['vod_area'] ?? ''
                ];
            }
        }
    }
    return $results;
}

function get_video_detail($vod_id, $source_url = null) {
    if (!$source_url) {
        $source = get_default_source();
        $source_url = $source ? $source['url'] : '';
    }
    if (!$source_url) return null;
    
    $url = $source_url;
    if (strpos($url, '?') === false) $url .= '?';
    else $url .= '&';
    $url .= http_build_query(['ac' => 'detail', 'ids' => $vod_id]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if ($data && isset($data['list'][0])) {
        return $data['list'][0];
    }
    return null;
}

function parse_play_urls($play_url_str) {
    $episodes = [];
    if (!$play_url_str) return $episodes;
    
    $sources = explode('$$$', $play_url_str);
    foreach ($sources as $source) {
        $parts = explode('#', $source);
        foreach ($parts as $part) {
            $ep_info = explode('$', $part);
            if (count($ep_info) >= 2) {
                $episodes[] = [
                    'name' => $ep_info[0],
                    'url' => $ep_info[1]
                ];
            }
        }
    }
    return $episodes;
}

function get_cache($key) {
    $db = Database::getInstance();
    $row = $db->fetch("SELECT cache_data FROM media_cache WHERE cache_key = ? AND (expires_at IS NULL OR expires_at > NOW())", [$key]);
    if ($row) {
        return json_decode($row['cache_data'], true);
    }
    return null;
}

function set_cache($key, $data, $ttl = 3600) {
    $db = Database::getInstance();
    $expires = $ttl ? date('Y-m-d H:i:s', time() + $ttl) : null;
    $db->query("DELETE FROM media_cache WHERE cache_key = ?", [$key]);
    $db->insert('media_cache', [
        'cache_key' => $key,
        'cache_data' => json_encode($data),
        'expires_at' => $expires
    ]);
}

function is_favorited($user_id, $media_id, $media_type) {
    $db = Database::getInstance();
    return (bool)$db->fetch("SELECT id FROM favorites WHERE user_id = ? AND media_id = ? AND media_type = ?", [$user_id, $media_id, $media_type]);
}

function add_favorite($user_id, $media_id, $media_type, $title, $poster) {
    $db = Database::getInstance();
    if (!is_favorited($user_id, $media_id, $media_type)) {
        return $db->insert('favorites', [
            'user_id' => $user_id,
            'media_id' => $media_id,
            'media_type' => $media_type,
            'title' => $title,
            'poster' => $poster
        ]);
    }
    return false;
}

function remove_favorite($user_id, $media_id, $media_type) {
    $db = Database::getInstance();
    return $db->delete('favorites', 'user_id = ? AND media_id = ? AND media_type = ?', [$user_id, $media_id, $media_type]);
}

function record_history($user_id, $media_id, $media_type, $title, $poster, $season = null, $episode = null) {
    $db = Database::getInstance();
    $db->query("DELETE FROM watch_history WHERE user_id = ? AND media_id = ? AND media_type = ? AND season <=> ? AND episode <=> ?", 
        [$user_id, $media_id, $media_type, $season, $episode]);
    return $db->insert('watch_history', [
        'user_id' => $user_id,
        'media_id' => $media_id,
        'media_type' => $media_type,
        'title' => $title,
        'poster' => $poster,
        'season' => $season,
        'episode' => $episode
    ]);
}

function get_active_announcement() {
    $db = Database::getInstance();
    $settings = get_settings();
    $ann_id = $settings['announcement_id'];
    if ($ann_id) {
        return $db->fetch("SELECT * FROM announcements WHERE id = ? AND is_active = 1", [$ann_id]);
    }
    return null;
}

function time_ago($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . '年前';
    if ($diff->m > 0) return $diff->m . '个月前';
    if ($diff->d > 0) return $diff->d . '天前';
    if ($diff->h > 0) return $diff->h . '小时前';
    if ($diff->i > 0) return $diff->i . '分钟前';
    return '刚刚';
}

function media_type_label($type) {
    $labels = [
        'movie' => '电影',
        'tv' => '电视剧',
        '综艺' => '综艺',
        '动漫' => '动漫'
    ];
    return $labels[$type] ?? $type;
}

function is_chinese_content($data) {
    $areas = ['大陆', '中国', '香港', '台湾', '国产'];
    $area = is_array($data) ? ($data['area'] ?? $data['original_language'] ?? '') : '';
    if ($area === 'zh' || in_array($area, $areas)) return true;
    return false;
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function json_response($code, $msg = '', $data = []) {
    header('Content-Type: application/json');
    echo json_encode(['code' => $code, 'msg' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        json_response(403, '非法请求');
    }
}

function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
