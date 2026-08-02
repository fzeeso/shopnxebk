<?php

declare(strict_types=1);

namespace Modules\Themes\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Authentication\Models\User;
use Modules\Themes\Http\Requests\IssueThemeLicenseRequest;
use Modules\Themes\Http\Requests\PlatformThemeVersionRequest;
use Modules\Themes\Http\Requests\ReviewThemeSubmissionRequest;
use Modules\Themes\Http\Requests\UpdateThemeLicenseRequest;
use Modules\Themes\Http\Resources\ThemeLicenseResource;
use Modules\Themes\Http\Resources\ThemeResource;
use Modules\Themes\Http\Resources\ThemeSubmissionResource;
use Modules\Themes\Http\Resources\ThemeVersionResource;
use Modules\Themes\Models\Theme;
use Modules\Themes\Models\ThemeLicense;
use Modules\Themes\Models\ThemeSubmission;
use Modules\Themes\Models\ThemeVersion;
use Modules\Themes\Services\ThemeReleaseAdminService;

final class PlatformThemeReleaseController extends Controller
{
    public function addVersion(PlatformThemeVersionRequest $request, Theme $theme, ThemeReleaseAdminService $service): JsonResponse
    {
        return response()->json(['data' => new ThemeVersionResource($service->addVersion($this->user($request), $theme, $request->validated()))], 201);
    }

    public function submit(Request $request, ThemeVersion $themeVersion, ThemeReleaseAdminService $service): JsonResponse
    {
        return response()->json(['data' => new ThemeSubmissionResource($service->submit($this->user($request), $themeVersion))], 201);
    }

    public function review(ReviewThemeSubmissionRequest $request, ThemeSubmission $themeSubmission, ThemeReleaseAdminService $service): JsonResponse
    {
        return response()->json(['data' => new ThemeSubmissionResource($service->review($this->user($request), $themeSubmission, $request->validated()))]);
    }

    public function publish(Request $request, ThemeVersion $themeVersion, ThemeReleaseAdminService $service): JsonResponse
    {
        return response()->json(['data' => new ThemeResource($service->publish($this->user($request), $themeVersion))]);
    }

    public function issueLicense(IssueThemeLicenseRequest $request, Theme $theme, ThemeReleaseAdminService $service): JsonResponse
    {
        return response()->json(['data' => new ThemeLicenseResource($service->issueLicense($this->user($request), $theme, $request->validated()))], 201);
    }

    public function updateLicense(UpdateThemeLicenseRequest $request, ThemeLicense $themeLicense, ThemeReleaseAdminService $service): JsonResponse
    {
        return response()->json(['data' => new ThemeLicenseResource($service->updateLicense($this->user($request), $themeLicense, (string) $request->validated('status')))]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
