<?php

namespace Tests\Unit\Services;

use App\Models\Accessory;
use App\Services\CompanionCatalogService;
use App\Services\CompanionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CompanionCatalogServiceTest extends TestCase
{
	use RefreshDatabase;

	public function test_catalog_lists_entries_with_ids_and_embedded_images(): void
	{
		$catalog = app(CompanionCatalogService::class)->catalog();

		$this->assertSame(['categories', 'species', 'colors', 'accessories'], array_keys($catalog));
		$this->assertContains('scuba', array_column($catalog['species'], 'id'));
		$this->assertContains('tophat', array_column($catalog['accessories'], 'id'));
		$this->assertContains('ocean', array_column($catalog['colors'], 'id'));
		$this->assertContains('hat', array_column($catalog['categories'], 'id'));

		foreach ($catalog['species'] as $species) {
			$this->assertStringStartsWith('<svg', $species['svg']);
			$this->assertStringContainsString('var(--companion-main', $species['svg']);
			$this->assertArrayNotHasKey('image', $species);
		}

		foreach ($catalog['accessories'] as $accessory) {
			$this->assertStringStartsWith('data:image/svg+xml;base64,', $accessory['image']);
		}
	}

	public function test_the_shipped_catalog_follows_the_norm(): void
	{
		$assets = config('companion.assets_path');
		$categories = array_keys(config('companion.categories'));
		$skinsPerCategory = Accessory::active()->pluck('category')->countBy()->all();

		foreach (config('companion.categories') as $id => $category) {
			$this->assertArrayHasKey('x', $category['origin']);
			$this->assertArrayHasKey('y', $category['origin']);
			$this->assertFileExists("{$assets}/templates/{$id}.svg");
			$this->assertGreaterThanOrEqual(2, $skinsPerCategory[$id] ?? 0, "Category {$id} needs at least two skins.");
		}

		foreach (Accessory::all() as $accessory) {
			$this->assertContains($accessory->category, $categories, "Accessory {$accessory->code} uses an unknown category.");
			$this->assertStringStartsWith('<svg', $accessory->image);
			$this->assertStringContainsString('viewBox="0 0 100 100"', $accessory->image, "Accessory {$accessory->code} must be drawn in the 100 by 100 template.");
		}

		foreach (config('companion.species') as $id => $species) {
			$this->assertEqualsCanonicalizing($categories, array_keys($species['anchors']), "Species {$id} must anchor every category.");
			$this->assertFileExists($assets.'/'.$species['image']);
			$this->assertStringContainsString('viewBox="0 0 200 200"', file_get_contents($assets.'/'.$species['image']), "Species {$id} must be drawn in the 200 by 200 base template.");
		}

		foreach (config('companion.colors') as $id => $color) {
			foreach (['main', 'shade', 'light'] as $tone) {
				$this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $color[$tone], "Color {$id} needs a {$tone} tone.");
			}
		}

		$this->assertArrayHasKey(CompanionService::DEFAULT_COLOR, config('companion.colors'));
		$this->assertArrayHasKey(CompanionService::DEFAULT_SPECIES, config('companion.species'));
		$this->assertFileExists("{$assets}/templates/base.svg");
	}

	public function test_an_accessory_added_to_the_database_appears_in_the_catalog(): void
	{
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40"/></svg>';
		Accessory::create(['code' => 'leaf_hat', 'label' => 'Chapeau de feuilles', 'category' => 'hat', 'image' => $svg, 'earnable' => true]);
		$service = app(CompanionCatalogService::class);
		$entry = collect($service->catalog()['accessories'])->firstWhere('id', 'leaf_hat');

		$this->assertSame(['id' => 'leaf_hat', 'label' => 'Chapeau de feuilles', 'category' => 'hat', 'image' => 'data:image/svg+xml;base64,'.base64_encode($svg), 'earnable' => true], $entry);
		$this->assertContains('leaf_hat', $service->earnableAccessoryIds());
		$this->assertSame(['leaf_hat'], $service->conflictingAccessories(['tophat', 'leaf_hat']));
	}

	public function test_an_inactive_accessory_leaves_the_catalog(): void
	{
		Accessory::where('code', 'tophat')->update(['active' => false]);
		$service = app(CompanionCatalogService::class);

		$this->assertNotContains('tophat', $service->accessoryIds());
		$this->assertNotContains('tophat', array_column($service->catalog()['accessories'], 'id'));
	}

	public function test_a_missing_asset_fails_loudly(): void
	{
		config(['companion.species.ghost' => ['label' => 'Fantôme', 'image' => 'species/ghost.svg', 'anchors' => []]]);

		$this->expectException(RuntimeException::class);

		app(CompanionCatalogService::class)->catalog();
	}

	public function test_a_base_that_is_not_an_svg_is_refused(): void
	{
		config(['companion.species.photo' => ['label' => 'Photo', 'image' => 'species/photo.png', 'anchors' => []]]);

		$this->expectException(RuntimeException::class);

		app(CompanionCatalogService::class)->catalog();
	}
}
