<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\CustomObjectEntry;

final readonly class CustomObjectEntryService
{
    public function __construct(private CustomObjectManagementService $management) {}

    /** @param array<string, mixed> $filters */
    public function listEntries(User $user, string $typePublicId, array $filters = []): LengthAwarePaginator
    {
        return $this->management->listEntries($user, $typePublicId, $filters);
    }

    public function showEntry(User $user, string $publicId): CustomObjectEntry
    {
        return $this->management->showEntry($user, $publicId);
    }

    /** @param array<string, mixed> $input */
    public function createEntry(User $user, string $typePublicId, array $input): CustomObjectEntry
    {
        return $this->management->createEntry($user, $typePublicId, $input);
    }

    /** @param array<string, mixed> $input */
    public function updateEntry(User $user, string $publicId, array $input): CustomObjectEntry
    {
        return $this->management->updateEntry($user, $publicId, $input);
    }

    public function archiveEntry(User $user, string $publicId): CustomObjectEntry
    {
        return $this->management->updateEntry($user, $publicId, ['status' => 'archived']);
    }

    /** @param list<array<string, mixed>> $translations */
    public function translateEntry(User $user, string $publicId, array $translations): CustomObjectEntry
    {
        return $this->management->updateEntry($user, $publicId, ['translations' => $translations]);
    }

    /** @param list<array<string, mixed>> $values */
    public function saveValues(User $user, string $publicId, array $values): CustomObjectEntry
    {
        return $this->management->updateEntry($user, $publicId, ['values' => $values]);
    }

    public function deleteEntry(User $user, string $publicId): void
    {
        $this->management->deleteEntry($user, $publicId);
    }
}
