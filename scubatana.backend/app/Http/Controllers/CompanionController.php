<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanionRequest;
use App\Http\Resources\CompanionResource;
use App\Services\CompanionCatalogService;
use App\Services\CompanionService;
use Illuminate\Http\JsonResponse;

class CompanionController extends Controller
{
	public function catalog(CompanionCatalogService $catalog): JsonResponse
	{
		return response()->json($catalog->catalog());
	}

	public function update(UpdateCompanionRequest $request, CompanionService $companions): JsonResponse
	{
		$companion = $request->user()->companion;

		if ($companion === null) {
			return response()->json(['message' => 'Complète ton profil avant de personnaliser ton compagnon.'], 409);
		}

		return response()->json([
			'companion' => new CompanionResource($companions->customize($companion, $request->validated())),
		]);
	}
}
