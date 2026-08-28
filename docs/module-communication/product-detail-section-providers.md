# Product Detail section-provider contract

## Purpose

Product Detail is a Store Admin application façade. Catalog owns the façade and
its built-in Product sections, but it must not become the owner of future
Discounts, Inventory, Shipping, Search, or other module state. An owning module
joins the façade through `ProductDetailSectionProvider` rather than making the
controller depend on that module or allowing Catalog to write its tables.

This contract provides automatic composition after one explicit registration:

- read-manifest validation;
- write-section validation;
- bootstrap and existing-Product reads;
- bounded `section_meta`;
- `capabilities.writable_sections` discovery;
- transactional dirty-section saves; and
- a shared request-local reference map.

Database discovery is intentionally forbidden. Creating a model or table does
not expose it through Product Detail.

## Contract

Providers implement:

```php
interface ProductDetailSectionProvider
{
    public function key(): string;

    public function priority(): int;

    public function rules(): array;

    public function bootstrap(
        User $user,
        Store $store,
        int $limit,
    ): ProductDetailSectionPayload;

    public function read(
        User $user,
        Store $store,
        Product $product,
        int $limit,
    ): ProductDetailSectionPayload;

    public function save(
        User $user,
        Store $store,
        Product $product,
        array $command,
        ProductDetailReferenceMap $references,
    ): void;
}
```

The implementation belongs in the owning module. Its service provider tags the
class during `register()`:

```php
$this->app->tag(
    [ProductDiscountDetailSection::class],
    ProductDetailSectionProvider::class,
);
```

`ProductDetailSectionRegistry` resolves tagged providers lazily, rejects
invalid/reserved/duplicate keys, and sorts them by `priority()` then `key()`.
Catalog built-ins always save before extension providers. Among providers,
lower priorities run first.

## Section key

`key()` is the stable public API name. It must:

- start with a lowercase letter;
- contain only lowercase letters, numbers, and underscores;
- remain stable after release;
- not be `product`; and
- not collide with `images`, `media`, `custom_fields`, `options`, `variants`,
  `shared_options`, `modifier_groups`, or `modifiers`.

Changing a released key is an API breaking change. A table name is not a good
reason to choose a key; use the public domain concept.

## Validation rules

`rules()` returns rules relative to `sections.<key>`. The empty key optionally
replaces the default `sometimes|array` root rule.

```php
public function rules(): array
{
    return [
        '' => ['sometimes', 'array:upsert,delete'],
        'upsert' => ['sometimes', 'array', 'list', 'max:100'],
        'upsert.*' => ['required', 'array'],
        'upsert.*.id' => ['sometimes', 'ulid', 'distinct'],
        'upsert.*.ref' => [
            'sometimes', 'string', 'max:100',
            'regex:/^[A-Za-z0-9_.-]+$/',
        ],
        'delete' => ['sometimes', 'array', 'list', 'max:100'],
        'delete.*' => ['required', 'ulid', 'distinct'],
    ];
}
```

The registry prefixes these rules automatically. A provider must keep its root
optional so partial Product Detail writes remain backward compatible. Request
validation protects shape and size; the owning domain service remains the
authority for permissions, Store ownership, state transitions, and business
invariants.

## Bootstrap and read

`bootstrap()` supplies new-Product section data. It should return the minimum
bounded information needed to render or initialize that module's Product form.
`read()` supplies current state for an existing Store-scoped Product. Both
return `ProductDetailSectionPayload`:

```php
return new ProductDetailSectionPayload(
    data: $publicItems,
    total: $total,
    returned: count($publicItems),
);
```

The façade calculates `limit` and `truncated` metadata from the payload.
Providers are called only when their key is selected by the read manifest or
when the manifest is omitted. Therefore an unselected provider must have no
query, service-call, or side-effect cost.

Read requirements:

- return serialization-ready public data, DTOs, or API resources;
- never expose `store_id`, internal bigint keys, secrets, or provider tokens;
- scope every query to the supplied Store and Product;
- use the supplied limit and report accurate counts;
- avoid N+1 queries and unbounded relation loading;
- perform no writes or external side effects; and
- use granular endpoints when a truncated collection needs continuation.

## Save

`save()` runs only when the write request contains that provider's section.
It runs inside the Product Detail outer PostgreSQL transaction after Catalog
built-ins. Throwing any exception rolls back Product core, built-ins, and every
provider that already ran.

The method must:

- recheck the user's module-specific permission when it is stronger than
  `manage products`;
- delegate to the owning module's domain/application service;
- resolve every Product-owned record within the supplied Store;
- treat the command as dirty-section intent, not full replacement unless the
  public contract explicitly says so;
- remain idempotent where the section command semantics allow;
- avoid HTTP, AI, object-storage, email, or other remote calls in the
  transaction; and
- record after-commit event/outbox work for external effects.

Do not import another module's Eloquent model to bypass its service. Do not
commit, roll back, or open an independent connection from the provider.

## Request-local references

`ProductDetailReferenceMap` is shared by Catalog and all providers for one
command. Catalog registers new Option, Option Value, Variant, and Modifier-group
references before providers run. A provider may resolve them:

```php
$variantId = $references->resolve('variants', '@small-variant');
```

It may also register a namespace for later providers or the response:

```php
$references->register(
    'discount_rules',
    $item['ref'] ?? null,
    (string) $rule->public_id,
);
```

Reference namespaces are public response keys and should use stable snake_case
domain names. References are request-local only. Public ULIDs must be persisted
or returned for future requests.

## Capabilities and client rollout

Registration immediately adds the key to
`capabilities.writable_sections`, accepted read manifests, and accepted write
sections. That means backend registration and frontend release planning must be
coordinated:

- older clients must ignore unknown response section keys safely;
- clients should use capabilities for discovery, not assume every discovered
  section has a UI;
- new frontend UI should be feature-gated if backend and frontend deploy
  independently; and
- removing a registered provider is a breaking API/behavior change for clients
  using that section.

## Example provider outline

```php
final readonly class ProductDiscountDetailSection
    implements ProductDetailSectionProvider
{
    public function __construct(
        private ProductDiscountService $discounts,
    ) {}

    public function key(): string
    {
        return 'discounts';
    }

    public function priority(): int
    {
        return 100;
    }

    public function rules(): array
    {
        return [
            '' => ['sometimes', 'array:upsert,delete'],
            'upsert' => ['sometimes', 'array', 'list', 'max:100'],
            'upsert.*' => ['required', 'array'],
            'delete' => ['sometimes', 'array', 'list', 'max:100'],
            'delete.*' => ['required', 'ulid', 'distinct'],
        ];
    }

    public function bootstrap(
        User $user,
        Store $store,
        int $limit,
    ): ProductDetailSectionPayload {
        return ProductDetailSectionPayload::empty();
    }

    public function read(
        User $user,
        Store $store,
        Product $product,
        int $limit,
    ): ProductDetailSectionPayload {
        return $this->discounts->productDetail($user, $store, $product, $limit);
    }

    public function save(
        User $user,
        Store $store,
        Product $product,
        array $command,
        ProductDetailReferenceMap $references,
    ): void {
        $this->discounts->saveProductDetail(
            $user,
            $store,
            $product,
            $command,
            $references,
        );
    }
}
```

This is an outline, not a Discount domain implementation. The owning module
defines its real request fields, policies, persistence, events, and resources.

## Required tests

Every new provider should cover:

1. valid registration and deterministic priority;
2. relative rule prefixing and invalid command rejection;
3. active-Store/Product isolation;
4. membership and owning-module write permissions;
5. bootstrap/read limits, totals, truncation, and public serialization;
6. selective reads do not invoke the unselected provider;
7. dirty writes do not alter omitted sections;
8. provider failure rolls back the aggregate transaction;
9. core `@ref` resolution and provider reference registration when used;
10. no remote work runs before commit; and
11. OpenAPI, API manual, module document, context, and development-log updates.

Run formatting, static analysis, documentation generation/checks, safe unit
coverage, and the relevant PostgreSQL-backed tests when repository data-safety
rules permit them.

## Review checklist

- [ ] The key is unique, stable, snake_case, and not reserved.
- [ ] Rules are bounded and keep the root optional.
- [ ] Reads and writes delegate to the owning module service.
- [ ] Store and Product scope are explicit in every query.
- [ ] Only public identifiers and safe JSON leave the provider.
- [ ] Read counts and truncation are accurate.
- [ ] Unselected reads perform no provider work.
- [ ] Save performs no remote I/O inside the transaction.
- [ ] External effects are recorded for after-commit processing.
- [ ] Client compatibility and feature rollout are documented.
- [ ] Tests and all owning documentation are updated.

See the [Product Detail Store Admin guide](../product-detail-guide.md),
[developer guide](../developer-guide.md), [API manual](../api-manual.md), and
[Catalog module](../modules/catalog.md).
