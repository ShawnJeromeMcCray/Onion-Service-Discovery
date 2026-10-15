<?php
declare(strict_types=1);

namespace OnionServiceDiscovery\Protocol;

final class ProtocolException extends \InvalidArgumentException
{
    /**
     * @param string|null $detail Where the problem is, for example a configuration key path and the
     *                            rule it breaks. Never a value: details are printed by the CLI.
     */
    public function __construct(public readonly string $errorCode, public readonly ?string $detail = null)
    {
        parent::__construct($errorCode);
    }
}
