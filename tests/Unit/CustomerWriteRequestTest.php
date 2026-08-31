<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Modules\Customers\Http\Requests\CustomerGroupWriteRequest;
use Modules\Customers\Http\Requests\CustomerWriteRequest;
use Modules\Customers\Models\Customer;
use Tests\TestCase;

final class CustomerWriteRequestTest extends TestCase
{
    public function test_customer_create_accepts_an_optional_confirmed_secure_password(): void
    {
        $payload = [
            'email' => 'buyer@example.com',
            'first_name' => 'Buyer',
            'status' => 'active',
        ];
        self::assertTrue($this->customerValidator($payload)->passes());

        $payload['password'] = 'StrongPassword1!';
        $payload['password_confirmation'] = 'StrongPassword1!';
        self::assertTrue($this->customerValidator($payload)->passes());

        unset($payload['password_confirmation']);
        self::assertTrue($this->customerValidator($payload)->fails());

        $payload['password'] = 'weak';
        $payload['password_confirmation'] = 'weak';
        self::assertTrue($this->customerValidator($payload)->fails());
    }

    public function test_customer_update_prohibits_password_fields(): void
    {
        $payload = [
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
        ];

        self::assertTrue($this->customerValidator($payload, 'PATCH')->fails());
    }

    public function test_customer_model_hashes_a_new_password_without_exposing_plaintext(): void
    {
        $plainPassword = 'StrongPassword1!';
        $customer = new Customer(['password' => $plainPassword]);
        $storedPassword = (string) $customer->getAttributes()['password'];

        self::assertNotSame($plainPassword, $storedPassword);
        self::assertTrue(Hash::check($plainPassword, $storedPassword));
        self::assertContains('password', $customer->getHidden());
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
    private function customerValidator(array $payload, string $method = 'POST'): Validator
    {
        $request = CustomerWriteRequest::create('/api/v1/store/customers', $method, $payload);

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
