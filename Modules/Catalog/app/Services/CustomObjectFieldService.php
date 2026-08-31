<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Catalog\Models\CustomObjectField;

final readonly class CustomObjectFieldService
{
    public function __construct(private CustomObjectManagementService $management) {}

    /** @return Collection<int, CustomObjectField> */
    public function listFields(User $user, string $typePublicId): Collection
    {
        return $this->management->listFields($user, $typePublicId);
    }

    public function showField(User $user, string $publicId): CustomObjectField
    {
        return $this->management->showField($user, $publicId);
    }

    /** @param array<string, mixed> $input */
    public function createField(User $user, string $typePublicId, array $input): CustomObjectField
    {
        return $this->management->createField($user, $typePublicId, $input);
    }

    /** @param array<string, mixed> $input */
    public function updateField(User $user, string $publicId, array $input): CustomObjectField
    {
        return $this->management->updateField($user, $publicId, $input);
    }

    /**
     * Replace the complete field order for one Custom Object Type.
     *
     * @param  list<string>  $fieldPublicIds
     * @return Collection<int, CustomObjectField>
     */
    public function reorderFields(User $user, string $typePublicId, array $fieldPublicIds): Collection
    {
        $fields = $this->management->listFields($user, $typePublicId);
        $currentIds = $fields->pluck('public_id')->map(static fn (mixed $id): string => (string) $id)->all();

        if ($fieldPublicIds === []
            || count($fieldPublicIds) !== count(array_unique($fieldPublicIds))
            || count($fieldPublicIds) !== count($currentIds)
            || array_diff($fieldPublicIds, $currentIds) !== []
            || array_diff($currentIds, $fieldPublicIds) !== []) {
            throw ValidationException::withMessages([
                'field_ids' => ['Field order must contain each current field exactly once.'],
            ]);
        }

        return DB::transaction(function () use ($user, $typePublicId, $fieldPublicIds): Collection {
            foreach ($fieldPublicIds as $sortOrder => $fieldPublicId) {
                $this->management->updateField($user, $fieldPublicId, ['sort_order' => $sortOrder]);
            }

            return $this->management->listFields($user, $typePublicId);
        });
    }

    public function deleteField(User $user, string $publicId): void
    {
        $this->management->deleteField($user, $publicId);
    }
}
