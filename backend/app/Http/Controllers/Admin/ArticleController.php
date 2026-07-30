<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\UpdateArticleRequest;
use App\Http\Requests\Admin\UploadArticleCoverRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
        ]);

        $articles = Article::with('author')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest()
            ->get();

        return ArticleResource::collection($articles);
    }

    public function store(StoreArticleRequest $request)
    {
        $data = $request->validated();

        $article = Article::create([
            ...$data,
            'author_id' => $request->user()->id,
            'slug' => $this->uniqueSlug($data['title']),
            'status' => 'draft',
        ]);

        return (new ArticleResource($article->load('author')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Article $article)
    {
        return new ArticleResource($article->load('author'));
    }

    public function update(UpdateArticleRequest $request, Article $article)
    {
        $article->update($request->validated());

        return new ArticleResource($article->load('author'));
    }

    public function destroy(Article $article)
    {
        if ($article->cover_image_path) {
            Storage::disk('public')->delete($article->cover_image_path);
        }

        $article->delete();

        return response()->json(['message' => 'Artikel dihapus.']);
    }

    public function publish(Article $article)
    {
        abort_if($article->status === 'published', 422, 'Artikel ini sudah dipublikasikan.');

        $article->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return new ArticleResource($article->load('author'));
    }

    public function unpublish(Article $article)
    {
        abort_unless($article->status === 'published', 422, 'Artikel ini belum dipublikasikan.');

        $article->update(['status' => 'draft']);

        return new ArticleResource($article->load('author'));
    }

    public function uploadCover(UploadArticleCoverRequest $request, Article $article)
    {
        if ($article->cover_image_path) {
            Storage::disk('public')->delete($article->cover_image_path);
        }

        $path = $request->file('cover')->store('article-covers', 'public');

        $article->update(['cover_image_path' => $path]);

        return new ArticleResource($article->load('author'));
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (Article::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
