<?php

namespace Mycro\Core\Messaging\RabbitMQ;

use Exception;
use Mycro\Core\Logging\CoreLoggerInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQService implements RabbitMQPublisherInterface
{
    protected AMQPStreamConnection $connection;
    protected AMQPChannel $channel;

    /**
     * @throws Exception
     */
    public function __construct(
        protected string $host,
        protected int $port,
        protected string $user,
        protected string $password,
        protected CoreLoggerInterface $logger,
    ) {
        $this->connection = new AMQPStreamConnection($host, $port, $user, $password);
        $this->channel = $this->connection->channel();
    }

    public function publish(string $exchange, string $routingKey, array $data, ?string $queue = null): void
    {
        $this->channel->exchange_declare($exchange, 'direct', false, true, false);

        if ($queue) {
            $this->channel->queue_declare($queue, false, true, false, false);
            $this->channel->queue_bind($queue, $exchange, $routingKey);
        }

        $msg = new AMQPMessage(json_encode($data), [
            'delivery_mode' => 2
        ]);

        $this->channel->basic_publish($msg, $exchange, $routingKey);
    }

    public function consume(string $queue, callable $callback): void
    {
        $this->channel->queue_declare($queue, false, true, false, false);

        $this->channel->basic_consume(
            $queue,
            '',
            false,
            false, // no_ack = false → ручное подтверждение
            false,
            false,
            function (AMQPMessage $message) use ($callback) {
                try {
                    $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);

                    $callback($payload);

                    $message->ack();
                } catch (\Throwable $e) {
                    $this->logger->error('RabbitMQ consumer error: ' . $e->getMessage());
                    $message->nack(false, true);
                }
            }
        );

        // слушаем в цикле
        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    /**
     * @throws Exception
     */
    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
