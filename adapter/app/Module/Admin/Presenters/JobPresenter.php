<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Repository\ClientRepository;
use App\Model\Repository\JobRepository;
use App\Model\Repository\ServiceAccountRepository;
use App\Model\Repository\ToolRepository;
use App\Model\Service\ArtifactService;
use Nette\Application\Responses\FileResponse;
use Nette\Application\UI\Form;

class JobPresenter extends BasePresenter
{
	private const META_TOOLS = ['get_job_status', 'list_my_recent_jobs'];

	public function __construct(
		private JobRepository $jobRepository,
		private ClientRepository $clientRepository,
		private ToolRepository $toolRepository,
		private ServiceAccountRepository $serviceAccountRepository,
		private ArtifactService $artifactService,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		$countQuery = $this->jobRepository->getTable();
		$query = $this->jobRepository->getTable()
			->order('created_at DESC');

		// Filters
		$status = $this->getParameter('status');
		if ($status) {
			$query->where('status', $status);
			$countQuery->where('status', $status);
		}

		$clientId = $this->getParameter('client_id');
		if ($clientId) {
			$query->where('client_id', (int) $clientId);
			$countQuery->where('client_id', (int) $clientId);
		}

		$toolId = $this->getParameter('tool_id');
		if ($toolId) {
			$query->where('tool_id', (int) $toolId);
			$countQuery->where('tool_id', (int) $toolId);
		}

		$page = max(1, (int) ($this->getParameter('page') ?? 1));
		$itemsPerPage = 50;
		$totalCount = $countQuery->count('*');

		$paginator = new \Nette\Utils\Paginator;
		$paginator->setItemsPerPage($itemsPerPage);
		$paginator->setPage($page);
		$paginator->setItemCount($totalCount);

		$this->template->jobs = $query->limit($paginator->getLength(), $paginator->getOffset())->fetchAll();
		$this->template->paginator = $paginator;
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


	public function renderCreate(): void
	{
		$this->requireAdmin();
	}


	protected function createComponentCreateJobForm(): Form
	{
		$tools = $this->toolRepository->findAllActive();
		$toolItems = [];
		foreach ($tools as $tool) {
			if (!in_array($tool->name, self::META_TOOLS, true)) {
				$toolItems[$tool->id] = $tool->name;
			}
		}

		$accounts = $this->serviceAccountRepository->findAllActive();
		$accountItems = [];
		foreach ($accounts as $account) {
			$accountItems[$account->id] = $account->name . ' (' . $account->username . ')';
		}

		$clients = $this->clientRepository->getTable()->fetchPairs('id', 'name');

		$form = new Form;
		$form->addSelect('tool_id', 'Tool:', $toolItems)
			->setPrompt('— Vyber tool —')
			->setRequired('Vyber tool.')
			->setHtmlAttribute('data-schema-url', $this->link('toolSchema'));
		$form->addSelect('client_id', 'Klient:', $clients)
			->setPrompt('— Vyber klienta —')
			->setRequired('Vyber klienta.');
		$form->addSelect('service_account_id', 'Service Account:', $accountItems)
			->setPrompt('— Vyber service account —')
			->setRequired('Vyber service account.');
		$form->addTextArea('payload', 'Payload (JSON):')
			->setRequired('Vyplň payload.')
			->setHtmlAttribute('rows', 12)
			->setHtmlAttribute('class', 'form-control font-monospace');
		$form->addSubmit('send', 'Vytvořit job');
		$form->onSuccess[] = $this->createJobFormSucceeded(...);

		return $form;
	}


	public function createJobFormSucceeded(Form $form, \stdClass $values): void
	{
		$payload = json_decode($values->payload, true);
		if ($payload === null && $values->payload !== '{}' && $values->payload !== 'null') {
			$form->addError('Nevalidní JSON v payloadu.');
			return;
		}

		$newJob = $this->jobRepository->create([
			'client_id' => $values->client_id,
			'service_account_id' => $values->service_account_id,
			'tool_id' => $values->tool_id,
			'payload' => json_encode($payload ?? []),
			'status' => 'pending',
		]);

		$tool = $this->toolRepository->findById($values->tool_id);
		$this->auditService->logAdminAction('job_created_manual', 'success', [
			'job_id' => $newJob->id,
			'tool' => $tool?->name,
		]);

		$this->flashMessage("Job vytvořen, ID: {$newJob->id}", 'success');
		$this->redirect('detail', $newJob->id);
	}


	/**
	 * AJAX endpoint: return JSON Schema for a tool's input.
	 */
	public function actionToolSchema(): void
	{
		$toolId = (int) $this->getParameter('tool_id');
		$tool = $toolId ? $this->toolRepository->findById($toolId) : null;
		if (!$tool) {
			$this->sendJson(['schema' => null]);
		}

		$schemaFile = __DIR__ . '/../../../../../packages/contracts/'
			. str_replace('_', '-', $tool->name) . '.input.json';

		if (is_file($schemaFile)) {
			$schema = json_decode(file_get_contents($schemaFile), true);
			$this->sendJson(['schema' => $schema]);
		}

		$this->sendJson(['schema' => null]);
	}


	/**
	 * Force-cancel a stuck processing/pending job (POST only).
	 */
	public function handleCancel(string $id): void
	{
		$this->requirePost();

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
	 * Retry a failed/timeout job by creating a new one with the same parameters (POST only).
	 */
	public function handleRetry(string $id): void
	{
		$this->requirePost();

		$job = $this->jobRepository->findById($id);
		if (!$job) {
			$this->error('Job not found');
		}

		if (!in_array($job->status, ['failed', 'timeout'], true)) {
			$this->flashMessage('Opakovat lze pouze selhané nebo timeout joby.', 'warning');
			$this->redirect('detail', $id);
		}

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

		$toolName = $job->ref('tools', 'tool_id')?->name ?? '?';
		$this->flashMessage("Job $toolName zopakován, nový job ID: {$newJob->id}", 'success');

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
