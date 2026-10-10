<?php
// Recebe o webhook "Compra aprovada" (purchase_approved) da Cakto e guarda só
// o horário da compra, pra notificação da página. Nenhum dado do cliente é salvo.
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function responder(int $status, array $corpo): void
{
    http_response_code($status);
    echo json_encode($corpo);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responder(405, ['erro' => 'use POST']);
}

$arquivoConfig = __DIR__ . '/config.php';
if (!is_file($arquivoConfig)) {
    responder(500, ['erro' => 'crie o api/config.php a partir do config.exemplo.php']);
}
$config = require $arquivoConfig;
$secret = (string)($config['secret'] ?? '');
if (strlen($secret) < 8 || strpos($secret, 'COLE_AQUI') === 0) {
    responder(500, ['erro' => 'secret não configurado no api/config.php']);
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    responder(400, ['erro' => 'json inválido']);
}
if (!hash_equals($secret, (string)($payload['secret'] ?? ''))) {
    responder(401, ['erro' => 'secret inválido']);
}

$dados = is_array($payload['data'] ?? null) ? $payload['data'] : [];
if (($payload['event'] ?? '') !== 'purchase_approved' || ($dados['status'] ?? '') !== 'paid') {
    responder(200, ['ok' => true, 'ignorado' => 'não é compra aprovada']);
}

$produtosAceitos = (array)($config['produtos'] ?? []);
if ($produtosAceitos) {
    $produto = is_array($dados['product'] ?? null) ? $dados['product'] : [];
    $idsProduto = [(string)($produto['id'] ?? ''), (string)($produto['short_id'] ?? '')];
    if (!array_intersect($idsProduto, $produtosAceitos)) {
        responder(200, ['ok' => true, 'ignorado' => 'outro produto']);
    }
}

$idPedido = (string)($dados['id'] ?? '');
if ($idPedido === '') {
    responder(400, ['erro' => 'pedido sem id']);
}
$id = hash('sha256', $idPedido);

$agora = time();
$quando = $agora;
try {
    $quando = (new DateTimeImmutable((string)($dados['paidAt'] ?? 'now')))->getTimestamp();
} catch (Exception $e) {
}
if ($quando > $agora) {
    $quando = $agora;
}

$pasta = __DIR__ . '/dados';
if (!is_dir($pasta) && !mkdir($pasta, 0755, true) && !is_dir($pasta)) {
    responder(500, ['erro' => 'não consegui criar api/dados']);
}

$fp = fopen($pasta . '/compras.json', 'c+');
if ($fp === false || !flock($fp, LOCK_EX)) {
    responder(500, ['erro' => 'não consegui abrir api/dados/compras.json']);
}

$compras = json_decode((string)stream_get_contents($fp), true);
$compras = is_array($compras) ? $compras : [];

$jaExiste = false;
foreach ($compras as $c) {
    if (($c['id'] ?? '') === $id) {
        $jaExiste = true;
        break;
    }
}

if (!$jaExiste) {
    $compras[] = ['id' => $id, 't' => $quando];
    // Guarda só os últimos 7 dias (no máximo 200 compras)
    $limite = $agora - 7 * 86400;
    $compras = array_values(array_filter($compras, function ($c) use ($limite) {
        return (int)($c['t'] ?? 0) >= $limite;
    }));
    usort($compras, function ($a, $b) {
        return $b['t'] <=> $a['t'];
    });
    $compras = array_slice($compras, 0, 200);

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, (string)json_encode($compras));
    fflush($fp);
}

flock($fp, LOCK_UN);
fclose($fp);

responder(200, ['ok' => true, 'duplicada' => $jaExiste]);
