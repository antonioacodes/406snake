<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$directory = __DIR__ . '/photos';
if (!is_dir($directory)) { echo '[]'; exit; }
$allowed = ['jpg','jpeg','png','webp','gif'];
$files = [];
foreach (scandir($directory) ?: [] as $file) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($file[0] !== '.' && in_array($extension, $allowed, true)) $files[] = 'photos/' . rawurlencode($file) . '?v=' . filemtime($directory . '/' . $file);
}
echo json_encode($files, JSON_UNESCAPED_SLASHES);
