<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TagController extends Controller
{
    protected $tagService;

    public function __construct(TagService $tagService)
    {
        $this->tagService = $tagService;
    }

    public function index(Request $request): JsonResponse
    {
        if ($request->has('no_paginate')) {
            $tags = Tag::all(['id', 'name']);
            return response()->json(['data' => $tags]);
        }

        $perPage = $request->input('per_page', 20);
        $tags = Tag::query()
            ->latest('id')
            ->paginate($perPage)
            ->appends($request->query->all());

        return response()->json([
            'data' => $tags->items(),
            'meta' => [
                'current_page' => $tags->currentPage(),
                'per_page' => $tags->perPage(),
                'total' => $tags->total(),
                'last_page' => $tags->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:tags,name'
        ]);

        $tag = $this->tagService->create($validated['name']);
        return response()->json(['data' => $tag], 201);
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:tags,name,' . $tag->id
        ]);

        $this->tagService->update($tag, $validated['name']);
        return response()->json(['data' => $tag]);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $this->tagService->delete($tag);
        return response()->json(null, 204);
    }
}
