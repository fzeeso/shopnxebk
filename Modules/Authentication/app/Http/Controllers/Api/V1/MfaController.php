<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Fortify;
use Modules\Authentication\Http\Requests\MfaConfirmRequest;
use Modules\Authentication\Http\Requests\MfaPasswordRequest;
use Modules\Authentication\Models\User;

final class MfaController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json([
            'enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'pending_confirmation' => $user->two_factor_secret !== null
                && $user->two_factor_confirmed_at === null,
            'confirmed_at' => $user->two_factor_confirmed_at?->toISOString(),
        ]);
    }

    public function setup(
        MfaPasswordRequest $request,
        EnableTwoFactorAuthentication $enable,
    ): JsonResponse {
        $user = $this->user($request);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            abort(409, 'MFA is already enabled.');
        }

        $enable($user, true);
        $user->refresh();

        return response()->json([
            'enabled' => false,
            'pending_confirmation' => true,
            'secret' => Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
            'otpauth_uri' => $user->twoFactorQrCodeUrl(),
            'qr_code_svg' => $user->twoFactorQrCodeSvg(),
        ])->header('Cache-Control', 'no-store');
    }

    public function confirm(
        MfaConfirmRequest $request,
        ConfirmTwoFactorAuthentication $confirm,
    ): JsonResponse {
        $user = $this->user($request);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            abort(409, 'MFA is already enabled.');
        }
        if ($user->two_factor_secret === null) {
            abort(409, 'Start MFA setup before confirming it.');
        }

        $confirm($user, $request->string('code')->toString());
        $user->refresh();

        return response()->json([
            'enabled' => true,
            'confirmed_at' => $user->two_factor_confirmed_at?->toISOString(),
            'recovery_codes' => $user->recoveryCodes(),
        ])->header('Cache-Control', 'no-store');
    }

    public function regenerateRecoveryCodes(
        MfaPasswordRequest $request,
        GenerateNewRecoveryCodes $generate,
    ): JsonResponse {
        $user = $this->enabledUser($request);
        $generate($user);
        $user->refresh();

        return response()->json([
            'recovery_codes' => $user->recoveryCodes(),
        ])->header('Cache-Control', 'no-store');
    }

    public function disable(
        MfaPasswordRequest $request,
        DisableTwoFactorAuthentication $disable,
    ): JsonResponse {
        $user = $this->user($request);

        if ($user->two_factor_secret === null) {
            abort(409, 'MFA is not configured.');
        }

        $disable($user);

        return response()->json([
            'enabled' => false,
            'message' => 'MFA has been disabled.',
        ]);
    }

    private function enabledUser(Request $request): User
    {
        $user = $this->user($request);

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            abort(409, 'MFA is not enabled.');
        }

        return $user;
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        return $user;
    }
}
