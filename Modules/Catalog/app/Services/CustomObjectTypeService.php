<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\CustomObjectType;

final readonly class CustomObjectTypeService
{
    public function __construct(private CustomObjectManagementService $management) {}

    /** @param array<string, mixed> $filters */
    public function listTypes(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->management->listTypes($user, $filters);
    }

    public function showType(User $user, string $publicId): CustomObjectType
    {
        return $this->management->showType($user, $publicId);
    }

    /** @param array<string, mixed> $input */
    public function createType(User $user, array $input): CustomObjectType
    {
        return $this->management->createType($user, $input);
    }

    /** @param array<string, mixed> $input */
    public function updateType(User $user, string $publicId, array $input): CustomObjectType
    {
        return $this->management->updateType($user, $publicId, $input);
    }

    public function archiveType(User $user, string $publicId): CustomObjectType
    {
        return $this->management->updateType($user, $publicId, ['status' => 'archived']);
    }

    /** @param list<array<string, mixed>> $translations */
    public function translateType(User $user, string $publicId, array $translations): CustomObjectType
    {
        return $this->management->updateType($user, $publicId, ['translations' => $translations]);
    }

    public function deleteType(User $user, string $publicId): void
    {
        $this->management->deleteType($user, $publicId);
    }
}
