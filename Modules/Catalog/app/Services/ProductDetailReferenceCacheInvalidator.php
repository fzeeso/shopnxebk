<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Models\Brand;
use App\Models\BrandTranslation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\CategoryTranslation;
use Modules\Catalog\Models\CustomFieldDefinition;
use Modules\Catalog\Models\CustomFieldDefinitionTranslation;
use Modules\Catalog\Models\CustomFieldOption;
use Modules\Catalog\Models\CustomFieldOptionTranslation;
use Modules\Catalog\Models\CustomObjectEntry;
use Modules\Catalog\Models\CustomObjectField;
use Modules\Catalog\Models\CustomObjectFieldTranslation;
use Modules\Catalog\Models\CustomObjectType;
use Modules\Catalog\Models\CustomObjectTypeTranslation;
use Modules\Catalog\Models\FulfillmentType;
use Modules\Catalog\Models\FulfillmentTypeTranslation;
use Modules\Catalog\Models\ModifierDefinition;
use Modules\Catalog\Models\ModifierLibraryCategory;
use Modules\Catalog\Models\ModifierLibraryCategoryTranslation;
use Modules\Catalog\Models\ModifierPriceAdjustment;
use Modules\Catalog\Models\ModifierTranslation;
use Modules\Catalog\Models\ModifierValidationRule;
use Modules\Catalog\Models\ModifierValidationRuleTranslation;
use Modules\Catalog\Models\ModifierValue;
use Modules\Catalog\Models\ModifierValuePriceAdjustment;
use Modules\Catalog\Models\ModifierValueTranslation;
use Modules\Catalog\Models\PlatformTaxonomy;
use Modules\Catalog\Models\PlatformTaxonomyNode;
use Modules\Catalog\Models\ProductType;
use Modules\Catalog\Models\ProductTypeTranslation;
use Modules\Catalog\Models\SharedProductOption;
use Modules\Catalog\Models\SharedProductOptionTranslation;
use Modules\Catalog\Models\SharedProductOptionValue;
use Modules\Catalog\Models\SharedProductOptionValueTranslation;
use Modules\Settings\Models\Currency;
use Modules\Settings\Models\Language;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreLanguage;

final readonly class ProductDetailReferenceCacheInvalidator
{
    /** @var list<class-string<Model>> */
    private const STORE_MODELS = [
        Brand::class,
        BrandTranslation::class,
        Category::class,
        CategoryTranslation::class,
        ProductType::class,
        ProductTypeTranslation::class,
        CustomFieldDefinition::class,
        CustomFieldDefinitionTranslation::class,
        CustomFieldOption::class,
        CustomFieldOptionTranslation::class,
        CustomObjectType::class,
        CustomObjectTypeTranslation::class,
        CustomObjectField::class,
        CustomObjectFieldTranslation::class,
        CustomObjectEntry::class,
        SharedProductOption::class,
        SharedProductOptionTranslation::class,
        SharedProductOptionValue::class,
        SharedProductOptionValueTranslation::class,
        ModifierLibraryCategory::class,
        ModifierLibraryCategoryTranslation::class,
        ModifierDefinition::class,
        ModifierTranslation::class,
        ModifierValue::class,
        ModifierValueTranslation::class,
        ModifierValidationRule::class,
        ModifierValidationRuleTranslation::class,
        ModifierPriceAdjustment::class,
        ModifierValuePriceAdjustment::class,
        Store::class,
        StoreLanguage::class,
    ];

    /** @var list<class-string<Model>> */
    private const GLOBAL_MODELS = [
        Currency::class,
        Language::class,
        FulfillmentType::class,
        FulfillmentTypeTranslation::class,
        PlatformTaxonomy::class,
        PlatformTaxonomyNode::class,
    ];

    public function __construct(private ProductDetailReferenceCache $cache) {}

    public function register(): void
    {
        if (! (bool) config('scalability.product_detail_reference_cache.enabled', false)) {
            return;
        }

        foreach (self::STORE_MODELS as $model) {
            $model::saved(function (Model $changed): void {
                $this->scheduleStoreInvalidation($changed);
            });
            $model::deleted(function (Model $changed): void {
                $this->scheduleStoreInvalidation($changed);
            });
        }
        foreach (self::GLOBAL_MODELS as $model) {
            $model::saved(function (): void {
                $this->scheduleGlobalInvalidation();
            });
            $model::deleted(function (): void {
                $this->scheduleGlobalInvalidation();
            });
        }
    }

    private function scheduleStoreInvalidation(Model $model): void
    {
        $storeId = $model instanceof Store
            ? (int) $model->getKey()
            : (int) $model->getAttribute('store_id');

        if ($storeId < 1) {
            $this->scheduleGlobalInvalidation();

            return;
        }

        DB::afterCommit(fn () => $this->cache->invalidateStore($storeId));
    }

    private function scheduleGlobalInvalidation(): void
    {
        DB::afterCommit(fn () => $this->cache->invalidateGlobal());
    }
}
