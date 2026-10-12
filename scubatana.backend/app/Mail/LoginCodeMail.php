<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LoginCodeMail extends Mailable
{
	public function __construct(public string $code, public int $ttlMinutes) {}

	public function envelope(): Envelope
	{
		return new Envelope(subject: 'Ton code de connexion Scubanion');
	}

	public function content(): Content
	{
		return new Content(
			view: 'mail.login-code',
			text: 'mail.login-code-text',
		);
	}
}
