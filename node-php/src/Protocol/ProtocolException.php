<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Protocol;

final class ProtocolException extends \InvalidArgumentException
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct($errorCode);
    }
}
