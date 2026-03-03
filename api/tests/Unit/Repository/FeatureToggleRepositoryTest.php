<?php

namespace Tests\Unit\Repository;

use App\Models\FeatureToggle;
use App\Repository\FeatureToggleRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeatureToggleRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private FeatureToggleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a fresh instance for each test so caches are clean
        $this->repository = new FeatureToggleRepository();
    }

    /**
     * Create or replace a feature toggle record using high IDs to avoid conflicts.
     */
    private function createFeatureToggle(int $id, bool $enabled, $value = null, bool $hasInput = true): void
    {
        DB::table('feature_toggles')->where('id', $id)->delete();
        DB::table('feature_toggles')->insert([
            'id' => $id,
            'enabled' => $enabled,
            'hidden' => false,
            'input' => $hasInput && $value !== null
                ? json_encode(['value' => $value])
                : json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_enabled_returns_true_when_feature_enabled(): void
    {
        $this->createFeatureToggle(9001, true);

        $result = $this->repository->enabled(9001);

        $this->assertTrue($result);
    }

    public function test_enabled_returns_false_when_feature_disabled(): void
    {
        $this->createFeatureToggle(9002, false);

        $result = $this->repository->enabled(9002);

        $this->assertFalse($result);
    }

    public function test_enabled_returns_false_when_feature_not_exists(): void
    {
        $result = $this->repository->enabled(9999);

        $this->assertFalse($result);
    }

    public function test_valueOf_returns_value_when_exists(): void
    {
        $this->createFeatureToggle(9003, true, 42);

        $result = $this->repository->valueOf(9003);

        $this->assertEquals(42, $result);
    }

    public function test_valueOf_returns_default_when_not_exists(): void
    {
        $result = $this->repository->valueOf(9998, 'fallback');

        $this->assertEquals('fallback', $result);
    }

    public function test_valueOf_returns_default_when_no_input(): void
    {
        // Create with empty input (no 'value' key)
        $this->createFeatureToggle(9004, true, null, false);

        $result = $this->repository->valueOf(9004, 'default_val');

        $this->assertEquals('default_val', $result);
    }

    public function test_enabled_caches_results(): void
    {
        $this->createFeatureToggle(9005, true);

        // First call loads from DB
        $result1 = $this->repository->enabled(9005);
        $this->assertTrue($result1);

        // Change the DB record directly
        DB::table('feature_toggles')
            ->where('id', 9005)
            ->update(['enabled' => false]);

        // Second call should still return cached value (true)
        $result2 = $this->repository->enabled(9005);
        $this->assertTrue($result2, 'Cached result should still return true after DB change');
    }

    public function test_enabled_bypasses_cache_when_noCache_true(): void
    {
        $this->createFeatureToggle(9006, true);

        // First call loads from DB
        $result1 = $this->repository->enabled(9006);
        $this->assertTrue($result1);

        // Change the DB record directly
        DB::table('feature_toggles')
            ->where('id', 9006)
            ->update(['enabled' => false]);

        // Call with noCache=true should bypass cache and return updated value
        $result2 = $this->repository->enabled(9006, true);
        $this->assertFalse($result2, 'noCache=true should bypass cache and return fresh DB value');
    }
}
