<?php

declare(strict_types=1);

namespace Modules\Themes\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Models\User;
use Modules\Stores\Models\Store;
use Modules\Themes\Enums\ThemeStatus;
use Modules\Themes\Enums\ThemeVersionStatus;
use Modules\Themes\Models\Theme;
use Modules\Themes\Models\ThemeLicense;
use Modules\Themes\Models\ThemeSubmission;
use Modules\Themes\Models\ThemeVersion;

final readonly class ThemeReleaseAdminService
{
    public function __construct(private ThemeAccessService $access) {}

    /** @param array<string, mixed> $data */
    public function addVersion(User $user, Theme $theme, array $data): ThemeVersion
    {
        $this->access->ensureCanManageMarketplace($user);

        return DB::transaction(fn (): ThemeVersion => ThemeVersion::query()->create([
            ...$data,
            'theme_id' => $theme->getKey(),
            'status' => ThemeVersionStatus::Uploaded,
            'engine_version' => $data['engine_version'] ?? config('themes.engine_version', 'shopnxe-theme-v1'),
            'settings_schema' => $data['settings_schema'] ?? [],
            'validation_report' => $data['validation_report'] ?? [],
            'uploaded_by_user_id' => $user->getKey(),
        ])->load(['uploader', 'approver', 'submissions']));
    }

    public function submit(User $user, ThemeVersion $version): ThemeSubmission
    {
        $this->access->ensureCanManageMarketplace($user);
        if (! in_array($version->statusValue(), ['uploaded', 'validation_failed', 'ready_for_review'], true)) {
            throw ValidationException::withMessages(['version' => ['Only an uploaded or corrected version can be submitted.']]);
        }

        return DB::transaction(function () use ($user, $version): ThemeSubmission {
            $number = ((int) $version->submissions()->max('submission_number')) + 1;
            $submission = $version->submissions()->create([
                'submission_number' => $number,
                'status' => 'submitted',
                'submitted_by_user_id' => $user->getKey(),
                'automated_results' => $version->validation_report,
                'submitted_at' => now(),
            ]);
            $version->forceFill(['status' => ThemeVersionStatus::ReadyForReview])->save();
            $version->theme()->update(['status' => ThemeStatus::PendingReview]);

            return $submission->load(['submitter', 'reviewer']);
        });
    }

    /** @param array<string, mixed> $data */
    public function review(User $user, ThemeSubmission $submission, array $data): ThemeSubmission
    {
        $this->access->ensureCanManageMarketplace($user);
        if (! in_array($submission->status, ['submitted', 'automated_review', 'manual_review'], true)) {
            throw ValidationException::withMessages(['submission' => ['This submission already has a final decision.']]);
        }

        return DB::transaction(function () use ($data, $submission, $user): ThemeSubmission {
            $decision = (string) $data['decision'];
            $submission->forceFill([
                'status' => $decision,
                'assigned_reviewer_user_id' => $user->getKey(),
                'review_notes' => $data['review_notes'] ?? null,
                'rejection_codes' => $data['rejection_codes'] ?? [],
                'review_started_at' => $submission->review_started_at ?? now(),
                'decided_at' => now(),
            ])->save();

            $version = $submission->themeVersion;
            $version->forceFill($decision === 'approved' ? [
                'status' => ThemeVersionStatus::Approved,
                'approved_by_user_id' => $user->getKey(),
                'approved_at' => now(),
            ] : [
                'status' => $decision === 'rejected' ? ThemeVersionStatus::Blocked : ThemeVersionStatus::ValidationFailed,
                'approved_by_user_id' => null,
                'approved_at' => null,
            ])->save();
            $version->theme()->update(['status' => $decision === 'approved' ? ThemeStatus::Approved : ($decision === 'rejected' ? ThemeStatus::Rejected : ThemeStatus::Draft)]);

            return $submission->refresh()->load(['submitter', 'reviewer']);
        });
    }

    public function publish(User $user, ThemeVersion $version): Theme
    {
        $this->access->ensureCanManageMarketplace($user);
        if ($version->statusValue() !== ThemeVersionStatus::Approved->value) {
            throw ValidationException::withMessages(['version' => ['Only an approved version can be published.']]);
        }

        return DB::transaction(function () use ($version): Theme {
            $theme = $version->theme()->lockForUpdate()->firstOrFail();
            if ($theme->current_version_id !== null && $theme->current_version_id !== $version->getKey()) {
                ThemeVersion::query()->whereKey($theme->current_version_id)->where('status', ThemeVersionStatus::Published)->update(['status' => ThemeVersionStatus::Deprecated]);
            }
            $version->forceFill(['status' => ThemeVersionStatus::Published, 'published_at' => now()])->save();
            $theme->forceFill(['current_version_id' => $version->getKey(), 'status' => ThemeStatus::Published, 'published_at' => now()])->save();

            return $theme->refresh()->load(['publisher.owner', 'ownerStore', 'creator', 'currentVersion.uploader', 'currentVersion.approver', 'versions.submissions', 'categories'])->loadCount(['licenses', 'installations']);
        });
    }

    /** @param array<string, mixed> $data */
    public function issueLicense(User $user, Theme $theme, array $data): ThemeLicense
    {
        $this->access->ensureCanManageMarketplace($user);
        $store = Store::query()->where('public_id', $data['store_id'])->firstOrFail();
        $purchaserId = isset($data['purchased_by_user_id'])
            ? User::query()->where('public_id', $data['purchased_by_user_id'])->value('id')
            : null;
        $type = (string) $data['license_type'];
        if ($type === 'custom_owner' && $theme->owner_store_id !== $store->getKey()) {
            throw ValidationException::withMessages(['license_type' => ['A custom-owner license can only be issued to the theme owner Store.']]);
        }
        if ($type === 'trial' && empty($data['trial_expires_at'])) {
            throw ValidationException::withMessages(['trial_expires_at' => ['A trial license requires an expiry date.']]);
        }

        return DB::transaction(fn (): ThemeLicense => ThemeLicense::query()->create([
            ...Arr::except($data, ['store_id', 'purchased_by_user_id']),
            'theme_id' => $theme->getKey(),
            'store_id' => $store->getKey(),
            'purchased_by_user_id' => $purchaserId,
            'status' => $type === 'trial' ? 'trial' : 'active',
            'issued_at' => now(),
        ])->load(['theme', 'store', 'purchaser']));
    }

    public function updateLicense(User $user, ThemeLicense $license, string $status): ThemeLicense
    {
        $this->access->ensureCanManageMarketplace($user);

        return DB::transaction(function () use ($license, $status): ThemeLicense {
            $timestamps = match ($status) {
                'revoked' => ['revoked_at' => now()],
                'refunded' => ['refunded_at' => now()],
                default => [],
            };
            $license->forceFill(['status' => $status, ...$timestamps])->save();
            if (in_array($status, ['revoked', 'refunded', 'expired'], true)) {
                $license->installations()->whereIn('status', ['draft', 'published'])->update(['status' => 'blocked']);
            }

            return $license->refresh()->load(['theme', 'store', 'purchaser']);
        });
    }
}
