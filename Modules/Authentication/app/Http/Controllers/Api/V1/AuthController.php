<?php

declare(strict_types=1);

namespace Modules\Authentication\Http\Controllers\Api\V1;

use App\Http\Requests\PaginatedIndexRequest;
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
use Modules\Authentication\Actions\IssueStoreToken;
use Modules\Authentication\Actions\RegisterUser;
use Modules\Authentication\Http\Requests\CreateTokenRequest;
use Modules\Authentication\Http\Requests\ForgotPasswordRequest;
use Modules\Authentication\Http\Requests\LoginRequest;
use Modules\Authentication\Http\Requests\RegisterRequest;
use Modules\Authentication\Http\Requests\ResetPasswordRequest;
use Modules\Authentication\Http\Requests\TokenLoginRequest;
use Modules\Authentication\Http\Resources\PersonalAccessTokenResource;
use Modules\Authentication\Http\Resources\UserResource;
use Modules\Authentication\Models\PersonalAccessToken;
use Modules\Authentication\Models\User;
use Modules\Authentication\Services\AccountInterfaceAccessService;
use Modules\Authentication\Support\MfaChallengeStore;
use Modules\Stores\Http\Resources\StoreResource;

final class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUser $action): JsonResponse
    {
        $result = $action->handle($request->validated());

        return response()->json(['user' => new UserResource($result->user), 'store' => new StoreResource($result->store)], 201);
    }

    public function login(LoginRequest $request, MfaChallengeStore $challenges): JsonResponse
    {
        $email = Str::lower($request->string('email')->toString());
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are invalid.']]);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'mfa_required' => true,
                'authentication_type' => MfaChallengeStore::SESSION,
                ...$challenges->create($user, MfaChallengeStore::SESSION),
            ], 202);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['mfa_required' => false, 'user' => new UserResource($user)]);
    }

    public function token(
        TokenLoginRequest $request,
        IssueStoreToken $action,
        MfaChallengeStore $challenges,
    ): JsonResponse {
        $data = $request->validated();
        $user = $action->userForCredentials($data);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'mfa_required' => true,
                'authentication_type' => MfaChallengeStore::TOKEN,
                ...$challenges->create($user, MfaChallengeStore::TOKEN, [
                    'device_name' => $data['device_name'],
                    'store_id' => $data['store_id'],
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]),
            ], 202);
        }

        $issued = $action->issue(
            $user,
            $data['device_name'],
            $data['store_id'],
            ['store:access'],
            null,
            ['ip' => $request->ip(), 'user_agent' => $request->userAgent(), 'mfa_verified' => false],
        );

        return response()->json(['mfa_required' => false, 'token' => $issued->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => $issued->accessToken->expires_at?->toISOString()]);
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
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [trans($status)]]);
        }

        return response()->json(['message' => 'Password reset successfully.']);
    }

    public function verifyEmail(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::query()->where('public_id', $id)->firstOrFail();
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

    public function interfaces(Request $request, AccountInterfaceAccessService $interfaces): JsonResponse
    {
        return response()->json(['data' => $interfaces->for($request->user())]);
    }

    public function stores(Request $request): JsonResponse
    {
        return response()->json(['data' => StoreResource::collection($request->user()->stores()->wherePivot('status', 'active')->get())]);
    }

    public function tokens(PaginatedIndexRequest $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->with('store')->latest()->paginate($request->perPage());

        return PersonalAccessTokenResource::collection($tokens)->response();
    }

    public function createToken(CreateTokenRequest $request, IssueStoreToken $action): JsonResponse
    {
        $data = $request->validated();
        $storePublicId = $data['store_id'] ?? null;
        $abilities = $data['abilities'] ?? ($storePublicId === null ? ['account:read'] : ['store:access']);
        $issued = $action->issue($request->user(), $data['device_name'], $storePublicId, $abilities, $data['expires_at'] ?? null, ['ip' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return response()->json(['token' => $issued->plainTextToken, 'token_type' => 'Bearer', 'id' => $issued->accessToken->public_id], 201);
    }

    public function revokeToken(Request $request, string $token): JsonResponse
    {
        $deleted = $request->user()->tokens()->where('public_id', $token)->delete();
        if ($deleted === 0) {
            abort(404, 'Token not found.');
        }

        return response()->json(null, 204);
    }
}
