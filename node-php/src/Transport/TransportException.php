<?php
declare(strict_types=1);
namespace OnionServiceDiscovery\Transport;
final class TransportException extends \RuntimeException
{
    /**
     * Failures after which the same request may succeed later: Tor or the Onion was unreachable
     * or slow, or the Onion answered with an HTTP error. A malformed answer is not in this list.
     */
    public const TRANSIENT = ['TOR_PROXY_UNREACHABLE', 'ONION_UNREACHABLE', 'TOR_REQUEST_TIMEOUT', 'TOR_REQUEST_FAILED',
        'HTTP_NOT_FOUND', 'HTTP_REDIRECT_REJECTED', 'HTTP_SERVER_ERROR', 'HTTP_STATUS_REJECTED'];
}
