<?php
/**
 * Shared configuration for the gallery.
 *
 * Single source of truth for the media root so the scanner (index.php)
 * and the file server (media_server.php) can never point at different
 * directories again.
 */

// Root folder that holds all media served by the gallery.
$mediaDirectory = 'E:\0 - B9\Assets';

// Password access: leave empty '' to disable login, or enter a password to protect gallery.
$galleryPassword = '';

// Extensions treated as images / videos.
$imageExtensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg');
$videoExtensions = array('mp4', 'webm', 'ogg', 'mkv', 'avi', 'mov');
