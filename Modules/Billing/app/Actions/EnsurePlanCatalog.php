<?php

declare(strict_types=1);

namespace Modules\Billing\Actions;

use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\FeatureValueType;
use Modules\Billing\Enums\PlanStatus;
use Modules\Billing\Models\Feature;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanFeature;

final class EnsurePlanCatalog
{
    /** @var list<array<string, mixed>> */
    private const PLANS = [
        [
            'name' => 'Launch 1',
            'slug' => 'launch-1',
            'description' => 'A focused storefront for one product, digital product, course, book, consultant, or local business.',
            'best_for' => 'Single-product businesses',
            'price_amount' => 300,
            'currency_code' => 'USD',
            'billing_interval' => BillingInterval::Month->value,
            'is_custom_pricing' => false,
            'status' => PlanStatus::Active->value,
            'sort_order' => 10,
        ],
        [
            'name' => 'Launch 5',
            'slug' => 'launch-5',
            'description' => 'A compact catalog for handmade goods, small food businesses, artists, and similar merchants.',
            'best_for' => 'Small catalog (up to 5 products)',
            'price_amount' => 500,
            'currency_code' => 'USD',
            'billing_interval' => BillingInterval::Month->value,
            'is_custom_pricing' => false,
            'status' => PlanStatus::Active->value,
            'sort_order' => 20,
        ],
        [
            'name' => 'Starter',
            'slug' => 'starter',
            'best_for' => 'Growing stores',
            'price_amount' => 900,
            'currency_code' => 'USD',
            'billing_interval' => BillingInterval::Month->value,
            'is_custom_pricing' => false,
            'status' => PlanStatus::Active->value,
            'sort_order' => 30,
        ],
        [
            'name' => 'Growth',
            'slug' => 'growth',
            'best_for' => 'Small businesses',
            'price_amount' => 2900,
            'currency_code' => 'USD',
            'billing_interval' => BillingInterval::Month->value,
            'is_custom_pricing' => false,
            'status' => PlanStatus::Active->value,
            'is_featured' => true,
            'sort_order' => 40,
        ],
        [
            'name' => 'Professional',
            'slug' => 'professional',
            'best_for' => 'Established brands',
            'price_amount' => 7900,
            'currency_code' => 'USD',
            'billing_interval' => BillingInterval::Month->value,
            'is_custom_pricing' => false,
            'status' => PlanStatus::Active->value,
            'sort_order' => 50,
        ],
        [
            'name' => 'Business',
            'slug' => 'business',
            'best_for' => 'Large businesses',
            'price_amount' => 19900,
            'currency_code' => 'USD',
            'billing_interval' => BillingInterval::Month->value,
            'is_custom_pricing' => false,
            'status' => PlanStatus::Active->value,
            'sort_order' => 60,
        ],
        [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'best_for' => 'High-volume merchants',
            'price_amount' => null,
            'currency_code' => 'USD',
            'billing_interval' => null,
            'is_custom_pricing' => true,
            'status' => PlanStatus::Active->value,
            'sort_order' => 70,
        ],
    ];

    /** @var list<array<string, mixed>> */
    private const FEATURES = [
        ['key' => 'product_limit', 'name' => 'Products', 'value_type' => FeatureValueType::Integer->value, 'unit' => 'products'],
        ['key' => 'landing_page_limit', 'name' => 'Landing pages', 'value_type' => FeatureValueType::Integer->value, 'unit' => 'pages'],
        ['key' => 'basic_checkout', 'name' => 'Basic checkout', 'value_type' => FeatureValueType::Boolean->value],
        ['key' => 'contact_page', 'name' => 'Contact page', 'value_type' => FeatureValueType::Boolean->value],
        ['key' => 'about_page', 'name' => 'About page', 'value_type' => FeatureValueType::Boolean->value],
        ['key' => 'faq_page', 'name' => 'FAQ page', 'value_type' => FeatureValueType::Boolean->value],
        ['key' => 'terms_page', 'name' => 'Terms page', 'value_type' => FeatureValueType::Boolean->value],
        ['key' => 'privacy_page', 'name' => 'Privacy page', 'value_type' => FeatureValueType::Boolean->value],
        ['key' => 'custom_domain_limit', 'name' => 'Custom domains', 'value_type' => FeatureValueType::Integer->value, 'unit' => 'domains'],
        ['key' => 'basic_analytics', 'name' => 'Basic analytics', 'value_type' => FeatureValueType::Boolean->value],
        ['key' => 'seo', 'name' => 'SEO', 'value_type' => FeatureValueType::Boolean->value],
        ['key' => 'ssl', 'name' => 'SSL', 'value_type' => FeatureValueType::Boolean->value],
        ['key' => 'featured_landing_page', 'name' => 'Featured landing page', 'value_type' => FeatureValueType::Boolean->value],
        ['key' => 'additional_product_sections', 'name' => 'Additional product sections', 'value_type' => FeatureValueType::Integer->value, 'unit' => 'sections'],
        ['key' => 'basic_blog', 'name' => 'Basic blog', 'value_type' => FeatureValueType::Boolean->value, 'is_addon_eligible' => true],
    ];

    /** @var array<string, array<string, int|bool>> */
    private const PLAN_FEATURES = [
        'launch-1' => [
            'product_limit' => 1,
            'landing_page_limit' => 1,
            'basic_checkout' => true,
            'contact_page' => true,
            'about_page' => true,
            'faq_page' => true,
            'terms_page' => true,
            'privacy_page' => true,
            'custom_domain_limit' => 1,
            'basic_analytics' => true,
            'seo' => true,
            'ssl' => true,
        ],
        'launch-5' => [
            'product_limit' => 5,
            'featured_landing_page' => true,
            'additional_product_sections' => 4,
            'about_page' => true,
            'contact_page' => true,
            'faq_page' => true,
            'terms_page' => true,
            'privacy_page' => true,
            'custom_domain_limit' => 1,
            'seo' => true,
            'basic_analytics' => true,
            'basic_blog' => true,
        ],
    ];

    public function ensure(): void
    {
        $plans = [];
        foreach (self::PLANS as $attributes) {
            $plans[$attributes['slug']] = Plan::query()->firstOrCreate(
                ['slug' => $attributes['slug']],
                $attributes,
            );
        }

        $features = [];
        foreach (self::FEATURES as $attributes) {
            $features[$attributes['key']] = Feature::query()->firstOrCreate(
                ['key' => $attributes['key']],
                [
                    ...$attributes,
                    'is_addon_eligible' => $attributes['is_addon_eligible'] ?? false,
                    'is_active' => true,
                ],
            );
        }

        foreach (self::PLAN_FEATURES as $planSlug => $assignments) {
            $sortOrder = 10;
            foreach ($assignments as $featureKey => $value) {
                $isAddon = $planSlug === 'launch-5' && $featureKey === 'basic_blog';
                PlanFeature::query()->firstOrCreate(
                    [
                        'plan_id' => $plans[$planSlug]->getKey(),
                        'feature_id' => $features[$featureKey]->getKey(),
                    ],
                    [
                        'value' => $value,
                        'is_included' => ! $isAddon,
                        'is_addon' => $isAddon,
                        'sort_order' => $sortOrder,
                    ],
                );
                $sortOrder += 10;
            }
        }
    }
}
