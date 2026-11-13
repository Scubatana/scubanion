<?php

namespace Tests\Feature\Monitoring;

use App\Enums\ReportStatus;
use App\Enums\TerminalStatus;
use App\Models\Activity;
use App\Models\Passage;
use App\Models\Report;
use App\Models\Terminal;
use App\Models\User;
use App\Models\UserQuest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsTest extends TestCase
{
	use RefreshDatabase;

	public function test_it_exposes_prometheus_metrics_with_live_counts(): void
	{
		$this->travelTo('2026-10-13 15:00:00');

		$completed = User::factory()->create(['pseudo' => 'Lea']);
		$reported = User::factory()->create(['pseudo' => 'Yanis']);
		User::factory()->withoutProfile()->create();

		Activity::factory()->for($completed)->create(['day' => '2026-10-13', 'steps' => 3400]);
		Activity::factory()->for($reported)->create(['day' => '2026-10-13', 'steps' => 1200]);

		$onlineTerminal = Terminal::factory()->create([
			'status' => TerminalStatus::Online,
			'last_seen_at' => now()->subSeconds(30),
		]);
		Terminal::factory()->create([
			'status' => TerminalStatus::Online,
			'last_seen_at' => now()->subMinutes(10),
		]);

		Passage::factory()->for($completed)->for($onlineTerminal)->create();
		UserQuest::factory()->for($completed)->create();
		UserQuest::factory()->for($completed)->create(['completed_at' => now()]);
		Report::create([
			'user_id' => $completed->id,
			'reported_id' => $reported->id,
			'reason' => 'behavior',
			'status' => ReportStatus::Pending,
		]);

		$response = $this->get('/metrics')->assertOk();
		$body = $response->getContent();

		$this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
		$this->assertStringContainsString('scubanion_users_total{profile_completed="true"} 2', $body);
		$this->assertStringContainsString('scubanion_users_total{profile_completed="false"} 1', $body);
		$this->assertStringContainsString('scubanion_steps_today_total 4600', $body);
		$this->assertStringContainsString('scubanion_passages_total 1', $body);
		$this->assertStringContainsString('scubanion_terminals_total 2', $body);
		$this->assertStringContainsString('scubanion_terminals_online 1', $body);
		$this->assertStringContainsString('scubanion_active_quests_total 1', $body);
		$this->assertStringContainsString('scubanion_reports_pending 1', $body);
	}

	public function test_it_enforces_bearer_token_when_configured(): void
	{
		config(['monitoring.metrics_token' => 'secret-prometheus-token']);

		$this->get('/metrics')->assertForbidden();
		$this->withHeader('Authorization', 'Bearer mauvais-jeton')->get('/metrics')->assertForbidden();
		$this->withHeader('Authorization', 'Bearer secret-prometheus-token')->get('/metrics')->assertOk();
	}

	public function test_it_allows_only_internal_nginx_scrapes_in_production_without_token(): void
	{
		config(['monitoring.metrics_token' => '']);
		$previousEnv = $this->app['env'];
		$this->app['env'] = 'production';

		try {
			$this->get('/metrics')->assertForbidden();
			$this->withHeader('X-Scubanion-Internal-Metrics', '1')->get('/metrics')->assertForbidden();
			$this->withServerVariables(['SCUBANION_INTERNAL_METRICS' => '1'])->get('/metrics')->assertOk();
		} finally {
			$this->app['env'] = $previousEnv;
		}
	}
}
