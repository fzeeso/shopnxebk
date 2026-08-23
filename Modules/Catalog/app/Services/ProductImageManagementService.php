<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Support\Translations\StoreTranslationLanguages;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductImage;
use Modules\Catalog\Models\ProductVariant;
use Modules\Stores\Contracts\StoreContext;
use Modules\Stores\Models\Store;

final readonly class ProductImageManagementService
{
    public function __construct(
        private StoreContext $context,
        private CatalogAccessService $access,
        private StoreTranslationLanguages $languages,
    ) {}

    public function list(User $user, string $productPublicId, int $page, int $perPage): LengthAwarePaginator
    {
        $store = $this->store($user, false);
        $product = $this->product($store, $productPublicId);

        return ProductImage::query()
            ->where('store_id', $store->getKey())
            ->where('product_id', $product->getKey())
            ->with(['product', 'variant', 'translations'])
            ->orderBy('position')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function show(User $user, string $productPublicId, string $imagePublicId): ProductImage
    {
        $store = $this->store($user, false);
        $product = $this->product($store, $productPublicId);

        return $this->image($store, $product, $imagePublicId)
            ->load(['product', 'variant', 'translations']);
    }

    /** @param array<string, mixed> $input */
    public function create(User $user, string $productPublicId, array $input): ProductImage
    {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $data = $this->validate($input, true);

        return DB::transaction(function () use ($store, $product, $data): ProductImage {
            $image = ProductImage::query()->create([
                'store_id' => $store->getKey(),
                'product_id' => $product->getKey(),
                'variant_id' => $this->variantId($store, $product, $data['variantId'] ?? null),
                'url' => $data['url'],
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null,
                'position' => $data['position'] ?? 0,
            ]);
            if (isset($data['translations'])) {
                $this->syncTranslations($store, $image, $data['translations']);
            }

            return $image->load(['product', 'variant', 'translations']);
        });
    }

    /** @param array<string, mixed> $input */
    public function update(
        User $user,
        string $productPublicId,
        string $imagePublicId,
        array $input,
    ): ProductImage {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $image = $this->image($store, $product, $imagePublicId);
        $data = $this->validate($input, false);
        if ($data === []) {
            throw ValidationException::withMessages(['input' => ['At least one field must be supplied.']]);
        }

        return DB::transaction(function () use ($store, $product, $image, $data): ProductImage {
            $attributes = [];
            foreach (['url', 'width', 'height', 'position'] as $attribute) {
                if (array_key_exists($attribute, $data)) {
                    $attributes[$attribute] = $data[$attribute];
                }
            }
            if (array_key_exists('variantId', $data)) {
                $attributes['variant_id'] = $this->variantId($store, $product, $data['variantId']);
            }
            $image->fill($attributes)->save();
            if (isset($data['translations'])) {
                $this->syncTranslations($store, $image, $data['translations']);
            }

            return $image->refresh()->load(['product', 'variant', 'translations']);
        });
    }

    public function delete(User $user, string $productPublicId, string $imagePublicId): void
    {
        $store = $this->store($user, true);
        $product = $this->product($store, $productPublicId);
        $this->image($store, $product, $imagePublicId)->delete();
    }

    private function store(User $user, bool $manage): Store
    {
        $store = $this->context->require();
        $manage
            ? $this->access->ensureCanManageProducts($user, $store)
            : $this->access->ensureCanView($user, $store);

        return $store;
    }

    private function product(Store $store, string $publicId): Product
    {
        return Product::query()
            ->where('store_id', $store->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function image(Store $store, Product $product, string $publicId): ProductImage
    {
        return ProductImage::query()
            ->where('store_id', $store->getKey())
            ->where('product_id', $product->getKey())
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function variantId(Store $store, Product $product, mixed $publicId): ?int
    {
        if ($publicId === null) {
            return null;
        }

        return (int) ProductVariant::query()
            ->where('store_id', $store->getKey())
            ->where('product_id', $product->getKey())
            ->where('public_id', (string) $publicId)
            ->firstOrFail()
            ->getKey();
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function validate(array $input, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return Validator::make($input, [
            'variantId' => ['sometimes', 'nullable', 'ulid'],
            'url' => [$required, 'string', 'max:500', 'regex:/^(?:\/|https?:\/\/)/i'],
            'width' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'height' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'translations' => ['sometimes', 'array', 'min:1'],
            'translations.*.locale' => ['required_with:translations', 'string', 'max:35'],
            'translations.*.altText' => ['sometimes', 'nullable', 'string', 'max:255'],
            'translations.*.lockIt' => ['sometimes', 'boolean'],
        ])->validate();
    }

    /** @param list<array<string, mixed>> $translations */
    private function syncTranslations(Store $store, ProductImage $image, array $translations): void
    {
        $activeLocales = $this->languages->activeFor($store)
            ->pluck('locale')
            ->map(fn (mixed $locale): string => $this->localeKey((string) $locale))
            ->all();
        if ($activeLocales === []) {
            $activeLocales = [$this->localeKey((string) ($store->language_code ?: config('app.locale', 'en')))];
        }
        $activeLocaleKeys = array_fill_keys($activeLocales, true);
        $existing = DB::table('product_image_translations')
            ->where('image_id', $image->getKey())
            ->get(['locale', 'lock_it'])
            ->keyBy(fn (object $row): string => $this->localeKey((string) $row->locale));
        $seen = [];
        $rows = [];
        $now = now();

        foreach ($translations as $index => $translation) {
            $locale = str_replace('-', '_', trim((string) ($translation['locale'] ?? '')));
            $localeKey = $this->localeKey($locale);
            if ($locale === '' || isset($seen[$localeKey])) {
                throw ValidationException::withMessages([
                    'translations' => ['Each translation must use one unique locale.'],
                ]);
            }
            if (! isset($activeLocaleKeys[$localeKey])) {
                throw ValidationException::withMessages([
                    "translations.{$index}.locale" => ["The locale [{$locale}] is not active for this Store."],
                ]);
            }
            $seen[$localeKey] = true;
            $rows[] = [
                'store_id' => $store->getKey(),
                'image_id' => $image->getKey(),
                'locale' => $locale,
                'alt_text' => $translation['altText'] ?? null,
                'lock_it' => Arr::has($translation, 'lockIt')
                    ? (bool) $translation['lockIt']
                    : (bool) ($existing->get($localeKey)?->lock_it ?? false),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('product_image_translations')->upsert(
            $rows,
            ['image_id', 'locale'],
            ['alt_text', 'lock_it', 'updated_at'],
        );
    }

    private function localeKey(string $locale): string
    {
        return strtolower(str_replace('-', '_', trim($locale)));
    }
}
