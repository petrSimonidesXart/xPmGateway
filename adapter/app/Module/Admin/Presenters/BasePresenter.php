<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Service\AuditService;
use Nette\Application\UI\Presenter;

abstract class BasePresenter extends Presenter
{
	/** @inject */
	public AuditService $auditService;


	protected function startup(): void
	{
		parent::startup();

		if (!$this->getUser()->isLoggedIn()) {
			$this->redirect(':Admin:Sign:in');
		}
	}


	protected function isAdmin(): bool
	{
		return $this->getUser()->isInRole('admin');
	}


	protected function requireAdmin(): void
	{
		if (!$this->isAdmin()) {
			$this->error('Access denied', 403);
		}
	}


	public function getCsrfToken(): string
	{
		$session = $this->getSession('csrf');
		if (!isset($session->token)) {
			$session->token = bin2hex(random_bytes(16));
		}
		return $session->token;
	}


	protected function requirePost(): void
	{
		if (!$this->getHttpRequest()->isMethod('POST')) {
			$this->error('Method not allowed', 405);
		}

		$token = $this->getHttpRequest()->getPost('_csrf');
		$session = $this->getSession('csrf');
		if (!$token || !hash_equals($session->token ?? '', $token)) {
			$this->error('Invalid CSRF token', 403);
		}
	}


	protected function beforeRender(): void
	{
		parent::beforeRender();
		$this->template->isAdmin = $this->isAdmin();
		$this->template->currentUser = $this->getUser()->getIdentity();
	}
}
