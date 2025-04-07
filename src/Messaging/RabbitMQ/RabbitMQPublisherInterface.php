<?php

namespace Marketplace\Core\Messaging\RabbitMQ;

interface RabbitMQPublisherInterface
{
    public function publish(string $exchange, string $routingKey, array $data): void;
}