<?php
declare(strict_types=1);

use App\Model\Service\AlertService;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class TestMailer implements Mailer
{
	public int $sent = 0;
	public ?Message $lastMessage = null;

	public function send(Message $mail): void
	{
		$this->sent++;
		$this->lastMessage = $mail;
	}
}


class MockJob
{
	public string $id = 'test-job-id';
	public string $error_message = 'Something went wrong';
	public string $finished_at = '2026-03-08 23:00:00';

	public function ref(string $table, string $column): ?object
	{
		return new class {
			public string $name = 'test';
		};
	}
}


test('does not send email when alertEmail is empty', function () {
	$mailer = new TestMailer();
	$service = new AlertService($mailer, '');

	$service->sendJobFailedAlert(new MockJob());

	Assert::same(0, $mailer->sent);
});


test('sends email when alertEmail is configured', function () {
	$mailer = new TestMailer();
	$service = new AlertService($mailer, 'admin@test.com');

	$service->sendJobFailedAlert(new MockJob());

	Assert::same(1, $mailer->sent);
	Assert::notNull($mailer->lastMessage);
});
