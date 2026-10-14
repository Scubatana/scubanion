<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanionResource;
use App\Services\PassageService;
use App\Services\TerminalDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PassageController extends Controller
{
	public function store(Request $request, PassageService $passages): JsonResponse
	{
		$token = $request->validate(['token' => ['required', 'string', 'max:64']])['token'];
		$passage = $passages->record($request->user(), $token);
		$display = $passages->display($passage);
		$terminal = $passage->terminal;

		dispatch(fn () => app(TerminalDisplay::class)->show($terminal, $display))->afterResponse();

		$companion = $request->user()->companion()->first();

		return response()->json([
			...$display,
			'terminal' => ['code' => $terminal->code, 'name' => $terminal->name],
			'companion' => $companion === null ? null : new CompanionResource($companion),
		]);
	}
}
