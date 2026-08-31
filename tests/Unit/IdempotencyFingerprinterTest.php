<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Idempotency\IdempotencyFingerprinter;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class IdempotencyFingerprinterTest extends TestCase
{
    private Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $container = new Container;
        $container->instance('config', new ConfigRepository([
            'idempotency' => ['fingerprint_version' => 1],
        ]));
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function test_query_order_does_not_change_the_fingerprint(): void
    {
        $left = $this->request('/api/v1/store/products/example?b=2&a=1', '{"name":"Example"}');
        $right = $this->request('/api/v1/store/products/example?a=1&b=2', '{"name":"Example"}');
        $fingerprinter = new IdempotencyFingerprinter;

        self::assertSame(
            $fingerprinter->fingerprint($left, 'api.v1.store.products.update'),
            $fingerprinter->fingerprint($right, 'api.v1.store.products.update'),
        );
    }

    public function test_changed_body_or_target_changes_the_fingerprint(): void
    {
        $fingerprinter = new IdempotencyFingerprinter;
        $original = $this->request('/api/v1/store/products/first', '{"name":"Example"}', 'first');
        $changedBody = $this->request('/api/v1/store/products/first', '{"name":"Changed"}', 'first');
        $changedTarget = $this->request('/api/v1/store/products/second', '{"name":"Example"}', 'second');

        $hash = $fingerprinter->fingerprint($original, 'api.v1.store.products.update');

        self::assertNotSame($hash, $fingerprinter->fingerprint($changedBody, 'api.v1.store.products.update'));
        self::assertNotSame($hash, $fingerprinter->fingerprint($changedTarget, 'api.v1.store.products.update'));
    }

    private function request(string $uri, string $body, string $product = 'example'): Request
    {
        $request = Request::create(
            uri: $uri,
            method: 'PATCH',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $body,
        );
        $route = new Route(
            ['PATCH'],
            'api/v1/store/products/{product}',
            static fn (): Response => new Response,
        );
        $route->bind($request);
        $route->setParameter('product', $product);
        $request->setRouteResolver(static fn (): Route => $route);

        return $request;
    }
}
