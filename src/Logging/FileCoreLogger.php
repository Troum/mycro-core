<?php

namespace Marketplace\Core\Logging;

use DateTimeImmutable;

class FileCoreLogger implements CoreLoggerInterface
{
    /**
     * @var string
     */
    protected string $logFile;

    /**
     * @param string|null $logFile
     */
    public function __construct(?string $logFile = null)
    {
        $this->logFile = $logFile ?? __DIR__ . '/../../../logs/core.log';

        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    /**
     * @param string $message
     * @return void
     */
    public function error(string $message): void
    {
        $this->log('ERROR', $message);
    }

    /**
     * @param string $message
     * @return void
     */
    public function info(string $message): void
    {
        $this->log('INFO', $message);
    }

    /**
     * @param string $message
     * @return void
     */
    public function debug(string $message): void
    {
        $this->log('DEBUG', $message);
    }

    /**
     * @param string $level
     * @param string $message
     * @return void
     */
    protected function log(string $level, string $message): void
    {
        $time = new DateTimeImmutable()->format('Y-m-d H:i:s');
        $entry = "[{$time}] {$level}: {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $entry, FILE_APPEND);
    }
}