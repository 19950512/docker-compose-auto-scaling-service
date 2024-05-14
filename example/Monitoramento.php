#!/usr/bin/php
<?php

declare(strict_types=1);

use PhpAmqpLib\Connection\AMQPStreamConnection;

// Verifica se o arquivo de autoload existe
$pathVendor = __DIR__ . '/vendor/autoload.php';
if (!is_file($pathVendor)) {
    echo "O arquivo {$pathVendor} não foi encontrado. Execute o comando composer install para instalar as dependências.";
    exit;
}

require_once $pathVendor;

// Configurações do RabbitMQ
$host = 'localhost';
$port = 5672;
$user = 'guest';
$pass = 'guest';
$vhost = '/';
$queueName = 'lancar_foguete';

// Intervalo entre as verificações (em segundos)
$intervalo = 10;

// Função para obter o tamanho da fila
function getQueueSize($channel, $queueName)
{
    try {
        // Obtem o número de mensagens na fila
        $result = $channel->queue_declare($queueName, true);
        return $result[1];
    } catch (Exception $e) {
        echo "Erro ao obter o tamanho da fila: {$e->getMessage()}\n";
        return -1;
    }
}

// Função para calcular o número de réplicas com base na quantidade de mensagens na fila
function calculateReplicas($queueSize)
{
    // Define os limites para o número de réplicas
    $minReplicas = 1;
    $maxReplicas = 50;

    // Calcula o número de réplicas com base na quantidade de mensagens
    if ($queueSize <= 10) {
        $replicas = $minReplicas;
    } else {
        $replicas = ceil($queueSize / 10); // Aumenta 1 réplica para cada 10 mensagens adicionais na fila
    }

    // Limita o número de réplicas ao máximo permitido
    return min($replicas, $maxReplicas);
}

// Função para atualizar o número de réplicas do serviço
function updateServiceReplicas($replicas)
{
    // Comando para atualizar réplicas do serviço (exemplo)
    shell_exec("docker-compose up -d --scale worker-lanca-foguete={$replicas}");
    echo "Atualizando réplicas do serviço para: $replicas\n";
}

// Conecta-se ao RabbitMQ
try {
    $connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
    $channel = $connection->channel();
} catch (Exception $e) {
    echo "Erro ao conectar-se ao RabbitMQ: {$e->getMessage()}\n";
    exit;
}

$numeroReplicaAtual = 0;

// Loop principal
while (true) {
    // Verifica o tamanho da fila
    $queueSize = getQueueSize($channel, $queueName);
    if ($queueSize < 0) {
        // Aguarda o intervalo antes de verificar novamente
        sleep($intervalo);
        continue;
    }

    echo "Número de mensagens na fila: $queueSize\n";

    // Calcula o número de réplicas com base na quantidade de mensagens e atualiza
    $newReplicas = calculateReplicas($queueSize);

    if($newReplicas != $numeroReplicaAtual and $newReplicas <= 50){
        $numeroReplicaAtual = $newReplicas;
        updateServiceReplicas($newReplicas);
    }

    // Aguarda o intervalo antes de verificar novamente
    sleep($intervalo);
}

// Fecha a conexão com o RabbitMQ (não será alcançado enquanto o loop estiver em execução)
$channel->close();
$connection->close();