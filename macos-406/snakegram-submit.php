<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(array $payload, int $status = 200): never { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function load_posts(string $file): array { $value = is_file($file) ? json_decode((string)file_get_contents($file), true) : []; return is_array($value) ? $value : []; }
function save_posts(string $file, array $posts): bool {
  $handle = fopen($file, 'c+'); if (!$handle || !flock($handle, LOCK_EX)) return false;
  $encoded = json_encode($posts, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
  rewind($handle); ftruncate($handle, 0); $ok = $encoded !== false && fwrite($handle, $encoded) !== false;
  fflush($handle); flock($handle, LOCK_UN); fclose($handle); return $ok;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['ok'=>false,'message'=>'Método não permitido.'],405);
$handle = trim((string)($_POST['handle'] ?? ''));
$handle = ltrim($handle, '@');
if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $handle)) respond(['ok'=>false,'message'=>'Use um @ com 3 a 30 caracteres: letras, números, ponto ou _.'],422);
if (!isset($_FILES['media']) || !is_array($_FILES['media']) || ($_FILES['media']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) respond(['ok'=>false,'message'=>'Selecione uma foto ou vídeo válido.'],422);
$file = $_FILES['media'];
if ((int)$file['size'] > 30 * 1024 * 1024) respond(['ok'=>false,'message'=>'O arquivo precisa ter no máximo 30 MB.'],422);
$ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg'=>'image','jpeg'=>'image','png'=>'image','webp'=>'image','gif'=>'image','mp4'=>'video','webm'=>'video','mov'=>'video'];
if (!isset($allowed[$ext])) respond(['ok'=>false,'message'=>'Formato não aceito. Use JPG, PNG, WEBP, GIF, MP4, WEBM ou MOV.'],422);
$mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']) ?: '';
if (!str_starts_with($mime, $allowed[$ext] === 'image' ? 'image/' : 'video/')) respond(['ok'=>false,'message'=>'O conteúdo do arquivo não confere com o formato informado.'],422);
$dataDir = __DIR__.'/data'; $pendingDir = __DIR__.'/uploads/pending';
foreach ([$dataDir,$pendingDir] as $dir) if (!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) respond(['ok'=>false,'message'=>'Não foi possível preparar o envio no servidor.'],500);
if (!is_writable($dataDir) || !is_writable($pendingDir)) respond(['ok'=>false,'message'=>'A pasta de uploads não tem permissão de escrita na VPS.'],500);
$id = 'sg-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
$name = $id.'.'.$ext;
if (!move_uploaded_file((string)$file['tmp_name'],$pendingDir.'/'.$name)) respond(['ok'=>false,'message'=>'Não foi possível salvar o arquivo.'],500);
$dataFile = $dataDir.'/snakegram-posts.json'; $posts = load_posts($dataFile);
$posts[] = ['id'=>$id,'file'=>$name,'author'=>'@'.$handle,'type'=>$allowed[$ext],'status'=>'pending','created_at'=>gmdate('c')];
if (!save_posts($dataFile,$posts)) { @unlink($pendingDir.'/'.$name); respond(['ok'=>false,'message'=>'Não foi possível registrar o post.'],500); }
respond(['ok'=>true,'message'=>'Post enviado para análise.']);
