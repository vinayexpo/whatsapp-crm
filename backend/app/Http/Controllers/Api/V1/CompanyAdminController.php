<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyAdminController extends Controller
{
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        $this->authorizeCompanyAdminsManage($request);

        return UserResource::collection(
            User::query()
                ->where('company_id', $company->id)
                ->role('admin')
                ->latest('created_at')
                ->get()
        );
    }

    public function store(Request $request, Company $company): JsonResponse
    {
        $this->authorizeCompanyAdminsManage($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $admin = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $admin->assignRole('admin');

        return response()->json(['data' => new UserResource($admin)], 201);
    }

    private function authorizeCompanyAdminsManage(Request $request): void
    {
        if (! $request->user()->can('company-admins.manage')) {
            throw new AuthorizationException;
        }
    }
}
