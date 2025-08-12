<?php

namespace HMRC\PAYE\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    private ?Client $httpClient = null;

    public function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client([
                'handler' => HandlerStack::create(new MockHandler()),
            ]);
        }
        return $this->httpClient;
    }

    public function setMockHttpResponseString(string $xml): void
    {
        $mock = new MockHandler([
            new Response(200, [], $xml),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $this->httpClient = new Client(['handler' => $handlerStack]);
    }

    public function setMockHttpResponseFile(string $filename): void
    {
        $this->setMockHttpResponseString(file_get_contents(__DIR__ . "/Mock/" . $filename));
    }
}
