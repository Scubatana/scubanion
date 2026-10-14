<?php

namespace App\Services;

use App\Models\Accessory;
use RuntimeException;

class CompanionCatalogService
{
	private const MIME_TYPES = [
		'svg' => 'image/svg+xml',
		'png' => 'image/png',
		'webp' => 'image/webp',
	];

	public function catalog(): array
	{
		return [
			'categories' => $this->entries(config('companion.categories')),
			'species' => $this->entries(config('companion.species'), withSvg: true),
			'colors' => $this->entries(config('companion.colors')),
			'accessories' => Accessory::active()->orderBy('id')->get()->map(fn (Accessory $accessory) => [
				'id' => $accessory->code,
				'label' => $accessory->label,
				'category' => $accessory->category,
				'image' => $accessory->dataUri(),
				'earnable' => $accessory->earnable,
			])->all(),
		];
	}

	public function speciesIds(): array
	{
		return array_keys(config('companion.species'));
	}

	public function colorIds(): array
	{
		return array_keys(config('companion.colors'));
	}

	public function accessoryIds(): array
	{
		return Accessory::active()->pluck('code')->all();
	}

	public function earnableAccessoryIds(): array
	{
		return Accessory::active()->where('earnable', true)->pluck('code')->all();
	}

	public function conflictingAccessories(array $accessories): array
	{
		$categories = Accessory::active()->pluck('category', 'code');
		$worn = [];
		$conflicts = [];

		foreach ($accessories as $accessory) {
			$category = is_string($accessory) ? ($categories[$accessory] ?? null) : null;

			if ($category === null) {
				continue;
			}

			if (in_array($category, $worn, true)) {
				$conflicts[] = $accessory;
			}

			$worn[] = $category;
		}

		return $conflicts;
	}

	private function entries(array $section, bool $withSvg = false): array
	{
		$entries = [];

		foreach ($section as $id => $definition) {
			if ($withSvg) {
				$definition['svg'] = $this->svgMarkup($definition['image']);
				unset($definition['image']);
			}

			$entries[] = ['id' => $id, ...$definition];
		}

		return $entries;
	}

	private function svgMarkup(string $relativePath): string
	{
		$path = $this->assetPath($relativePath);

		if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'svg') {
			throw new RuntimeException("Companion base must be an SVG file: {$relativePath}");
		}

		return trim(file_get_contents($path));
	}

	private function assetPath(string $relativePath): string
	{
		$path = config('companion.assets_path').'/'.$relativePath;

		if (! isset(self::MIME_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))]) || ! is_file($path)) {
			throw new RuntimeException("Companion asset missing or unsupported: {$relativePath}");
		}

		return $path;
	}
}
