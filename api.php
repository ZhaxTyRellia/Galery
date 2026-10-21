<?php
/**
 * Kawaii Gallery - File Management API (Rename & Delete)
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

// Authentication check if password is set in config.php
if (!empty($galleryPassword)) {
    $sessionLifetime = 43200;
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
        echo json_encode(array('success' => false, 'error' => 'Authentication required. Please log in first.'));
        exit;
    }
}

$baseReal = realpath($mediaDirectory);
if (!$baseReal || !is_dir($baseReal)) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'error' => 'Media directory not accessible.'));
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if ($action === 'rename') {
    $relPath = isset($_POST['path']) ? trim($_POST['path']) : '';
    $newName = isset($_POST['newName']) ? trim($_POST['newName']) : '';

    if ($relPath === '' || $newName === '') {
        echo json_encode(array('success' => false, 'error' => 'Path and new name cannot be empty.'));
        exit;
    }

    // Normalize slashes
    $normalizedRel = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $relPath);
    $oldFullPath = realpath($baseReal . DIRECTORY_SEPARATOR . ltrim($normalizedRel, DIRECTORY_SEPARATOR));

    // Security check: file must exist, be a regular file, and within base directory
    if (!$oldFullPath || !is_file($oldFullPath) || strpos($oldFullPath, $baseReal) !== 0) {
        echo json_encode(array('success' => false, 'error' => 'Original file not found or invalid path.'));
        exit;
    }

    // Clean up newName: only take basename to prevent directory traversal
    $newName = basename($newName);
    $oldExt = strtolower(pathinfo($oldFullPath, PATHINFO_EXTENSION));
    $newExt = strtolower(pathinfo($newName, PATHINFO_EXTENSION));

    // If newName doesn't have an extension, preserve original extension
    if ($newExt === '' || !in_array($newExt, array_merge($imageExtensions, $videoExtensions), true)) {
        $cleanBase = pathinfo($newName, PATHINFO_FILENAME);
        $newName = ($cleanBase !== '' ? $cleanBase : 'file') . '.' . $oldExt;
    }

    // Disallow illegal characters in Windows filenames: \ / : * ? " < > |
    $cleanedName = preg_replace('[/\\\\:*?"<>|]', '', $newName);
    if ($cleanedName === '' || $cleanedName === '.' || $cleanedName === '..') {
        echo json_encode(array('success' => false, 'error' => 'Invalid file name.'));
        exit;
    }

    $parentDir = dirname($oldFullPath);
    $newFullPath = $parentDir . DIRECTORY_SEPARATOR . $cleanedName;

    if (file_exists($newFullPath) && $newFullPath !== $oldFullPath) {
        echo json_encode(array('success' => false, 'error' => 'A file with that name already exists in this folder!'));
        exit;
    }

    if (@rename($oldFullPath, $newFullPath)) {
        $newRelPath = ltrim(substr($newFullPath, strlen($baseReal)), DIRECTORY_SEPARATOR);
        echo json_encode(array(
            'success'    => true,
            'message'    => 'File renamed successfully! ✨',
            'newName'    => $cleanedName,
            'newRelPath' => str_replace('\\', '/', $newRelPath)
        ));
    } else {
        echo json_encode(array('success' => false, 'error' => 'Failed to rename file. Please check folder permissions.'));
    }
    exit;
}

if ($action === 'delete') {
    $relPath = isset($_POST['path']) ? trim($_POST['path']) : '';
    if ($relPath === '') {
        echo json_encode(array('success' => false, 'error' => 'Path cannot be empty.'));
        exit;
    }

    $normalizedRel = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $relPath);
    $targetFullPath = realpath($baseReal . DIRECTORY_SEPARATOR . ltrim($normalizedRel, DIRECTORY_SEPARATOR));

    if (!$targetFullPath || !is_file($targetFullPath) || strpos($targetFullPath, $baseReal) !== 0) {
        echo json_encode(array('success' => false, 'error' => 'File not found or invalid path.'));
        exit;
    }

    // Check extension whitelist
    $ext = strtolower(pathinfo($targetFullPath, PATHINFO_EXTENSION));
    if (!in_array($ext, array_merge($imageExtensions, $videoExtensions), true)) {
        echo json_encode(array('success' => false, 'error' => 'Cannot delete non-media files.'));
        exit;
    }

    if (@unlink($targetFullPath)) {
        echo json_encode(array(
            'success' => true,
            'message' => 'File deleted successfully! 🗑️'
        ));
    } else {
        echo json_encode(array('success' => false, 'error' => 'Failed to delete file. Please check folder permissions.'));
    }
    exit;
}

http_response_code(400);
echo json_encode(array('success' => false, 'error' => 'Unknown action.'));
exit;
