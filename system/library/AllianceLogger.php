<?php

class AllianceLogger
{
    public const INFO = 'info';
    public const DEBUG = 'debug';
    public const ERROR = 'error';
    private const ALLIANCE_LOG_FILE = 'alliance.log';
    private $logger;
    public function __construct(Registry $registry)
    {
        $logger = $registry->get('log');
        $this->logger = new Log(self::ALLIANCE_LOG_FILE);
    }

    public function log($message, string $level = 'INFO') {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        $timestamp = date('Y-m-d H:i:s');
        $this->logger->write("[{$timestamp}] [{$level}] {$message}");
    }
}
