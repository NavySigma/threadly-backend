<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use CreatesUser, RefreshDatabase;

    private function seedData(): array
    {
        $user = $this->makeUser(['username' => 'searchuser']);
        $category = Category::create([
            'name' => 'SearchCat',
            'slug' => 'search-cat',
        ]);
        $tag = Tag::create(['name' => 'laravel', 'slug' => 'laravel', 'usage_count' => 5]);
        $post = Post::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'How to use Laravel eloquent properly',
            'body' => 'This post explains laravel eloquent in detail.',
            'status' => 'open',
        ]);

        return [$user, $post, $tag, $category];
    }

    // ── GET /api/search ───────────────────────────────────────────────

    public function test_global_search_mengembalikan_semua_entitas(): void
    {
        $this->seedData();

        $this->getJson('/api/search?q=laravel')
            ->assertStatus(200)
            ->assertJsonStructure([
                'query',
                'data' => ['posts', 'users', 'tags', 'categories'],
            ]);
    }

    public function test_global_search_gagal_tanpa_query(): void
    {
        $this->getJson('/api/search')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_global_search_menemukan_post_berdasarkan_judul(): void
    {
        $this->seedData();

        $res = $this->getJson('/api/search?q=eloquent');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.posts'));
    }

    public function test_global_search_tidak_tampilkan_post_deleted(): void
    {
        [$user, , , $category] = $this->seedData();

        Post::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Deleted post about laravel tips',
            'body' => 'This deleted post should not appear in search.',
            'status' => 'deleted',
        ]);

        $res = $this->getJson('/api/search?q=laravel');
        $this->assertCount(1, $res->json('data.posts'));
    }

    // ── GET /api/search/posts ─────────────────────────────────────────

    public function test_search_posts_dengan_keyword(): void
    {
        $this->seedData();

        $this->getJson('/api/search/posts?q=laravel')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links']);
    }

    public function test_search_posts_bisa_filter_by_category_id(): void
    {
        [$user, , , $category] = $this->seedData();
        $otherCat = Category::create(['name' => 'Other', 'slug' => 'other']);

        Post::create([
            'user_id' => $user->id,
            'category_id' => $otherCat->id,
            'title' => 'Post in other category here',
            'body' => 'Body of post in a different category.',
            'status' => 'open',
        ]);

        $res = $this->getJson("/api/search/posts?category_id={$category->id}");
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
    }

    public function test_search_posts_bisa_filter_is_answered(): void
    {
        $this->seedData();

        $res = $this->getJson('/api/search/posts?is_answered=0');
        $res->assertStatus(200);
        foreach ($res->json('data') as $post) {
            $this->assertFalse($post['is_answered']);
        }
    }

    // ── GET /api/search/users ─────────────────────────────────────────

    public function test_search_users_dengan_keyword(): void
    {
        $this->seedData();

        $res = $this->getJson('/api/search/users?q=search');
        $res->assertStatus(200);
        $this->assertGreaterThan(0, count($res->json('data')));
    }

    public function test_search_users_gagal_tanpa_q(): void
    {
        $this->getJson('/api/search/users')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    // ── GET /api/search/tags ──────────────────────────────────────────

    public function test_search_tags_dengan_keyword(): void
    {
        $this->seedData();

        $res = $this->getJson('/api/search/tags?q=lara');
        $res->assertStatus(200);
        $this->assertGreaterThan(0, count($res->json('data')));
    }

    public function test_search_tags_gagal_tanpa_q(): void
    {
        $this->getJson('/api/search/tags')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_search_posts_bisa_sort_latest(): void
    {
        $this->seedData();

        $this->getJson('/api/search/posts?sort=latest')->assertStatus(200);
    }

    public function test_search_posts_bisa_sort_votes(): void
    {
        $this->seedData();

        $this->getJson('/api/search/posts?sort=votes')->assertStatus(200);
    }
}
