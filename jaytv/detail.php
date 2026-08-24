<?php
$id = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'movie';
$source = (int)($_GET['source'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$detail = get_tmdb_detail($id, $type);
$page_title = $detail ? ($detail['title'] ?? $detail['name'] ?? '详情') : '影视详情';

$is_tv = ($type === 'tv');
$selected_season = isset($_GET['season']) ? (int)$_GET['season'] : 1;
$season_data = null;
if ($is_tv && $detail) {
    $season_data = get_tmdb_season($id, $selected_season);
}

$search_results = search_videos($detail['title'] ?? $detail['name'] ?? '');
$play_url = '';
$episodes = [];
$video_info = null;

if ($search_results) {
    $video_info = $search_results[0];
    if (!empty($video_info['play_url'])) {
        $episodes = parse_play_urls($video_info['play_url']);
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($detail): ?>
<div class="detail-page">
    <div class="detail-hero">
        <div class="detail-hero-bg" style="background-image: url('<?= tmdb_image($detail['backdrop_path'], 'w1280') ?>');"></div>
        <div class="container">
            <div class="detail-hero-content">
                <div class="detail-poster">
                    <img src="<?= tmdb_image($detail['poster_path'], 'w500') ?>" alt="<?= esc($page_title) ?>" onerror="this.style.display='none'">
                </div>
                <div class="detail-info">
                    <h1><?= esc($detail['title'] ?? $detail['name']) ?></h1>
                    <div class="detail-meta">
                        <?php if (!empty($detail['vote_average'])): ?>
                        <span class="score">⭐ <?= number_format($detail['vote_average'], 1) ?>分</span>
                        <?php endif; ?>
                        <span><?= date('Y', strtotime($detail['release_date'] ?? $detail['first_air_date'] ?? '')) ?></span>
                        <?php if (!empty($detail['genres'])): ?>
                            <?php foreach (array_slice($detail['genres'], 0, 3) as $g): ?>
                            <span><?= esc($g['name']) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if (!empty($detail['runtime'])): ?>
                        <span><?= $detail['runtime'] ?>分钟</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($detail['tagline'])): ?>
                    <p style="color: var(--theme); font-style: italic; margin-bottom: 16px; font-size: 15px;">"<?= esc($detail['tagline']) ?>"</p>
                    <?php endif; ?>
                    
                    <p class="detail-overview"><?= esc($detail['overview'] ?: '暂无简介') ?></p>
                    
                    <?php
                    $is_chinese = false;
                    if (!empty($detail['original_language']) && $detail['original_language'] === 'zh') $is_chinese = true;
                    if (!empty($video_info['area']) && in_array($video_info['area'], ['大陆', '中国', '香港', '台湾', '国产'])) $is_chinese = true;
                    ?>
                    
                    <div class="detail-actions">
                        <?php if (!$is_tv && !empty($episodes)): ?>
                        <a href="play.php?id=<?= $id ?>&type=<?= $type ?>&ep=0" class="btn btn-primary btn-lg" onclick="<?= !is_logged_in() ? "event.preventDefault();openModal('login-modal');JayTV.toast('需要登录才可以观看哦，如没有账号请注册！','warning');" : '' ?>">
                            <span style="width:0;height:0;border-top:10px solid transparent;border-bottom:10px solid transparent;border-left:14px solid #fff;margin-right:8px;"></span>
                            立即播放
                        </a>
                        <?php elseif (!$is_tv): ?>
                        <button class="btn btn-primary btn-lg" disabled>暂无片源</button>
                        <?php endif; ?>
                        <button class="btn btn-outline btn-lg favorite-btn <?= is_logged_in() && is_favorited($current_user['id'], $id, $type) ? 'active' : '' ?>" data-id="<?= $id ?>" data-type="<?= $type ?>">
                            <span class="heart-icon"></span>
                            收藏
                        </button>
                    </div>
                    
                    <?php if (!$is_chinese): ?>
                    <div style="margin-top: 16px;">
                        <span style="font-size: 14px; color: var(--text2); margin-right: 10px;">音轨：</span>
                        <div class="audio-toggle">
                            <button class="audio-btn active" data-audio="original">原版</button>
                            <button class="audio-btn" data-audio="mandarin">普通话</button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <?php if ($is_tv): ?>
            <?php if (!empty($detail['seasons'])): ?>
            <section class="section">
                <h3 class="section-title" style="font-size: 20px; margin-bottom: 16px;">选择季数</h3>
                <div class="season-tabs">
                    <?php foreach ($detail['seasons'] as $s): ?>
                        <?php if ($s['season_number'] == 0) continue; ?>
                        <a href="?id=<?= $id ?>&type=tv&season=<?= $s['season_number'] ?>" 
                           class="season-tab <?= $selected_season == $s['season_number'] ? 'active' : '' ?>">
                            第<?= $s['season_number'] ?>季
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($season_data): ?>
                    <div style="background: var(--card); border-radius: var(--radius-lg); padding: 20px; margin-bottom: 24px;">
                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                            <?php if (!empty($season_data['poster_path'])): ?>
                            <img src="<?= tmdb_image($season_data['poster_path'], 'w200') ?>" style="width:120px; border-radius: 10px;" alt="">
                            <?php endif; ?>
                            <div style="flex:1; min-width:200px;">
                                <h4 style="margin-bottom: 10px;"><?= esc($season_data['name']) ?></h4>
                                <p style="color: var(--text2); font-size: 14px; margin-bottom: 8px;">
                                    首播: <?= $season_data['air_date'] ?? '未知' ?> | 
                                    集数: <?= count($season_data['episodes'] ?? []) ?>集
                                </p>
                                <?php if (!empty($season_data['vote_average'])): ?>
                                <p style="color: var(--warning); font-size: 14px;">评分: <?= number_format($season_data['vote_average'], 1) ?>分</p>
                                <?php endif; ?>
                                <?php if (!empty($season_data['overview'])): ?>
                                <p style="color: var(--text2); font-size: 14px; line-height: 1.6; margin-top: 10px;"><?= esc($season_data['overview']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($season_data['episodes'])): ?>
                <h3 class="section-title" style="font-size: 20px; margin-bottom: 16px;">剧集列表</h3>
                <div class="episodes-grid">
                    <?php foreach ($season_data['episodes'] as $idx => $ep): ?>
                    <?php
                    $ep_url = '';
                    if (count($episodes) > $idx) {
                        $ep_url = $episodes[$idx]['url'] ?? '';
                    }
                    ?>
                    <a href="<?= $ep_url ? "play.php?id={$id}&type=tv&season={$selected_season}&ep={$idx}" : 'javascript:;' ?>" 
                       class="episode-card"
                       <?= !is_logged_in() ? "onclick=\"event.preventDefault();openModal('login-modal');JayTV.toast('需要登录才可以观看哦，如没有账号请注册！','warning');\"" : '' ?>
                       <?= !$ep_url ? 'style="opacity:0.6;pointer-events:none;"' : '' ?>>
                        <div class="episode-still">
                            <?php if (!empty($ep['still_path'])): ?>
                            <img src="<?= tmdb_image($ep['still_path'], 'w300') ?>" alt="" loading="lazy">
                            <?php else: ?>
                            <div style="width:100%;height:100%;background:var(--bg3);display:flex;align-items:center;justify-content:center;font-size:40px;">📺</div>
                            <?php endif; ?>
                            <span class="episode-num">第<?= $ep['episode_number'] ?>集</span>
                            <?php if ($ep_url): ?><span class="episode-play"></span><?php endif; ?>
                        </div>
                        <div class="episode-info">
                            <div class="episode-title"><?= esc($ep['name'] ?: '第' . $ep['episode_number'] . '集') ?></div>
                            <div class="episode-date"><?= $ep['air_date'] ?? '' ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <p>暂无剧集数据</p>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (!empty($detail['credits']['cast'])): ?>
        <section class="section">
            <h3 class="section-title" style="font-size: 20px;">演职员</h3>
            <div style="display: flex; gap: 15px; overflow-x: auto; padding: 10px 0; margin-top: 16px;">
                <?php foreach (array_slice($detail['credits']['cast'], 0, 10) as $cast): ?>
                <div style="flex-shrink:0; width: 100px; text-align: center;">
                    <div style="width:80px; height:80px; border-radius:50%; overflow:hidden; background:var(--bg3); margin:0 auto 8px;">
                        <?php if (!empty($cast['profile_path'])): ?>
                        <img src="<?= tmdb_image($cast['profile_path'], 'w185') ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                        <?php else: ?>
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:30px;">👤</div>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:13px; font-weight:500;"><?= esc($cast['name']) ?></div>
                    <div style="font-size:11px; color:var(--text3);"><?= esc($cast['character']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="container" style="padding-top: 60px;">
    <div class="empty-state">
        <div class="icon">😕</div>
        <p>未找到该影视信息</p>
        <a href="index.php" class="btn btn-primary" style="margin-top: 20px;">返回首页</a>
    </div>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('.audio-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.audio-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
