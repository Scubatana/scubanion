<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportPlayerRequest;
use App\Models\Report;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
	public function store(ReportPlayerRequest $request): JsonResponse
	{
		$comment = trim((string) $request->validated('comment'));

		Report::create([
			'user_id' => $request->user()->id,
			'reported_id' => $request->validated('player_id'),
			'reason' => $request->validated('reason'),
			'comment' => $comment === '' ? null : $comment,
		]);

		return response()->json(['message' => "Merci, ton signalement a été envoyé. L'équipe va l'examiner."], 201);
	}
}
