<?php
$q = trim($_GET['q'] ?? '');
$page_title = $q ? '搜索：' . $q : '搜索';
$page = max(1, (int)($_GET['page'] ?? 1));

$results = [];
$total_pages = 1;

if ($q) {
    $data = tmdb_api('search/multi', ['query' => $q, 'page' => $page, 'include_adult' => false]);
    if ($data && !empty($data['results'])) {
        $results = array_filter($data['results'], function($item) {
            return in_array($item['media_type'], ['movie', 'tv']);
        });
        $results = array_values($results);
        $total_pages = min($data['total_pages'] ?? 1, 50);
    }
    
    $video_results = search_videos($q);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 30px;">
    <div class="section-header">
        <h2 class="section-title">
            🔍 搜索结果：<?= esc($q) ?>
        </h2>
    </div>
    
    <?php if (!$q): ?>
    <div class="empty-state">
        <div class="icon">🔍</div>
        <p>请输入关键词搜索影视</p>
    </div>
    <?php elseif (empty($results) && empty($video_results)): ?>
    <div class="empty-state">
        <div class="icon">😕</div>
        <p>未找到"<?= esc($q) ?>"相关结果</p>
    </div>
    <?php else: ?>
    <div class="media-grid">
        <?php foreach ($results as $item):
            $item_type = $item['media_type'] ?? 'movie';
            if (!in_array($item_type, ['movie', 'tv'])) continue;
            $item_id = $item['id'];
        ?>
        <a href="detail.php?id=<?= $item_id ?>&type=<?= $item_type ?>" class="media-card">
            <div class="poster">
                <img src="<?= tmdb_image($item['poster_path'], 'w342') ?>" alt="<?= esc($item['title'] ?? $item['name']) ?>" loading="lazy" onerror="this.style.display='none'">
                <span class="play-btn"></span>
                <?php if (!empty($item['vote_average'])): ?>
                <span class="score-tag"><?= number_format($item['vote_average'], 1) ?></span>
                <?php endif; ?>
                <span class="quality-tag"><?= $item_type === 'movie' ? '电影' : '剧集' ?></span>
                <button class="favorite-btn" data-id="<?= $item_id ?>" data-type="<?= $item_type ?>">
                    <span class="heart-icon"></span>
                </button>
            </div>
            <div class="info">
                <div class="title"><?= esc($item['title'] ?? $item['name']) ?></div>
                <div class="meta">
                    <span><?= date('Y', strtotime($item['release_date'] ?? $item['first_air_date'] ?? '')) ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
        
        <?php if (!empty($video_results)): foreach ($video_results as $v): ?>
        <a href="play.php?id=z_<?= $v['id'] ?>&source=1" class="media-card">
            <div class="poster">
                <?php if (!empty($v['pic'])): ?>
                <img src="<?= esc($v['pic']) ?>" alt="<?= esc($v['name']) ?>" loading="lazy">
                <?php else: ?>
                <div style="width:100%;height:100%;background:var(--bg3);display:flex;align-items:center;justify-content:center;font-size:50px;">🎬</div>
                <?php endif; ?>
                <span class="play-btn"></span>
                <?php if (!empty($v['remarks'])): ?>
                <span class="quality-tag"><?= esc($v['remarks']) ?></span>
                <?php endif; ?>
            </div>
            <div class="info">
                <div class="title"><?= esc($v['name']) ?></div>
                <div class="meta">
                    <span><?= esc($v['type'] ?? '') ?></span>
                    <span><?= esc($v['year'] ?? '') ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; endif; ?>
    </div>
    
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?q=<?= urlencode($q) ?>&page=<?= $page - 1 ?>">‹</a>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        if ($start > 1) echo '<a href="?q=' . urlencode($q) . '&page=1">1</a>';
        if ($start > 2) echo '<span>...</span>';
        for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i == $page): ?>
            <span class="active"><?= $i ?></span>
            <?php else: ?>
            <a href="?q=<?= urlencode($q) ?>&page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor;
        if ($end < $total_pages - 1) echo '<span>...</span>';
        if ($end < $total_pages) echo '<a href="?q=' . urlencode($q) . '&page=' . $total_pages . '">' . $total_pages . '</a>';
        ?>
        <?php if ($page < $total_pages): ?>
        <a href="?q=<?= urlencode($q) ?>&page=<?= $page + 1 ?>">›</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
