<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function response(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') response(['ok' => false, 'message' => 'Método não permitido.'], 405);
$input = json_decode((string)file_get_contents('php://input'), true);
$photo = is_array($input) ? (string)($input['photo'] ?? '') : '';
$reaction = is_array($input) ? (string)($input['reaction'] ?? '') : '';
if (!in_array($reaction, ['heart', 'laugh', 'four'], true)) response(['ok' => false, 'message' => 'Reação inválida.'], 422);

$photosDirectory = __DIR__ . '/photos';
$path = $photosDirectory . '/' . $photo;
$extension = strtolower(pathinfo($photo, PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'mov'];
if ($photo === '' || basename($photo) !== $photo || !in_array($extension, $allowed, true) || !is_file($path)) {
    response(['ok' => false, 'message' => 'Post não encontrado.'], 404);
}

$dataDirectory = __DIR__ . '/data';
if (!is_dir($dataDirectory) && !mkdir($dataDirectory, 0755, true) && !is_dir($dataDirectory)) {
    response(['ok' => false, 'message' => 'Não foi possível salvar a reação.'], 500);
}
$file = $dataDirectory . '/photos-reactions.json';
$handle = fopen($file, 'c+');
if ($handle === false || !flock($handle, LOCK_EX)) response(['ok' => false, 'message' => 'Não foi possível salvar a reação.'], 500);
$raw = stream_get_contents($handle);
$data = json_decode($raw ?: '{}', true);
$data = is_array($data) ? $data : [];
$data[$photo] = is_array($data[$photo] ?? null) ? $data[$photo] : [];
foreach (['heart', 'laugh', 'four'] as $key) $data[$photo][$key] = max(0, (int)($data[$photo][$key] ?? 0));
$data[$photo]['users'] = is_array($data[$photo]['users'] ?? null) ? $data[$photo]['users'] : [];

$user = hash('sha256', session_id());
$previous = (string)($data[$photo]['users'][$user] ?? '');
if ($previous !== '' && in_array($previous, ['heart', 'laugh', 'four'], true) && $previous !== $reaction) {
    $data[$photo][$previous] = max(0, $data[$photo][$previous] - 1);
}
if ($previous !== $reaction) {
    $data[$photo][$reaction]++;
    $data[$photo]['users'][$user] = $reaction;
}
rewind($handle);
ftruncate($handle, 0);
fwrite($handle, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
fflush($handle);
flock($handle, LOCK_UN);
fclose($handle);
response(['ok' => true, 'reactions' => [
    'heart' => $data[$photo]['heart'],
    'laugh' => $data[$photo]['laugh'],
    'four' => $data[$photo]['four'],
]]);
