<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Modules\Customers\Http\Requests\CustomerGroupWriteRequest;
use Modules\Customers\Http\Requests\CustomerWriteRequest;
use PHPUnit\Framework\TestCase;

final class CustomerWriteRequestTest extends TestCase
{
    public function test_customer_create_accepts_identity_fields_but_rejects_credentials(): void
    {
        $payload = [
            'email' => 'buyer@example.com',
            'first_name' => 'Buyer',
            'status' => 'active',
        ];
        self::assertTrue($this->customerValidator($payload)->passes());

        $payload['password'] = 'must-not-cross-this-contract';
        self::assertTrue($this->customerValidator($payload)->fails());
    }

    public function test_group_create_requires_language_safe_display_name_and_logic_code(): void
    {
        $payload = [
            'code' => 'WHOLESALE',
            'discount_method' => 'price',
            'category_access_type' => 'specific',
            'category_ids' => ['01K4Z1BMX5A1F8QMCV1A8Z5E21'],
            'translations' => [[
                'language_id' => '01K4Z1BMX5A1F8QMCV1A8Z5E22',
                'name' => 'Wholesale customers',
            ]],
        ];

        self::assertTrue($this->groupValidator($payload)->passes());
        $payload['translations'][] = $payload['translations'][0];
        self::assertTrue($this->groupValidator($payload)->fails());
    }

    /** @param array<string, mixed> $payload */
    private function customerValidator(array $payload): Validator
    {
        $request = CustomerWriteRequest::create('/api/v1/store/customers', 'POST', $payload);

        return $this->factory()->make($payload, $request->rules());
    }

    /** @param array<string, mixed> $payload */
    private function groupValidator(array $payload): Validator
    {
        $request = CustomerGroupWriteRequest::create('/api/v1/store/customer-groups', 'POST', $payload);

        return $this->factory()->make($payload, $request->rules());
    }

    private function factory(): Factory
    {
        return new Factory(new Translator(new ArrayLoader, 'en'));
    }
}
