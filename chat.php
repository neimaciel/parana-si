<?php
/**
 * parana.si - Recebe a resposta do chat "Esse domínio te interessa?".
 * Guarda no mesmo banco do painel. Sem cookies; o IP vira só um hash para limitar abuso.
 */
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(int $codigo, array $corpo): void {
    http_response_code($codigo);
    echo json_encode($corpo);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') responder(405, ['ok' => false]);

if (is_file(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';
if (!defined('ANALYTICS_SALT') || ANALYTICS_SALT === '') responder(503, ['ok' => false]);

$raw = file_get_contents('php://input', false, null, 0, 8192);
$in = json_decode($raw ?: '', true);
if (!is_array($in)) responder(400, ['ok' => false]);

// Isca para robôs: campo escondido que gente não preenche.
if (!empty($in['site'])) responder(200, ['ok' => true]);

$interesse = in_array($in['interesse'] ?? '', ['sim', 'olhando'], true) ? $in['interesse'] : '';
$contato = trim((string)($in['contato'] ?? ''));
$mensagem = trim((string)($in['mensagem'] ?? ''));
$contato = mb_substr(preg_replace('/[\r\n\t]+/', ' ', $contato), 0, 120);
$mensagem = mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $mensagem), 0, 800);
if ($interesse === '' && $contato === '' && $mensagem === '') responder(400, ['ok' => false]);

$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ipHash = hash('sha256', $ip . ANALYTICS_SALT);

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);

try {
    $pdo = new PDO('sqlite:' . $dataDir . '/analytics.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        created_at TEXT NOT NULL,
        ip_hash TEXT NOT NULL,
        interesse TEXT NOT NULL,
        contato TEXT NOT NULL,
        mensagem TEXT NOT NULL
    )");
    $limite = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE ip_hash = ? AND created_at > ?");
    $limite->execute([$ipHash, date('Y-m-d H:i:s', time() - 3600)]);
    if ((int)$limite->fetchColumn() >= 5) responder(429, ['ok' => false]);

    $ins = $pdo->prepare("INSERT INTO chat_messages (created_at, ip_hash, interesse, contato, mensagem) VALUES (?, ?, ?, ?, ?)");
    $ins->execute([date('Y-m-d H:i:s'), $ipHash, $interesse, $contato, $mensagem]);
} catch (Throwable $e) {
    responder(500, ['ok' => false]);
}

// Aviso por e-mail, sem derrubar a resposta se o servidor não enviar.
if ($interesse === 'sim' || $contato !== '' || $mensagem !== '') {
    $assunto = 'parana.si: resposta no chat (' . ($interesse ?: 'sem escolha') . ')';
    $corpo = "Interesse: $interesse\nContato: $contato\n\n$mensagem\n";
    @mail('neimaciel@random.marketing', '=?UTF-8?B?' . base64_encode($assunto) . '?=', $corpo,
        "Content-Type: text/plain; charset=UTF-8\r\n");
}

responder(200, ['ok' => true]);
