<?php
// Modelo de configuração do webhook da Cakto.
// No servidor (Hostinger), copie este arquivo para api/config.php e cole o secret.
// O config.php fica fora do Git, pra o secret não aparecer no repositório público.
return [
    // Mesmo valor do campo "secret" cadastrado no webhook da Cakto
    'secret' => 'COLE_AQUI_O_SECRET_DO_WEBHOOK',

    // Opcional: só conta compras destes produtos (id ou short_id do produto na Cakto).
    // Deixe vazio pra aceitar todos os produtos ligados ao webhook.
    'produtos' => [],
];
