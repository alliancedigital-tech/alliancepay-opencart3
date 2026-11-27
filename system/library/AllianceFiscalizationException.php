<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

/**
 * Class AllianceFiscalizationException.
 *
 * Local (pre-flight) fiscalization error. The message is a ready-to-show default (uk);
 * the key and args allow the caller to translate it.
 */
class AllianceFiscalizationException extends \RuntimeException
{
    private $messageKey;

    private $messageArgs;

    /**
     * @param string $messageKey
     * @param string $message
     * @param array $messageArgs
     */
    public function __construct(string $messageKey, string $message, array $messageArgs = [])
    {
        parent::__construct($message);
        $this->messageKey = $messageKey;
        $this->messageArgs = $messageArgs;
    }

    /**
     * @return string
     */
    public function getMessageKey(): string
    {
        return $this->messageKey;
    }

    /**
     * @return array
     */
    public function getMessageArgs(): array
    {
        return $this->messageArgs;
    }
}
