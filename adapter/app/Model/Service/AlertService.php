<?php
declare(strict_types=1);

namespace App\Model\Service;

use Nette\Mail\Message;
use Nette\Mail\Mailer;

class AlertService
{
	public function __construct(
		private Mailer $mailer,
		private string $alertEmail,
	) {
	}


	public function sendJobFailedAlert(object $job): void
	{
		if ($this->alertEmail === '') {
			return;
		}

		$mail = new Message();
		$mail->setFrom('pm-gateway@pm-gateway.local', 'PM Gateway')
			->addTo($this->alertEmail)
			->setSubject("PM Gateway: Job failed [{$job->id}]")
			->setBody(implode("\n", [
				"Job ID: {$job->id}",
				"Tool: {$job->ref('tools', 'tool_id')?->name}",
				"Client: {$job->ref('clients', 'client_id')?->name}",
				"Error: {$job->error_message}",
				"Time: {$job->finished_at}",
			]));

		try {
			$this->mailer->send($mail);
		} catch (\Throwable $e) {
			\Tracy\Debugger::log($e, \Tracy\ILogger::WARNING);
		}
	}
}
