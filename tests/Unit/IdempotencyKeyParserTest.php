<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Idempotency\IdempotencyKeyParser;
use Illuminate\Http\Request;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdempotencyKeyParserTest extends TestCase
{
    private IdempotencyKeyParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new IdempotencyKeyParser;
    }

    #[DataProvider('validKeys')]
    public function test_uuid_v4_structured_and_legacy_values_are_normalized(string $header): void
    {
        $request = Request::create('/api/v1/stores', 'POST');
        $request->headers->set('Idempotency-Key', $header);

        self::assertTrue($this->parser->isPresent($request));
        self::assertSame('8e03978e-40d5-43e8-bc93-6894a57f9324', $this->parser->parse($request));
    }

    /** @return iterable<string, array{string}> */
    public static function validKeys(): iterable
    {
        yield 'structured string' => ['"8e03978e-40d5-43e8-bc93-6894a57f9324"'];
        yield 'legacy token' => ['8E03978E-40D5-43E8-BC93-6894A57F9324'];
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_or_low_entropy_values_are_rejected(string $header): void
    {
        $request = Request::create('/api/v1/stores', 'POST');
        $request->headers->set('Idempotency-Key', $header);

        $this->expectException(InvalidArgumentException::class);
        $this->parser->parse($request);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'business data' => ['store-42-order-7'];
        yield 'uuid v7' => ['01956f7c-83df-7a4f-9d11-c5d7e992f531'];
        yield 'broken structured string' => ['"8e03978e-40d5-43e8-bc93-6894a57f9324'];
        yield 'multiple serialized values' => ['8e03978e-40d5-43e8-bc93-6894a57f9324, another'];
    }

    public function test_missing_header_returns_null(): void
    {
        $request = Request::create('/api/v1/stores', 'POST');

        self::assertFalse($this->parser->isPresent($request));
        self::assertNull($this->parser->parse($request));
    }

    public function test_multiple_header_lines_are_rejected(): void
    {
        $request = Request::create('/api/v1/stores', 'POST');
        $request->headers->set('Idempotency-Key', [
            '8e03978e-40d5-43e8-bc93-6894a57f9324',
            'e8e3a4d4-19b2-462a-b1c4-e79321cd067b',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->parser->parse($request);
    }
}
