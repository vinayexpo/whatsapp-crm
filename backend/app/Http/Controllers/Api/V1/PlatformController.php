<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ApiConnection;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Services\PlatformManagement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class PlatformController extends Controller
{
    use PaginatesRequests;

    public function summary()
    {
        return response()->json(['data' => [
            'companies' => Company::query()->count(),
            'activeCompanies' => Company::query()->where('status', 'active')->count(),
            'suspendedCompanies' => Company::query()->where('status', 'suspended')->count(),
            'companiesWithoutAdmin' => Company::query()->where('status', 'active')->whereDoesntHave('users', fn ($q) => $q->role('admin')->where('status', 'active')->whereNull('suspended_at'))->count(),
            'users' => User::query()->count(),
            'activeUsers' => User::query()->where('status', 'active')->whereNull('suspended_at')->where(fn ($q) => $q->whereNull('company_id')->orWhereHas('company', fn ($q) => $q->where('status', 'active')))->count(),
            'suspendedUsers' => User::query()->whereNotNull('suspended_at')->count(),
            'contacts' => Contact::withoutGlobalScopes()->count(),
            'conversations' => Conversation::withoutGlobalScopes()->count(),
            'campaigns' => Campaign::withoutGlobalScopes()->count(),
            'orders' => Order::withoutGlobalScopes()->count(),
            'branches' => Branch::withoutGlobalScopes()->count(),
            'connectedChannels' => ApiConnection::withoutGlobalScopes()->where('status', 'connected')->count(),
            'disconnectedChannels' => ApiConnection::withoutGlobalScopes()->where('status', '!=', 'connected')->count(),
            'generatedAt' => now()->toIso8601String(),
        ]]);
    }

    public function activity(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'], 'action' => ['nullable', 'string', 'max:100'],
            'companyId' => ['nullable', 'uuid', 'exists:companies,uuid'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
        $query = PlatformAuditLog::query()
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('description', 'like', '%'.$request->query('search').'%')->orWhere('actor_name', 'like', '%'.$request->query('search').'%')->orWhere('company_name', 'like', '%'.$request->query('search').'%')))
            ->when($request->filled('companyId'), fn ($q) => $q->where('company_id', Company::query()->where('uuid', $request->query('companyId'))->value('id')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->query('action')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('to')))
            ->orderByDesc('id')->paginate($this->perPageFrom($request));
        $query->through(fn ($log) => [
            'id' => $log->uuid, 'actorName' => $log->actor_name, 'companyName' => $log->company_name,
            'action' => $log->action, 'targetType' => $log->target_type, 'targetId' => $log->target_id,
            'description' => $log->description, 'createdAt' => $log->created_at->toIso8601String(),
        ]);

        return response()->json($query);
    }

    public function profile(Request $request, PlatformManagement $management)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'currentPassword' => ['required', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);
        $management->verifyPassword($user, $data['currentPassword']);
        DB::transaction(function () use ($user, $data, $management, $request) {
            $user->fill(['name' => $data['name'], 'email' => $data['email']]);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
                $accessToken = $user->currentAccessToken();
                $tokenId = $accessToken instanceof PersonalAccessToken ? $accessToken->getKey() : null;
                $user->tokens()->when($tokenId, fn ($q) => $q->whereKeyNot($tokenId))->delete();
                $sessionId = $request->hasSession() ? $request->session()->getId() : null;
                DB::table('sessions')->where('user_id', $user->id)->when($sessionId, fn ($q) => $q->where('id', '!=', $sessionId))->delete();
            }
            $user->save();
            $management->audit($user, 'profile.updated', 'user', $user->uuid, 'Updated own platform account settings.');
        });

        return response()->json(['data' => new UserResource($user)]);
    }
}
