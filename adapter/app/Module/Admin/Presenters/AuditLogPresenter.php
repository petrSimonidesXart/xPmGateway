<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Repository\AuditLogRepository;
use App\Model\Repository\ClientRepository;

class AuditLogPresenter extends BasePresenter
{
	public function __construct(
		private AuditLogRepository $auditLogRepository,
		private ClientRepository $clientRepository,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		$clientId = $this->getParameter('client_id') ? (int) $this->getParameter('client_id') : null;
		$action = $this->getParameter('action');
		$dateFrom = $this->getParameter('date_from') ? new \DateTime($this->getParameter('date_from')) : null;
		$dateTo = $this->getParameter('date_to') ? new \DateTime($this->getParameter('date_to') . ' 23:59:59') : null;

		$this->template->logs = $this->auditLogRepository->findFiltered(
			$clientId, $action, $dateFrom, $dateTo, 100, 0,
		)->fetchAll();

		$this->template->clients = $this->clientRepository->getTable()->fetchPairs('id', 'name');
		$this->template->filterClientId = $clientId;
		$this->template->filterAction = $action;
		$this->template->filterDateFrom = $this->getParameter('date_from');
		$this->template->filterDateTo = $this->getParameter('date_to');
	}
}
