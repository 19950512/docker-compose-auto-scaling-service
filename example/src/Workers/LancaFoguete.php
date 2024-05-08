#!/usr/bin/php
<?php

declare(strict_types=1);

namespace App\Workers;

$pathVendor = __DIR__ . '/../../vendor/autoload.php';
if(!is_file($pathVendor)) {
    echo "O arquivo {$pathVendor} não foi encontrado. Execute o comando composer install para instalar as dependências.";
    exit;
}

require_once $pathVendor;

use App\Queues\RabbitMQ;
use Exception;

$rabbitMQ = new RabbitMQ();

$callback = function($message)
{

    echo date('m/d/Y h:i:s a', time()) . " [x] Novo Foguete para lançar: ", $message->body, "\n";
    echo "------------------------------------------------------------\n";

    try {

        sleep(2); // Simulando lançamento do foguete (2 segundos)
        echo date('m/d/Y h:i:s a', time()) . " [x] Foguete lançado com sucesso!\n";
        $message->getChannel()->basic_ack($message->getDeliveryTag());

    }catch (Exception $e) {
        echo "Erro ao lançar o foguete: {$e->getMessage()}\n";
        $message->getChannel()->basic_nack($message->getDeliveryTag(), false, false);
    }
};

try {

    echo date('m/d/Y H:i:s a', time()) . " [x] Aguardando novas Foguetes para lançar!\n";

    $rabbitMQ->subscribe(
        queue: 'lancar_foguete',
        callback: $callback
    );

} catch (Exception $e) {

    echo "{$e->getMessage()}\n";
}