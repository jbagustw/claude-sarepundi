<?php

namespace Tests\Feature\Article;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArticleModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user', 'mitra', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function fromFrontend(): static
    {
        return $this->withHeaders(['Origin' => 'http://localhost:3000']);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function regularUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    private function article(User $author, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'author_id' => $author->id,
            'title' => 'Tips Liburan Hemat',
            'slug' => 'tips-liburan-hemat-'.uniqid(),
            'category' => 'Tips Liburan',
            'excerpt' => 'Cara liburan hemat tanpa mengurangi keseruan.',
            'content' => 'Isi lengkap artikel tentang liburan hemat.',
            'status' => 'draft',
        ], $overrides));
    }

    // --- Admin CRUD ---

    public function test_admin_can_create_article_as_draft(): void
    {
        $admin = $this->admin();

        $response = $this->fromFrontend()->actingAs($admin)->postJson('/api/admin/articles', [
            'title' => 'Tips Packing Ringan',
            'category' => 'Tips Liburan',
            'excerpt' => 'Biar koper tidak kelebihan bagasi.',
            'content' => 'Isi artikel lengkap di sini.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.title', 'Tips Packing Ringan');
        $this->assertDatabaseHas('articles', ['title' => 'Tips Packing Ringan', 'status' => 'draft']);
    }

    public function test_article_slug_is_auto_generated_and_unique(): void
    {
        $admin = $this->admin();

        $this->fromFrontend()->actingAs($admin)->postJson('/api/admin/articles', [
            'title' => 'Tips Liburan Keluarga', 'content' => 'Isi 1',
        ]);
        $response = $this->fromFrontend()->actingAs($admin)->postJson('/api/admin/articles', [
            'title' => 'Tips Liburan Keluarga', 'content' => 'Isi 2',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('articles', ['slug' => 'tips-liburan-keluarga']);
        $this->assertDatabaseHas('articles', ['slug' => 'tips-liburan-keluarga-2']);
    }

    public function test_non_admin_cannot_create_article(): void
    {
        $this->fromFrontend()->actingAs($this->regularUser())
            ->postJson('/api/admin/articles', ['title' => 'X', 'content' => 'Y'])
            ->assertForbidden();
    }

    public function test_admin_can_update_article(): void
    {
        $admin = $this->admin();
        $article = $this->article($admin);

        $response = $this->fromFrontend()->actingAs($admin)
            ->patchJson("/api/admin/articles/{$article->id}", ['title' => 'Judul Baru']);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Judul Baru');
        $this->assertSame('Judul Baru', $article->fresh()->title);
    }

    public function test_admin_can_delete_article(): void
    {
        $admin = $this->admin();
        $article = $this->article($admin);

        $this->fromFrontend()->actingAs($admin)
            ->deleteJson("/api/admin/articles/{$article->id}")
            ->assertOk();

        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
    }

    public function test_admin_can_list_articles_filtered_by_status(): void
    {
        $admin = $this->admin();
        $this->article($admin, ['status' => 'draft']);
        $this->article($admin, ['status' => 'published', 'published_at' => now()]);

        $response = $this->fromFrontend()->actingAs($admin)->getJson('/api/admin/articles?status=published');

        $statuses = collect($response->json('data'))->pluck('status');
        $this->assertTrue($statuses->every(fn ($s) => $s === 'published'));
    }

    // --- Publish / unpublish ---

    public function test_admin_can_publish_a_draft_article(): void
    {
        $admin = $this->admin();
        $article = $this->article($admin, ['status' => 'draft']);

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/articles/{$article->id}/publish");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'published');
        $this->assertNotNull($article->fresh()->published_at);
    }

    public function test_cannot_publish_an_already_published_article(): void
    {
        $admin = $this->admin();
        $article = $this->article($admin, ['status' => 'published', 'published_at' => now()]);

        $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/articles/{$article->id}/publish")
            ->assertStatus(422);
    }

    public function test_admin_can_unpublish_an_article(): void
    {
        $admin = $this->admin();
        $article = $this->article($admin, ['status' => 'published', 'published_at' => now()]);

        $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/articles/{$article->id}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');
    }

    // --- Public visibility ---

    public function test_public_article_listing_only_shows_published_articles(): void
    {
        $admin = $this->admin();
        $published = $this->article($admin, ['status' => 'published', 'published_at' => now(), 'title' => 'Published One']);
        $this->article($admin, ['status' => 'draft', 'title' => 'Draft One']);

        $response = $this->fromFrontend()->getJson('/api/articles');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Published One'));
        $this->assertFalse($titles->contains('Draft One'));
    }

    public function test_public_article_listing_can_filter_by_category(): void
    {
        $admin = $this->admin();
        $this->article($admin, ['status' => 'published', 'published_at' => now(), 'category' => 'Tips Wisata', 'title' => 'Wisata Artikel']);
        $this->article($admin, ['status' => 'published', 'published_at' => now(), 'category' => 'Tips Liburan', 'title' => 'Liburan Artikel']);

        $response = $this->fromFrontend()->getJson('/api/articles?category=Tips Wisata');

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Wisata Artikel'));
        $this->assertFalse($titles->contains('Liburan Artikel'));
    }

    public function test_public_can_view_a_published_article_by_slug(): void
    {
        $admin = $this->admin();
        $article = $this->article($admin, ['status' => 'published', 'published_at' => now()]);

        $this->fromFrontend()->getJson("/api/articles/{$article->slug}")
            ->assertOk()
            ->assertJsonPath('data.title', $article->title);
    }

    public function test_draft_article_is_not_publicly_accessible_by_slug(): void
    {
        $admin = $this->admin();
        $article = $this->article($admin, ['status' => 'draft']);

        $this->fromFrontend()->getJson("/api/articles/{$article->slug}")->assertNotFound();
    }

    // --- Cover image ---

    public function test_admin_can_upload_and_replace_article_cover(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $admin = $this->admin();
        $article = $this->article($admin);

        $file = \Illuminate\Http\UploadedFile::fake()->image('cover.jpg');

        $response = $this->fromFrontend()->actingAs($admin)
            ->postJson("/api/admin/articles/{$article->id}/cover", ['cover' => $file]);

        $response->assertOk();
        $this->assertNotNull($article->fresh()->cover_image_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($article->fresh()->cover_image_path);
    }
}
