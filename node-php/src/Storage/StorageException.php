<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Storage;

final class StorageException extends \RuntimeException
{
    public function __construct(public readonly string $errorCode = 'STORAGE_ERROR')
    {
        parent::__construct($errorCode);
    }
}
