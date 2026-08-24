<?php
$type = $_GET['type'] ?? 'movie';
$page = max(1, (int)($_GET['page'] ?? 1));
$page_title = media_type_label($type);

$valid_types = ['movie', 'tv', '综艺', '动漫'];
if (!in_array($type, $valid_types)) {
    $type = 'movie';
}

if (in_array($type, ['综艺', '动漫'])) {
    $category_map = ['综艺' => '2', '动漫' => '4'];
    $keyword = $type;
    $search_results = search_videos($keyword);
    $items = [];
    foreach ($search_results as $r) {
        $items[] = [
            'id' => 'z_' . $r['id'],
            'title' => $r['name'],
            'name' => $r['name'],
            'poster_path' => null,
            'poster' => $r['pic'],
            'vote_average' => 0,
            'release_date' => $r['year'],
            'first_air_date' => $r['year'],
            'overview' => $r['blurb'],
            'external' => true,
            'type_name' => $r['type'],
            'remarks' => $r['remarks'],
            'vod_id' => $r['id']
        ];
    }
    $total_pages = 1;
} else {
    $endpoint = $type === 'movie' ? 'movie/popular' : 'tv/popular';
    $data = tmdb_api($endpoint, ['page' => $page]);
    $items = $data['results'] ?? [];
    $total_pages = min($data['total_pages'] ?? 1, 500);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="padding-top: 30px;">
    <div class="section-header" style="margin-bottom: 24px;">
        <h2 class="section-title">
            <?php if ($type === 'movie'): ?>🎬 电影
            <?php elseif ($type === 'tv'): ?>📺 电视剧
            <?php elseif ($type === '综艺'): ?>🎤 综艺
            <?php elseif ($type === '动漫'): ?>🎨 动漫
            <?php endif; ?>
        </h2>
    </div>
    
    <?php if (empty($items)): ?>
    <div class="empty-state">
        <div class="icon">📭</div>
        <p>暂无数据，请配置TMDB API Key或稍后再试</p>
    </div>
    <?php else: ?>
    <div class="media-grid">
        <?php foreach ($items as $item):
            $is_external = !empty($item['external']);
            $item_id = $is_external ? $item['vod_id'] : $item['id'];
            $item_type = $type;
        ?>
        <a href="<?= $is_external ? "play.php?id={$item_id}&source=1" : "detail.php?id={$item_id}&type={$type}" ?>" class="media-card">
            <div class="poster">
                <?php if ($is_external && !empty($item['poster'])): ?>
                    <img src="<?= esc($item['poster']) ?>" alt="<?= esc($item['title']) ?>" loading="lazy" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 300%22><rect fill=%22%2316213e%22 width=%22200%22 height=%22300%22/><text x=%22100%22 y=%22150%22 fill=%22%23666%22 text-anchor=%22middle%22 font-size=%2240%22>🎬</text></svg>'">
                <?php else: ?>
                    <img src="<?= tmdb_image($item['poster_path'], 'w342') ?>" alt="<?= esc($item['title'] ?? $item['name']) ?>" loading="lazy" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 300%22><rect fill=%22%2316213e%22 width=%22200%22 height=%22300%22/><text x=%22100%22 y=%22150%22 fill=%22%23666%22 text-anchor=%22middle%22 font-size=%2240%22>🎬</text></svg>'">
                <?php endif; ?>
                <span class="play-btn"></span>
                <?php if (!empty($item['vote_average']) && $item['vote_average'] > 0): ?>
                <span class="score-tag"><?= number_format($item['vote_average'], 1) ?></span>
                <?php endif; ?>
                <?php if (!empty($item['remarks'])): ?>
                <span class="quality-tag"><?= esc($item['remarks']) ?></span>
                <?php endif; ?>
            </div>
            <div class="info">
                <div class="title"><?= esc($item['title'] ?? $item['name']) ?></div>
                <div class="meta">
                    <span><?= esc($item['release_date'] ?? $item['first_air_date'] ?? '') ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    
    <?php if (!in_array($type, ['综艺', '动漫']) && $total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?type=<?= $type ?>&page=<?= $page - 1 ?>">‹</a>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        if ($start > 1) echo '<a href="?type=' . $type . '&page=1">1</a>';
        if ($start > 2) echo '<span>...</span>';
        for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i == $page): ?>
            <span class="active"><?= $i ?></span>
            <?php else: ?>
            <a href="?type=<?= $type ?>&page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor;
        if ($end < $total_pages - 1) echo '<span>...</span>';
        if ($end < $total_pages) echo '<a href="?type=' . $type . '&page=' . $total_pages . '">' . $total_pages . '</a>';
        ?>
        <?php if ($page < $total_pages): ?>
        <a href="?type=<?= $type ?>&page=<?= $page + 1 ?>">›</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
