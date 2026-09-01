<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('name')->get();

        return $this->successResponse($categories, 'Institution categories retrieved successfully');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
        ]);

        $category = Category::create($data);

        return $this->successResponse($category, 'Institution category created successfully', 201);
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return $this->successResponse(null, 'Institution category deleted successfully');
    }
}
