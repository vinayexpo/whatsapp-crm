<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Database\Seeders\ApiConnectionsSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Company::class);

        return CompanyResource::collection(
            Company::query()->latest('created_at')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $company = Company::query()->create([
            'name' => $data['name'],
            'status' => 'active',
        ]);

        (new ApiConnectionsSeeder($company->id))->run();

        return response()->json(['data' => new CompanyResource($company)], 201);
    }
}
