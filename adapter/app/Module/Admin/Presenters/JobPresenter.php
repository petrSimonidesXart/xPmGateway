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

		// Retry chain
		$this->template->retryOf = $job->retry_of_job_id
			? $this->jobRepository->findById($job->retry_of_job_id)
			: null;
		$this->template->retriedAs = $this->jobRepository->getTable()
			->where('retry_of_job_id', $id)
			->order('created_at DESC')
			->fetch() ?: null;
	}


	/**
	 * Force-cancel a stuck processing/pending job.
	 */
	public function actionCancel(string $id): void
	{
		$job = $this->jobRepository->findById($id);
		if (!$job) {
			$this->error('Job not found');
		}

		if (!in_array($job->status, ['pending', 'processing'], true)) {
			$this->flashMessage('Zrušit lze pouze pending nebo processing joby.', 'warning');
			$this->redirect('detail', $id);
		}

		$this->jobRepository->getTable()
			->where('id', $id)
			->update([
				'status' => 'failed',
				'error_message' => 'Manually cancelled from admin UI',
				'finished_at' => new \DateTime,
			]);

		$this->auditService->logAdminAction('job_cancelled', 'success', [
			'job_id' => $id,
			'tool' => $job->ref('tools', 'tool_id')?->name,
			'previous_status' => $job->status,
		]);

		$this->flashMessage('Job byl zrušen.', 'success');
		$this->redirect('detail', $id);
	}


	/**
	 * Retry a failed/timeout job by creating a new one with the same parameters.
	 */
	public function actionRetry(string $id): void
	{
		$job = $this->jobRepository->findById($id);
		if (!$job) {
			$this->error('Job not found');
		}

		if (!in_array($job->status, ['failed', 'timeout'], true)) {
			$this->flashMessage('Opakovat lze pouze selhané nebo timeout joby.', 'warning');
			$this->redirect('detail', $id);
		}

		$payload = json_decode($job->payload, true) ?: [];
		$newJob = $this->jobRepository->create([
			'client_id' => $job->client_id,
			'service_account_id' => $job->service_account_id,
			'tool_id' => $job->tool_id,
			'payload' => $job->payload,
			'status' => 'pending',
			'timeout_seconds' => $job->timeout_seconds,
			'retry_of_job_id' => $job->id,
		]);

		$this->auditService->logAdminAction('job_retried', 'success', [
			'original_job_id' => $id,
			'new_job_id' => $newJob->id,
			'tool' => $job->ref('tools', 'tool_id')?->name,
		]);

		$this->flashMessage('Job byl znovu vytvořen.', 'success');
		$this->redirect('detail', $newJob->id);
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


	/**
	 * Serve artifact inline (Content-Disposition: inline) for embedding in video player etc.
	 */
	public function actionArtifactView(string $id): void
	{
		$artifact = $this->artifactService->findById($id);
		if (!$artifact) {
			$this->error('Artifact not found');
		}

		$fullPath = $this->artifactService->getFullPath($artifact);
		if (!is_file($fullPath)) {
			$this->error('Artifact file missing');
		}

		$this->sendResponse(new FileResponse($fullPath, $artifact->filename, $artifact->mime_type, false));
	}


	/**
	 * AJAX endpoint for toast notifications — returns jobs completed/failed since given timestamp.
	 */
	public function actionNotifications(): void
	{
		$since = $this->getParameter('since');
		if (!$since) {
			$this->sendJson([]);
		}

		$jobs = $this->jobRepository->getTable()
			->where('status', ['success', 'failed'])
			->where('finished_at > ?', $since)
			->order('finished_at DESC')
			->limit(10)
			->fetchAll();

		$result = [];
		foreach ($jobs as $job) {
			$result[] = [
				'id' => $job->id,
				'tool_name' => $job->ref('tools', 'tool_id')->name ?? '?',
				'client_name' => $job->ref('clients', 'client_id')->name ?? '?',
				'status' => $job->status,
				'finished_at' => $job->finished_at->format('c'),
				'error' => $job->error_message,
			];
		}

		$this->sendJson($result);
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
