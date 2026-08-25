<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Modules\Catalog\Models\ModifierDefinition;
use Modules\Catalog\Models\ModifierPriceAdjustment;
use Modules\Catalog\Models\ModifierTranslation;
use Modules\Catalog\Models\ModifierValue;
use Modules\Catalog\Models\ModifierValuePriceAdjustment;
use Modules\Catalog\Models\ModifierValueTranslation;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductModifierAssignment;
use Modules\Catalog\Models\ProductModifierPriceOverride;
use Modules\Catalog\Models\ProductModifierValueAssignment;
use Modules\Catalog\Services\ModifierPricingResolver;
use Modules\Catalog\Services\ModifierSelectionValidator;
use Modules\Catalog\Services\ModifierTranslationResolver;
use Modules\Catalog\Services\ProductModifierResolver;
use Modules\Stores\Models\Store;
use PHPUnit\Framework\TestCase;

final class ProductModifierLibraryTest extends TestCase
{
    public function test_01_modifier_can_be_reused_across_multiple_products(): void
    {
        [$store, $product, $assignment] = $this->fixture();
        $otherProduct = (new Product)->forceFill(['id' => 202, 'store_id' => 1, 'price' => '100.0000']);
        $otherAssignment = clone $assignment;
        $otherAssignment->forceFill(['id' => 302, 'product_id' => 202, 'public_id' => '01KAAAAAAAAAAAAAAAAAAAAAA2']);
        $resolver = $this->resolver();

        self::assertSame(
            $resolver->resolveLoaded($store, $product, [$assignment], 'en', 'GBP')[0]['modifierId'],
            $resolver->resolveLoaded($store, $otherProduct, [$otherAssignment], 'en', 'GBP')[0]['modifierId'],
        );
    }

    public function test_02_modifier_translations_resolve_for_requested_locale(): void
    {
        $result = (new ModifierTranslationResolver)->resolveModifier([], [
            ['locale' => 'fr', 'name' => 'Couleur du ruban'],
        ], 'fr', 'en', 'ribbon_colour');

        self::assertSame('Couleur du ruban', $result['name']);
    }

    public function test_03_default_locale_fallback_works(): void
    {
        $result = (new ModifierTranslationResolver)->resolveModifier([], [
            ['locale' => 'en', 'name' => 'Ribbon Colour'],
        ], 'de', 'en', 'ribbon_colour');

        self::assertSame('Ribbon Colour', $result['name']);
    }

    public function test_04_product_specific_translation_override_wins(): void
    {
        $result = (new ModifierTranslationResolver)->resolveModifier(
            [['locale' => 'en', 'name_override' => 'Gift ribbon']],
            [['locale' => 'en', 'name' => 'Ribbon Colour']],
            'en',
            'en',
            'ribbon_colour',
        );

        self::assertSame('Gift ribbon', $result['name']);
    }

    public function test_05_product_can_restrict_available_modifier_values(): void
    {
        [$store, $product, $assignment] = $this->fixture();
        $assignment->setRelation('valueAssignments', Collection::make([
            (new ProductModifierValueAssignment)->forceFill(['modifier_value_id' => 401, 'is_enabled' => true]),
        ]));

        $resolved = $this->resolver()->resolveLoaded($store, $product, [$assignment], 'en', 'GBP');

        self::assertSame(['white'], array_column($resolved[0]['values'], 'code'));
    }

    public function test_06_product_required_override_works(): void
    {
        [$store, $product, $assignment] = $this->fixture();
        $assignment->forceFill(['is_required_override' => true]);

        self::assertTrue($this->resolver()->resolveLoaded($store, $product, [$assignment], 'en', 'GBP')[0]['required']);
    }

    public function test_07_modifier_level_price_adjustment_works(): void
    {
        [$store, $product, $assignment] = $this->fixture();
        $assignment->modifier->setRelation('priceAdjustments', Collection::make([$this->modifierPrice('fixed', '2.5000')]));

        self::assertSame('2.5000', $this->resolver()->resolveLoaded($store, $product, [$assignment], 'en', 'GBP')[0]['priceAdjustment']['amount']);
    }

    public function test_08_value_level_price_adjustment_works(): void
    {
        [$store, $product, $assignment] = $this->fixture();
        $assignment->modifier->values[0]->setRelation('priceAdjustments', Collection::make([$this->valuePrice('fixed', '1.5000')]));

        self::assertSame('1.5000', $this->resolver()->resolveLoaded($store, $product, [$assignment], 'en', 'GBP')[0]['values'][0]['priceAdjustment']['amount']);
    }

    public function test_09_product_specific_price_override_wins(): void
    {
        [, $product, $assignment] = $this->fixture();
        $assignment->modifier->setRelation('priceAdjustments', Collection::make([$this->modifierPrice('fixed', '2.0000')]));
        $assignment->setRelation('priceOverrides', Collection::make([
            (new ProductModifierPriceOverride)->forceFill(['id' => 9, 'currency_code' => 'GBP', 'adjustment_type' => 'fixed', 'amount' => '5.0000', 'is_active' => true]),
        ]));

        self::assertSame('5.0000', (new ModifierPricingResolver)->resolve($assignment, $assignment->modifier, null, 'GBP', $product->price)['amount']);
    }

    public function test_10_currency_specific_adjustment_works(): void
    {
        [, $product, $assignment] = $this->fixture();
        $assignment->modifier->setRelation('priceAdjustments', Collection::make([
            $this->modifierPrice('fixed', '2.0000', 'GBP'),
            $this->modifierPrice('fixed', '9.0000', 'USD'),
        ]));

        self::assertSame('9.0000', (new ModifierPricingResolver)->resolve($assignment, $assignment->modifier, null, 'USD', $product->price)['amount']);
    }

    public function test_11_customer_group_and_channel_filtering_prefers_most_specific_row(): void
    {
        $picked = (new ModifierPricingResolver)->pick([
            ['id' => 1, 'currency_code' => 'GBP', 'amount' => 1, 'is_active' => true],
            ['id' => 2, 'currency_code' => 'GBP', 'amount' => 2, 'channel_id' => 7, 'is_active' => true],
            ['id' => 3, 'currency_code' => 'GBP', 'amount' => 3, 'channel_id' => 7, 'customer_group_id' => 8, 'is_active' => true],
        ], 'GBP', 7, 8);

        self::assertSame(3, $picked['id']);
    }

    public function test_12_text_modifier_cart_selection_validates(): void
    {
        $errors = (new ModifierSelectionValidator)->validate([
            'type' => 'text', 'required' => true, 'supportsMultiple' => false,
            'validationRules' => [['type' => 'min_length', 'value' => ['value' => 2], 'message' => 'Too short']],
        ], [['input_value' => ['text' => 'Chris']]]);

        self::assertSame([], $errors);
    }

    public function test_13_file_modifier_selection_accepts_public_asset_ulids(): void
    {
        $errors = (new ModifierSelectionValidator)->validate([
            'type' => 'file', 'required' => true, 'supportsMultiple' => true, 'maxSelections' => 1,
        ], [['input_value' => ['asset_ids' => ['01KAAAAAAAAAAAAAAAAAAAAAAA']]]]);

        self::assertSame([], $errors);
    }

    public function test_14_multi_select_is_validated_as_separate_selection_rows(): void
    {
        $errors = (new ModifierSelectionValidator)->validate([
            'type' => 'checkbox_group', 'required' => true, 'supportsMultiple' => true, 'maxSelections' => 2,
            'values' => [['id' => '01KAAAAAAAAAAAAAAAAAAAAAAA'], ['id' => '01KAAAAAAAAAAAAAAAAAAAAAAB']],
        ], [['value_id' => '01KAAAAAAAAAAAAAAAAAAAAAAA'], ['value_id' => '01KAAAAAAAAAAAAAAAAAAAAAAB']]);
        $source = file_get_contents(dirname(__DIR__, 2).'/Modules/Catalog/app/Services/CartModifierSelectionService.php');

        self::assertSame([], $errors);
        self::assertStringContainsString('foreach ($grouped->get($assignmentId', (string) $source);
    }

    public function test_15_wrong_store_modifier_cannot_be_assigned(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/Modules/Catalog/app/Services/ProductModifierAssignmentService.php');

        self::assertStringContainsString("where('store_id', \$store->getKey())", (string) $source);
        self::assertStringContainsString("where('public_id', \$publicId)", (string) $source);
    }

    public function test_16_wrong_modifier_value_cannot_be_submitted(): void
    {
        $errors = (new ModifierSelectionValidator)->validate([
            'type' => 'swatch', 'required' => false, 'supportsMultiple' => false,
            'values' => [['id' => '01KAAAAAAAAAAAAAAAAAAAAAAA']],
        ], [['value_id' => '01KBBBBBBBBBBBBBBBBBBBBBBB']]);

        self::assertArrayHasKey('selections.0.value_id', $errors);
    }

    public function test_17_server_rejects_invalid_required_modifier_selections(): void
    {
        $errors = (new ModifierSelectionValidator)->validate([
            'type' => 'radio', 'required' => true, 'supportsMultiple' => false, 'values' => [],
        ], []);

        self::assertArrayHasKey('selections', $errors);
    }

    public function test_18_server_recalculates_price_instead_of_trusting_client_input(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/Modules/Catalog/app/Services/CartModifierSelectionService.php');

        self::assertStringContainsString('$this->pricing->resolve', (string) $source);
        self::assertStringContainsString("'price_adjustment' => \$price['amount']", (string) $source);
    }

    public function test_19_checkout_creates_complete_order_snapshots(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/Modules/Catalog/app/Services/OrderModifierSnapshotService.php');

        foreach (['modifier_public_id', 'modifier_code', 'modifier_name', 'value_public_id', 'price_adjustment', 'currency_code', 'locale'] as $field) {
            self::assertStringContainsString("'{$field}'", (string) $source);
        }
    }

    public function test_20_catalogue_edits_cannot_update_historic_order_snapshots(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/Modules/Catalog/app/Services/OrderModifierSnapshotService.php');

        self::assertStringContainsString('immutable and may only be created once', (string) $source);
        self::assertStringNotContainsString('->update(', (string) $source);
    }

    public function test_21_schema_contract_defines_all_tables_indexes_and_only_five_public_ulids(): void
    {
        $root = dirname(__DIR__, 2).'/Modules/Catalog/database/migrations/';
        $source = (string) file_get_contents($root.'2026_08_25_001000_create_modifier_library_tables.php')
            .(string) file_get_contents($root.'2026_08_25_001100_create_product_modifier_assignment_tables.php')
            .(string) file_get_contents($root.'2026_08_25_001200_create_cart_and_order_modifier_tables.php');
        $tables = [
            'modifier_library_categories', 'modifier_library_category_translations', 'modifier_definitions',
            'modifier_translations', 'modifier_values', 'modifier_value_translations', 'modifier_validation_rules',
            'modifier_validation_rule_translations', 'modifier_price_adjustments', 'modifier_value_price_adjustments',
            'product_modifier_groups', 'product_modifier_group_translations', 'product_modifier_assignments',
            'product_modifier_assignment_translations', 'product_modifier_value_assignments',
            'product_modifier_price_overrides', 'product_modifier_value_price_overrides',
            'cart_item_modifier_selections', 'order_item_modifier_snapshots',
        ];
        foreach ($tables as $table) {
            self::assertStringContainsString("'{$table}'", $source);
        }
        foreach ([
            'modifier_definitions_store_active_idx', 'modifier_values_store_modifier_active_idx',
            'modifier_translations_modifier_locale_idx', 'modifier_value_translations_value_locale_idx',
            'product_modifier_assignments_store_product_active_idx',
            'product_modifier_value_assignments_assignment_enabled_idx',
            'cart_item_modifier_selections_cart_item_idx', 'order_item_modifier_snapshots_order_item_idx',
        ] as $index) {
            self::assertStringContainsString($index, $source);
        }
        self::assertStringContainsString('{$tableName}_store_resource_currency_idx', $source);
        self::assertSame(5, substr_count($source, "ulid('public_id')"));
        self::assertStringNotContainsString('tenant_id', $source);
    }

    /** @return array{Store, Product, ProductModifierAssignment} */
    private function fixture(): array
    {
        $store = (new Store)->forceFill(['id' => 1, 'language_code' => 'en', 'currency_code' => 'GBP']);
        $product = (new Product)->forceFill(['id' => 201, 'store_id' => 1, 'price' => '100.0000']);
        $white = $this->value(401, '01KAAAAAAAAAAAAAAAAAAAAAA3', 'white', 'White');
        $blue = $this->value(402, '01KAAAAAAAAAAAAAAAAAAAAAA4', 'royal_blue', 'Royal Blue');
        $modifier = (new ModifierDefinition)->forceFill([
            'id' => 101, 'store_id' => 1, 'public_id' => '01KAAAAAAAAAAAAAAAAAAAAAA1',
            'code' => 'ribbon_colour', 'type' => 'swatch', 'is_required_default' => false,
            'supports_multiple' => false, 'min_selections' => null, 'max_selections' => 1,
        ]);
        $modifier->setRelations([
            'translations' => Collection::make([(new ModifierTranslation)->forceFill(['locale' => 'en', 'name' => 'Ribbon Colour'])]),
            'values' => Collection::make([$white, $blue]),
            'validationRules' => Collection::make(),
            'priceAdjustments' => Collection::make(),
        ]);
        $assignment = (new ProductModifierAssignment)->forceFill([
            'id' => 301, 'store_id' => 1, 'product_id' => 201, 'modifier_id' => 101,
            'public_id' => '01KAAAAAAAAAAAAAAAAAAAAAA0', 'is_required_override' => null,
        ]);
        $assignment->setRelations([
            'modifier' => $modifier, 'translations' => Collection::make(), 'group' => null,
            'valueAssignments' => Collection::make(), 'priceOverrides' => Collection::make(),
            'valuePriceOverrides' => Collection::make(),
        ]);

        return [$store, $product, $assignment];
    }

    private function value(int $id, string $publicId, string $code, string $name): ModifierValue
    {
        $value = (new ModifierValue)->forceFill([
            'id' => $id, 'store_id' => 1, 'modifier_id' => 101, 'public_id' => $publicId,
            'code' => $code, 'sort_order' => $id, 'is_default' => false, 'is_active' => true,
        ]);
        $value->setRelations([
            'translations' => Collection::make([(new ModifierValueTranslation)->forceFill(['locale' => 'en', 'name' => $name])]),
            'priceAdjustments' => Collection::make(), 'image' => null,
        ]);

        return $value;
    }

    private function modifierPrice(string $type, string $amount, string $currency = 'GBP'): ModifierPriceAdjustment
    {
        return (new ModifierPriceAdjustment)->forceFill(['id' => random_int(10, 999), 'currency_code' => $currency, 'adjustment_type' => $type, 'amount' => $amount, 'is_active' => true]);
    }

    private function valuePrice(string $type, string $amount, string $currency = 'GBP'): ModifierValuePriceAdjustment
    {
        return (new ModifierValuePriceAdjustment)->forceFill(['id' => random_int(1000, 1999), 'currency_code' => $currency, 'adjustment_type' => $type, 'amount' => $amount, 'is_active' => true]);
    }

    private function resolver(): ProductModifierResolver
    {
        return new ProductModifierResolver(new ModifierTranslationResolver, new ModifierPricingResolver);
    }
}
