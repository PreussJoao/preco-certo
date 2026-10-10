<?php
// Devolve os horários das compras reais das últimas 24h (no máximo 19) pra
// notificação da página. Só horários, sem nenhum dado de cliente.
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

$agora = time();
$desde = $agora - 24 * 3600;

$arquivo = __DIR__ . '/dados/compras.json';
$compras = is_file($arquivo) ? json_decode((string)file_get_contents($arquivo), true) : [];

$horarios = [];
foreach ((array)$compras as $c) {
    $t = (int)($c['t'] ?? 0);
    if ($t >= $desde && $t <= $agora) {
        $horarios[] = $t;
    }
}
rsort($horarios);

echo json_encode(['agora' => $agora, 'compras' => array_slice($horarios, 0, 19)]);
