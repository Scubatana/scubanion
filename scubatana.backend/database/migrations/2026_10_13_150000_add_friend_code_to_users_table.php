<?php

use App\Support\FriendCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('users', function (Blueprint $table) {
			$table->string('friend_code', FriendCode::LENGTH)->nullable()->unique()->after('pseudo');
		});

		foreach (DB::table('users')->whereNull('friend_code')->pluck('id') as $id) {
			DB::table('users')->where('id', $id)->update(['friend_code' => FriendCode::generate()]);
		}
	}

	public function down(): void
	{
		Schema::table('users', function (Blueprint $table) {
			$table->dropUnique(['friend_code']);
			$table->dropColumn('friend_code');
		});
	}
};
