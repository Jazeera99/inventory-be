<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryStoreRequest;
use App\Http\Requests\CategoryUpdateRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show list of categories, with optional search by name.
     */
    public function index(): JsonResource
    {
        $this->authorize('viewAny', Category::class);
        $categories = Category::query()
            ->when(request('search'), function ($query, $search): void {
                $query->where('category_name', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(50);

        return CategoryResource::collection($categories);
    }

    /**
     * Save new category.
     */
    public function store(CategoryStoreRequest $request)
    {
        $this->authorize('create', Category::class);

        $category = Category::create($request->validated());

        return new CategoryResource($category);
    }

    /**
     * Show detail category.
     */
    public function show(Category $category)
    {
        $this->authorize('viewAny', $category);

        return new CategoryResource($category);
    }

    /**
     * Update category data.
     */
    public function update(CategoryUpdateRequest $request, Category $category)
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return new CategoryResource($category);
    }

    /**
     * Toggle category active status. If currently active, it will be deactivated.
     */
    public function toggleActive(Category $category)
    {
        $this->authorize('update', $category);

        $category->update([
            'is_active' => ! $category->is_active,
        ]);

        return new CategoryResource($category);
    }
}
