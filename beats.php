<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$tracks = [];
$directory = __DIR__ . '/media/beats';
if (is_dir($directory)) {
    foreach (scandir($directory) ?: [] as $file) {
        if ($file === '' || $file[0] === '.' || !is_file($directory . '/' . $file)) continue;
        if (strtolower($file) === 'transition.wav') continue;
        if (!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['mp3', 'wav', 'm4a', 'ogg'], true)) continue;
        $tracks[] = [
            'src' => 'media/beats/' . rawurlencode($file) . '?v=' . filemtime($directory . '/' . $file),
            'title' => str_replace(['_', '-'], ' ', pathinfo($file, PATHINFO_FILENAME)),
        ];
    }
}
if (!$tracks) $tracks[] = ['src' => 'media/room-beat-127.wav', 'title' => '406 — Beat 127'];
echo json_encode($tracks, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
