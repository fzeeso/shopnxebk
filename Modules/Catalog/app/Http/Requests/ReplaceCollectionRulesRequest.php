<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReplaceCollectionRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'rules' => ['present', 'array', 'list', 'max:100'],
            'rules.*.field' => ['required', 'string', 'max:50'],
            'rules.*.operator' => ['required', 'string', 'max:20'],
            'rules.*.value' => ['required', 'string', 'max:255'],
            'rules.*.position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
