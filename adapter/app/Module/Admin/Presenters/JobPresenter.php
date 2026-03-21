<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Repository\ClientRepository;
use App\Model\Repository\JobRepository;
use App\Model\Repository\ServiceAccountRepository;
use App\Model\Repository\ToolRepository;
use App\Model\Service\ArtifactService;
use App\Model\Service\SchemaValidator;
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
		private SchemaValidator $schemaValidator,
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

		$allJobs = $query->limit($paginator->getLength(), $paginator->getOffset())->fetchAll();

		// Group jobs into chains: root jobs + their children (retries/continuations)
		$jobChains = $this->buildJobChains($allJobs);

		$this->template->jobChains = $jobChains;
		$this->template->jobs = $allJobs; // keep for backward compat
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

		// Continuation chain (disambiguation flow)
		$this->template->continuedFrom = $job->continued_from_job_id
			? $this->jobRepository->findById($job->continued_from_job_id)
			: null;
		$this->template->continuedAs = $this->jobRepository->getTable()
			->where('continued_from_job_id', $id)
			->order('created_at DESC')
			->fetch() ?: null;
	}


	/**
	 * Group flat job list into chains: root jobs with their retries/continuations as children.
	 * @return array<array{root: ActiveRow, children: ActiveRow[]}>
	 */
	private function buildJobChains(array $jobs): array
	{
		$byId = [];
		foreach ($jobs as $job) {
			$byId[$job->id] = $job;
		}

		// Find parent IDs (jobs that have children in this result set)
		$childIds = new \SplObjectStorage;
		foreach ($jobs as $job) {
			$parentId = $job->retry_of_job_id ?? $job->continued_from_job_id ?? null;
			if ($parentId && isset($byId[$parentId])) {
				$childIds->attach($job);
			}
		}

		$chains = [];
		foreach ($jobs as $job) {
			if ($childIds->contains($job)) {
				continue; // skip children, they'll be nested under root
			}

			// This is a root job — collect its chain
			$children = [];
			$this->collectChildren($job->id, $byId, $jobs, $children);

			$chains[] = [
				'root' => $job,
				'children' => $children,
			];
		}

		return $chains;
	}


	private function collectChildren(string $parentId, array $byId, array $allJobs, array &$children): void
	{
		foreach ($allJobs as $job) {
			if ($job->retry_of_job_id === $parentId || $job->continued_from_job_id === $parentId) {
				$children[] = $job;
				$this->collectChildren($job->id, $byId, $allJobs, $children);
			}
		}
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

		// Validate payload against JSON Schema
		$tool = $this->toolRepository->findById($values->tool_id);
		if ($tool) {
			$schemaFile = str_replace('_', '-', $tool->name) . '.input.json';
			$errors = $this->schemaValidator->validate($payload ?? [], $schemaFile);
			if ($errors !== null) {
				foreach ($errors as $error) {
					$form->addError($error);
				}
				return;
			}
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
	 * Duplicate a job — same tool, payload, client, account — no chain link.
	 */
	public function handleDuplicate(string $id): void
	{
		$this->requirePost();

		$job = $this->jobRepository->findById($id);
		if (!$job) {
			$this->error('Job not found');
		}

		$newJob = $this->jobRepository->create([
			'client_id' => $job->client_id,
			'service_account_id' => $job->service_account_id,
			'tool_id' => $job->tool_id,
			'scenario_id' => $job->scenario_id,
			'payload' => $job->payload,
			'status' => 'pending',
			'timeout_seconds' => $job->timeout_seconds,
		]);

		$this->auditService->logAdminAction('job_duplicated', 'success', [
			'original_job_id' => $id,
			'new_job_id' => $newJob->id,
		]);

		$this->flashMessage("Úloha duplikována, nový job ID: {$newJob->id}");
		$this->redirect('detail', $newJob->id);
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
	 * Retry a failed/timeout/awaiting_input job by creating a new one.
	 * For awaiting_input: merges selected option (path_info) into payload.
	 */
	public function handleRetry(string $id): void
	{
		$this->requirePost();

		$job = $this->jobRepository->findById($id);
		if (!$job) {
			$this->error('Job not found');
		}

		if (!in_array($job->status, ['failed', 'timeout', 'awaiting_input'], true)) {
			$this->flashMessage('Opakovat lze pouze selhané, timeout nebo čekající joby.', 'warning');
			$this->redirect('detail', $id);
		}

		// Build payload — for awaiting_input, merge selected path_info
		$payload = json_decode($job->payload, true) ?? [];
		$isContinuation = $job->status === 'awaiting_input';
		$selectedPathInfo = $this->getHttpRequest()->getPost('selected_path_info');
		if ($selectedPathInfo && $isContinuation) {
			$payload['path_info'] = $selectedPathInfo;
			unset($payload['query']);
		}

		$newJob = $this->jobRepository->create([
			'client_id' => $job->client_id,
			'service_account_id' => $job->service_account_id,
			'tool_id' => $job->tool_id,
			'payload' => json_encode($payload),
			'status' => 'pending',
			'timeout_seconds' => $job->timeout_seconds,
			'retry_of_job_id' => $isContinuation ? null : $job->id,
			'continued_from_job_id' => $isContinuation ? $job->id : null,
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


	/**
	 * AJAX endpoint: returns current job status + step_results for live progress polling.
	 */
	public function actionProgress(string $id): void
	{
		$job = $this->jobRepository->findById($id);
		if (!$job) {
			$this->sendJson(['status' => 'not_found']);
		}

		$this->sendJson([
			'status' => $job->status,
			'step_results' => $job->step_results ? json_decode($job->step_results, true) : null,
		]);
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
