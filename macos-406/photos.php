<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$directory = __DIR__ . '/photos';
$allowedImages = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$allowedVideos = ['mp4', 'webm', 'mov'];
$stored = [];
$dataFile = __DIR__ . '/data/photos-reactions.json';
if (is_file($dataFile)) {
    $decoded = json_decode((string)file_get_contents($dataFile), true);
    $stored = is_array($decoded) ? $decoded : [];
}
$posts = [];
$authors = [];
$moderationFile = __DIR__ . '/data/snakegram-posts.json';
if (is_file($moderationFile)) {
    $moderation = json_decode((string)file_get_contents($moderationFile), true);
    if (is_array($moderation)) {
        foreach ($moderation as $post) {
            if (is_array($post) && ($post['status'] ?? '') === 'approved' && isset($post['file'])) {
                $authors[(string)$post['file']] = (string)($post['author'] ?? '@snakegram');
            }
        }
    }
}
if (is_dir($directory)) {
    foreach (scandir($directory) ?: [] as $file) {
        if ($file === '' || $file[0] === '.' || !is_file($directory . '/' . $file)) continue;
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $type = in_array($extension, $allowedImages, true) ? 'image' : (in_array($extension, $allowedVideos, true) ? 'video' : '');
        if ($type === '') continue;
        $counts = is_array($stored[$file] ?? null) ? $stored[$file] : [];
        $posts[] = [
            'id' => $file,
            'src' => 'photos/' . rawurlencode($file) . '?v=' . filemtime($directory . '/' . $file),
            'type' => $type,
            'author' => $authors[$file] ?? null,
            'reactions' => [
                'heart' => max(0, (int)($counts['heart'] ?? 0)),
                'laugh' => max(0, (int)($counts['laugh'] ?? 0)),
                'four' => max(0, (int)($counts['four'] ?? 0)),
            ],
        ];
    }
}
echo json_encode($posts, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
