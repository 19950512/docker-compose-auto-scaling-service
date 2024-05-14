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
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPStreamConnection;



$host = 'localhost';
$port = 5672;
$user = 'guest';
$pass = 'guest';
$vhost = '/';

$connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
$channel = $connection->channel();

// Nome da fila
$queueName = 'lancar_foguete';


$quantidadeMensagens = $argv[1] ?? 1;
for($i = 1; $i <= $quantidadeMensagens; $i++) {
    $channel->basic_publish(new AMQPMessage(
        "Foguete {$i}",
        [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]
    ), '', $queueName);
}

