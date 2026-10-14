<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteAccountRequest;
use App\Services\AccountService;
use Illuminate\Http\Response;

class AccountController extends Controller
{
	public function destroy(DeleteAccountRequest $request, AccountService $accounts): Response
	{
		$accounts->delete($request->user());

		return response()->noContent();
	}
}
