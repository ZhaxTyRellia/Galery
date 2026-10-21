<?php
set_time_limit(0); // Allow long streaming for large files
require_once __DIR__ . '/config.php';
$sessionLifetime = 43200;
ini_set('session.gc_maxlifetime', $sessionLifetime);
session_set_cookie_params($sessionLifetime);
session_start();

// Check if user is logged in and session not expired (12 hours)
if (
    !isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true ||
    !isset($_SESSION['logged_in_time']) || (time() - $_SESSION['logged_in_time'] > $sessionLifetime)
) {
    http_response_code(403);
    echo "Access denied. Please log in first.";
    exit;
}

$baseDir = $mediaDirectory;

$file = isset($_GET['file']) ? $_GET['file'] : '';

$file = str_replace(array('../', '..\\'), '', $file);
$fullPath = rtrim($baseDir, '\\/') . DIRECTORY_SEPARATOR . ltrim($file, '\\/');

if (empty($file) || !file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    echo "File not found.";
    exit;
}

$size = filesize($fullPath);

/**
 * Resolve the MIME type for the requested file.
 *
 * Uses the fileinfo extension when available and falls back to an
 * extension map so streaming never breaks on hosts without fileinfo.
 */
function resolveMimeType($filePath)
{
    if (function_exists('mime_content_type')) {
        $detected = @mime_content_type($filePath);
        if ($detected !== false && $detected !== '') {
            return $detected;
        }
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $map = array(
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'bmp'  => 'image/bmp',
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'ogv'  => 'video/ogg',
        'ogg'  => 'video/ogg',
        'mkv'  => 'video/x-matroska',
        'avi'  => 'video/x-msvideo',
        'mov'  => 'video/quicktime',
    );

    return isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
}

$mimeType = resolveMimeType($fullPath);

$fp = @fopen($fullPath, 'rb');
if (!$fp) {
    http_response_code(500);
    echo "Failed to read file.";
    exit;
}

if (ob_get_level()) {
    ob_end_clean();
}

if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    $range = str_replace('bytes=', '', $range);
    $range = explode('-', $range);

    $start = intval($range[0]);
    $end = (isset($range[1]) && is_numeric($range[1])) ? intval($range[1]) : ($size - 1);

    if ($start > $end || $start >= $size) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header("Content-Range: bytes */$size");
        exit;
    }

    $length = $end - $start + 1;

    header('HTTP/1.1 206 Partial Content');
    header("Content-Type: $mimeType");
    header("Content-Length: $length");
    header("Content-Range: bytes $start-$end/$size");
    header('Accept-Ranges: bytes');

    fseek($fp, $start);

    $bufferSize = 1048576;
    $bytesToRead = $length;

    while ($bytesToRead > 0 && !feof($fp)) {
        $readSize = ($bytesToRead > $bufferSize) ? $bufferSize : $bytesToRead;
        $data = fread($fp, $readSize);
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
        echo fread($fp, $bufferSize);
        flush();
    }
}

fclose($fp);
exit;
