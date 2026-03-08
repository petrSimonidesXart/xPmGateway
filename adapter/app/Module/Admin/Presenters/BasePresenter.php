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


	protected function beforeRender(): void
	{
		parent::beforeRender();
		$this->template->isAdmin = $this->isAdmin();
		$this->template->currentUser = $this->getUser()->getIdentity();
	}
}
