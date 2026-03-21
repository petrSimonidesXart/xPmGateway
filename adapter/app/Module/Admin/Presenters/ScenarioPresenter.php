<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Repository\ClientRepository;
use App\Model\Repository\JobRepository;
use App\Model\Repository\ScenarioRepository;
use App\Model\Repository\ServiceAccountRepository;
use App\Model\Repository\ToolRepository;
use Nette\Application\UI\Form;
use Nette\Utils\Json;

class ScenarioPresenter extends BasePresenter
{
	public function __construct(
		private ScenarioRepository $scenarioRepository,
		private ToolRepository $toolRepository,
		private JobRepository $jobRepository,
		private ClientRepository $clientRepository,
		private ServiceAccountRepository $serviceAccountRepository,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		$this->template->scenarios = $this->scenarioRepository->getTable()
			->order('name ASC')
			->fetchAll();
	}


	public function renderDetail(int $id): void
	{
		$scenario = $this->scenarioRepository->findById($id);
		if (!$scenario) {
			$this->error('Scénář nenalezen');
		}

		$this->template->scenario = $scenario;
		$this->template->steps = json_decode($scenario->steps, true) ?? [];
		$this->template->inputSchema = json_decode($scenario->input_schema, true) ?? [];
		$this->template->tools = $this->toolRepository->getTable()->fetchPairs('id', 'name');

		// For the run form
		$this->template->clients = $this->clientRepository->getTable()
			->where('is_active', true)
			->fetchPairs('id', 'name');
		$this->template->serviceAccounts = $this->serviceAccountRepository->findAllActive();

		// Recent runs of this scenario
		$this->template->recentRuns = $this->jobRepository->getTable()
			->where('scenario_id', $id)
			->order('created_at DESC')
			->limit(10)
			->fetchAll();
	}


	public function actionCreate(): void
	{
		$this->requireAdmin();
	}


	public function actionEdit(int $id): void
	{
		$this->requireAdmin();
		$scenario = $this->scenarioRepository->findById($id);
		if (!$scenario) {
			$this->error('Scénář nenalezen');
		}

		$this['scenarioForm']->setDefaults([
			'name' => $scenario->name,
			'description' => $scenario->description,
			'input_schema' => json_encode(json_decode($scenario->input_schema, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
			'steps' => json_encode(json_decode($scenario->steps, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
			'is_active' => $scenario->is_active,
		]);
	}


	protected function createComponentScenarioForm(): Form
	{
		$form = new Form;
		$form->addText('name', 'Identifikátor:')
			->setRequired('Zadejte identifikátor.')
			->setHtmlAttribute('placeholder', 'add_comment');
		$form->addText('description', 'Popis:')
			->setRequired('Zadejte popis.');
		$form->addTextArea('input_schema', 'Vstupní schéma (JSON):')
			->setRequired('Zadejte vstupní schéma.')
			->setHtmlAttribute('rows', 8)
			->setHtmlAttribute('class', 'form-control font-monospace');
		$form->addTextArea('steps', 'Kroky (JSON):')
			->setRequired('Zadejte kroky.')
			->setHtmlAttribute('rows', 20)
			->setHtmlAttribute('class', 'form-control font-monospace');
		$form->addCheckbox('is_active', 'Aktivní')
			->setDefaultValue(true);
		$form->addSubmit('send', 'Uložit');
		$form->onSuccess[] = $this->scenarioFormSucceeded(...);

		return $form;
	}


	public function scenarioFormSucceeded(Form $form, \stdClass $values): void
	{
		$this->requireAdmin();

		$inputSchema = json_decode($values->input_schema, true);
		if ($inputSchema === null) {
			$form->addError('Nevalidní JSON ve vstupním schématu.');
			return;
		}

		$steps = json_decode($values->steps, true);
		if ($steps === null || !is_array($steps)) {
			$form->addError('Nevalidní JSON v krocích.');
			return;
		}

		$data = [
			'name' => $values->name,
			'description' => $values->description,
			'input_schema' => json_encode($inputSchema),
			'steps' => json_encode($steps),
			'is_active' => $values->is_active,
		];

		$id = $this->getParameter('id');

		if ($id) {
			$this->scenarioRepository->getTable()->where('id', $id)->update($data);
			$this->auditService->logAdminAction('scenario_updated', 'success', ['id' => $id, 'name' => $values->name]);
			$this->flashMessage('Scénář upraven.');
		} else {
			$scenario = $this->scenarioRepository->getTable()->insert($data);
			$this->auditService->logAdminAction('scenario_created', 'success', ['id' => $scenario->id, 'name' => $values->name]);
			$this->flashMessage('Scénář vytvořen.');
			$id = $scenario->id;
		}

		$this->redirect('detail', (int) $id);
	}


	/**
	 * Run a scenario — creates a job with tool_name=run_scenario.
	 */
	public function handleRun(int $id): void
	{
		$this->requirePost();
		$this->requireAdmin();

		$scenario = $this->scenarioRepository->findById($id);
		if (!$scenario) {
			$this->error('Scénář nenalezen');
		}

		$clientId = (int) $this->getHttpRequest()->getPost('client_id');
		$serviceAccountId = (int) $this->getHttpRequest()->getPost('service_account_id');
		$inputJson = $this->getHttpRequest()->getPost('scenario_input') ?? '{}';
		$inputData = json_decode($inputJson, true);

		if ($inputData === null) {
			$this->flashMessage('Nevalidní JSON ve vstupních datech.', 'error');
			$this->redirect('detail', $id);
		}

		// Find run_scenario tool
		$runTool = $this->toolRepository->getTable()->where('name', 'run_scenario')->fetch();
		if (!$runTool) {
			$this->flashMessage('Tool "run_scenario" nenalezen v databázi.', 'error');
			$this->redirect('detail', $id);
		}

		$payload = [
			'scenario' => [
				'name' => $scenario->name,
				'description' => $scenario->description,
				'input_schema' => json_decode($scenario->input_schema, true),
				'steps' => json_decode($scenario->steps, true),
			],
			'input' => $inputData,
		];

		$newJob = $this->jobRepository->create([
			'client_id' => $clientId,
			'service_account_id' => $serviceAccountId,
			'tool_id' => $runTool->id,
			'scenario_id' => $id,
			'payload' => json_encode($payload),
			'status' => 'pending',
		]);

		$this->auditService->logAdminAction('scenario_run', 'success', [
			'scenario_id' => $id,
			'scenario_name' => $scenario->name,
			'job_id' => $newJob->id,
		]);

		$this->flashMessage("Scénář spuštěn, job ID: {$newJob->id}");
		$this->redirect(':Admin:Job:detail', $newJob->id);
	}


	public function handleToggle(int $id): void
	{
		$this->requirePost();
		$this->requireAdmin();

		$scenario = $this->scenarioRepository->findById($id);
		if (!$scenario) {
			$this->error('Scénář nenalezen');
		}

		$this->scenarioRepository->getTable()->where('id', $id)
			->update(['is_active' => !$scenario->is_active]);

		$this->auditService->logAdminAction('scenario_toggled', 'success', [
			'id' => $id,
			'is_active' => !$scenario->is_active,
		]);

		$this->flashMessage($scenario->is_active ? 'Scénář deaktivován.' : 'Scénář aktivován.');
		$this->redirect('this');
	}
}
