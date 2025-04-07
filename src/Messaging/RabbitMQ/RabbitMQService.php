<?php

namespace Marketplace\Core\Messaging\RabbitMQ;

use Exception;
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
        protected string $password
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

    /**
     * @throws Exception
     */
    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}