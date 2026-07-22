<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Controllers\Api\V1;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Authentication\Actions\IssueTenantToken;
use Modules\Authentication\Actions\RegisterUser;
use Modules\Authentication\Http\Requests\CreateTokenRequest;
use Modules\Authentication\Http\Requests\ForgotPasswordRequest;
use Modules\Authentication\Http\Requests\LoginRequest;
use Modules\Authentication\Http\Requests\RegisterRequest;
use Modules\Authentication\Http\Requests\ResetPasswordRequest;
use Modules\Authentication\Http\Requests\TokenLoginRequest;
use Modules\Authentication\Http\Resources\UserResource;
use Modules\Authentication\Models\PersonalAccessToken;
use Modules\Authentication\Models\User;
use Modules\Tenancy\Http\Resources\TenantResource;

final class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUser $action): JsonResponse
    {
        $result = $action->handle($request->validated());

        return response()->json(['user' => new UserResource($result->user), 'tenant' => new TenantResource($result->tenant)], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = ['email' => Str::lower($request->string('email')->toString()), 'password' => $request->string('password')->toString()];
        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are invalid.']]);
        }

        $request->session()->regenerate();

        return response()->json(['user' => new UserResource($request->user())]);
    }

    public function token(TokenLoginRequest $request, IssueTenantToken $action): JsonResponse
    {
        $issued = $action->forCredentials($request->validated(), (string) $request->ip(), $request->userAgent());

        return response()->json(['token' => $issued->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => $issued->accessToken->expires_at?->toISOString()]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink(['email' => Str::lower($request->string('email')->toString())]);

        return response()->json(['message' => 'If an account matches that email, a reset link has been queued.'], 202);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset($request->validated(), function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [trans($status)]]);
        }

        return response()->json(['message' => 'Password reset successfully.']);
    }

    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'Invalid verification link.');
        }
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json(['message' => 'Email verified.']);
    }

    public function sendVerification(Request $request): JsonResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'Verification notification queued.'], 202);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } else {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => new UserResource($request->user())]);
    }

    public function tenants(Request $request): JsonResponse
    {
        return response()->json(['data' => TenantResource::collection($request->user()->tenants()->wherePivot('status', 'active')->get())]);
    }

    public function tokens(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->tokens()->latest()->get(['id', 'name', 'tenant_id', 'abilities', 'last_used_at', 'expires_at', 'created_at'])]);
    }

    public function createToken(CreateTokenRequest $request, IssueTenantToken $action): JsonResponse
    {
        $data = $request->validated();
        $tenantId = $data['tenant_id'] ?? null;
        $abilities = $data['abilities'] ?? ($tenantId === null ? ['account:read'] : ['tenant:access']);
        $issued = $action->issue($request->user(), $data['device_name'], $tenantId, $abilities, $data['expires_at'] ?? null, ['ip' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return response()->json(['token' => $issued->plainTextToken, 'token_type' => 'Bearer', 'id' => $issued->accessToken->getKey()], 201);
    }

    public function revokeToken(Request $request, string $token): JsonResponse
    {
        $deleted = $request->user()->tokens()->whereKey($token)->delete();
        if ($deleted === 0) {
            abort(404, 'Token not found.');
        }

        return response()->json(null, 204);
    }
}
