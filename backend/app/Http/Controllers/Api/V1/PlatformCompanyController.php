<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformCompanyResource;
use App\Models\Company;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\PlatformManagement;
use Database\Seeders\ApiConnectionsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformCompanyController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request)
    {
        $request->validate(['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::in(['active', 'suspended'])], 'needsAdmin' => ['nullable', 'boolean']]);

        return PlatformCompanyResource::collection($this->query()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->query('search').'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->boolean('needsAdmin'), fn ($q) => $q->where('status', 'active')->whereDoesntHave('users', fn ($q) => $q->role('admin')->where('status', 'active')->whereNull('suspended_at')))
            ->orderByDesc('id')->paginate($this->perPageFrom($request)));
    }

    public function show(Company $company)
    {
        $company = $this->query()->withCount(['contacts', 'conversations', 'campaigns', 'branches', 'orders'])->findOrFail($company->id);

        return response()->json(['data' => [
            'company' => new PlatformCompanyResource($company),
            'connections' => $company->connections()->select(['uuid', 'channel', 'label', 'status', 'account_name'])->orderBy('channel')->get()
                ->map(fn ($connection) => ['id' => $connection->uuid, 'channel' => $connection->channel, 'label' => $connection->label, 'status' => $connection->status, 'accountName' => $connection->account_name]),
        ]]);
    }

    public function store(Request $request, PlatformManagement $management)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $company = DB::transaction(function () use ($data, $request, $management) {
            $company = Company::query()->create(['name' => $data['name'], 'status' => 'active']);
            (new ApiConnectionsSeeder($company->id))->run();
            $management->audit($request->user(), 'company.created', 'company', $company->uuid, "Created company {$company->name}.", $company);

            return $company;
        });

        return response()->json(['data' => new PlatformCompanyResource($this->query()->findOrFail($company->id))], 201);
    }

    public function update(Request $request, Company $company, PlatformManagement $management)
    {
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:255'], 'status' => ['sometimes', 'required', Rule::in(['active', 'suspended'])]]);
        DB::transaction(function () use ($company, $data, $management, $request) {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);
            $oldStatus = $company->status;
            $company->update($data);
            if ($company->status === 'suspended' && $oldStatus !== 'suspended') {
                $company->users()->eachById(fn (User $user) => $management->revokeAccess($user));
            }
            $action = $oldStatus !== $company->status ? ($company->status === 'suspended' ? 'company.suspended' : 'company.reactivated') : 'company.updated';
            $management->audit($request->user(), $action, 'company', $company->uuid, "Updated company {$company->name} ({$company->status}).", $company);
        });

        return response()->json(['data' => new PlatformCompanyResource($this->query()->findOrFail($company->id))]);
    }

    public function destroy(Request $request, Company $company, PlatformManagement $management)
    {
        $data = $request->validate(['confirmation' => ['required', 'string'], 'currentPassword' => ['required', 'string']]);
        $management->verifyPassword($request->user(), $data['currentPassword']);
        DB::transaction(function () use ($company, $data, $management, $request) {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);
            if ($data['confirmation'] !== $company->name || $company->status !== 'suspended') {
                throw ValidationException::withMessages(['confirmation' => 'Suspend this company first, then enter its exact name to delete it.']);
            }
            $management->audit($request->user(), 'company.deleted', 'company', $company->uuid, "Permanently deleted company {$company->name} and its database records.", $company);
            // users.company_id is SET NULL on delete: remove users explicitly so they never become unscoped accounts.
            $company->users()->eachById(function (User $user) use ($management) {
                $management->revokeAccess($user);
                $user->delete();
            });
            // Stage IDs are shared lookup keys referenced by contacts in other tenants.
            // Preserve them instead of cascading a deleted company's ownership into those references.
            PipelineStage::withoutGlobalScopes()->where('company_id', $company->id)->update(['company_id' => null]);
            $company->delete();
        });

        return response()->noContent();
    }

    public function branches(Request $request, Company $company)
    {
        return response()->json(['data' => $company->branches()->orderBy('name')->get(['uuid', 'name'])
            ->map(fn ($branch) => ['id' => $branch->uuid, 'name' => $branch->name])]);
    }

    private function query()
    {
        return Company::query()->withCount([
            'users',
            'users as active_admins_count' => fn ($q) => $q->role('admin')->where('status', 'active')->whereNull('suspended_at'),
            'connections as connected_channels_count' => fn ($q) => $q->where('status', 'connected'),
        ]);
    }
}
