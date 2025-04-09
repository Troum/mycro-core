<?php

namespace Marketplace\Core\Messaging\RabbitMQ;

class RabbitMQConsumer
{
    public function __construct(
        private readonly RabbitMQService $client,
    ) {}

    public function listen(string $queue, callable $handler): void
    {
        $this->client->consume($queue, $handler);
    }
}