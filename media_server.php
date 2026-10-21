<?php
/**
 * Media Server - Secure media streaming & caching handler.
 */
set_time_limit(0);
require_once __DIR__ . '/config.php';

// Authentication check if password is set in config.php
if (!empty($galleryPassword)) {
    $sessionLifetime = 43200; // 12 hours
    ini_set('session.gc_maxlifetime', $sessionLifetime);
    session_set_cookie_params($sessionLifetime);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (
        !isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
        !isset($_SESSION['logged_in_time']) || (time() - $_SESSION['logged_in_time'] > $sessionLifetime)
    ) {
        http_response_code(403);
        echo "Access denied. Please log in first.";
        exit;
    }
}

// Ensure base media directory exists
$baseReal = realpath($mediaDirectory);
if (!$baseReal || !is_dir($baseReal)) {
    http_response_code(500);
    echo "Media directory is not configured or not accessible.";
    exit;
}

$file = isset($_GET['file']) ? $_GET['file'] : '';
if ($file === '') {
    http_response_code(400);
    echo "No file specified.";
    exit;
}

// Normalize path separators and prevent traversal
$file = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $file);
$candidate = $baseReal . DIRECTORY_SEPARATOR . ltrim($file, DIRECTORY_SEPARATOR);
$targetReal = realpath($candidate);

// Strict check: must exist, be a regular file, and reside inside base directory
if (!$targetReal || !is_file($targetReal) || strpos($targetReal, $baseReal) !== 0) {
    http_response_code(404);
    echo "File not found.";
    exit;
}

// Extension whitelist check
$ext = strtolower(pathinfo($targetReal, PATHINFO_EXTENSION));
$allowedExts = array_merge($imageExtensions, $videoExtensions);
if (!in_array($ext, $allowedExts, true)) {
    http_response_code(403);
    echo "File type not permitted.";
    exit;
}

$size  = filesize($targetReal);
$mtime = filemtime($targetReal);
$etag  = '"' . md5($targetReal . '-' . $size . '-' . $mtime) . '"';

// Browser Caching: 304 Not Modified
header("Cache-Control: public, max-age=86400, stale-while-revalidate=604800");
header("ETag: $etag");
header("Last-Modified: " . gmdate("D, d M Y H:i:s", $mtime) . " GMT");

if (
    (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) ||
    (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && @strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime)
) {
    http_response_code(304);
    exit;
}

/**
 * Determine MIME type safely
 */
function resolveMimeType($filePath, $ext)
{
    $map = array(
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'bmp'  => 'image/bmp',
        'svg'  => 'image/svg+xml',
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'ogv'  => 'video/ogg',
        'ogg'  => 'video/ogg',
        'mkv'  => 'video/x-matroska',
        'avi'  => 'video/x-msvideo',
        'mov'  => 'video/quicktime'
    );

    if (isset($map[$ext])) {
        return $map[$ext];
    }

    if (function_exists('mime_content_type')) {
        $detected = @mime_content_type($filePath);
        if ($detected !== false && $detected !== '') {
            return $detected;
        }
    }

    return 'application/octet-stream';
}

$mimeType = resolveMimeType($targetReal, $ext);

$fp = @fopen($targetReal, 'rb');
if (!$fp) {
    http_response_code(500);
    echo "Failed to open file.";
    exit;
}

if (ob_get_level()) {
    ob_end_clean();
}

// Support HTTP Range requests (crucial for video scrubbing & streaming)
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    $range = str_replace('bytes=', '', $range);
    $rangeParts = explode('-', $range);

    $start = intval($rangeParts[0]);
    $end   = (isset($rangeParts[1]) && is_numeric($rangeParts[1])) ? intval($rangeParts[1]) : ($size - 1);

    if ($start > $end || $start >= $size) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header("Content-Range: bytes */$size");
        fclose($fp);
        exit;
    }

    $length = $end - $start + 1;

    header('HTTP/1.1 206 Partial Content');
    header("Content-Type: $mimeType");
    header("Content-Length: $length");
    header("Content-Range: bytes $start-$end/$size");
    header('Accept-Ranges: bytes');

    fseek($fp, $start);

    $bufferSize = 1048576; // 1MB buffer
    $bytesToRead = $length;

    while ($bytesToRead > 0 && !feof($fp)) {
        $readSize = ($bytesToRead > $bufferSize) ? $bufferSize : $bytesToRead;
        $data = fread($fp, $readSize);
        if ($data === false) break;
        echo $data;
        flush();
        $bytesToRead -= strlen($data);
    }
} else {
    header("Content-Type: $mimeType");
    header("Content-Length: $size");
    header('Accept-Ranges: bytes');

    $bufferSize = 1048576;
    while (!feof($fp)) {
        $data = fread($fp, $bufferSize);
        if ($data === false) break;
        echo $data;
        flush();
    }
}

fclose($fp);
exit;
