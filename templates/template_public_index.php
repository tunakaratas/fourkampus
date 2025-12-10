<?php
/**
 * Community public landing template.
 * Lightweight, read-only page shown at /communities/{slug}/public/.
 */

if (!defined('APP_ENV')) {
    $env = getenv('APP_ENV') ?: ($_SERVER['APP_ENV'] ?? null);
    define('APP_ENV', $env ? strtolower($env) : 'production');
}

if (APP_ENV === 'production') {
    error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

$tplLogFile = __DIR__ . '/../logs/php_errors.log';
if (!file_exists($tplLogFile)) {
    @touch($tplLogFile);
}
@chmod($tplLogFile, 0600);
ini_set('log_errors', 1);
ini_set('error_log', $tplLogFile);

require_once __DIR__ . '/partials/logging.php';
require_once __DIR__ . '/partials/security_headers.php';
require_once __DIR__ . '/partials/path_guard.php';
require_once __DIR__ . '/partials/db_security.php';
require_once __DIR__ . '/community_bootstrap.php';

if (!defined('DB_PATH')) {
    define('DB_PATH', community_path('unipanel.sqlite'));
}
ensure_database_permissions(DB_PATH);

set_security_headers();

/**
 * Local DB helper (lite version – no full module bootstrap required).
 */
if (!function_exists('tpl_public_db')) {
    function tpl_public_db(): SQLite3
    {
        static $db = null;
        if ($db instanceof SQLite3) {
            return $db;
        }

        $db = new SQLite3(DB_PATH);
        $db->busyTimeout(3000);
        @$db->exec('PRAGMA foreign_keys = ON');
        return $db;
    }
}

if (!function_exists('tpl_public_get_setting')) {
    function tpl_public_get_setting(string $key, $default = '')
    {
        try {
            $db = tpl_public_db();
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE club_id = :club AND setting_key = :key LIMIT 1");
            $stmt->bindValue(':club', 1, SQLITE3_INTEGER);
            $stmt->bindValue(':key', $key, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : null;
            return $row && isset($row['setting_value']) ? $row['setting_value'] : $default;
        } catch (Throwable $e) {
            tpl_error_log("public_view get_setting error: " . $e->getMessage());
            return $default;
        }
    }
}

if (!function_exists('tpl_public_get_events')) {
    function tpl_public_get_events(int $limit = 3): array
    {
        try {
            $db = tpl_public_db();
            $stmt = $db->prepare("
                SELECT id, title, date, time, location, category, description
                FROM events
                WHERE club_id = :club
                ORDER BY date DESC, time DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':club', 1, SQLITE3_INTEGER);
            $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $events = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $events[] = $row;
            }
            return $events;
        } catch (Throwable $e) {
            tpl_error_log("public_view events error: " . $e->getMessage());
            return [];
        }
    }
}

$clubName = tpl_public_get_setting('club_name', basename(COMMUNITY_BASE_PATH));
$clubDescription = tpl_public_get_setting('club_description', 'Topluluğumuz faaliyetleriyle öğrencileri bir araya getirir.');
$uiLanguage = tpl_public_get_setting('ui_language', 'tr');
$publicContact = tpl_public_get_setting('contact_email', tpl_public_get_setting('smtp_from_email', ''));
$publicPhone = tpl_public_get_setting('contact_phone', '');
$featuredEvents = tpl_public_get_events(3);
$logoPath = tpl_public_get_setting('club_logo', '');
$heroImage = $logoPath && file_exists(community_path($logoPath)) ? $logoPath : 'assets/images/public-hero.jpg';
$publicLinks = [
    'Instagram' => tpl_public_get_setting('social_instagram', ''),
    'Twitter' => tpl_public_get_setting('social_twitter', ''),
    'LinkedIn' => tpl_public_get_setting('social_linkedin', ''),
];

?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($uiLanguage ?: 'tr') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($clubName) ?> | Topluluk Tanıtımı</title>
    <script src="https://cdn.tailwindcss.com" crossorigin="anonymous"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: radial-gradient(circle at top, rgba(59,130,246,.25), transparent 60%); }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
    <div class="relative overflow-hidden gradient-bg">
        <header class="max-w-6xl mx-auto px-6 pt-12 pb-16">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-8">
                <div>
                    <p class="text-sm uppercase tracking-wide text-blue-600 font-semibold">Topluluk Tanıtım Sayfası</p>
                    <h1 class="mt-3 text-4xl sm:text-5xl font-bold text-slate-900 leading-tight">
                        <?= htmlspecialchars($clubName) ?>
                    </h1>
                    <p class="mt-4 text-lg text-slate-600 max-w-2xl">
                        <?= nl2br(htmlspecialchars($clubDescription)) ?>
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="../index.php" class="inline-flex items-center px-5 py-2.5 text-white bg-blue-600 hover:bg-blue-700 rounded-full text-sm font-semibold shadow">
                            Yönetim Paneline Giriş
                        </a>
                        <a href="../login.php" class="inline-flex items-center px-5 py-2.5 text-blue-600 bg-white border border-blue-200 hover:border-blue-300 rounded-full text-sm font-semibold shadow-sm">
                            Üye / Yönetici Girişi
                        </a>
                    </div>
                </div>
                <div class="w-full lg:w-56 bg-white/80 backdrop-blur border border-white shadow-xl rounded-3xl p-5 flex flex-col items-center text-center">
                    <div class="w-24 h-24 rounded-full bg-slate-100 flex items-center justify-center mb-4 overflow-hidden">
                        <?php if ($logoPath && file_exists(community_path($logoPath))): ?>
                            <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-3xl font-bold text-blue-600"><?= mb_substr($clubName, 0, 1) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-slate-500">İletişim</p>
                    <?php if ($publicContact): ?>
                        <a href="mailto:<?= htmlspecialchars($publicContact) ?>" class="text-base font-semibold text-slate-800 mt-1 hover:text-blue-600">
                            <?= htmlspecialchars($publicContact) ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($publicPhone): ?>
                        <p class="text-sm text-slate-500 mt-2"><?= htmlspecialchars($publicPhone) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </header>
    </div>

    <main class="max-w-6xl mx-auto px-6 pb-16 space-y-16">
        <?php if (!empty($featuredEvents)): ?>
        <section>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <p class="text-sm uppercase tracking-wide text-slate-500 font-semibold">Etkinlikler</p>
                    <h2 class="text-2xl font-bold text-slate-900 mt-1">Öne Çıkan Programlar</h2>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach ($featuredEvents as $event): ?>
                <article class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <p class="text-xs uppercase tracking-wide text-blue-600 font-semibold">
                        <?= htmlspecialchars($event['category'] ?? 'Etkinlik') ?>
                    </p>
                    <h3 class="mt-2 text-xl font-semibold text-slate-900">
                        <?= htmlspecialchars($event['title'] ?? 'Etkinlik') ?>
                    </h3>
                    <?php if (!empty($event['date'])): ?>
                        <p class="mt-2 text-sm text-slate-500">
                            <?= date('d.m.Y', strtotime($event['date'])) ?>
                            <?php if (!empty($event['time'])): ?>
                                • <?= htmlspecialchars($event['time']) ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($event['location'])): ?>
                        <p class="text-sm text-slate-500"><?= htmlspecialchars($event['location']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($event['description'])): ?>
                        <p class="mt-3 text-sm text-slate-600 line-clamp-3"><?= htmlspecialchars($event['description']) ?></p>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
                <h2 class="text-2xl font-bold text-slate-900 mb-3">Vizyon & Misyon</h2>
                <p class="text-slate-600 leading-relaxed">
                    <?= nl2br(htmlspecialchars(tpl_public_get_setting('vision_statement', 'Topluluğumuz, öğrencilerin kendini geliştirdiği ve birlikte ürettiği bir ekosistem kurmayı hedefler.'))) ?>
                </p>
                <div class="grid grid-cols-2 gap-4 mt-8">
                    <div>
                        <p class="text-sm uppercase tracking-wide text-slate-500">Üye Sayısı</p>
                        <p class="text-3xl font-semibold text-blue-600"><?= htmlspecialchars(tpl_public_get_setting('public_member_count', '250+')) ?></p>
                    </div>
                    <div>
                        <p class="text-sm uppercase tracking-wide text-slate-500">Aktif Proje</p>
                        <p class="text-3xl font-semibold text-blue-600"><?= htmlspecialchars(tpl_public_get_setting('public_project_count', '12')) ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-slate-900 text-white rounded-3xl p-8 shadow-lg">
                <h2 class="text-2xl font-bold mb-3">Bize Katılın</h2>
                <p class="text-slate-200 leading-relaxed">
                    Topluluk faaliyetlerimize katılmak veya iş birliği yapmak için bize ulaşabilirsiniz. Sosyal medya hesaplarımızı takip edin.
                </p>
                <div class="mt-6 flex flex-col gap-3">
                    <?php foreach ($publicLinks as $label => $url): ?>
                        <?php if (!empty($url)): ?>
                            <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener" class="inline-flex items-center justify-between px-4 py-2 bg-white/10 hover:bg-white/20 rounded-xl">
                                <span><?= htmlspecialchars($label) ?></span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>
</body>
</html>

