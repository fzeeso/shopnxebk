<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CustomerModuleContractTest extends TestCase
{
    public function test_migration_is_additive_postgresql_and_store_safe(): void
    {
        $migration = $this->source('Modules/Customers/database/migrations/2026_08_31_001000_create_customer_domain_tables.php');

        foreach ([
            "Schema::create('customer_groups'",
            "Schema::create('customer_group_translations'",
            "Schema::create('customers'",
            "Schema::create('customer_credits'",
            "Schema::create('customer_group_categories'",
            "Schema::create('customer_group_discounts'",
            'customers_group_store_fk',
            'customer_group_categories_category_store_fk',
            'customer_group_discounts_product_store_fk',
            'customers_store_email_unique',
            'customer_groups_one_default_per_store',
        ] as $contract) {
            self::assertStringContainsString($contract, $migration);
        }
        self::assertStringNotContainsString('Schema::table(', $migration);
        self::assertStringNotContainsString('DB::table(', $migration);
    }

    public function test_only_customer_group_display_names_are_translated(): void
    {
        $migration = $this->source('Modules/Customers/database/migrations/2026_08_31_001000_create_customer_domain_tables.php');

        self::assertSame(1, substr_count($migration, "Schema::create('customer_group_translations'"));
        self::assertStringNotContainsString("Schema::create('customer_translations'", $migration);
        self::assertStringNotContainsString("Schema::create('customer_credit_translations'", $migration);
        self::assertStringContainsString("return 'customer_group';", $this->source(
            'Modules/Customers/app/Services/Translations/CustomerGroupTranslationHandler.php',
        ));
    }

    public function test_routes_expose_profiles_groups_credits_translations_categories_and_discounts(): void
    {
        $routes = $this->source('Modules/Customers/routes/api.php');

        foreach ([
            "Route::get('customers'",
            "Route::post('customers'",
            "Route::patch('customers/{customer}'",
            "Route::get('customers/{customer}/credits'",
            "Route::post('customers/{customer}/credits'",
            "Route::get('customer-groups'",
            "Route::put('customer-groups/{customerGroup}/categories'",
            "Route::put('customer-groups/{customerGroup}/translations/{language}'",
            "Route::post('customer-groups/{customerGroup}/discounts'",
        ] as $endpoint) {
            self::assertStringContainsString($endpoint, $routes);
        }
        self::assertStringContainsString("'store.bindings'", $routes);
        self::assertStringNotContainsString('credits/{credit}', $routes);
    }

    public function test_services_hash_new_customer_passwords_keep_them_private_and_keep_credits_append_only(): void
    {
        $customerRequest = $this->source('Modules/Customers/app/Http/Requests/CustomerWriteRequest.php');
        $customerModel = $this->source('Modules/Customers/app/Models/Customer.php');
        $customerService = $this->source('Modules/Customers/app/Services/CustomerManagementService.php');
        $customerResource = $this->source('Modules/Customers/app/Http/Resources/CustomerResource.php');
        $creditService = $this->source('Modules/Customers/app/Services/CustomerCreditService.php');

        self::assertStringContainsString('Password::min(12)->mixedCase()->numbers()->symbols()', $customerRequest);
        self::assertStringContainsString("'password' => 'hashed'", $customerModel);
        self::assertStringContainsString("'password' => \$data['password'] ?? null", $customerService);
        self::assertStringNotContainsString("'password' =>", $customerResource);
        self::assertStringContainsString('CustomerCredit::query()->create(', $creditService);
        self::assertStringNotContainsString('->update(', $creditService);
        self::assertStringNotContainsString('->delete(', $creditService);
    }

    public function test_module_exports_a_store_scoped_group_resolver(): void
    {
        $contract = $this->source('Modules/Customers/app/Contracts/CustomerGroupResolver.php');
        $implementation = $this->source('Modules/Customers/app/Services/CustomerGroupReferenceService.php');

        self::assertStringContainsString('interface CustomerGroupResolver', $contract);
        self::assertStringContainsString('Store $store', $contract);
        self::assertStringContainsString("where('store_id', \$store->getKey())", $implementation);
        self::assertStringContainsString("where('public_id', \$publicId)", $implementation);
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }
}
