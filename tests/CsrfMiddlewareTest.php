<?php

namespace Test;

use App\CsrfMiddleware;
use App\InvalidCsrfTokenException;
use App\NoCsrfTokenException;
use Interop\Http\Server\RequestHandlerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @coversNothing
 */
class CsrfMiddlewareTest extends TestCase
{
    /**
     * @var CsrfMiddleware
     */
    private $middleware;

    /**
     * @var RequestHandlerInterface
     */
    private $handler;

    public function setUp()
    {
        $this->middleware = new CsrfMiddleware();
        $this->handler = $this->makeHandler();
    }

    public function testValidSessionInConstructor()
    {
        $session1 = [];
        $session2 = $this->createMock(\ArrayAccess::class);

        $middleware1 = new CsrfMiddleware($session1);
        $middleware2 = new CsrfMiddleware($session2);
        $this->assertInstanceOf(CsrfMiddleware::class, $middleware1);
        $this->assertInstanceOf(CsrfMiddleware::class, $middleware2);
    }

    public function testInvalidSessionInConstructor()
    {
        $this->expectException(\TypeError::class);
        $sessionInvalid = new \stdClass();
        new CsrfMiddleware($sessionInvalid);
    }

    public function testLetGetMethodPass()
    {
        $this->handler->expects($this->once())->method('handle');
        $this->middleware->process(
            $this->makeRequest('GET'),
            $this->handler
        );
    }

    public function testNoTokenRequest()
    {
        $this->handler->expects($this->never())->method('handle');
        $this->expectException(NoCsrfTokenException::class);
        $this->middleware->process(
            $this->makeRequest('POST'),
            $this->handler
        );
    }

    public function testValidTokenRequest()
    {
        $this->handler->expects($this->once())->method('handle')->willReturn($this->makeResponse());

        $token = $this->middleware->generateToken();
        $this->middleware->process(
            $this->makeRequest('POST', ['_token' => $token]),
            $this->handler
        );
    }

    public function testInvalidTokenRequest()
    {
        $this->middleware->generateToken();
        $this->handler->expects($this->never())->method('handle');

        $this->expectException(InvalidCsrfTokenException::class);
        $this->middleware->process(
            $this->makeRequest('POST', ['_token' => 'invalid_token_333']),
            $this->handler
        );
    }

    public function testTokenDuplicateUsed()
    {
        $token = $this->middleware->generateToken();
        $this->handler->expects($this->once())->method('handle');
        $this->middleware->process(
            $this->makeRequest('POST', ['_token' => $token]),
            $this->handler
        );

        $this->expectException(InvalidCsrfTokenException::class);
        $this->middleware->process(
            $this->makeRequest('POST', ['_token' => $token]),
            $this->handler
        );
    }

    public function testTokensLimit()
    {
        $session = [];
        $middleware = new CsrfMiddleware($session);

        $token = '';
        for ($i = 0; $i < 100; ++$i) {
            $token = $middleware->generateToken();
        }
        $this->assertSame($middleware->getLimit(), count($session[$middleware->getSessionKey()]));
        $this->assertSame($token, $session[$middleware->getSessionKey()][$middleware->getLimit() - 1]);
    }

    /*
    public function testTokenExpired()
    {

    }
    */
    private function makeRequest(string $method = 'GET', array $params = null)
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getParsedBody')->willReturn($params);

        return $request;
    }

    private function makeHandler()
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->makeResponse());

        return $handler;
    }

    private function makeResponse()
    {
        return $this->createMock(ResponseInterface::class);
    }
}
