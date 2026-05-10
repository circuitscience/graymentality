<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_auth();
$db = get_db_connection();

function gm_library_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function gm_library_columns(PDO $db): array
{
    try {
        $stmt = $db->query("SHOW COLUMNS FROM general_articles");
        $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        return array_map('strval', is_array($columns) ? $columns : []);
    } catch (Throwable $e) {
        return [];
    }
}

function gm_library_article_key(array $article): string
{
    if (isset($article['id']) && (string)$article['id'] !== '') {
        return 'id-' . (string)$article['id'];
    }

    return gm_library_article_ref($article);
}

function gm_library_article_ref(array $article): string
{
    return 'key-' . substr(hash('sha256', implode('|', [
        (string)($article['date'] ?? ''),
        (string)($article['author'] ?? ''),
        (string)($article['title'] ?? ''),
    ])), 0, 16);
}

function gm_library_articles(PDO $db, array $columns): array
{
    if ($columns === []) {
        return [];
    }

    $hasId = in_array('id', $columns, true);
    $hasCategory = in_array('category', $columns, true);
    $select = [
        $hasId ? 'id' : 'NULL AS id',
        '`date`',
        'author',
        'title',
        '`text`',
        'image',
        $hasCategory ? 'category' : "'General' AS category",
    ];
    $order = $hasCategory ? 'category ASC, `date` DESC, title ASC' : '`date` DESC, title ASC';

    try {
        $stmt = $db->query('SELECT ' . implode(', ', $select) . ' FROM general_articles ORDER BY ' . $order);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        return is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
        return [];
    }
}

function gm_library_excerpt(?string $text, int $maxLength = 170): string
{
    $plain = trim(html_entity_decode(strip_tags((string)$text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $plain = preg_replace('/\s+/', ' ', $plain) ?? $plain;

    if (strlen($plain) <= $maxLength) {
        return $plain;
    }

    $excerpt = rtrim(substr($plain, 0, $maxLength - 1));
    $lastSpace = strrpos($excerpt, ' ');
    if ($lastSpace !== false && $lastSpace > 70) {
        $excerpt = substr($excerpt, 0, $lastSpace);
    }

    return $excerpt . '...';
}

function gm_library_body(?string $text): string
{
    $plain = trim(html_entity_decode(strip_tags((string)$text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $plain = preg_replace("/\r\n|\r/", "\n", $plain) ?? $plain;
    $blocks = preg_split("/\n{2,}/", $plain, -1, PREG_SPLIT_NO_EMPTY);

    if (!$blocks) {
        $blocks = [$plain];
    }

    $html = '';
    foreach ($blocks as $block) {
        $html .= '<p>' . gm_library_h(trim(preg_replace('/\s+/', ' ', $block) ?? $block)) . '</p>';
    }

    return $html;
}

function gm_library_date(?string $value): string
{
    if (!$value) {
        return 'Undated';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y');
    } catch (Throwable $e) {
        return $value;
    }
}

function gm_library_image(?string $value): string
{
    $image = trim((string)$value);
    if ($image === '') {
        return '';
    }

    if (preg_match('#^(https?://|/|data:)#i', $image)) {
        return $image;
    }

    $publicRoot = dirname(__DIR__, 2);
    foreach ([$image, 'assets/images/' . $image, 'modules/assets/' . $image] as $candidate) {
        if (is_file($publicRoot . '/' . ltrim($candidate, '/'))) {
            return '/' . ltrim($candidate, '/');
        }
    }

    return '/' . ltrim($image, '/');
}

$columns = gm_library_columns($db);
$articles = gm_library_articles($db, $columns);
$selectedKey = isset($_GET['article']) ? (string)$_GET['article'] : '';
$selectedArticle = $articles[0] ?? null;
$categories = [];

foreach ($articles as $article) {
    $category = trim((string)($article['category'] ?? 'General'));
    if ($category === '') {
        $category = 'General';
    }
    $key = gm_library_article_key($article);
    $ref = gm_library_article_ref($article);
    $categories[$category][] = $article + ['_key' => $key, '_ref' => $ref, '_category' => $category];
    if ($selectedKey !== '' && (hash_equals($key, $selectedKey) || hash_equals($ref, $selectedKey))) {
        $selectedArticle = $article + ['_key' => $key, '_ref' => $ref, '_category' => $category];
    }
}

if ($selectedArticle && !isset($selectedArticle['_key'])) {
    $category = trim((string)($selectedArticle['category'] ?? 'General'));
    $selectedArticle['_key'] = gm_library_article_key($selectedArticle);
    $selectedArticle['_ref'] = gm_library_article_ref($selectedArticle);
    $selectedArticle['_category'] = $category !== '' ? $category : 'General';
}

$selectedImage = $selectedArticle ? gm_library_image(isset($selectedArticle['image']) ? (string)$selectedArticle['image'] : '') : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Library | Gray Mentality</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <style>
        :root {
            --lib-bg: #050507;
            --lib-panel: rgba(14, 16, 22, 0.92);
            --lib-panel-2: rgba(20, 22, 30, 0.82);
            --lib-line: rgba(255, 255, 255, 0.14);
            --lib-text: #f4f1ea;
            --lib-muted: #aaa39b;
            --lib-soft: #7c7771;
            --lib-orange: #ff6a00;
            --lib-purple: #9b5cff;
            --lib-width: 1280px;
        }

        html { color-scheme: dark; }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--lib-text);
            font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background:
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.025) 1px, transparent 1px),
                radial-gradient(circle at 16% 8%, rgba(155, 92, 255, 0.2), transparent 30%),
                radial-gradient(circle at 88% 8%, rgba(255, 106, 0, 0.16), transparent 28%),
                linear-gradient(180deg, #050507 0%, #101116 52%, #050507 100%);
            background-size: 72px 72px, 72px 72px, auto, auto, auto;
        }

        a { color: inherit; text-decoration: none; }
        h1, h2, h3, p { margin-top: 0; }

        .library {
            width: min(var(--lib-width), calc(100% - 32px));
            margin: 0 auto;
            padding: 28px 0 46px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding-bottom: 28px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            display: block;
            width: 42px;
            height: 42px;
            object-fit: contain;
            flex: 0 0 auto;
        }

        .brand-copy {
            display: grid;
            gap: 4px;
        }

        .brand span,
        .eyebrow {
            color: var(--lib-orange);
            font-size: 0.76rem;
            font-weight: 900;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .brand strong {
            font-size: 1rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            border: 1px solid var(--lib-line);
            padding: 0 14px;
            background: rgba(255, 255, 255, 0.04);
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .panel {
            border: 1px solid var(--lib-line);
            background: var(--lib-panel);
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
        }

        .hero {
            padding: clamp(28px, 5vw, 54px);
            margin-bottom: 18px;
        }

        .hero h1 {
            max-width: 920px;
            margin-bottom: 14px;
            font-size: clamp(3rem, 8vw, 7rem);
            line-height: 0.88;
            text-transform: uppercase;
        }

        .hero p {
            max-width: 720px;
            margin-bottom: 0;
            color: var(--lib-muted);
            line-height: 1.62;
        }

        .layout {
            display: grid;
            grid-template-columns: minmax(260px, 0.34fr) minmax(0, 0.66fr);
            gap: 18px;
            align-items: start;
        }

        .index {
            position: sticky;
            top: 18px;
            max-height: calc(100vh - 36px);
            overflow: auto;
            padding: 18px;
        }

        .category {
            margin-top: 18px;
        }

        .category:first-of-type {
            margin-top: 0;
        }

        .category h2 {
            margin-bottom: 10px;
            color: var(--lib-orange);
            font-size: 0.78rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .article-link {
            display: block;
            border: 1px solid var(--lib-line);
            padding: 12px;
            background: rgba(255, 255, 255, 0.035);
        }

        .article-link + .article-link {
            margin-top: 8px;
        }

        .article-link[aria-current="page"] {
            border-color: rgba(255, 106, 0, 0.62);
            background: rgba(255, 106, 0, 0.1);
        }

        .article-link strong {
            display: block;
            margin-bottom: 6px;
            line-height: 1.1;
            text-transform: uppercase;
        }

        .article-link span,
        .article-link p {
            color: var(--lib-muted);
            font-size: 0.82rem;
            line-height: 1.38;
        }

        .reader {
            overflow: hidden;
        }

        .reader-image {
            min-height: 300px;
            background:
                linear-gradient(135deg, rgba(255, 106, 0, 0.18), transparent 42%),
                linear-gradient(315deg, rgba(155, 92, 255, 0.16), transparent 48%),
                var(--lib-panel-2);
        }

        .reader-image img {
            display: block;
            width: 100%;
            max-height: 460px;
            object-fit: cover;
        }

        .reader-body {
            padding: clamp(24px, 5vw, 54px);
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
            color: var(--lib-soft);
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .reader h2 {
            margin-bottom: 18px;
            font-size: clamp(2.1rem, 5vw, 5rem);
            line-height: 0.92;
            text-transform: uppercase;
        }

        .article-text {
            max-width: 820px;
            color: var(--lib-text);
            font-size: 1.02rem;
            line-height: 1.75;
        }

        .article-text p {
            margin-bottom: 1.25rem;
        }

        .empty {
            padding: clamp(24px, 5vw, 54px);
            color: var(--lib-muted);
        }

        @media (max-width: 900px) {
            .topbar,
            .layout {
                grid-template-columns: 1fr;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .index {
                position: static;
                max-height: none;
            }
        }
    </style>
</head>
<body>
    <div class="library">
        <header class="topbar">
            <a class="brand" href="/modules/index.php" aria-label="Gray Mentality dashboard">
                <img class="brand-logo" src="<?= gm_logo_url() ?>" alt="" aria-hidden="true">
                <span class="brand-copy">
                    <span>Gray Mentality</span>
                    <strong>Library</strong>
                </span>
            </a>
            <a class="button" href="/modules/index.php">Dashboard</a>
        </header>

        <main>
            <section class="hero panel">
                <p class="eyebrow">Article Library</p>
                <h1>Read The Full Article</h1>
                <p>Browse every available article by category, then open the full text in the reader.</p>
            </section>

            <?php if (!$selectedArticle): ?>
                <section class="empty panel">
                    <h2>No articles found</h2>
                    <p>The `general_articles` table is empty or unavailable.</p>
                </section>
            <?php else: ?>
                <section class="layout">
                    <aside class="index panel" aria-label="Article index">
                        <?php foreach ($categories as $category => $categoryArticles): ?>
                            <div class="category">
                                <h2><?= gm_library_h((string)$category) ?></h2>
                                <?php foreach ($categoryArticles as $article): ?>
                                    <?php $key = (string)$article['_key']; ?>
                                    <a
                                        class="article-link"
                                        href="/modules/Library/index.php?article=<?= gm_library_h(rawurlencode($key)) ?>"
                                        <?= $key === (string)$selectedArticle['_key'] ? 'aria-current="page"' : '' ?>
                                    >
                                        <strong><?= gm_library_h((string)$article['title']) ?></strong>
                                        <span><?= gm_library_h(gm_library_date(isset($article['date']) ? (string)$article['date'] : null)) ?></span>
                                        <p><?= gm_library_h(gm_library_excerpt(isset($article['text']) ? (string)$article['text'] : '')) ?></p>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </aside>

                    <article class="reader panel">
                        <div class="reader-image">
                            <?php if ($selectedImage !== ''): ?>
                                <img src="<?= gm_library_h($selectedImage) ?>" alt="">
                            <?php endif; ?>
                        </div>
                        <div class="reader-body">
                            <p class="eyebrow"><?= gm_library_h((string)$selectedArticle['_category']) ?></p>
                            <div class="meta">
                                <span><?= gm_library_h(gm_library_date(isset($selectedArticle['date']) ? (string)$selectedArticle['date'] : null)) ?></span>
                                <?php if (!empty($selectedArticle['author'])): ?>
                                    <span><?= gm_library_h((string)$selectedArticle['author']) ?></span>
                                <?php endif; ?>
                            </div>
                            <h2><?= gm_library_h((string)$selectedArticle['title']) ?></h2>
                            <div class="article-text">
                                <?= gm_library_body(isset($selectedArticle['text']) ? (string)$selectedArticle['text'] : '') ?>
                            </div>
                        </div>
                    </article>
                </section>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
