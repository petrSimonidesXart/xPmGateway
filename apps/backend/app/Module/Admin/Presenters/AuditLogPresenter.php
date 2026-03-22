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
		$action = $this->getParameter('audit_action') ?: null;
		$dateFrom = $this->getParameter('date_from') ? new \DateTime($this->getParameter('date_from')) : null;
		$dateTo = $this->getParameter('date_to') ? new \DateTime($this->getParameter('date_to') . ' 23:59:59') : null;

		$page = max(1, (int) ($this->getParameter('page') ?? 1));
		$itemsPerPage = 50;
		$totalCount = $this->auditLogRepository->countFiltered($clientId, $action, $dateFrom, $dateTo);

		$paginator = new \Nette\Utils\Paginator;
		$paginator->setItemsPerPage($itemsPerPage);
		$paginator->setPage($page);
		$paginator->setItemCount($totalCount);

		$this->template->logs = $this->auditLogRepository->findFiltered(
			$clientId, $action, $dateFrom, $dateTo, $paginator->getLength(), $paginator->getOffset(),
		)->fetchAll();

		$this->template->paginator = $paginator;
		$this->template->clients = $this->clientRepository->getTable()->fetchPairs('id', 'name');
		$this->template->filterClientId = $clientId;
		$this->template->filterAction = $action;
		$this->template->filterDateFrom = $this->getParameter('date_from');
		$this->template->filterDateTo = $this->getParameter('date_to');
	}
}
