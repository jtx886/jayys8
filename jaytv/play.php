<?php
define('IN_SITE', true);
require_once __DIR__ . '/includes/init.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'movie';
$ep = (int)($_GET['ep'] ?? 0);
$season = (int)($_GET['season'] ?? 1);
$direct_source = isset($_GET['source']) ? true : false;

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$detail = null;
$title = '播放';
$poster = '';
$episodes = [];
$video_info = null;
$play_ep_url = '';
$media_type = $type;
$media_id = $id;

if ($direct_source) {
    $video_detail = get_video_detail($id);
    if ($video_detail) {
        $title = $video_detail['vod_name'] ?? '播放';
        $poster = $video_detail['vod_pic'] ?? '';
        $video_info = $video_detail;
        $episodes = parse_play_urls($video_detail['vod_play_url'] ?? '');
        if (isset($episodes[$ep])) {
            $play_ep_url = $episodes[$ep]['url'];
        } elseif (!empty($episodes)) {
            $play_ep_url = $episodes[0]['url'];
        }
        $media_type = 'direct';
    }
} else {
    $detail = get_tmdb_detail($id, $type);
    $title = $detail ? ($detail['title'] ?? $detail['name'] ?? '播放') : '播放';
    $poster = $detail ? tmdb_image($detail['poster_path'], 'w200') : '';
    
    $video_results = search_videos($detail['title'] ?? $detail['name'] ?? '');
    if ($video_results) {
        $video_info = $video_results[0];
        $episodes = parse_play_urls($video_info['play_url'] ?? '');
        if (isset($episodes[$ep])) {
            $play_ep_url = $episodes[$ep]['url'];
        } elseif (!empty($episodes)) {
            $play_ep_url = $episodes[0]['url'];
        }
    }
}

$parse_url = setting('parse_url');
$iframe_url = '';
if ($play_ep_url) {
    $iframe_url = $parse_url . urlencode($play_ep_url);
}

record_history($current_user['id'], $media_id, $media_type, $title, $poster, $type === 'tv' ? $season : null, $type === 'tv' ? $ep + 1 : ($direct_source ? $ep + 1 : null));

$page_title = '正在播放：' . $title;
require_once __DIR__ . '/includes/header.php';
?>

<div class="play-page">
    <div class="player-container">
        <?php if ($iframe_url): ?>
        <iframe src="<?= esc($iframe_url) ?>" allowfullscreen="allowfullscreen" allow="autoplay; fullscreen; encrypted-media"></iframe>
        <?php else: ?>
        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;flex-direction:column;background:var(--bg2);">
            <div style="font-size:80px;margin-bottom:20px;">😢</div>
            <h3 style="margin-bottom:10px;">暂无可用播放源</h3>
            <p style="color:var(--text2);margin-bottom:20px;">该影视暂时没有可用的播放资源，请尝试其他影视</p>
            <a href="javascript:history.back()" class="btn btn-primary">返回上一页</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="container" style="padding: 20px 0;">
    <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap;">
        <div style="flex:1;min-width:280px;">
            <h2 style="font-size:22px;margin-bottom:8px;">
                <?= esc($title) ?>
                <?php if (isset($episodes[$ep]) && !empty($episodes[$ep]['name'])): ?>
                - <?= esc($episodes[$ep]['name']) ?>
                <?php elseif (count($episodes) > 1): ?>
                - 第<?= $ep + 1 ?>集
                <?php endif; ?>
            </h2>
            <?php if ($detail): ?>
            <div style="color:var(--text2);font-size:14px;">
                ⭐ <?= number_format($detail['vote_average'] ?? 0, 1) ?>分 | 
                <?= date('Y', strtotime($detail['release_date'] ?? $detail['first_air_date'] ?? '')) ?>
            </div>
            <?php elseif ($video_info): ?>
            <div style="color:var(--text2);font-size:14px;">
                <?php if (!empty($video_info['vod_year'])): ?>年份：<?= esc($video_info['vod_year']) ?><?php endif; ?>
                <?php if (!empty($video_info['type_name'])): ?> | 分类：<?= esc($video_info['type_name']) ?><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <?php if (!$direct_source): ?>
            <button class="btn btn-outline favorite-btn <?= is_favorited($current_user['id'], $id, $type) ? 'active' : '' ?>" data-id="<?= $id ?>" data-type="<?= $type ?>">
                <span class="heart-icon"></span>
                收藏
            </button>
            <a href="detail.php?id=<?= $id ?>&type=<?= $type ?>" class="btn btn-ghost">返回详情</a>
            <?php else: ?>
            <a href="javascript:history.back()" class="btn btn-ghost">返回</a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (count($episodes) > 1): ?>
    <div style="margin-top: 24px;">
        <h3 style="font-size:18px;margin-bottom:16px;">选集列表</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:10px;max-height:200px;overflow-y:auto;padding-right:10px;">
            <?php foreach ($episodes as $idx => $eps): ?>
            <a href="?id=<?= $id ?><?= $direct_source ? '&source=1' : '&type=' . $type ?><?= $type === 'tv' ? '&season=' . $season : '' ?>&ep=<?= $idx ?>" 
               class="btn btn-ghost btn-sm" 
               style="<?= $idx === $ep ? 'background:var(--theme);color:#fff;' : '' ?>">
                <?php if (!empty($eps['name']) && !is_numeric($eps['name'])): ?>
                    <?= esc(mb_substr($eps['name'], 0, 4)) ?>
                <?php else: ?>
                    第<?= $idx + 1 ?>集
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
