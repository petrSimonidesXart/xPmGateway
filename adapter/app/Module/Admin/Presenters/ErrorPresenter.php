<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use Nette\Application\BadRequestException;
use Nette\Application\UI\Presenter;

class ErrorPresenter extends Presenter
{
	public function renderDefault(\Throwable $exception): void
	{
		if ($exception instanceof BadRequestException) {
			$code = $exception->getHttpCode();
		} else {
			$code = 500;
			\Tracy\Debugger::log($exception, \Tracy\ILogger::EXCEPTION);
		}

		$this->template->code = $code;
		$this->template->message = match ($code) {
			403 => 'Přístup zamítnut.',
			404 => 'Stránka nenalezena.',
			default => 'Nastala chyba.',
		};

		if ($this->isAjax()) {
			$this->getHttpResponse()->setCode($code);
			$this->sendJson(['error' => $this->template->message]);
		}
	}
}
