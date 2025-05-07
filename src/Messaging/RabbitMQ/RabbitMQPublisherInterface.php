<?php

namespace Mycro\Core\Messaging\RabbitMQ;

interface RabbitMQPublisherInterface
{
    public function publish(string $exchange, string $routingKey, array $data, ?string $queue = null): void;
}
