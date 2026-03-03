<?php

namespace Tests\Unit\Services;

use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagServiceTest extends TestCase
{
    use DatabaseTransactions;

    private TagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TagService();
    }

    // ---------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------

    private function createTag(string $name = null): Tag
    {
        $name = $name ?? 'tag_' . uniqid();
        $id = DB::table('tags')->insertGetId([
            'name'       => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return Tag::find($id);
    }

    // ---------------------------------------------------------------
    //  Tests
    // ---------------------------------------------------------------

    public function test_create_creates_tag(): void
    {
        $tag = $this->service->create('VIP');

        $this->assertInstanceOf(Tag::class, $tag);
        $this->assertEquals('VIP', $tag->name);
        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'VIP']);
    }

    public function test_update_updates_tag_name(): void
    {
        $tag = $this->createTag('OldName');

        $result = $this->service->update($tag, 'NewName');

        $this->assertTrue($result);
        $tag->refresh();
        $this->assertEquals('NewName', $tag->name);
    }

    public function test_delete_removes_tag(): void
    {
        $tag = $this->createTag('ToDelete');
        $tagId = $tag->id;

        $result = $this->service->delete($tag);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('tags', ['id' => $tagId]);
    }

    public function test_getAllTags_returns_all_tags(): void
    {
        // Create a few tags
        $tag1 = $this->createTag('Alpha');
        $tag2 = $this->createTag('Beta');

        $tags = $this->service->getAllTags();

        $this->assertGreaterThanOrEqual(2, $tags->count());
        $this->assertTrue($tags->contains('id', $tag1->id));
        $this->assertTrue($tags->contains('id', $tag2->id));
    }
}
