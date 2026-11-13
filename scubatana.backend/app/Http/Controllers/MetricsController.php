<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Models\Activity;
use App\Models\Passage;
use App\Models\Report;
use App\Models\Terminal;
use App\Models\User;
use App\Models\UserQuest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
	/**
	 * Expose application metrics in Prometheus text exposition format.
	 */
	public function index(Request $request): Response
	{
		$this->authorizeScrape($request);

		$usersCompleted = User::whereNotNull('pseudo')->count();
		$usersIncomplete = User::whereNull('pseudo')->count();
		$stepsToday = (int) Activity::whereDate('day', now()->toDateString())->sum('steps');
		$passagesTotal = Passage::count();
		$terminals = Terminal::orderBy('code')->get(['id', 'code', 'status', 'last_seen_at']);
		$terminalsOnline = $terminals->filter(fn (Terminal $terminal) => $terminal->isOnline())->count();
		$activeQuests = UserQuest::activeAt(now())->count();
		$pendingReports = Report::where('status', ReportStatus::Pending)->count();

		$lines = [
			'# HELP scubanion_users_total Nombre de comptes joueurs inscrits.',
			'# TYPE scubanion_users_total gauge',
			"scubanion_users_total{profile_completed=\"true\"} {$usersCompleted}",
			"scubanion_users_total{profile_completed=\"false\"} {$usersIncomplete}",
			'# HELP scubanion_steps_today_total Total des pas enregistrés aujourd\'hui.',
			'# TYPE scubanion_steps_today_total gauge',
			"scubanion_steps_today_total {$stepsToday}",
			'# HELP scubanion_passages_total Nombre total de passages en borne NFC.',
			'# TYPE scubanion_passages_total counter',
			"scubanion_passages_total {$passagesTotal}",
			'# HELP scubanion_terminals_total Nombre total de bornes déclarées.',
			'# TYPE scubanion_terminals_total gauge',
			"scubanion_terminals_total {$terminals->count()}",
			'# HELP scubanion_terminals_online Nombre de bornes actuellement en ligne.',
			'# TYPE scubanion_terminals_online gauge',
			"scubanion_terminals_online {$terminalsOnline}",
			'# HELP scubanion_terminal_online Signal de vie de chaque borne : 1 en ligne, 0 hors ligne (BOR-08).',
			'# TYPE scubanion_terminal_online gauge',
			...$terminals->map(fn (Terminal $terminal) => sprintf('scubanion_terminal_online{code="%s"} %d', $terminal->code, $terminal->isOnline() ? 1 : 0))->all(),
			'# HELP scubanion_active_quests_total Nombre de quêtes joueurs actuellement actives.',
			'# TYPE scubanion_active_quests_total gauge',
			"scubanion_active_quests_total {$activeQuests}",
			'# HELP scubanion_reports_pending Nombre de signalements en attente de traitement.',
			'# TYPE scubanion_reports_pending gauge',
			"scubanion_reports_pending {$pendingReports}",
		];

		return response(implode("\n", $lines)."\n", 200, [
			'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
		]);
	}

	private function authorizeScrape(Request $request): void
	{
		// Paramètre FastCGI posé par le vhost Nginx interne (port 9091) : un en-tête HTTP ne peut pas le forger.
		if ($request->server('SCUBANION_INTERNAL_METRICS') === '1') {
			return;
		}

		$token = (string) config('monitoring.metrics_token', '');

		if ($token !== '') {
			abort_unless(hash_equals($token, (string) $request->bearerToken()), 403, 'Accès refusé.');

			return;
		}

		abort_unless(app()->environment('local', 'testing'), 403, 'Accès refusé.');
	}
}
