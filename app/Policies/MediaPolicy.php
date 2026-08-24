<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Media;
use App\Services\Media\MediaAccessService;
use Modules\Authentication\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class MediaPolicy
{
    public function __construct(private MediaAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->allowed(fn () => $this->access->view($user));
    }

    public function view(User $user, Media $media): bool
    {
        return $this->allowed(fn () => $this->access->view($user)->getKey() === $media->store_id);
    }

    public function create(User $user): bool
    {
        return $this->allowed(fn () => $this->access->manage($user));
    }

    public function update(User $user, Media $media): bool
    {
        return $this->allowed(fn () => $this->access->manage($user)->getKey() === $media->store_id);
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->update($user, $media);
    }

    private function allowed(callable $callback): bool
    {
        try {
            return (bool) $callback();
        } catch (AccessDeniedHttpException) {
            return false;
        }
    }
}
