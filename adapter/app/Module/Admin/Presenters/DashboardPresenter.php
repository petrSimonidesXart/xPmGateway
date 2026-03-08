<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Repository\JobRepository;
use App\Model\Repository\AuditLogRepository;
use App\Model\Repository\ClientRepository;

class DashboardPresenter extends BasePresenter
{
	public function __construct(
		private JobRepository $jobRepository,
		private AuditLogRepository $auditLogRepository,
		private ClientRepository $clientRepository,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		$this->template->pendingJobs = $this->jobRepository->getTable()
			->where('status', 'pending')
			->count('*');

		$this->template->failedJobs = $this->jobRepository->getTable()
			->where('status', 'failed')
			->where('finished_at > ?', new \DateTime('-24 hours'))
			->count('*');

		$this->template->totalJobsToday = $this->jobRepository->getTable()
			->where('created_at > ?', new \DateTime('today'))
			->count('*');

		$this->template->activeClients = $this->clientRepository->getTable()
			->where('is_active', true)
			->count('*');

		$this->template->recentActivity = $this->auditLogRepository->getTable()
			->order('created_at DESC')
			->limit(10)
			->fetchAll();

		$this->template->recentJobs = $this->jobRepository->getTable()
			->order('created_at DESC')
			->limit(10)
			->fetchAll();
	}
}
