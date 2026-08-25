<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaAiOperation: string
{
    case GenerateAltText = 'generate_alt_text';
    case RemoveBackground = 'remove_background';
    case EnhanceImage = 'enhance_image';
    case GenerateAttributes = 'generate_attributes';
    case GenerateTags = 'generate_tags';
    case GenerateSeoFilename = 'generate_seo_filename';

    public function createsMedia(): bool
    {
        return in_array($this, [self::RemoveBackground, self::EnhanceImage], true);
    }
}
