<?php

namespace Tests\Unit\Services;

use App\Models\Terminal;
use App\Services\MqttAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class MqttAccountsTest extends TestCase
{
	use RefreshDatabase;

	private string $directory;

	protected function setUp(): void
	{
		parent::setUp();

		$this->directory = storage_path('framework/testing/mqtt');
		config([
			'bornes.mqtt.username' => 'scubanion.api',
			'bornes.mqtt.password' => 'mot-de-passe-api',
			'bornes.mqtt.accounts_directory' => $this->directory,
		]);
	}

	protected function tearDown(): void
	{
		File::deleteDirectory($this->directory);

		parent::tearDown();
	}

	private function assertHashOf(string $password, string $hash): void
	{
		[, $algorithm, $iterations, $salt, $digest] = explode('$', $hash);

		$this->assertSame('7', $algorithm);
		$this->assertSame(MqttAccounts::SALT_BYTES, strlen(base64_decode($salt)));
		$this->assertSame(base64_decode($digest), hash_pbkdf2('sha512', $password, base64_decode($salt), (int) $iterations, 64, true));
	}

	public function test_a_hash_has_the_format_of_mosquitto_passwd(): void
	{
		$this->assertHashOf('motdepasse', MqttAccounts::hash('motdepasse'));
		$this->assertNotSame(MqttAccounts::hash('motdepasse'), MqttAccounts::hash('motdepasse'));
	}

	public function test_the_password_file_has_the_api_then_one_account_per_terminal(): void
	{
		Terminal::factory()->create(['code' => 'musee-02', 'secret' => 'un-autre-secret']);
		Terminal::factory()->create(['code' => 'chateau-01', 'secret' => 'secret-de-test-scubanion']);

		$lines = explode("\n", trim(app(MqttAccounts::class)->passwordFile()));
		$accounts = collect($lines)->mapWithKeys(fn (string $line) => [explode(':', $line, 2)[0] => explode(':', $line, 2)[1]]);

		$this->assertSame(['scubanion.api', 'chateau-01', 'musee-02'], $accounts->keys()->all());
		$this->assertHashOf('mot-de-passe-api', $accounts['scubanion.api']);
		$this->assertHashOf('eq27dNBa_0w-n0P9aCVmWRopOfEXQWMvMNDfz7MS-dc', $accounts['chateau-01']);
		$this->assertHashOf('JMHu3fsCSnrZByPSJJ63pYjxBxs5H5qC70wmnTvSAaQ', $accounts['musee-02']);
	}

	public function test_the_acl_gives_the_api_every_terminal_and_a_terminal_only_its_own_topics(): void
	{
		$this->assertSame(
			"user scubanion.api\ntopic readwrite scubanion/bornes/#\ntopic read \$SYS/#\n\npattern write scubanion/bornes/%u/etat\npattern read scubanion/bornes/%u/affichage\n",
			app(MqttAccounts::class)->aclFile(),
		);
	}

	public function test_both_files_are_written_in_the_accounts_directory(): void
	{
		Terminal::factory()->create(['code' => 'chateau-01']);

		app(MqttAccounts::class)->write();

		$this->assertStringContainsString("\nchateau-01:\$7\$", File::get("{$this->directory}/passwd"));
		$this->assertStringStartsWith('user scubanion.api', File::get("{$this->directory}/acl"));
		$this->assertFileDoesNotExist("{$this->directory}/passwd.tmp");
	}

	public function test_the_api_account_needs_a_username_and_a_password(): void
	{
		config(['bornes.mqtt.password' => '']);
		$this->expectException(RuntimeException::class);

		app(MqttAccounts::class)->passwordFile();
	}

	public function test_a_terminal_cannot_take_the_name_of_the_api_account(): void
	{
		config(['bornes.mqtt.username' => 'chateau-01']);
		Terminal::factory()->create(['code' => 'chateau-01']);
		$this->expectException(RuntimeException::class);

		app(MqttAccounts::class)->passwordFile();
	}
}
