<?php

declare(strict_types=1);

namespace Modules\Authentication\Services;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Authentication\Enums\AccessScope;
use Modules\Authentication\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PlatformUserAdminService
{
    public function __construct(
        private PlatformUserAccessService $access,
        private ScopedRoleAssignmentService $roleAssignments,
    ) {}

    /** @return LengthAwarePaginator<int, User> */
    public function list(User $actor, int $perPage = 25): LengthAwarePaginator
    {
        $this->access->ensureCanManage($actor);
        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            return User::query()
                ->with('roles')
                ->where('scope', AccessScope::Platform->value)
                ->orderBy('name')
                ->orderBy('email')
                ->paginate($perPage);
        } finally {
            setPermissionsTeamId($previousStoreId);
        }
    }

    /** @param array{name: string, email: string, password: string, roles: list<string>} $data */
    public function create(User $actor, array $data): User
    {
        $this->access->ensureCanManage($actor);

        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'scope' => AccessScope::Platform,
            ]);
            $this->roleAssignments->syncPlatformRoles($user, $data['roles']);

            DB::afterCommit(function () use ($user): void {
                event(new Registered($user));
                $user->sendEmailVerificationNotification();
            });

            return $user;
        });

        return $this->loadPlatformRole($user);
    }

    public function view(User $actor, string $publicId): User
    {
        $this->access->ensureCanManage($actor);
        $user = $this->findPlatformUser($publicId);

        return $this->loadPlatformRole($user);
    }

    /** @param array{name: string, email: string, password?: string|null, roles: list<string>} $data */
    public function update(User $actor, string $publicId, array $data): User
    {
        $this->access->ensureCanManage($actor);
        $user = $this->findPlatformUser($publicId);
        $emailChanged = $user->email !== $data['email'];

        DB::transaction(function () use ($data, $emailChanged, $user): void {
            $user->name = $data['name'];
            $user->email = $data['email'];
            if ($emailChanged) {
                $user->email_verified_at = null;
            }
            if (isset($data['password']) && $data['password'] !== '') {
                $user->password = $data['password'];
            }
            $user->save();
            $this->roleAssignments->syncPlatformRoles($user, $data['roles']);

            if ($emailChanged) {
                DB::afterCommit(function () use ($user): void {
                    $user->sendEmailVerificationNotification();
                });
            }
        });

        return $this->loadPlatformRole($user->refresh());
    }

    /** @return list<string> */
    public function roles(User $actor): array
    {
        $this->access->ensureCanManage($actor);

        return $this->roleAssignments->platformRoleNames();
    }

    private function loadPlatformRole(User $user): User
    {
        $this->loadPlatformRoles(new Collection([$user]));

        return $user;
    }

    private function findPlatformUser(string $publicId): User
    {
        $user = User::query()
            ->where('public_id', $publicId)
            ->where('scope', AccessScope::Platform->value)
            ->first();

        if ($user === null) {
            throw new NotFoundHttpException('Platform user not found.');
        }

        return $user;
    }

    /** @param Collection<int, User> $users @return Collection<int, User> */
    private function loadPlatformRoles(Collection $users): Collection
    {
        $previousStoreId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            $users->load('roles');
        } finally {
            setPermissionsTeamId($previousStoreId);
        }

        return $users;
    }
}
