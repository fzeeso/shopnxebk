<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateThemeLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['active', 'revoked', 'transferred', 'refunded', 'expired'])]];
    }
}
