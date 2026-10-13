<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\CompanionResource;
use App\Http\Resources\PlayerResource;
use App\Services\AccountService;
use App\Services\CompanionService;
use App\Services\StepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MeController extends Controller
{
	public function show(Request $request, StepService $steps): JsonResponse
	{
		$user = $request->user();
		$companion = $user->companion;

		return response()->json([
			'player' => new PlayerResource($user),
			'companion' => $companion ? new CompanionResource($companion) : null,
			'today' => $steps->today($user),
			'streak' => $steps->streak($user),
		]);
	}

	public function updateProfile(UpdateProfileRequest $request, CompanionService $companions): JsonResponse
	{
		$user = $request->user();
		$user->update([
			'pseudo' => $request->validated('pseudo'),
			'birth_date' => $request->validated('birth_date'),
			'gender' => $request->validated('gender'),
			'ambition' => $request->validated('ambition'),
		]);

		return response()->json([
			'player' => new PlayerResource($user),
			'companion' => new CompanionResource($companions->createFor($user)),
		]);
	}

	public function destroy(DeleteAccountRequest $request, AccountService $accounts): Response
	{
		$accounts->delete($request->user());

		return response()->noContent();
	}
}
