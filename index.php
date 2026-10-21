<?php
/**
 * Gallery Hub - Cute, Feminine, Aesthetic Media Gallery 🌸✨
 * Folder-based navigation, file rename & delete, English UI.
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
            $loginError = 'Incorrect password, please try again~ 🥺💖';
        }
    }

    // Show login form if not authenticated
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sweet Login ~ Gallery Hub 🎀</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text x=%2250%25%22 y=%2250%25%22 font-size=%2280%22 text-anchor=%22middle%22 dominant-baseline=%22central%22>🎀</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <canvas id="floatingCanvas"></canvas>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-brand-icon">🎀</div>
            <h2>Gallery Hub</h2>
            <p>Enter the password to explore this lovely gallery~ ✨🌸</p>

            <?php if (!empty($loginError)): ?>
                <div class="error-msg"><?php echo htmlspecialchars($loginError); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="password" name="password" class="login-input" placeholder="Type password here... 💖" required autofocus>
                <button type="submit" class="login-btn">Unlock Gallery 🗝️✨</button>
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

// Category filter: strictly 'image', 'video', or 'music'
$category = isset($_GET['cat']) ? $_GET['cat'] : 'image';
if (!in_array($category, array('image', 'video', 'music'), true)) {
    $category = 'image';
}

// Format bytes
function formatBytes($bytes, $precision = 1) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Folder Resolution
$baseReal = realpath($mediaDirectory);
$rawFolder = isset($_GET['folder']) ? trim($_GET['folder']) : '';
$currentFolder = '';
$targetDir = $baseReal;

if ($baseReal && $rawFolder !== '') {
    $normalized = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $rawFolder);
    $candidate = realpath($baseReal . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR));
    if ($candidate && is_dir($candidate) && strpos($candidate, $baseReal) === 0) {
        $targetDir = $candidate;
        $currentFolder = ltrim(substr($candidate, strlen($baseReal)), DIRECTORY_SEPARATOR);
    }
}

// Calculate breadcrumbs and parent folder
$breadcrumbs = array();
$breadcrumbs[] = array('name' => 'Root', 'path' => '');
if ($currentFolder !== '') {
    $parts = explode(DIRECTORY_SEPARATOR, $currentFolder);
    $accum = '';
    foreach ($parts as $p) {
        if ($p === '') continue;
        $accum = ($accum === '' ? $p : $accum . '/' . $p);
        $breadcrumbs[] = array('name' => $p, 'path' => $accum);
    }
}

$parentFolder = null;
if ($currentFolder !== '') {
    $parent = dirname($currentFolder);
    $parentFolder = ($parent === '.' || $parent === '') ? '' : str_replace('\\', '/', $parent);
}

// Scan the CURRENT directory (subfolders + media files)
$subfolders = array();
$mediaList = array();
$imageCount = 0;
$videoCount = 0;
$musicCount = 0;

if ($targetDir && is_dir($targetDir)) {
    $items = @scandir($targetDir);
    if ($items !== false) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'Thumbs.db') continue;
            $fullPath = $targetDir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($fullPath)) {
                $subRel = ltrim(substr($fullPath, strlen($baseReal)), DIRECTORY_SEPARATOR);
                $subItems = @scandir($fullPath);
                $subCount = 0;
                if ($subItems !== false) {
                    foreach ($subItems as $si) {
                        if ($si === '.' || $si === '..' || $si === 'Thumbs.db') continue;
                        $subCount++;
                    }
                }
                $subfolders[] = array(
                    'name'    => $item,
                    'relPath' => str_replace('\\', '/', $subRel),
                    'count'   => $subCount
                );
            } elseif (is_file($fullPath)) {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                $type = null;

                if (in_array($ext, $imageExtensions, true)) {
                    $type = 'image';
                    $imageCount++;
                } elseif (in_array($ext, $videoExtensions, true)) {
                    $type = 'video';
                    $videoCount++;
                } elseif (in_array($ext, $audioExtensions, true)) {
                    $type = 'music';
                    $musicCount++;
                }

                if ($type !== null) {
                    if ($category === $type) {
                        $fileRel = ltrim(substr($fullPath, strlen($baseReal)), DIRECTORY_SEPARATOR);
                        $folderName = ($currentFolder === '') ? 'Root' : basename($currentFolder);
                        $fileSize = @filesize($fullPath) ?: 0;
                        $fileTime = @filemtime($fullPath) ?: 0;

                        $mediaList[] = array(
                            'name'          => $item,
                            'relPath'       => str_replace('\\', '/', $fileRel),
                            'folder'        => $folderName,
                            'type'          => $type,
                            'ext'           => $ext,
                            'size'          => $fileSize,
                            'sizeFormatted' => formatBytes($fileSize),
                            'mtime'         => $fileTime,
                            'dateFormatted' => $fileTime ? date('M d, Y', $fileTime) : '-'
                        );
                    }
                }
            }
        }
    }
}

// Sort subfolders by name alphabetically
usort($subfolders, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery Hub 🌸✨</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text x=%2250%25%22 y=%2250%25%22 font-size=%2280%22 text-anchor=%22middle%22 dominant-baseline=%22central%22>🎀</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="gallery.js" defer></script>
</head>
<body>

    <!-- Floating Cute Background Animation Canvas -->
    <canvas id="floatingCanvas"></canvas>

    <!-- Top Bar Navigation -->
    <header class="top-bar">
        <div class="top-bar-inner">
            <a href="index.php?cat=<?php echo $category; ?>" class="brand" title="Home">
                <span class="brand-icon">🎀</span>
                <span class="brand-name">Gallery Hub</span>
                <span class="brand-sparkle">✨</span>
            </a>

            <button class="mobile-toggle" onclick="toggleTopMenu()" aria-label="Menu">🌸</button>

            <!-- Nav Tabs: Photos, Videos & Music -->
            <nav class="top-nav" id="topNav">
                <a href="index.php?cat=image<?php echo ($currentFolder !== '' ? '&folder=' . urlencode(str_replace('\\', '/', $currentFolder)) : ''); ?>" 
                   class="nav-item <?php if ($category === 'image') echo 'active'; ?>">
                    🌸 Photos <span class="nav-badge"><?php echo $imageCount; ?></span>
                </a>
                <a href="index.php?cat=video<?php echo ($currentFolder !== '' ? '&folder=' . urlencode(str_replace('\\', '/', $currentFolder)) : ''); ?>" 
                   class="nav-item <?php if ($category === 'video') echo 'active'; ?>">
                    🎀 Videos <span class="nav-badge"><?php echo $videoCount; ?></span>
                </a>
                <a href="index.php?cat=music<?php echo ($currentFolder !== '' ? '&folder=' . urlencode(str_replace('\\', '/', $currentFolder)) : ''); ?>" 
                   class="nav-item <?php if ($category === 'music') echo 'active'; ?>">
                    🎵 Music <span class="nav-badge"><?php echo $musicCount; ?></span>
                </a>
            </nav>

            <div class="top-controls" id="topControls">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="searchInput" oninput="filterAndSortMedia()" placeholder="Search in this folder... 🍓">
                </div>

                <div class="sort-select-wrapper">
                    <span class="sort-icon">✨</span>
                    <select id="sortSelect" class="sort-select" onchange="filterAndSortMedia()">
                        <option value="date-desc">🌸 Newest</option>
                        <option value="date-asc">⏳ Oldest</option>
                        <option value="name-asc">🔤 Name (A → Z)</option>
                        <option value="name-desc">🔤 Name (Z → A)</option>
                        <option value="size-desc">📦 Largest</option>
                        <option value="size-asc">🎀 Smallest</option>
                    </select>
                </div>

                <div class="item-counter-badge">
                    <span class="counter-heart">💖</span>
                    <strong id="itemCount"><?php echo count($mediaList); ?></strong> items
                </div>

                <?php if ($isAuthEnabled): ?>
                    <a href="index.php?action=logout" class="nav-item nav-item-logout" title="Sign out">👋 Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="main-content">

        <!-- Folder Breadcrumbs & Navigation Bar -->
        <div class="folder-header-bar">
            <div class="breadcrumbs-trail">
                <span class="crumb-icon">📂</span>
                <?php foreach ($breadcrumbs as $idx => $crumb): ?>
                    <?php if ($idx > 0): ?>
                        <span class="crumb-separator">/</span>
                    <?php endif; ?>
                    <?php if ($idx === count($breadcrumbs) - 1): ?>
                        <span class="crumb-item crumb-current"><?php echo htmlspecialchars($crumb['name']); ?></span>
                    <?php else: ?>
                        <a href="index.php?cat=<?php echo $category; ?>&folder=<?php echo urlencode($crumb['path']); ?>" class="crumb-item crumb-link">
                            <?php echo htmlspecialchars($crumb['name']); ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <?php if ($parentFolder !== null): ?>
                <a href="index.php?cat=<?php echo $category; ?>&folder=<?php echo urlencode($parentFolder); ?>" class="back-folder-btn">
                    ⬅️ Back to Parent
                </a>
            <?php endif; ?>
        </div>

        <!-- Subfolders Section (if any) -->
        <?php if (!empty($subfolders)): ?>
            <div class="subfolders-section">
                <h3 class="section-title">📁 Subfolders <span class="section-count">(<?php echo count($subfolders); ?>)</span></h3>
                <div class="folders-grid">
                    <?php foreach ($subfolders as $sf): ?>
                        <a href="index.php?cat=<?php echo $category; ?>&folder=<?php echo urlencode($sf['relPath']); ?>" class="folder-card">
                            <div class="folder-icon-wrap">📁</div>
                            <div class="folder-details">
                                <span class="folder-card-name" title="<?php echo htmlspecialchars($sf['name']); ?>">
                                    <?php echo htmlspecialchars($sf['name']); ?>
                                </span>
                                <span class="folder-card-badge"><?php echo $sf['count']; ?> items</span>
                            </div>
                            <span class="folder-arrow">➔</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Media Files Section -->
        <div class="media-section">
            <h3 class="section-title">
                <?php 
                if ($category === 'image') echo '🌸 Photos in this folder';
                elseif ($category === 'video') echo '🎀 Videos in this folder';
                else echo '🎵 Music in this folder';
                ?>
                <span class="section-count">(<?php echo count($mediaList); ?>)</span>
            </h3>

            <div class="gallery-grid" id="galleryGrid">
                <?php if (empty($mediaList)): ?>
                    <div class="empty-media-msg">
                        <div class="empty-icon-wrap">
                            <span class="empty-icon">🥺🌸</span>
                        </div>
                        <h3>No <?php 
                            if ($category === 'image') echo 'Photos';
                            elseif ($category === 'video') echo 'Videos';
                            else echo 'Music Tracks';
                        ?> in This Folder</h3>
                        <p class="empty-desc">
                            <?php if (!empty($subfolders)): ?>
                                Check out the subfolders above, or switch tabs to explore! 🎀✨
                            <?php else: ?>
                                This folder does not have any media files matching this category yet.
                            <?php endif; ?>
                        </p>
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
                                    <?php 
                                    if ($item['type'] === 'image') echo '🌸 PHOTO';
                                    elseif ($item['type'] === 'video') echo '🎀 VIDEO';
                                    else echo '🎵 MUSIC';
                                    ?>
                                </span>

                                <?php if ($item['type'] === 'image'): ?>
                                    <img src="media_server.php?file=<?php echo rawurlencode($item['relPath']); ?>" loading="lazy" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>">
                                <?php elseif ($item['type'] === 'video'): ?>
                                    <canvas class="thumb-canvas"></canvas>
                                    <span class="video-play-overlay">
                                        <span class="play-symbol">▶</span>
                                    </span>
                                <?php else: ?>
                                    <div class="audio-card-visual">
                                        <div class="vinyl-record">
                                            <div class="vinyl-center">🎵</div>
                                        </div>
                                        <div class="audio-bars">
                                            <span></span><span></span><span></span><span></span><span></span>
                                        </div>
                                    </div>
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

                                <!-- Card Action Buttons: Rename & Delete -->
                                <div class="card-action-bar">
                                    <button type="button" class="action-btn btn-rename" 
                                            onclick="event.stopPropagation(); triggerRename('<?php echo htmlspecialchars($item['relPath'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>')"
                                            title="Rename file">
                                        ✏️ Rename
                                    </button>
                                    <button type="button" class="action-btn btn-delete" 
                                            onclick="event.stopPropagation(); triggerDelete('<?php echo htmlspecialchars($item['relPath'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>')"
                                            title="Delete file">
                                        🗑️ Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <!-- Modal Lightbox / Player -->
    <div id="mediaModal" class="modal">
        <button class="close-btn" onclick="closePlayer()" aria-label="Close">🌸</button>
        <div class="modal-content-wrapper" id="modalContainer"></div>
    </div>

    <!-- Cute Rename Dialog Modal -->
    <div id="renameDialog" class="cute-dialog-overlay">
        <div class="cute-dialog-card">
            <div class="dialog-icon">✏️✨</div>
            <h3 class="dialog-title">Rename File</h3>
            <p class="dialog-subtitle">Enter a cute new name for this file:</p>
            <div class="dialog-filename-preview" id="renameCurrentFile"></div>
            
            <form id="renameForm" onsubmit="submitRename(event)">
                <div class="rename-input-wrap">
                    <input type="text" id="renameInput" class="dialog-input" required autocomplete="off" spellcheck="false">
                    <span id="renameExtPreview" class="ext-badge"></span>
                </div>
                <div class="dialog-button-row">
                    <button type="button" class="dialog-btn btn-secondary" onclick="closeRenameDialog()">Cancel 🌸</button>
                    <button type="submit" class="dialog-btn btn-primary">Save Name ✨</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cute Delete Confirmation Dialog Modal -->
    <div id="deleteDialog" class="cute-dialog-overlay">
        <div class="cute-dialog-card">
            <div class="dialog-icon">🗑️💔</div>
            <h3 class="dialog-title">Delete File?</h3>
            <p class="dialog-subtitle">Are you sure you want to delete this file?</p>
            <div class="dialog-filename-preview highlight-delete" id="deleteCurrentFile"></div>
            <p class="delete-warning">⚠️ This action cannot be undone!</p>
            
            <div class="dialog-button-row">
                <button type="button" class="dialog-btn btn-secondary" onclick="closeDeleteDialog()">Cancel 🌸</button>
                <button type="button" class="dialog-btn btn-danger" onclick="submitDelete()">Delete Forever 💔</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="toast-container"></div>

</body>
</html>
