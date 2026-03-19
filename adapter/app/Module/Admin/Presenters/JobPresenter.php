<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Repository\ClientRepository;
use App\Model\Repository\JobRepository;
use App\Model\Repository\ToolRepository;
use App\Model\Service\ArtifactService;
use Nette\Application\Responses\FileResponse;

class JobPresenter extends BasePresenter
{
	public function __construct(
		private JobRepository $jobRepository,
		private ClientRepository $clientRepository,
		private ToolRepository $toolRepository,
		private ArtifactService $artifactService,
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
		$this->template->artifacts = $this->artifactService->findByJobId($id);

		// Calculate duration
		$this->template->duration = null;
		if ($job->started_at && $job->finished_at) {
			$diff = $job->finished_at->diff($job->started_at);
			$seconds = $diff->s + $diff->i * 60 + $diff->h * 3600;
			$this->template->duration = $seconds;
		}
	}


	public function actionArtifactDownload(string $id): void
	{
		$artifact = $this->artifactService->findById($id);
		if (!$artifact) {
			$this->error('Artifact not found');
		}

		$fullPath = $this->artifactService->getFullPath($artifact);
		if (!is_file($fullPath)) {
			$this->error('Artifact file missing');
		}

		$this->sendResponse(new FileResponse($fullPath, $artifact->filename, $artifact->mime_type));
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
