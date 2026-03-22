<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Repository\ClientRepository;
use App\Model\Repository\JobRepository;
use App\Model\Repository\ScenarioRepository;
use App\Model\Repository\ServiceAccountRepository;
use App\Model\Repository\ToolRepository;
use App\Model\Service\SchemaValidator;
use Nette\Application\UI\Form;

class ToolPresenter extends BasePresenter
{
	private const META_TOOLS = ['get_job_status', 'list_my_recent_jobs', 'run_scenario'];
	private const STANDALONE_TOOLS = ['pm_login', 'pm_open_project'];
	private const TOOL_PREREQUISITES = [
		'pm_open_task' => ['pm_open_project'],
		'pm_read_task' => ['pm_open_project', 'pm_open_task'],
		'pm_update_task' => ['pm_open_project', 'pm_open_task'],
		'pm_close_task' => ['pm_open_project', 'pm_open_task'],
		'pm_create_comment' => ['pm_open_project', 'pm_open_task'],
		'pm_create_subtask' => ['pm_open_project', 'pm_open_task'],
		'pm_search_comments' => ['pm_open_project', 'pm_open_task'],
		'pm_search_subtasks' => ['pm_open_project', 'pm_open_task'],
		'pm_close_subtask' => ['pm_open_project', 'pm_open_task'],
		'pm_update_subtask' => ['pm_open_project', 'pm_open_task'],
		'pm_time_track' => ['pm_open_project', 'pm_open_task'],
		'pm_export_csv' => ['pm_open_project'],
	];

	public function __construct(
		private ToolRepository $toolRepository,
		private ClientRepository $clientRepository,
		private ServiceAccountRepository $serviceAccountRepository,
		private JobRepository $jobRepository,
		private ScenarioRepository $scenarioRepository,
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
		$this->template->standaloneTools = self::STANDALONE_TOOLS;
		$this->template->toolPrerequisites = self::TOOL_PREREQUISITES;
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


	/**
	 * Create a scenario pre-filled with prerequisite steps + this tool.
	 */
	public function handleCreateScenario(int $id): void
	{
		$this->requirePost();
		$this->requireAdmin();

		$tool = $this->toolRepository->findById($id);
		if (!$tool) {
			$this->error('Nástroj nenalezen');
		}

		$prereqs = self::TOOL_PREREQUISITES[$tool->name] ?? [];

		// Build step templates
		$toolInputTemplates = [
			'pm_open_project' => ['query' => '{{input.project}}'],
			'pm_open_task' => ['query' => '{{input.task}}'],
			'pm_create_comment' => ['text' => '{{input.text}}'],
			'pm_create_subtask' => ['name' => '{{input.subtask_name}}', 'assignee' => '{{input.subtask_assignee}}', 'label' => '{{input.subtask_label}}', 'schedule' => '{{input.subtask_schedule}}', 'estimate' => '{{input.subtask_estimate}}'],
			'pm_close_task' => [],
			'pm_read_task' => [],
			'pm_search_comments' => ['query' => '{{input.search_query}}'],
			'pm_search_subtasks' => ['query' => '{{input.search_query}}'],
			'pm_update_task' => ['fields' => []],
			'pm_close_subtask' => ['path_info' => '{{input.subtask_path}}'],
			'pm_update_subtask' => ['path_info' => '{{input.subtask_path}}', 'fields' => []],
			'pm_time_track' => ['hours' => '{{input.hours}}', 'date' => '{{input.date}}'],
			'pm_export_csv' => ['type' => 'tasks'],
		];

		$steps = [];
		$counter = 0;
		foreach ($prereqs as $prereqName) {
			$counter++;
			$steps[] = [
				'id' => str_replace('pm_', '', $prereqName),
				'type' => 'tool',
				'tool' => $prereqName,
				'input' => $toolInputTemplates[$prereqName] ?? [],
			];
		}
		$counter++;
		$steps[] = [
			'id' => str_replace('pm_', '', $tool->name),
			'type' => 'tool',
			'tool' => $tool->name,
			'input' => $toolInputTemplates[$tool->name] ?? [],
		];

		// Extract input variables from steps
		$stepsJson = json_encode($steps);
		preg_match_all('/\{\{input\.([a-zA-Z0-9_]+)\}\}/', $stepsJson, $matches);
		$variables = array_unique($matches[1] ?? []);

		$properties = [];
		foreach ($variables as $var) {
			$properties[$var] = ['type' => 'string', 'minLength' => 1, 'description' => ''];
		}

		$inputSchema = [
			'type' => 'object',
			'required' => $variables,
			'properties' => $properties,
			'additionalProperties' => false,
		];

		$scenarioName = 'test_' . $tool->name;
		$existing = $this->scenarioRepository->findByName($scenarioName);
		if ($existing) {
			$this->flashMessage("Scénář \"{$scenarioName}\" už existuje.");
			$this->redirect(':Admin:Scenario:detail', $existing->id);
		}

		$scenario = $this->scenarioRepository->getTable()->insert([
			'name' => $scenarioName,
			'description' => 'Test scénář pro ' . $tool->name . ': ' . $tool->description,
			'input_schema' => json_encode($inputSchema),
			'steps' => json_encode($steps),
			'is_active' => true,
		]);

		$this->auditService->logAdminAction('scenario_created_from_tool', 'success', [
			'tool_id' => $id,
			'scenario_id' => $scenario->id,
		]);

		$this->flashMessage("Scénář \"{$scenarioName}\" vytvořen s " . count($steps) . " kroky.");
		$this->redirect(':Admin:Scenario:edit', (int) $scenario->id);
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
