<?php
// Set session duration to 12 hours (43200 seconds)
$sessionLifetime = 43200;

ini_set('session.gc_maxlifetime', $sessionLifetime);
session_set_cookie_params($sessionLifetime);
session_start();

// Gallery access password
$galleryPassword = 'xxx';

// Check and manage session expiry (12 hours)
if (isset($_SESSION['logged_in_time'])) {
    if (time() - $_SESSION['logged_in_time'] > $sessionLifetime) {
        session_unset();
        session_destroy();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Process login form submission
$loginError = '';
if (isset($_POST['password'])) {
    if ($_POST['password'] === $galleryPassword) {
        $_SESSION['authenticated'] = true;
        $_SESSION['logged_in_time'] = time(); // Save login timestamp
    } else {
        $loginError = 'Incorrect password, please try again!';
    }
}

// Logout process
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Show login page if not authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Expression Hub</title>
        <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text x=%2250%25%22 y=%2250%25%22 font-size=%2280%22 text-anchor=%22middle%22 dominant-baseline=%22central%22>☠</text></svg>">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=UnifrakturMaguntia&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="style.css">
    </head>

    <body>
        <div class="login-wrapper">
            <div class="login-card">
                <h2>Expression Hub 🔒</h2>
                <p>Enter password to access gallery</p>

                <?php if (!empty($loginError)): ?>
                    <div class="error-msg"><?php echo $loginError; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="password" name="password" class="login-input" placeholder="Password..." required autofocus>
                    <button type="submit" class="login-btn">Login ✨</button>
                </form>
            </div>
        </div>
    </body>

    </html>
<?php
    exit;
}

// Main media scanning logic
require_once __DIR__ . '/config.php';

$category = isset($_GET['cat']) ? $_GET['cat'] : 'all';
$mediaList = array();

/**
 * Recursively scan directory
 */
function scanDirectoryRecursively(
    string $dir,
    string $baseDir,
    array $imageExts,
    array $videoExts,
    string $category,
    array &$result
) {
    if (!file_exists($dir) || !is_dir($dir)) return;

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($fullPath)) {
            scanDirectoryRecursively($fullPath, $baseDir, $imageExts, $videoExts, $category, $result);
        } else {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $type = null;

            if (in_array($ext, $imageExts)) {
                $type = 'image';
            } elseif (in_array($ext, $videoExts)) {
                $type = 'video';
            }

            if ($type !== null) {
                if ($category === 'all' || $category === $type) {
                    $relativePath = ltrim(str_replace($baseDir, '', $fullPath), '\\/');
                    $folderName = dirname($relativePath);
                    if ($folderName === '.') $folderName = 'Root';

                    $fileTime = filemtime($fullPath);

                    $result[] = array(
                        'name'     => $item,
                        'relPath'  => $relativePath,
                        'folder'   => $folderName,
                        'type'     => $type,
                        'ext'      => $ext,
                        'mtime'    => $fileTime
                    );
                }
            }
        }
    }
}

scanDirectoryRecursively($mediaDirectory, $mediaDirectory, $imageExtensions, $videoExtensions, $category, $mediaList);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galery - Expression Hub</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text x=%2250%25%22 y=%2250%25%22 font-size=%2280%22 text-anchor=%22middle%22 dominant-baseline=%22central%22>☠</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=UnifrakturMaguntia&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <!-- Top Bar -->
    <header class="top-bar">
        <div class="top-bar-inner">
            <div class="brand">
                <span class="brand-icon">☠</span>
                <span class="brand-name">Galery Hub</span>
            </div>

            <button class="mobile-toggle" onclick="toggleTopMenu()">☰</button>

            <nav class="top-nav" id="topNav">
                <a href="index.php?cat=all" class="nav-item <?php if ($category === 'all') echo 'active'; ?>">All Media</a>
                <a href="index.php?cat=image" class="nav-item <?php if ($category === 'image') echo 'active'; ?>">Images</a>
                <a href="index.php?cat=video" class="nav-item <?php if ($category === 'video') echo 'active'; ?>">Video</a>
            </nav>

            <div class="top-controls" id="topControls">
                <div class="search-box">
                    <span class="search-icon">?</span>
                    <input type="text" id="searchInput" onkeyup="filterAndSortMedia()" placeholder="Search media or folder...">
                </div>

                <div class="sort-select-wrapper">
                    <select id="sortSelect" class="sort-select" onchange="filterAndSortMedia()">
                        <option value="date-desc">Newest</option>
                        <option value="date-asc">Oldest</option>
                        <option value="name-asc">A → Z</option>
                        <option value="name-desc">Z → A</option>
                    </select>
                </div>

                <div class="item-counter-badge">
                    <strong><?php echo count($mediaList); ?></strong> items
                </div>

                <a href="index.php?action=logout" class="nav-item nav-item-logout">Logout</a>
            </div>
        </div>
    </header>

    <main class="main-content">
        <!-- Gallery Grid -->
        <div class="gallery-grid" id="galleryGrid">
                <?php if (empty($mediaList)): ?>
                    <p class="empty-media-msg">
                        (⁠•⁠–⁠•⁠) Media file not found. (⁠•⁠–⁠•⁠)<code><?php echo htmlspecialchars($mediaDirectory); ?></code>
                    </p>
                <?php else: ?>
                    <?php foreach ($mediaList as $index => $item): ?>
                        <div class="media-card"
                            data-index="<?php echo $index; ?>"
                            data-title="<?php echo strtolower(htmlspecialchars($item['name'])); ?>"
                            data-folder="<?php echo strtolower(htmlspecialchars($item['folder'])); ?>"
                            data-mtime="<?php echo $item['mtime']; ?>"
                            data-relpath="<?php echo urlencode($item['relPath']); ?>"
                            data-type="<?php echo $item['type']; ?>"
                            onclick="openPlayerByIndex(<?php echo $index; ?>)">

                            <div class="media-thumb-container">
                                <span class="badge-type">
                                    <?php echo $item['type'] === 'image' ? '🖼️ IMG' : '🎥 VID'; ?>
                                </span>

                                <?php if ($item['type'] === 'image'): ?>
                                    <img src="media_server.php?file=<?php echo urlencode($item['relPath']); ?>" loading="lazy" alt="Preview">
                                <?php else: ?>
                                    <canvas id="thumb-canvas-<?php echo $index; ?>"></canvas>
                                    <video id="thumb-video-<?php echo $index; ?>"
                                        src="media_server.php?file=<?php echo urlencode($item['relPath']); ?>#t=2.0"
                                        preload="metadata"
                                        style="display:none;"
                                        onloadeddata="generateVideoThumbnail(<?php echo $index; ?>)">
                                    </video>
                                <?php endif; ?>
                            </div>

                            <div class="media-info">
                                <p class="media-title" title="<?php echo htmlspecialchars($item['name']); ?>">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </p>
                                <p class="media-folder" title="Folder: <?php echo htmlspecialchars($item['folder']); ?>">
                                    📂 <?php echo htmlspecialchars($item['folder']); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>

    <!-- Modal Lightbox / Player -->
    <div id="mediaModal" class="modal">
        <span class="close-btn" onclick="closePlayer()">&times;</span>
        <div class="modal-content-wrapper" id="modalContainer"></div>
    </div>

    <script>
        let currentVisibleCards = [];
        let currentPlayingIndex = -1;
        let isLoopSingle = false;

        // Top bar mobile menu toggle
        function toggleTopMenu() {
            const nav = document.getElementById('topNav');
            const controls = document.getElementById('topControls');
            nav.classList.toggle('open');
            controls.classList.toggle('open');
        }

        // Search & Sort Filter
        function filterAndSortMedia() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const sortOption = document.getElementById('sortSelect').value;
            const grid = document.getElementById('galleryGrid');
            const cards = Array.from(grid.querySelectorAll('.media-card'));

            cards.forEach(card => {
                const title = card.getAttribute('data-title');
                const folder = card.getAttribute('data-folder');
                if (title.includes(query) || folder.includes(query)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });

            cards.sort((a, b) => {
                const nameA = a.getAttribute('data-title');
                const nameB = b.getAttribute('data-title');
                const timeA = parseInt(a.getAttribute('data-mtime'));
                const timeB = parseInt(b.getAttribute('data-mtime'));

                if (sortOption === 'date-desc') return timeB - timeA;
                if (sortOption === 'date-asc') return timeA - timeB;
                if (sortOption === 'name-asc') return nameA.localeCompare(nameB);
                if (sortOption === 'name-desc') return nameB.localeCompare(nameA);
            });

            cards.forEach(card => grid.appendChild(card));
            updateVisibleCards();
        }

        function updateVisibleCards() {
            const grid = document.getElementById('galleryGrid');
            currentVisibleCards = Array.from(grid.querySelectorAll('.media-card')).filter(card => card.style.display !== 'none');
        }

        document.addEventListener("DOMContentLoaded", function() {
            filterAndSortMedia();
        });

        // Player Logic
        function openPlayerByIndex(originalIndex) {
            updateVisibleCards();
            const cardIndex = currentVisibleCards.findIndex(card => card.getAttribute('data-index') == originalIndex);
            if (cardIndex !== -1) {
                playMediaAtVisibleIndex(cardIndex);
            }
        }

        function playMediaAtVisibleIndex(index) {
            if (index < 0 || index >= currentVisibleCards.length) return;
            currentPlayingIndex = index;

            const card = currentVisibleCards[index];
            const relPath = card.getAttribute('data-relpath');
            const type = card.getAttribute('data-type');

            const modal = document.getElementById('mediaModal');
            const container = document.getElementById('modalContainer');
            const mediaUrl = 'media_server.php?file=' + relPath;

            if (type === 'image') {
                container.innerHTML = `
                    <img src="${mediaUrl}" class="modal-content">
                    <div class="player-controls">
                        <button class="player-btn" onclick="playPrev()">⏮ Prev</button>
                        <button class="player-btn" onclick="playNext()">Next ⏭</button>
                    </div>`;
            } else if (type === 'video') {
                container.innerHTML = `
                    <video id="activeVideo" controls autoplay class="modal-content">
                        <source src="${mediaUrl}">
                        Browser does not support video playback.
                    </video>
                    <div class="player-controls">
                        <button class="player-btn" onclick="playPrev()">⏮ Prev</button>
                        <button id="loopBtn" class="player-btn ${isLoopSingle ? 'active' : ''}" onclick="toggleLoop()">🔁 Loop Single</button>
                        <button class="player-btn" onclick="playNext()">Next ⏭</button>
                    </div>`;

                const videoElement = document.getElementById('activeVideo');
                videoElement.onended = function() {
                    if (isLoopSingle) {
                        videoElement.currentTime = 0;
                        videoElement.play();
                    } else {
                        playNext();
                    }
                };
            }

            modal.style.display = 'flex';
        }

        function toggleLoop() {
            isLoopSingle = !isLoopSingle;
            const btn = document.getElementById('loopBtn');
            if (btn) {
                btn.classList.toggle('active', isLoopSingle);
            }
        }

        function playNext() {
            if (currentVisibleCards.length === 0) return;
            let nextIndex = (currentPlayingIndex + 1) % currentVisibleCards.length;
            playMediaAtVisibleIndex(nextIndex);
        }

        function playPrev() {
            if (currentVisibleCards.length === 0) return;
            let prevIndex = (currentPlayingIndex - 1 + currentVisibleCards.length) % currentVisibleCards.length;
            playMediaAtVisibleIndex(prevIndex);
        }

        function closePlayer() {
            const modal = document.getElementById('mediaModal');
            const container = document.getElementById('modalContainer');
            modal.style.display = 'none';
            container.innerHTML = '';
        }

        function generateVideoThumbnail(index) {
            const video = document.getElementById('thumb-video-' + index);
            const canvas = document.getElementById('thumb-canvas-' + index);
            if (!video || !canvas) return;

            const ctx = canvas.getContext('2d');
            canvas.width = video.videoWidth || 320;
            canvas.height = video.videoHeight || 180;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closePlayer();
            if (e.key === 'ArrowRight') playNext();
            if (e.key === 'ArrowLeft') playPrev();
        });
    </script>
</body>

</html>