<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Repository\ClientRepository;
use App\Model\Repository\JobRepository;
use App\Model\Repository\ServiceAccountRepository;
use App\Model\Repository\ToolRepository;
use App\Model\Service\SchemaValidator;
use Nette\Application\UI\Form;

class ToolPresenter extends BasePresenter
{
	private const META_TOOLS = ['get_job_status', 'list_my_recent_jobs', 'run_scenario'];

	public function __construct(
		private ToolRepository $toolRepository,
		private ClientRepository $clientRepository,
		private ServiceAccountRepository $serviceAccountRepository,
		private JobRepository $jobRepository,
		private SchemaValidator $schemaValidator,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		$this->template->tools = $this->toolRepository->getTable()
			->order('name ASC')
			->fetchAll();
		$this->template->metaTools = self::META_TOOLS;
	}


	public function renderTest(int $id): void
	{
		$this->requireAdmin();

		$tool = $this->toolRepository->findById($id);
		if (!$tool) {
			$this->error('Nástroj nenalezen');
		}

		$this->template->tool = $tool;

		// Load schema for display
		$schemaFile = __DIR__ . '/../../../../../packages/contracts/'
			. str_replace('_', '-', $tool->name) . '.input.json';
		$this->template->inputSchema = is_file($schemaFile)
			? json_decode(file_get_contents($schemaFile), true)
			: null;
	}


	protected function createComponentTestForm(): Form
	{
		$clients = $this->clientRepository->getTable()
			->where('is_active', true)
			->fetchPairs('id', 'name');

		$accounts = $this->serviceAccountRepository->findAllActive();
		$accountItems = [];
		foreach ($accounts as $account) {
			$accountItems[$account->id] = $account->name . ' (' . $account->username . ')';
		}

		$form = new Form;
		$form->addSelect('client_id', 'Klient:', $clients)
			->setPrompt('— Vyber klienta —')
			->setRequired('Vyber klienta.');
		$form->addSelect('service_account_id', 'Service Account:', $accountItems)
			->setPrompt('— Vyber service account —')
			->setRequired('Vyber service account.');
		$form->addTextArea('payload', 'Payload (JSON):')
			->setRequired('Vyplň payload.')
			->setHtmlAttribute('rows', 10)
			->setHtmlAttribute('class', 'form-control font-monospace');
		$form->addSubmit('send', 'Spustit test');
		$form->onSuccess[] = $this->testFormSucceeded(...);

		return $form;
	}


	public function testFormSucceeded(Form $form, \stdClass $values): void
	{
		$this->requireAdmin();

		$toolId = (int) $this->getParameter('id');
		$tool = $this->toolRepository->findById($toolId);
		if (!$tool) {
			$this->error('Nástroj nenalezen');
		}

		$payload = json_decode($values->payload, true);
		if ($payload === null && $values->payload !== '{}') {
			$form->addError('Nevalidní JSON v payloadu.');
			return;
		}

		// Validate against schema
		$schemaFile = str_replace('_', '-', $tool->name) . '.input.json';
		$errors = $this->schemaValidator->validate($payload ?? [], $schemaFile);
		if ($errors !== null) {
			foreach ($errors as $error) {
				$form->addError($error);
			}
			return;
		}

		$newJob = $this->jobRepository->create([
			'client_id' => $values->client_id,
			'service_account_id' => $values->service_account_id,
			'tool_id' => $toolId,
			'payload' => json_encode($payload ?? []),
			'status' => 'pending',
		]);

		$this->auditService->logAdminAction('tool_test', 'success', [
			'tool_id' => $toolId,
			'tool_name' => $tool->name,
			'job_id' => $newJob->id,
		]);

		$this->flashMessage("Test spuštěn, job ID: {$newJob->id}");
		$this->redirect(':Admin:Job:detail', $newJob->id);
	}


	public function handleToggle(int $id): void
	{
		$this->requirePost();
		$this->requireAdmin();

		$tool = $this->toolRepository->findById($id);
		if (!$tool) {
			$this->error('Tool not found');
		}

		$this->toolRepository->getTable()
			->where('id', $id)
			->update(['is_active' => !$tool->is_active]);

		$this->auditService->logAdminAction('tool_toggled', 'success', [
			'tool_id' => $id,
			'is_active' => !$tool->is_active,
		]);

		$this->flashMessage($tool->is_active ? 'Tool deaktivován.' : 'Tool aktivován.');
		$this->redirect('this');
	}
}
