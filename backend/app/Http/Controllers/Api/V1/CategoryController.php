<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\PaginatesRequests;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    use PaginatesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Category::class);

        return CategoryResource::collection(
            Category::query()
                ->with('parent')
                ->when($request->filled('parentId'), function ($q) use ($request) {
                    $parent = Category::where('uuid', $request->query('parentId'))->first();
                    $q->where('parent_id', $parent?->id);
                })
                ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->query('search').'%'))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate($this->perPageFrom($request))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $data = $this->validateCategory($request);

        $category = Category::query()->create($this->mapCategoryData($data));

        return response()->json(['data' => new CategoryResource($category->load('parent'))], 201);
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        return response()->json(['data' => new CategoryResource($category->load('parent'))]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $data = $this->validateCategory($request, isUpdate: true);

        $category->update($this->mapCategoryData($data));

        return response()->json(['data' => new CategoryResource($category->load('parent'))]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCategory(Request $request, bool $isUpdate = false): array
    {
        $sometimes = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'parentId' => ['sometimes', 'nullable', 'string', 'exists:categories,uuid'],
            'name' => [$sometimes, 'string', 'max:255'],
            'slug' => [$sometimes, 'string', 'max:255'],
            'imageUrl' => ['sometimes', 'nullable', 'string'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
            'isActive' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapCategoryData(array $data): array
    {
        $update = [];

        if (array_key_exists('parentId', $data)) {
            $update['parent_id'] = $data['parentId']
                ? Category::where('uuid', $data['parentId'])->value('id')
                : null;
        }

        $map = [
            'name' => 'name',
            'slug' => 'slug',
            'imageUrl' => 'image_url',
            'sortOrder' => 'sort_order',
            'isActive' => 'is_active',
        ];

        foreach ($map as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $update[$column] = $data[$requestKey];
            }
        }

        return $update;
    }
}
