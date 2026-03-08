<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Repository\ClientRepository;
use App\Model\Repository\JobRepository;
use App\Model\Repository\ToolRepository;
use Nette\Application\Responses\FileResponse;

class JobPresenter extends BasePresenter
{
	public function __construct(
		private JobRepository $jobRepository,
		private ClientRepository $clientRepository,
		private ToolRepository $toolRepository,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		$query = $this->jobRepository->getTable()
			->order('created_at DESC');

		// Filters
		$status = $this->getParameter('status');
		if ($status) {
			$query->where('status', $status);
		}

		$clientId = $this->getParameter('client_id');
		if ($clientId) {
			$query->where('client_id', (int) $clientId);
		}

		$toolId = $this->getParameter('tool_id');
		if ($toolId) {
			$query->where('tool_id', (int) $toolId);
		}

		$this->template->jobs = $query->limit(100)->fetchAll();
		$this->template->clients = $this->clientRepository->getTable()->fetchPairs('id', 'name');
		$this->template->tools = $this->toolRepository->getTable()->fetchPairs('id', 'name');
		$this->template->filterStatus = $status;
		$this->template->filterClientId = $clientId;
		$this->template->filterToolId = $toolId;
	}


	public function renderDetail(string $id): void
	{
		$job = $this->jobRepository->findById($id);
		if (!$job) {
			$this->error('Job not found');
		}

		$this->template->job = $job;
		$this->template->screenshots = $job->screenshots
			? json_decode($job->screenshots, true)
			: [];
	}


	public function actionScreenshot(string $id, string $filename): void
	{
		// Sanitize filename to prevent directory traversal
		$filename = basename($filename);
		$path = __DIR__ . '/../../../../storage/screenshots/' . $id . '/' . $filename;

		if (!is_file($path)) {
			$this->error('Screenshot not found');
		}

		$this->sendResponse(new FileResponse($path, $filename, 'image/png'));
	}
}
