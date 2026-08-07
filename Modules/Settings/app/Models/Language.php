<?php

declare(strict_types=1);

namespace Modules\Settings\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Settings\Enums\LanguageDirection;

#[Fillable(['name', 'native_name', 'locale', 'lang_icon', 'lang_image', 'direction', 'is_active'])]
final class Language extends Model
{
    use HasPublicId;

    protected function casts(): array
    {
        return [
            'direction' => LanguageDirection::class,
            'is_active' => 'boolean',
        ];
    }

    public function langIconUrl(): string
    {
        $reference = trim((string) ($this->getAttributes()['lang_icon'] ?? ''));

        if ($reference === '') {
            $reference = '/assets/languages/flags/generic.svg';
        }

        return $this->assetUrl($reference);
    }

    public function langImageUrl(): string
    {
        $reference = trim((string) ($this->getAttributes()['lang_image'] ?? ''));

        if ($reference === '') {
            return $this->langIconUrl();
        }

        return $this->assetUrl($reference);
    }

    private function assetUrl(string $reference): string
    {
        if (Str::startsWith($reference, ['http://', 'https://'])) {
            return $reference;
        }

        return url('/'.ltrim($reference, '/'));
    }
}
