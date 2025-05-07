<?php

namespace Mycro\Core\Logging;

interface CoreLoggerInterface
{
    public function error(string $message): void;
    public function info(string $message): void;
    public function debug(string $message): void;
}
