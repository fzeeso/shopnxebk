<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Catalog\Services\ProductDetailSectionRegistry;

final class ProductDetailWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $sectionRegistry = app(ProductDetailSectionRegistry::class);
        $productRule = $this->isMethod('post') ? ['required', 'array'] : ['sometimes', 'array'];
        $reference = ['sometimes', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'];
        $publicIdOrReference = ['required', 'string', 'max:101', 'regex:/^(?:[0-9A-HJKMNP-TV-Z]{26}|@[A-Za-z0-9_.-]{1,100})$/i'];
        $optionalPublicIdOrReference = ['sometimes', 'nullable', 'string', 'max:101', 'regex:/^(?:[0-9A-HJKMNP-TV-Z]{26}|@[A-Za-z0-9_.-]{1,100})$/i'];

        $rules = [
            'expected_updated_at' => ['sometimes', 'nullable', 'date'],
            'product' => $productRule,
            'sections' => [
                'sometimes',
                'array:'.implode(',', [
                    ...ProductDetailSectionRegistry::BUILT_IN_SECTIONS,
                    ...$sectionRegistry->keys(),
                ]),
            ],

            'sections.images' => ['sometimes', 'array:upsert,delete'],
            'sections.images.upsert' => ['sometimes', 'array', 'list', 'max:100'],
            'sections.images.upsert.*' => ['required', 'array'],
            'sections.images.upsert.*.id' => ['sometimes', 'ulid', 'distinct'],
            'sections.images.upsert.*.variant_id' => $optionalPublicIdOrReference,
            'sections.images.delete' => ['sometimes', 'array', 'list', 'max:100'],
            'sections.images.delete.*' => ['required', 'ulid', 'distinct'],

            'sections.media' => ['sometimes', 'array:attach,detach,variant_attach,variant_detach,primary_media_id'],
            'sections.media.attach' => ['sometimes', 'array', 'list', 'max:100'],
            'sections.media.attach.*' => ['required', 'array'],
            'sections.media.attach.*.media_id' => ['required', 'ulid', 'distinct'],
            'sections.media.attach.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'sections.media.attach.*.is_primary' => ['sometimes', 'boolean'],
            'sections.media.detach' => ['sometimes', 'array', 'list', 'max:100'],
            'sections.media.detach.*' => ['required', 'ulid', 'distinct'],
            'sections.media.variant_attach' => ['sometimes', 'array', 'list', 'max:100'],
            'sections.media.variant_attach.*' => ['required', 'array'],
            'sections.media.variant_attach.*.variant_id' => $publicIdOrReference,
            'sections.media.variant_attach.*.media_id' => ['required', 'ulid'],
            'sections.media.variant_attach.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'sections.media.variant_detach' => ['sometimes', 'array', 'list', 'max:100'],
            'sections.media.variant_detach.*' => ['required', 'array'],
            'sections.media.variant_detach.*.variant_id' => ['required', 'ulid'],
            'sections.media.variant_detach.*.media_id' => ['required', 'ulid'],
            'sections.media.primary_media_id' => ['sometimes', 'ulid'],

            'sections.custom_fields' => ['sometimes', 'array:upsert,delete'],
            'sections.custom_fields.upsert' => ['sometimes', 'array', 'list', 'max:500'],
            'sections.custom_fields.upsert.*' => ['required', 'array'],
            'sections.custom_fields.upsert.*.definition_id' => ['required', 'ulid'],
            'sections.custom_fields.upsert.*.variant_id' => $optionalPublicIdOrReference,
            'sections.custom_fields.delete' => ['sometimes', 'array', 'list', 'max:500'],
            'sections.custom_fields.delete.*' => ['required', 'array'],
            'sections.custom_fields.delete.*.definition_id' => ['required', 'ulid'],
            'sections.custom_fields.delete.*.variant_id' => ['sometimes', 'nullable', 'ulid'],

            'sections.custom_objects' => ['sometimes', 'array:replace,clear'],
            'sections.custom_objects.replace' => ['sometimes', 'array', 'list', 'max:100'],
            'sections.custom_objects.replace.*' => ['required', 'array'],
            'sections.custom_objects.replace.*.definition_id' => ['required', 'ulid', 'distinct'],
            'sections.custom_objects.replace.*.entry_ids' => ['required', 'array', 'list', 'max:100'],
            'sections.custom_objects.replace.*.entry_ids.*' => ['required', 'ulid', 'distinct'],
            'sections.custom_objects.clear' => ['sometimes', 'array', 'list', 'max:100'],
            'sections.custom_objects.clear.*' => ['required', 'ulid', 'distinct'],

            'sections.options' => ['sometimes', 'array:upsert,delete,value_upsert,value_delete'],
            'sections.options.upsert' => ['sometimes', 'array', 'list', 'max:100'],
            'sections.options.upsert.*' => ['required', 'array'],
            'sections.options.upsert.*.id' => ['sometimes', 'ulid', 'distinct'],
            'sections.options.upsert.*.ref' => $reference,
            'sections.options.upsert.*.values.*.ref' => $reference,
            'sections.options.delete' => ['sometimes', 'array', 'list', 'max:100'],
            'sections.options.delete.*' => ['required', 'ulid', 'distinct'],
            'sections.options.value_upsert' => ['sometimes', 'array', 'list', 'max:500'],
            'sections.options.value_upsert.*' => ['required', 'array'],
            'sections.options.value_upsert.*.option_id' => $publicIdOrReference,
            'sections.options.value_upsert.*.id' => ['sometimes', 'ulid', 'distinct'],
            'sections.options.value_upsert.*.ref' => $reference,
            'sections.options.value_delete' => ['sometimes', 'array', 'list', 'max:500'],
            'sections.options.value_delete.*' => ['required', 'array'],
            'sections.options.value_delete.*.option_id' => ['required', 'ulid'],
            'sections.options.value_delete.*.id' => ['required', 'ulid'],

            'sections.variants' => ['sometimes', 'array:upsert,delete'],
            'sections.variants.upsert' => ['sometimes', 'array', 'list', 'max:250'],
            'sections.variants.upsert.*' => ['required', 'array'],
            'sections.variants.upsert.*.id' => ['sometimes', 'ulid', 'distinct'],
            'sections.variants.upsert.*.ref' => $reference,
            'sections.variants.upsert.*.option_value_ids' => ['sometimes', 'array', 'list', 'max:100'],
            'sections.variants.upsert.*.option_value_ids.*' => $publicIdOrReference,
            'sections.variants.delete' => ['sometimes', 'array', 'list', 'max:250'],
            'sections.variants.delete.*' => ['required', 'ulid', 'distinct'],

            'sections.shared_options' => ['sometimes', 'array:upsert,delete'],
            'sections.shared_options.upsert' => ['sometimes', 'array', 'list', 'max:100'],
            'sections.shared_options.upsert.*' => ['required', 'array'],
            'sections.shared_options.upsert.*.option_id' => ['required', 'ulid', 'distinct'],
            'sections.shared_options.delete' => ['sometimes', 'array', 'list', 'max:100'],
            'sections.shared_options.delete.*' => ['required', 'ulid', 'distinct'],

            'sections.modifier_groups' => ['sometimes', 'array:upsert,delete'],
            'sections.modifier_groups.upsert' => ['sometimes', 'array', 'list', 'max:100'],
            'sections.modifier_groups.upsert.*' => ['required', 'array'],
            'sections.modifier_groups.upsert.*.id' => ['sometimes', 'ulid', 'distinct'],
            'sections.modifier_groups.upsert.*.ref' => $reference,
            'sections.modifier_groups.delete' => ['sometimes', 'array', 'list', 'max:100'],
            'sections.modifier_groups.delete.*' => ['required', 'ulid', 'distinct'],

            'sections.modifiers' => ['sometimes', 'array:upsert,delete'],
            'sections.modifiers.upsert' => ['sometimes', 'array', 'list', 'max:250'],
            'sections.modifiers.upsert.*' => ['required', 'array'],
            'sections.modifiers.upsert.*.id' => ['sometimes', 'ulid', 'distinct'],
            'sections.modifiers.upsert.*.group_id' => $optionalPublicIdOrReference,
            'sections.modifiers.delete' => ['sometimes', 'array', 'list', 'max:250'],
            'sections.modifiers.delete.*' => ['required', 'ulid', 'distinct'],
        ];

        return [...$rules, ...$sectionRegistry->validationRules()];
    }
}
