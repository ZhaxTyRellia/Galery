<?php
/**
 * Kawaii Gallery Hub - Cute, Feminine, Aesthetic Media Gallery 🌸✨
 */

require_once __DIR__ . '/config.php';

// Session lifetime: 12 hours (43200s)
$sessionLifetime = 43200;
$isAuthEnabled = !empty($galleryPassword);

if ($isAuthEnabled) {
    ini_set('session.gc_maxlifetime', $sessionLifetime);
    session_set_cookie_params($sessionLifetime);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check session expiration
    if (isset($_SESSION['logged_in_time']) && (time() - $_SESSION['logged_in_time'] > $sessionLifetime)) {
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit;
    }

    // Handle logout action
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit;
    }

    // Handle login submission
    $loginError = '';
    if (isset($_POST['password'])) {
        if ($_POST['password'] === $galleryPassword) {
            $_SESSION['authenticated'] = true;
            $_SESSION['logged_in_time'] = time();
            header("Location: index.php");
            exit;
        } else {
            $loginError = 'Kata sandi kurang tepat nih, coba lagi yaa~ 🥺💖';
        }
    }

    // Show login form if not authenticated
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Manis ~ Kawaii Gallery 🎀</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text x=%2250%25%22 y=%2250%25%22 font-size=%2280%22 text-anchor=%22middle%22 dominant-baseline=%22central%22>🎀</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <canvas id="kawaiiCanvas"></canvas>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-brand-icon">🎀</div>
            <h2>Kawaii Gallery</h2>
            <p>Masukkan kata sandi untuk menjelajahi galeri gemas ini yaa~ ✨🌸</p>

            <?php if (!empty($loginError)): ?>
                <div class="error-msg"><?php echo htmlspecialchars($loginError); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="password" name="password" class="login-input" placeholder="Ketik kata sandi di sini... 💖" required autofocus>
                <button type="submit" class="login-btn">Buka Pintu Manis 🗝️✨</button>
            </form>
        </div>
    </div>
    <script src="gallery.js" defer></script>
</body>
</html>
<?php
        exit;
    }
}

// Media scanning logic
$category = isset($_GET['cat']) ? $_GET['cat'] : 'all';
if (!in_array($category, array('all', 'image', 'video'), true)) {
    $category = 'all';
}

$mediaList = array();
$totalCount = 0;
$imageCount = 0;
$videoCount = 0;

/**
 * Format bytes to readable string
 */
function formatBytes($bytes, $precision = 1) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Recursively scans directory for media files
 */
function scanDirectoryRecursively($dir, $baseDir, $imageExts, $videoExts, $category, &$result, &$imgCount, &$vidCount)
{
    if (!file_exists($dir) || !is_dir($dir)) return;
    $items = @scandir($dir);
    if ($items === false) return;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($fullPath)) {
            scanDirectoryRecursively($fullPath, $baseDir, $imageExts, $videoExts, $category, $result, $imgCount, $vidCount);
        } else {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $type = null;

            if (in_array($ext, $imageExts, true)) {
                $type = 'image';
                $imgCount++;
            } elseif (in_array($ext, $videoExts, true)) {
                $type = 'video';
                $vidCount++;
            }

            if ($type !== null) {
                if ($category === 'all' || $category === $type) {
                    $relPath = ltrim(substr($fullPath, strlen($baseDir)), '\\/');
                    $folderName = dirname($relPath);
                    if ($folderName === '.' || $folderName === '') {
                        $folderName = 'Root';
                    }

                    $fileTime = @filemtime($fullPath) ?: 0;
                    $fileSize = @filesize($fullPath) ?: 0;

                    $result[] = array(
                        'name'          => $item,
                        'relPath'       => $relPath,
                        'folder'        => $folderName,
                        'type'          => $type,
                        'ext'           => $ext,
                        'size'          => $fileSize,
                        'sizeFormatted' => formatBytes($fileSize),
                        'mtime'         => $fileTime,
                        'dateFormatted' => $fileTime ? date('d M Y', $fileTime) : '-'
                    );
                }
            }
        }
    }
}

if (file_exists($mediaDirectory) && is_dir($mediaDirectory)) {
    scanDirectoryRecursively(
        $mediaDirectory,
        $mediaDirectory,
        $imageExtensions,
        $videoExtensions,
        $category,
        $mediaList,
        $imageCount,
        $videoCount
    );
    $totalCount = $imageCount + $videoCount;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kawaii Gallery Hub 🌸✨</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text x=%2250%25%22 y=%2250%25%22 font-size=%2280%22 text-anchor=%22middle%22 dominant-baseline=%22central%22>🎀</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="gallery.js" defer></script>
</head>
<body>

    <!-- Floating Cute Background Animation Canvas -->
    <canvas id="kawaiiCanvas"></canvas>

    <!-- Top Bar Navigation -->
    <header class="top-bar">
        <div class="top-bar-inner">
            <div class="brand">
                <span class="brand-icon">🎀</span>
                <span class="brand-name">Kawaii Gallery</span>
                <span class="brand-sparkle">✨</span>
            </div>

            <button class="mobile-toggle" onclick="toggleTopMenu()" aria-label="Menu">🌸</button>

            <nav class="top-nav" id="topNav">
                <a href="index.php?cat=all" class="nav-item <?php if ($category === 'all') echo 'active'; ?>">
                    💖 Semua <span class="nav-badge"><?php echo $totalCount; ?></span>
                </a>
                <a href="index.php?cat=image" class="nav-item <?php if ($category === 'image') echo 'active'; ?>">
                    🌸 Foto <span class="nav-badge"><?php echo $imageCount; ?></span>
                </a>
                <a href="index.php?cat=video" class="nav-item <?php if ($category === 'video') echo 'active'; ?>">
                    🎀 Video <span class="nav-badge"><?php echo $videoCount; ?></span>
                </a>
            </nav>

            <div class="top-controls" id="topControls">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="searchInput" oninput="filterAndSortMedia()" placeholder="Cari media manis... 🍓">
                </div>

                <div class="sort-select-wrapper">
                    <span class="sort-icon">✨</span>
                    <select id="sortSelect" class="sort-select" onchange="filterAndSortMedia()">
                        <option value="date-desc">🌸 Terbaru</option>
                        <option value="date-asc">⏳ Terlama</option>
                        <option value="name-asc">🔤 Nama (A → Z)</option>
                        <option value="name-desc">🔤 Nama (Z → A)</option>
                        <option value="size-desc">📦 Terbesar</option>
                        <option value="size-asc">🎀 Terkecil</option>
                    </select>
                </div>

                <div class="item-counter-badge">
                    <span class="counter-heart">💖</span>
                    <strong id="itemCount"><?php echo count($mediaList); ?></strong> item manis
                </div>

                <?php if ($isAuthEnabled): ?>
                    <a href="index.php?action=logout" class="nav-item nav-item-logout" title="Keluar sesi">👋 Keluar</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="main-content">
        <div class="gallery-grid" id="galleryGrid">
            <?php if (empty($mediaList)): ?>
                <div class="empty-media-msg">
                    <div class="empty-icon-wrap">
                        <span class="empty-icon">🥺🌸</span>
                    </div>
                    <h3>Media Belum Ditemukan Yaa~</h3>
                    <p class="empty-path">Folder: <code><?php echo htmlspecialchars($mediaDirectory); ?></code></p>
                    <p class="empty-desc">Pastikan foldernya sudah ada dan berisi foto atau video lucu yaa! 🎀✨</p>
                </div>
            <?php else: ?>
                <?php foreach ($mediaList as $index => $item): ?>
                    <div class="media-card"
                        data-index="<?php echo $index; ?>"
                        data-name="<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>"
                        data-title="<?php echo htmlspecialchars(strtolower($item['name']), ENT_QUOTES); ?>"
                        data-folder="<?php echo htmlspecialchars(strtolower($item['folder']), ENT_QUOTES); ?>"
                        data-mtime="<?php echo $item['mtime']; ?>"
                        data-size="<?php echo $item['size']; ?>"
                        data-relpath="<?php echo rawurlencode($item['relPath']); ?>"
                        data-type="<?php echo $item['type']; ?>"
                        onclick="openPlayerByIndex(<?php echo $index; ?>)">

                        <div class="media-thumb-container">
                            <span class="badge-type">
                                <?php echo $item['type'] === 'image' ? '🌸 FOTO' : '🎀 VIDEO'; ?>
                            </span>

                            <?php if ($item['type'] === 'image'): ?>
                                <img src="media_server.php?file=<?php echo rawurlencode($item['relPath']); ?>" loading="lazy" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>">
                            <?php else: ?>
                                <canvas class="thumb-canvas"></canvas>
                                <span class="video-play-overlay">
                                    <span class="play-symbol">▶</span>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="media-info">
                            <p class="media-title" title="<?php echo htmlspecialchars($item['name']); ?>">
                                <?php echo htmlspecialchars($item['name']); ?>
                            </p>
                            <div class="media-meta-row">
                                <span class="media-folder" title="Folder: <?php echo htmlspecialchars($item['folder']); ?>">
                                    📁 <?php echo htmlspecialchars($item['folder']); ?>
                                </span>
                                <span class="media-size">🍬 <?php echo $item['sizeFormatted']; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Lightbox / Player -->
    <div id="mediaModal" class="modal">
        <button class="close-btn" onclick="closePlayer()" aria-label="Tutup">🌸</button>
        <div class="modal-content-wrapper" id="modalContainer"></div>
    </div>

</body>
</html>
