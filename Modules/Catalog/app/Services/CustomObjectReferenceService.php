<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Collection as CatalogCollection;
use Modules\Catalog\Models\CustomFieldDefinition;
use Modules\Catalog\Models\CustomObjectEntry;
use Modules\Catalog\Models\CustomObjectReference;
use Modules\Catalog\Models\Product;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Page;
use Modules\Stores\Models\Store;

final readonly class CustomObjectReferenceService
{
    /** @var array<string, class-string<Model>> */
    private const SOURCE_MODELS = [
        'product' => Product::class,
        'collection' => CatalogCollection::class,
        'category' => Category::class,
        'brand' => Brand::class,
        'page' => Page::class,
    ];

    public function __construct(
        private StoreContext $context,
        private CatalogAccessService $access,
    ) {}

    /** @return Collection<int, CustomObjectReference> */
    public function list(
        User $user,
        string $sourceType,
        string $sourcePublicId,
        ?string $definitionPublicId = null,
    ): Collection {
        $store = $this->store($user, false);
        $source = $this->source($store, $sourceType, $sourcePublicId);
        $query = CustomObjectReference::query()
            ->where('store_id', $store->getKey())
            ->where('source_type', $sourceType)
            ->where('source_id', $source->getKey())
            ->with($this->relations())
            ->orderBy('custom_field_definition_id')
            ->orderBy('sort_order')
            ->orderBy('id');
        if ($definitionPublicId !== null) {
            $definition = $this->definition($store, $definitionPublicId);
            $query->where('custom_field_definition_id', $definition->getKey());
        }

        return $query->get();
    }

    /**
     * @param  list<string>  $entryPublicIds
     * @return Collection<int, CustomObjectReference>
     */
    public function replace(
        User $user,
        string $sourceType,
        string $sourcePublicId,
        string $definitionPublicId,
        array $entryPublicIds,
    ): Collection {
        $store = $this->store($user, true);
        $source = $this->source($store, $sourceType, $sourcePublicId);
        $definition = $this->definition($store, $definitionPublicId);
        $this->ensureDefinitionApplies($definition, $sourceType, $source);
        if (! in_array($definition->field_type, ['object_reference', 'multi_object_reference'], true)) {
            throw ValidationException::withMessages([
                'definition_id' => ['The Custom Field must use object_reference or multi_object_reference.'],
            ]);
        }
        if ($definition->field_type === 'object_reference' && count($entryPublicIds) > 1) {
            throw ValidationException::withMessages(['entry_ids' => ['A single object reference accepts at most one entry.']]);
        }
        if (count($entryPublicIds) > 100 || count(array_unique($entryPublicIds)) !== count($entryPublicIds)) {
            throw ValidationException::withMessages(['entry_ids' => ['Entry IDs must be distinct and contain at most 100 items.']]);
        }
        $entries = CustomObjectEntry::query()
            ->where('store_id', $store->getKey())
            ->where('custom_object_type_id', $definition->reference_object_type_id)
            ->where('status', 'active')
            ->whereIn('public_id', $entryPublicIds)
            ->get()
            ->keyBy('public_id');
        if ($entries->count() !== count($entryPublicIds)) {
            throw ValidationException::withMessages([
                'entry_ids' => ['Every entry must be active, Store-owned, and belong to the field reference type.'],
            ]);
        }

        return DB::transaction(function () use (
            $store,
            $sourceType,
            $source,
            $definition,
            $entryPublicIds,
            $entries,
        ): Collection {
            CustomObjectReference::query()
                ->where('store_id', $store->getKey())
                ->where('source_type', $sourceType)
                ->where('source_id', $source->getKey())
                ->where('custom_field_definition_id', $definition->getKey())
                ->delete();

            foreach ($entryPublicIds as $position => $publicId) {
                /** @var CustomObjectEntry $entry */
                $entry = $entries[$publicId];
                CustomObjectReference::query()->create([
                    'store_id' => $store->getKey(),
                    'source_type' => $sourceType,
                    'source_id' => $source->getKey(),
                    'custom_field_definition_id' => $definition->getKey(),
                    'custom_object_type_id' => $definition->reference_object_type_id,
                    'custom_object_entry_id' => $entry->getKey(),
                    'sort_order' => $position,
                ]);
            }

            return CustomObjectReference::query()
                ->where('store_id', $store->getKey())
                ->where('source_type', $sourceType)
                ->where('source_id', $source->getKey())
                ->where('custom_field_definition_id', $definition->getKey())
                ->with($this->relations())
                ->orderBy('sort_order')->orderBy('id')->get();
        });
    }

    public function clear(
        User $user,
        string $sourceType,
        string $sourcePublicId,
        string $definitionPublicId,
    ): void {
        $store = $this->store($user, true);
        $source = $this->source($store, $sourceType, $sourcePublicId);
        $definition = $this->definition($store, $definitionPublicId);
        CustomObjectReference::query()
            ->where('store_id', $store->getKey())
            ->where('source_type', $sourceType)
            ->where('source_id', $source->getKey())
            ->where('custom_field_definition_id', $definition->getKey())
            ->delete();
    }

    private function store(User $user, bool $manage): Store
    {
        $store = $this->context->require();
        $manage ? $this->access->ensureCanManageProducts($user, $store) : $this->access->ensureCanView($user, $store);

        return $store;
    }

    private function source(Store $store, string $sourceType, string $publicId): Model
    {
        $model = self::SOURCE_MODELS[$sourceType] ?? null;
        if ($model === null) {
            throw ValidationException::withMessages([
                'source_type' => ['Supported source types: '.implode(', ', array_keys(self::SOURCE_MODELS)).'.'],
            ]);
        }

        return $model::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function definition(Store $store, string $publicId): CustomFieldDefinition
    {
        return CustomFieldDefinition::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function ensureDefinitionApplies(
        CustomFieldDefinition $definition,
        string $sourceType,
        Model $source,
    ): void {
        if ($definition->product_type === null) {
            return;
        }
        if ($sourceType !== 'product' || ! $source instanceof Product) {
            throw ValidationException::withMessages([
                'definition_id' => ['Product-type-scoped Custom Fields cannot be attached to this source type.'],
            ]);
        }
        $productTypeCode = $source->productType()->value('code');
        if ($productTypeCode !== $definition->product_type) {
            throw ValidationException::withMessages([
                'definition_id' => ['This Custom Field does not apply to the Product type.'],
            ]);
        }
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'definition.translations',
            'definition.referenceObjectType.translations',
            'type.translations',
            'entry.translations',
        ];
    }
}
