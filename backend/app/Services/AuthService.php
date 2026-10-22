<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Autentikasi berbasis Bearer Token (Laravel Sanctum).
 */
class AuthService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Memverifikasi kredensial dan menerbitkan token baru.
     *
     * @return array{user: User, token: string}
     */
    public function attempt(string $email, string $password, ?Request $request = null): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->audit->log(
                ActivityAction::LoginFailed,
                user: $user,
                newValues: ['email' => $email],
                request: $request,
            );

            throw BusinessRuleException::unprocessable('Email or password is incorrect.');
        }

        if (! $user->is_active) {
            $this->audit->log(
                ActivityAction::LoginFailed,
                user: $user,
                newValues: ['email' => $email, 'reason' => 'inactive_account'],
                request: $request,
            );

            throw BusinessRuleException::forbidden('Your account has been deactivated.');
        }

        $token = $user->createToken('daetsys-'.$request?->header('X-Device-Name', 'api'))->plainTextToken;

        $this->audit->log(
            ActivityAction::LoginSucceeded,
            user: $user,
            request: $request,
        );

        return ['user' => $user->load('role'), 'token' => $token];
    }

    /**
     * Mencabut token yang sedang dipakai.
     */
    public function logout(User $user, ?Request $request = null): void
    {
        DB::transaction(function () use ($user, $request): void {
            $token = $user->currentAccessToken();

            if ($token !== null && isset($token->id)) {
                $user->tokens()->where('id', $token->id)->delete();
            } elseif ($token !== null && method_exists($token, 'delete')) {
                $token->delete();
            } else {
                // Fallback: delete all tokens for safety
                $user->tokens()->delete();
            }

            $this->audit->log(
                ActivityAction::LoggedOut,
                user: $user,
                request: $request,
            );
        });
    }

    /**
     * Mengganti password dan mencabut seluruh token milik pengguna.
     */
    public function changePassword(
        User $user,
        string $currentPassword,
        string $newPassword,
        ?Request $request = null,
    ): void {
        DB::transaction(function () use ($user, $currentPassword, $newPassword, $request): void {
            if (! Hash::check($currentPassword, $user->password)) {
                throw BusinessRuleException::unprocessable('Current password is incorrect.');
            }

            $user->update(['password' => $newPassword]);

            $user->tokens()->delete();

            $this->audit->log(
                ActivityAction::PasswordChanged,
                user: $user,
                request: $request,
            );
        });
    }
}
