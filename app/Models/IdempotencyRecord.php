<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $request_fingerprint
 * @property int $response_status
 * @property array<string, list<string>>|null $response_headers
 * @property string $response_body_ciphertext
 * @property string $response_body_sha256
 * @property string $original_request_id
 * @property CarbonImmutable|null $expires_at
 */
final class IdempotencyRecord extends Model
{
    /** @var list<string> */
    protected $hidden = [
        'scope_hash',
        'key_hash',
        'request_fingerprint',
        'response_headers',
        'response_body_ciphertext',
        'response_body_sha256',
    ];

    /** @var list<string> */
    protected $fillable = [
        'scope_hash',
        'key_hash',
        'fingerprint_version',
        'request_fingerprint',
        'actor_id',
        'store_id',
        'route_name',
        'http_method',
        'response_status',
        'response_headers',
        'response_body_ciphertext',
        'response_body_sha256',
        'original_request_id',
        'completed_at',
        'expires_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'response_headers' => 'array',
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
