<?php

/*
 * server-handler-swoole (https://github.com/juliangut/server-handler-swoole).
 * Swoole with PSR-15.
 *
 * @license BSD-3-Clause
 * @link https://github.com/juliangut/server-handler-swoole
 * @author Julián Gutiérrez <juliangut@gmail.com>
 */

declare(strict_types=1);

namespace Jgut\ServerHandler\Swoole\Tests\Http;

use Jgut\ServerHandler\Swoole\Http\PsrRequestFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFileFactory;
use Laminas\Diactoros\UriFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;
use Swoole\Http\Request as SwooleRequest;

class PsrRequestFactoryTest extends TestCase
{
    /**
     * @var PsrRequestFactory
     */
    private $requestFactory;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->requestFactory = new PsrRequestFactory(
            new ServerRequestFactory(),
            new UriFactory(),
            new StreamFactory(),
            new UploadedFileFactory()
        );
    }

    public function testDefault(): void
    {
        $swooleRequest = $this->getMockBuilder(SwooleRequest::class)->disableOriginalConstructor()->getMock();
        $swooleRequest->expects(self::once())
            ->method('rawContent')
            ->willReturn('');

        $psrRequest = $this->requestFactory->fromSwooleRequest($swooleRequest);

        self::assertEquals('GET', $psrRequest->getMethod());
        self::assertEquals('1.1', $psrRequest->getProtocolVersion());
        self::assertEmpty($psrRequest->getHeaders());
        self::assertEmpty($psrRequest->getServerParams());
        self::assertEmpty($psrRequest->getCookieParams());
        self::assertEmpty($psrRequest->getQueryParams());
        self::assertEmpty($psrRequest->getUploadedFiles());
        self::assertEquals('http:/', (string) $psrRequest->getUri());
        self::assertEquals('', (string) $psrRequest->getBody());
    }

    public function testProtocolVersion(): void
    {
        $swooleRequest = $this->getMockBuilder(SwooleRequest::class)->disableOriginalConstructor()->getMock();
        $swooleRequest->expects(self::once())
            ->method('rawContent')
            ->willReturn('');
        $swooleRequest->server = ['SERVER_PROTOCOL' => '1.0'];

        $psrRequest = $this->requestFactory->fromSwooleRequest($swooleRequest);

        self::assertEquals('1.0', $psrRequest->getProtocolVersion());
    }

    public function testMethod(): void
    {
        $swooleRequest = $this->getMockBuilder(SwooleRequest::class)->disableOriginalConstructor()->getMock();
        $swooleRequest->expects(self::once())
            ->method('rawContent')
            ->willReturn('');
        $swooleRequest->server = ['REQUEST_METHOD' => 'POST'];

        $psrRequest = $this->requestFactory->fromSwooleRequest($swooleRequest);

        self::assertEquals('POST', $psrRequest->getMethod());
    }

    public function testUriHttps(): void
    {
        $swooleRequest = $this->getMockBuilder(SwooleRequest::class)->disableOriginalConstructor()->getMock();
        $swooleRequest->expects(self::once())
            ->method('rawContent')
            ->willReturn('');
        $swooleRequest->server = [
            'HTTPS' => 'on',
            'HTTP_HOST' => 'example.com',
        ];

        $psrRequest = $this->requestFactory->fromSwooleRequest($swooleRequest);

        self::assertEquals('https://example.com/', (string) $psrRequest->getUri());
    }

    public function testUriHostAndPort(): void
    {
        $swooleRequest = $this->getMockBuilder(SwooleRequest::class)->disableOriginalConstructor()->getMock();
        $swooleRequest->expects(self::once())
            ->method('rawContent')
            ->willReturn('');
        $swooleRequest->header = ['HOST' => 'example.com:8080'];

        $psrRequest = $this->requestFactory->fromSwooleRequest($swooleRequest);

        self::assertEquals('http://example.com:8080/', (string) $psrRequest->getUri());
    }

    public function testUriServerNameAndPort(): void
    {
        $swooleRequest = $this->getMockBuilder(SwooleRequest::class)->disableOriginalConstructor()->getMock();
        $swooleRequest->expects(self::once())
            ->method('rawContent')
            ->willReturn('');
        $swooleRequest->server = [
            'SERVER_NAME' => 'example.com',
            'SERVER_PORT' => 8080,
        ];

        $psrRequest = $this->requestFactory->fromSwooleRequest($swooleRequest);

        self::assertEquals('http://example.com:8080/', (string) $psrRequest->getUri());
    }

    public function testUriIPV6(): void
    {
        $swooleRequest = $this->getMockBuilder(SwooleRequest::class)->disableOriginalConstructor()->getMock();
        $swooleRequest->expects(self::once())
            ->method('rawContent')
            ->willReturn('');
        $swooleRequest->server = [
            'SERVER_NAME' => '2001:0db8:0000:0000:0000:ff00:0042:8329',
            'SERVER_PORT' => 8080,
            'SERVER_ADDR' => '2001:0db8:0000:0000:0000:ff00:0042:8329:8080',
        ];

        $psrRequest = $this->requestFactory->fromSwooleRequest($swooleRequest);

        self::assertEquals('http://[2001:0db8:0000:0000:0000:ff00:0042:8329:8080]/', (string) $psrRequest->getUri());
    }

    public function testUriPathAndQuerystring(): void
    {
        $swooleRequest = $this->getMockBuilder(SwooleRequest::class)->disableOriginalConstructor()->getMock();
        $swooleRequest->expects(self::once())
            ->method('rawContent')
            ->willReturn('');
        $swooleRequest->server = [
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/path#segment',
            'QUERY_STRING' => 'key=value',
        ];

        $psrRequest = $this->requestFactory->fromSwooleRequest($swooleRequest);

        self::assertEquals('http://example.com/path?key=value#segment', (string) $psrRequest->getUri());
    }

    public function testHeaders(): void
    {
        $swooleRequest = $this->getMockBuilder(SwooleRequest::class)->disableOriginalConstructor()->getMock();
        $swooleRequest->expects(self::once())
            ->method('rawContent')
            ->willReturn('');
        $swooleRequest->header = ['Accept' => 'application/json'];

        $psrRequest = $this->requestFactory->fromSwooleRequest($swooleRequest);

        self::assertCount(1, $psrRequest->getHeaders());
        self::assertEquals('application/json', $psrRequest->getHeaderLine('Accept'));
    }

    public function testServerParams(): void
    {
        $swooleRequest = $this->getMockBuilder(SwooleRequest::class)->disableOriginalConstructor()->getMock();
        $swooleRequest->expects(self::once())
            ->method('rawContent')
            ->willReturn('');
        $swooleRequest->server = ['Remote-Ip' => '127.0.0.1'];

        $psrRequest = $this->requestFactory->fromSwooleRequest($swooleRequest);

        self::assertCount(1, $psrRequest->getServerParams());
        self::assertEquals(['REMOTE-IP' => '127.0.0.1'], $psrRequest->getServerParams());
    }

    public function testCookieParams(): void
    {
        $swooleRequest = $this->getMockBuilder(SwooleRequest::class)->disableOriginalConstructor()->getMock();
        $swooleRequest->expects(self::once())
            ->method('rawContent')
            ->willReturn('');
        $swooleRequest->cookie = ['SessionId' => '123456'];

        $psrRequest = $this->requestFactory->fromSwooleRequest($swooleRequest);

        self::assertCount(1, $psrRequest->getCookieParams());
        self::assertEquals(['SessionId' => '123456'], $psrRequest->getCookieParams());
    }

    public function testUploadedFiles(): void
    {
        $swooleRequest = $this->getMockBuilder(SwooleRequest::class)->disableOriginalConstructor()->getMock();
        $swooleRequest->expects(self::once())
            ->method('rawContent')
            ->willReturn('');
        $swooleRequest->files = [
            'picture' => [
                ['tmp_name' => 'tmpFile', 'size' => 100, 'error' => 0, 'name' => 'picture.jpg'],
            ],
        ];

        $psrRequest = $this->requestFactory->fromSwooleRequest($swooleRequest);

        self::assertCount(1, $psrRequest->getUploadedFiles());
        /** @var UploadedFileInterface $file */
        $uploadedFile = $psrRequest->getUploadedFiles()['picture'];
        self::assertEquals('picture.jpg', $uploadedFile[0]->getClientFilename());
    }

    public function testBody(): void
    {
        $swooleRequest = $this->getMockBuilder(SwooleRequest::class)->disableOriginalConstructor()->getMock();
        $swooleRequest->expects(self::once())
            ->method('rawContent')
            ->willReturn('Body content');

        $psrRequest = $this->requestFactory->fromSwooleRequest($swooleRequest);

        self::assertEquals('Body content', (string) $psrRequest->getBody());
    }
}
