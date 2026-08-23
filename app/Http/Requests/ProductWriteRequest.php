<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProductWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $decimal = ['sometimes', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999999.9999'];
        $flag = ['sometimes', 'integer', 'between:0,1'];
        $unsignedInteger = ['sometimes', 'integer', 'min:0', 'max:2147483647'];

        return [
            'brand_id' => ['sometimes', 'nullable', 'ulid'],
            'platform_taxonomy_node_id' => ['sometimes', 'nullable', 'ulid'],
            'vendor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product_type_id' => ['sometimes', 'nullable', 'ulid'],
            'fulfillment_type' => ['sometimes', 'in:physical,digital,software,service'],
            'track_inventory' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:draft,active,archived'],
            'category_ids' => ['sometimes', 'array', 'max:100'],
            'category_ids.*' => ['required', 'ulid', 'distinct'],
            'primary_category_id' => ['sometimes', 'nullable', 'ulid'],
            'sku' => ['sometimes', 'string', 'max:250'],
            'downloadfile' => ['sometimes', 'string', 'max:250'],
            'availability' => ['sometimes', 'string', 'max:250'],
            'price' => $decimal,
            'costprice' => $decimal,
            'retailprice' => $decimal,
            'msrpprice' => $decimal,
            'saleprice' => $decimal,
            'calculatedprice' => $decimal,
            'sortorder' => ['sometimes', 'integer'],
            'is_featured' => $flag,
            'currentinv' => ['sometimes', 'integer'],
            'lowinv' => ['sometimes', 'integer'],
            'warranty' => ['sometimes', 'nullable', 'string'],
            'weight' => $decimal,
            'width' => $decimal,
            'height' => $decimal,
            'proddepth' => $decimal,
            'fixedshippingcost' => $decimal,
            'freeshipping' => $flag,
            'ratingtotal' => $unsignedInteger,
            'numratings' => $unsignedInteger,
            'numsold' => $unsignedInteger,
            'numviews' => $unsignedInteger,
            'allowpurchases' => $flag,
            'hideprice' => $flag,
            'is_login_for_price' => $flag,
            'is_global_search' => $flag,
            'condition' => ['sometimes', 'in:New,Used,Refurbished'],
            'showcondition' => $flag,
            'pre_order' => $flag,
            'releasedate' => ['sometimes', 'nullable', 'date'],
            'releasedateremove' => $flag,
            'minqty' => $unsignedInteger,
            'maxqty' => $unsignedInteger,
            'tax_class_id' => $unsignedInteger,
            'show_related_product' => $unsignedInteger,
            'prodpoints' => $unsignedInteger,
            'reviews_on' => $flag,
            'upc' => ['sometimes', 'nullable', 'string', 'max:32'],
            'hs_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'gtin' => ['sometimes', 'nullable', 'string', 'max:32'],
            'mpn' => ['sometimes', 'nullable', 'string', 'max:32'],
            'bpn' => ['sometimes', 'nullable', 'string', 'max:32'],
            'translations' => [$required, 'array', 'min:1'],
            'translations.*.locale' => ['required_with:translations', 'string', 'max:35', 'regex:/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/'],
            'translations.*.title' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.slug' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.description' => ['sometimes', 'nullable', 'string'],
            'translations.*.seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'translations.*.seo_description' => ['sometimes', 'nullable', 'string'],
            'translations.*.lock_it' => ['sometimes', 'boolean'],
        ];
    }
}
