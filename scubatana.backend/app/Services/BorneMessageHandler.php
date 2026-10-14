<?php

namespace App\Services;

use App\Enums\TerminalStatus;
use App\Models\Terminal;

class BorneMessageHandler
{
	public function handleState(string $terminalCode, string $message): void
	{
		$terminal = Terminal::where('code', $terminalCode)->first();
		$data = json_decode($message, true);
		$status = is_array($data) && is_string($data['status'] ?? null) ? TerminalStatus::tryFrom($data['status']) : null;

		if ($terminal === null || $status === null) {
			return;
		}

		$terminal->update([
			'status' => $status,
			'last_seen_at' => $status === TerminalStatus::Online ? now() : $terminal->last_seen_at,
			'firmware' => is_string($data['firmware'] ?? null) ? substr($data['firmware'], 0, 32) : $terminal->firmware,
		]);
	}
}
