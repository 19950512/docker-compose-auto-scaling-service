<?php

declare(strict_types=1);

namespace App\Queues;

use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPRuntimeException;

class RabbitMQ
{

    private AMQPStreamConnection $connection;
    private AMQPChannel $channel;

    public function __construct(){

        $host = 'localhost';
        $port = '5674';
        $user = 'guest';
        $password = 'guest';
        $max_retry_connections = 3;
        $retry_delay_seconds = 2;

        $attempts = 0;
        while ($attempts < $max_retry_connections) {
            try {
                $this->connection = new AMQPStreamConnection(
                    $host,
                    $port,
                    $user,
                    $password
                );
                break;
            } catch (AMQPIOException $e) {
                echo "Erro de E/S: " . $e->getMessage() . "\n";
            } catch (AMQPRuntimeException $e) {
                echo "Erro de tempo de execução: " . $e->getMessage() . "\n";
            } catch (Exception $e) {
                echo "Erro desconhecido: " . $e->getMessage() . "\n";
            }

            $attempts++;
            if ($attempts < $max_retry_connections) {
                echo "Tentando novamente em $retry_delay_seconds segundos...\n";
                sleep($retry_delay_seconds);
            } else {
                echo "Limite máximo de tentativas de conexão excedido\n";
                break;
            }
        }

        if (!empty($this->connection)) {
            $this->channel = $this->connection->channel();
        }
    }

    public function publish(string $queue, string $message): void
    {

        $mensagem = new AMQPMessage(
            body: $message,
            properties: [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
            ]
        );

        // Inicia a transação
       // $this->channel->tx_select();

        try {

            $this->channel->basic_publish(
                msg: $mensagem,
                routing_key: $queue
            );

            // Confirma a transação
           // $this->channel->tx_commit();

        }catch(Exception $e) {
            // Desfaz a transação
           // $this->channel->tx_rollback();
        }
    }

    public function subscribe(string $queue, callable $callback): void
    {
        $this->channel->basic_qos(
            prefetch_size: null,
            prefetch_count: 1, // Quantidade de mensagens que o consumidor pode receber por vez até que ele envie um ack
            a_global: null
        );

        try{

            $this->channel->basic_consume(
                queue: $queue,
                no_ack: false,
                callback: $callback
            );

            /*
             Não usar isso, da forma que eu imagino, essa pratica não é útil.
             while ($this->channel->is_consuming()) {
                $this->channel->wait(
                    timeout: 20
                );
            }*/

        }catch(Exception $e){

            $erro = $e->getMessage();

            if(str_contains($erro, 'NOT_FOUND - no queue')){

                // se a fila não existe, vamos cria-la.
                $this->channel->queue_declare(
                    queue: $queue,
                    passive: false,
                    durable: true,
                    exclusive: false,
                    auto_delete: false
                );

                // tenta novamente
                throw new Exception("A fila {$queue} não existe, porem foi criada. Tente novamente.");
            }

            throw new Exception($erro);
        }

        while(count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    public function __destruct()
    {
        if (!empty($this->channel)) {
            $this->channel->close();
        }
        if (!empty($this->connection)) {
            $this->connection->close();
        }
    }
}
