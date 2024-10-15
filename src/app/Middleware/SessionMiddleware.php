<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use ArrayAccess;

class SessionMiddleware implements MiddlewareInterface, ArrayAccess
{
    private array $storage;

    public function __construct()
    {
        session_start();
        $this->storage =& $_SESSION;
    }

    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        if (!isset($this->storage['logged'])) {
            $this->storage['logged'] = false;
        }

        $request = $request->withAttribute('session', $this);
        $request = $request->withAttribute('user', $this->storage['user'] ?? null);
        return $handler->handle($request);
    }

    /**
     * ArrayAccess for storage
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->storage[] = $value;
        } else {
            $this->storage[$offset] = $value;
        }
    }
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->storage[$offset]);
    }
    public function offsetUnset(mixed $offset): void
    {
        unset($this->storage[$offset]);
    }
    public function &offsetGet(mixed $offset): mixed
    {
        $ret = null;

        if ($this->offsetExists($offset)) {
            $ret = $this->storage[$offset];
        }

        return $ret;
    }
}
