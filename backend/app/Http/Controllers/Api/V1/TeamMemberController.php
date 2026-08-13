<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TeamMemberController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        return UserResource::collection(
            User::query()
                ->where('company_id', $request->user()->company_id)
                ->latest('created_at')
                ->get()
        );
    }

    public function assignable(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAssignable', User::class);

        return UserResource::collection(
            User::query()
                ->where('company_id', $request->user()->company_id)
                ->latest('created_at')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:manager,agent'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'company_id' => $request->user()->company_id,
            'status' => 'active',
        ]);

        $user->assignRole($data['role']);

        return response()->json(['data' => new UserResource($user)], 201);
    }

    public function updateRole(Request $request, User $teamMember): JsonResponse
    {
        $this->authorize('update', $teamMember);

        $data = $request->validate([
            'role' => ['required', Rule::in(['manager', 'agent'])],
        ]);

        $teamMember->syncRoles([$data['role']]);

        return response()->json(['data' => new UserResource($teamMember)]);
    }

    public function destroy(Request $request, User $teamMember): JsonResponse
    {
        $this->authorize('delete', $teamMember);

        if ($teamMember->is($request->user())) {
            abort(422, 'You cannot remove yourself from the team.');
        }

        $teamMember->delete();

        return response()->json(null, 204);
    }
}
