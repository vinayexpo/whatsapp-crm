<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformUserRequest;
use App\Http\Resources\PlatformUserResource;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\PlatformManagement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformUserController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(['superadmin', 'admin', 'manager', 'agent', 'branch_manager', 'staff'])],
            'status' => ['nullable', Rule::in(['active', 'invited', 'suspended'])],
            'companyId' => ['nullable', 'uuid', 'exists:companies,uuid'],
        ]);
        $query = User::query()->with(['roles', 'permissions', 'company', 'staffBranch'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->query('search').'%')->orWhere('email', 'like', '%'.$request->query('search').'%')))
            ->when($request->filled('role'), fn ($q) => $q->role($request->query('role')))
            ->when($request->filled('companyId'), fn ($q) => $q->whereHas('company', fn ($q) => $q->where('uuid', $request->query('companyId'))));
        if ($request->query('status') === 'suspended') {
            $query->whereNotNull('suspended_at');
        } elseif ($request->filled('status')) {
            $query->whereNull('suspended_at')->where('status', $request->query('status'));
        }

        return PlatformUserResource::collection($query->orderByDesc('id')->paginate($this->perPageFrom($request)));
    }

    public function show(User $platformUser)
    {
        return new PlatformUserResource($platformUser->load(['company', 'staffBranch', 'roles']));
    }

    public function store(PlatformUserRequest $request, PlatformManagement $management)
    {
        $data = $request->validated();
        $user = DB::transaction(function () use ($data, $request, $management) {
            $company = ! empty($data['companyId']) ? Company::query()->where('uuid', $data['companyId'])->lockForUpdate()->firstOrFail() : null;
            $user = User::query()->create([
                'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
                'company_id' => $company?->id,
                'staff_branch_id' => ! empty($data['staffBranchId']) ? Branch::query()->where('uuid', $data['staffBranchId'])->value('id') : null,
                'status' => $data['status'] === 'invited' ? 'invited' : 'active',
            ]);
            $user->suspended_at = $data['status'] === 'suspended' ? now() : null;
            $user->save();
            $user->assignRole($data['role']);
            $management->audit($request->user(), 'user.created', 'user', $user->uuid, "Created {$data['role']} account {$user->email}.", $company);

            return $user;
        });

        return response()->json(['data' => new PlatformUserResource($user->load(['company', 'staffBranch', 'roles']))], 201);
    }

    public function update(PlatformUserRequest $request, User $platformUser, PlatformManagement $management)
    {
        abort_if($platformUser->is($request->user()), 422, 'Use Account settings to edit your own profile.');
        $data = $request->validated();
        $company = $platformUser->company;
        if (($data['companyId'] ?? null) !== $company?->uuid || ($platformUser->isSuperadmin() !== ($data['role'] === 'superadmin'))) {
            throw ValidationException::withMessages(['companyId' => 'Accounts cannot be moved between companies or converted to platform accounts. Create a separate account instead.']);
        }
        DB::transaction(function () use ($platformUser, $management, $data, $request, $company) {
            $management->lockUserScope($platformUser);
            $user = User::query()->lockForUpdate()->findOrFail($platformUser->id);
            $management->protectLastAdmin($user, $data['role'], $data['status']);
            $nextBranchId = ! empty($data['staffBranchId']) ? Branch::query()->where('uuid', $data['staffBranchId'])->value('id') : null;
            $changedAccess = $user->role() !== $data['role'] || $user->email !== $data['email'] || $user->status !== $data['status'] || $user->suspended_at !== null || $user->staff_branch_id !== $nextBranchId;
            $user->fill(['name' => $data['name'], 'email' => $data['email'], 'status' => $data['status'] === 'invited' ? 'invited' : 'active']);
            $user->suspended_at = $data['status'] === 'suspended' ? now() : null;
            $user->staff_branch_id = $nextBranchId;
            $user->save();
            $user->syncRoles([$data['role']]);
            if ($changedAccess) {
                $management->revokeAccess($user);
            }
            $management->audit($request->user(), 'user.updated', 'user', $user->uuid, "Updated {$user->email}: {$data['role']}, {$data['status']}.", $company);
        });

        return response()->json(['data' => new PlatformUserResource($platformUser->fresh()->load(['company', 'staffBranch', 'roles']))]);
    }

    public function destroy(Request $request, User $platformUser, PlatformManagement $management)
    {
        abort_if($platformUser->is($request->user()), 422, 'You cannot delete your own account.');
        $data = $request->validate(['confirmation' => ['required', 'string'], 'currentPassword' => ['required', 'string']]);
        $management->verifyPassword($request->user(), $data['currentPassword']);
        if ($data['confirmation'] !== $platformUser->email) {
            throw ValidationException::withMessages(['confirmation' => 'Enter the exact email address to delete this user.']);
        }
        DB::transaction(function () use ($platformUser, $management, $request) {
            $management->lockUserScope($platformUser);
            $user = User::query()->lockForUpdate()->findOrFail($platformUser->id);
            $management->protectLastAdmin($user, '', '', deleting: true);
            $management->audit($request->user(), 'user.deleted', 'user', $user->uuid, "Deleted account {$user->email}.", $user->company);
            $management->revokeAccess($user);
            $user->delete();
        });

        return response()->noContent();
    }

    public function password(Request $request, User $platformUser, PlatformManagement $management)
    {
        abort_if($platformUser->is($request->user()), 422, 'Use Account settings to change your own password.');
        $data = $request->validate(['currentPassword' => ['required', 'string'], 'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed']]);
        $management->verifyPassword($request->user(), $data['currentPassword']);
        DB::transaction(function () use ($platformUser, $data, $management, $request) {
            $platformUser->update(['password' => $data['password']]);
            $management->revokeAccess($platformUser);
            $management->audit($request->user(), 'user.password_reset', 'user', $platformUser->uuid, "Reset password and signed out {$platformUser->email}.", $platformUser->company);
        });

        return response()->json(['message' => 'Password updated. Existing sessions have been signed out.']);
    }
}
