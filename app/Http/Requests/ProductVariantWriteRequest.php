<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProductVariantWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $nullableUnsigned = ['sometimes', 'nullable', 'integer', 'min:0'];
        $nullableDimension = ['sometimes', 'nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:99999999.9999'];

        return [
            'sku' => ['sometimes', 'nullable', 'string', 'max:100'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:100'],
            'price_amount_minor' => [$required, 'integer', 'min:0'],
            'compare_at_price_amount_minor' => $nullableUnsigned,
            'msrp_amount_minor' => $nullableUnsigned,
            'cost_per_item_amount_minor' => $nullableUnsigned,
            'currency_code' => [
                $required,
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                Rule::exists('currencies', 'code')->where('is_active', true),
            ],
            'inventory_qty' => ['sometimes', 'integer'],
            'inventory_policy' => ['sometimes', 'in:deny,continue'],
            'weight_grams' => $nullableUnsigned,
            'height' => $nullableDimension,
            'width' => $nullableDimension,
            'depth' => $nullableDimension,
            'dimension_unit' => ['sometimes', 'string', 'max:10'],
            'requires_shipping' => ['sometimes', 'boolean'],
            'taxable' => ['sometimes', 'boolean'],
            'call_for_price' => ['sometimes', 'boolean'],
            'image_id' => ['sometimes', 'nullable', 'ulid'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'option_value_ids' => ['sometimes', 'array', 'list', 'max:100'],
            'option_value_ids.*' => ['required', 'ulid', 'distinct'],
            'translations' => ['sometimes', 'array', 'list', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:35'],
            'translations.*.title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
        ];
    }
}
