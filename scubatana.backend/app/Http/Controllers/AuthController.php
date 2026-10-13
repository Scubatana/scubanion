<?php

namespace App\Http\Controllers;

use App\Http\Requests\RequestCodeRequest;
use App\Http\Requests\VerifyCodeRequest;
use App\Http\Resources\PlayerResource;
use App\Mail\LoginCodeMail;
use App\Models\User;
use App\Services\LoginCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
	public function requestCode(RequestCodeRequest $request, LoginCodeService $codes): Response
	{
		$email = $request->validated('email');

		Mail::to($email)->send(new LoginCodeMail($codes->issue($email), config('login.code_ttl_minutes')));

		return response()->noContent();
	}

	public function verify(VerifyCodeRequest $request, LoginCodeService $codes): JsonResponse
	{
		$email = $request->validated('email');
		$user = User::where('email', $email)->first();

		if ($user === null && ! $request->boolean('terms_accepted')) {
			throw ValidationException::withMessages([
				'terms_accepted' => "Tu dois accepter les conditions d'utilisation pour créer ton compte.",
			]);
		}

		if (! $codes->verify($email, $request->validated('code'))) {
			throw ValidationException::withMessages([
				'code' => 'Ce code est incorrect ou expiré.',
			]);
		}

		$user ??= User::create([
			'email' => $email,
			'terms_accepted_at' => now(),
		]);

		$user->tokens()->delete();

		return response()->json([
			'token' => $user->createToken('mobile')->plainTextToken,
			'player' => new PlayerResource($user),
		]);
	}

	public function logout(Request $request): Response
	{
		$request->user()->currentAccessToken()->delete();

		return response()->noContent();
	}
}
