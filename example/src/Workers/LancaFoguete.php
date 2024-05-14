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

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;



$host = 'rabbitmq-master';
$port = 5672;
$user = 'guest';
$pass = 'guest';
$vhost = '/';

$connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
$channel = $connection->channel();

// Nome da fila
$queueName = 'lancar_foguete';

// Declaração da fila
$channel->queue_declare($queueName, false, true, false, false);

echo "Fila '$queueName' criada com sucesso.\n";




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


    $channel->basic_qos(
        prefetch_size: null,
        prefetch_count: 1, // Quantidade de mensagens que o consumidor pode receber por vez até que ele envie um ack
        a_global: null
    );

    // Consumindo mensagens da fila
    $channel->basic_consume($queueName, '', false, false, false, false, $callback);

    while ($channel->is_consuming()) {
        $channel->wait();
    }

    $channel->close();
    $connection->close();

} catch (Exception $e) {

    echo "{$e->getMessage()}\n";
}