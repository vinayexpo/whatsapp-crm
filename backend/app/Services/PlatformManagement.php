<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PlatformManagement
{
    public function audit(User $actor, string $action, string $type, string $uuid, string $description, ?Company $company = null): void
    {
        PlatformAuditLog::query()->create([
            'actor_id' => $actor->id, 'actor_name' => $actor->name,
            'company_id' => $company?->id, 'company_name' => $company?->name,
            'action' => $action, 'target_type' => $type, 'target_id' => $uuid, 'description' => $description,
        ]);
    }

    public function verifyPassword(User $actor, string $password): void
    {
        if (! Hash::check($password, $actor->password)) {
            throw ValidationException::withMessages(['currentPassword' => 'Your current password is incorrect.']);
        }
    }

    public function revokeAccess(User $user): void
    {
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    /** Lock a company (or platform administrator set) before checking the last-admin invariant. */
    public function lockUserScope(User $user): void
    {
        if ($user->company_id) {
            Company::query()->whereKey($user->company_id)->lockForUpdate()->firstOrFail();
        } else {
            User::query()->role('superadmin')->orderBy('id')->lockForUpdate()->get();
        }
    }

    public function protectLastAdmin(User $user, string $nextRole, string $nextStatus, bool $deleting = false): void
    {
        $role = $user->role();
        if (! in_array($role, ['admin', 'superadmin'], true) || $user->suspended_at || $user->status !== 'active') {
            return;
        }
        if (! $deleting && $nextRole === $role && $nextStatus === 'active') {
            return;
        }
        if ($role === 'admin' && $user->company?->status !== 'active') {
            return;
        }
        $others = User::query()->where('company_id', $user->company_id)->role($role)
            ->where('status', 'active')->whereNull('suspended_at')->whereKeyNot($user->id)->exists();
        if (! $others) {
            throw ValidationException::withMessages(['role' => 'Keep at least one active administrator. Add another administrator before making this change.']);
        }
    }
}
