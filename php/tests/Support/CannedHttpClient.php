<?php

declare(strict_types=1);

namespace Pepito\Tests\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** PSR-18: a client handing back a canned response. */
final class CannedHttpClient implements ClientInterface
{
    public ?RequestInterface $lastRequest = null;

    public function __construct(private readonly string $body) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        return new Response(200, [], $this->body);
    }
}
