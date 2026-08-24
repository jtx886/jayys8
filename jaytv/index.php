<?php
$page_title = '首页';
require_once __DIR__ . '/includes/header.php';

$trending_movies = tmdb_api('trending/movie/week', ['page' => 1]);
$trending_tv = tmdb_api('trending/tv/week', ['page' => 1]);
$popular_movies = tmdb_api('movie/popular', ['page' => 1]);
$popular_tv = tmdb_api('tv/popular', ['page' => 1]);

$featured = null;
if ($trending_movies && !empty($trending_movies['results'])) {
    $featured = $trending_movies['results'][0];
} elseif ($popular_movies && !empty($popular_movies['results'])) {
    $featured = $popular_movies['results'][0];
}
?>

<?php if ($featured): ?>
<section class="hero">
    <div class="hero-bg" style="background-image: url('<?= tmdb_image($featured['backdrop_path'], 'original') ?>');"></div>
    <div class="container">
        <div class="hero-content">
            <span class="hero-tag">🔥 本周热门</span>
            <h1><?= esc($featured['title'] ?? $featured['name']) ?></h1>
            <div class="hero-meta">
                <span class="score">⭐ <?= number_format($featured['vote_average'], 1) ?>分</span>
                <span><?= date('Y', strtotime($featured['release_date'] ?? $featured['first_air_date'] ?? '')) ?></span>
                <?php if (!empty($featured['genre_ids'])): ?>
                    <span>电影</span>
                <?php endif; ?>
            </div>
            <p class="hero-desc"><?= esc($featured['overview']) ?></p>
            <div class="hero-actions">
                <a href="detail.php?id=<?= $featured['id'] ?>&type=movie" class="btn btn-primary btn-lg">
                    <span style="width:0;height:0;border-top:8px solid transparent;border-bottom:8px solid transparent;border-left:12px solid #fff;margin-right:6px;"></span>
                    立即播放
                </a>
                <button class="btn btn-outline btn-lg favorite-btn" data-id="<?= $featured['id'] ?>" data-type="movie">
                    <span class="heart-icon"></span>
                    收藏
                </button>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<div class="container">
    <?php if ($trending_movies && !empty($trending_movies['results'])): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">🔥 热门电影</h2>
            <a href="list.php?type=movie" class="section-more">查看更多</a>
        </div>
        <div class="media-grid">
            <?php foreach (array_slice($trending_movies['results'], 0, 12) as $item): ?>
            <a href="detail.php?id=<?= $item['id'] ?>&type=movie" class="media-card">
                <div class="poster">
                    <img src="<?= tmdb_image($item['poster_path'], 'w342') ?>" alt="<?= esc($item['title']) ?>" loading="lazy" onerror="this.style.display='none'">
                    <span class="play-btn"></span>
                    <span class="score-tag"><?= number_format($item['vote_average'], 1) ?></span>
                    <button class="favorite-btn <?= is_logged_in() && is_favorited($current_user['id'], $item['id'], 'movie') ? 'active' : '' ?>" data-id="<?= $item['id'] ?>" data-type="movie">
                        <span class="heart-icon"></span>
                    </button>
                </div>
                <div class="info">
                    <div class="title"><?= esc($item['title']) ?></div>
                    <div class="meta">
                        <span><?= date('Y', strtotime($item['release_date'])) ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($trending_tv && !empty($trending_tv['results'])): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">📺 热门剧集</h2>
            <a href="list.php?type=tv" class="section-more">查看更多</a>
        </div>
        <div class="media-grid">
            <?php foreach (array_slice($trending_tv['results'], 0, 12) as $item): ?>
            <a href="detail.php?id=<?= $item['id'] ?>&type=tv" class="media-card">
                <div class="poster">
                    <img src="<?= tmdb_image($item['poster_path'], 'w342') ?>" alt="<?= esc($item['name']) ?>" loading="lazy" onerror="this.style.display='none'">
                    <span class="play-btn"></span>
                    <span class="score-tag"><?= number_format($item['vote_average'], 1) ?></span>
                    <button class="favorite-btn <?= is_logged_in() && is_favorited($current_user['id'], $item['id'], 'tv') ? 'active' : '' ?>" data-id="<?= $item['id'] ?>" data-type="tv">
                        <span class="heart-icon"></span>
                    </button>
                </div>
                <div class="info">
                    <div class="title"><?= esc($item['name']) ?></div>
                    <div class="meta">
                        <span><?= date('Y', strtotime($item['first_air_date'])) ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($popular_movies && !empty($popular_movies['results'])): ?>
    <section class="section">
        <div class="section-header">
            <h2 class="section-title">🎬 最新电影</h2>
            <a href="list.php?type=movie" class="section-more">查看更多</a>
        </div>
        <div class="media-grid">
            <?php foreach (array_slice($popular_movies['results'], 0, 12) as $item): ?>
            <a href="detail.php?id=<?= $item['id'] ?>&type=movie" class="media-card">
                <div class="poster">
                    <img src="<?= tmdb_image($item['poster_path'], 'w342') ?>" alt="<?= esc($item['title']) ?>" loading="lazy" onerror="this.style.display='none'">
                    <span class="play-btn"></span>
                    <button class="favorite-btn <?= is_logged_in() && is_favorited($current_user['id'], $item['id'], 'movie') ? 'active' : '' ?>" data-id="<?= $item['id'] ?>" data-type="movie">
                        <span class="heart-icon"></span>
                    </button>
                </div>
                <div class="info">
                    <div class="title"><?= esc($item['title']) ?></div>
                    <div class="meta">
                        <span><?= date('Y', strtotime($item['release_date'])) ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
