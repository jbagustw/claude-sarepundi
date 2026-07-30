<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $articles = Article::published()
            ->with('author')
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->latest('published_at')
            ->paginate(9);

        return ArticleResource::collection($articles);
    }

    public function show(string $slug)
    {
        $article = Article::published()
            ->with('author')
            ->where('slug', $slug)
            ->firstOrFail();

        return new ArticleResource($article);
    }
}
