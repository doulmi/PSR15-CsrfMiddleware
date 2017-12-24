<?php

namespace App;

use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * @var array | \ArrayAccess
     */
    private $session;
    private $sessionKey = 'csrf.token';
    private $formKey = '_token';
    /**
     * @var int
     */
    private $limit;

    public function __construct(&$session = [], int $limit = 50)
    {
        if (!is_array($session) && !$session instanceof \ArrayAccess) {
            throw new \TypeError();
        }

        $this->session = &$session;
        $this->limit = $limit;
    }

    /**
     * Process an incoming server request and return a response, optionally delegating
     * response creation to a handler.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE'], true)) {
            $params = $request->getParsedBody() ?? [];
            if (!array_key_exists($this->formKey, $params)) {
                throw new NoCsrfTokenException();
            }

            if (!in_array($params[$this->formKey], $this->session[$this->sessionKey], true)) {
                throw new InvalidCsrfTokenException();
            }

            $this->removeToken($params[$this->formKey]);

            return $handler->handle($request);
        }

        return $handler->handle($request);
    }

    public function generateToken(): string
    {
        $token = bin2hex(random_bytes(16));
        $tokens = $this->session[$this->sessionKey] ?? [];

        $tokens[] = $token;
        $this->session[$this->sessionKey] = $this->checkTokenLimit($tokens);

        return $token;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @return string
     */
    public function getSessionKey(): string
    {
        return $this->sessionKey;
    }

    private function removeToken(string $token)
    {
        $currentTokens = $this->session[$this->sessionKey] ?? [];
        $this->session[$this->sessionKey] =
            array_filter($currentTokens, function ($t) use ($token) {
                return $token !== $t;
            });
    }

    private function checkTokenLimit($tokens)
    {
        if (count($tokens) === $this->limit + 1) {
            array_shift($tokens);
        }

        return $tokens;
    }
}
