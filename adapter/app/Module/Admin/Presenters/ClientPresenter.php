<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Repository\ClientRepository;
use App\Model\Repository\ClientPermissionRepository;
use App\Model\Repository\ServiceAccountRepository;
use App\Model\Repository\ToolRepository;
use Nette\Application\UI\Form;

class ClientPresenter extends BasePresenter
{
	public function __construct(
		private ClientRepository $clientRepository,
		private ClientPermissionRepository $permissionRepository,
		private ServiceAccountRepository $serviceAccountRepository,
		private ToolRepository $toolRepository,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		$this->template->clients = $this->clientRepository->getTable()
			->order('name ASC')
			->fetchAll();
	}


	public function renderDetail(int $id): void
	{
		$client = $this->clientRepository->findById($id);
		if (!$client) {
			$this->error('Client not found');
		}
		$this->template->client = $client;
		$this->template->permissions = $this->permissionRepository->getPermittedToolIds($id);
		$this->template->tools = $this->toolRepository->getTable()->fetchAll();
	}


	public function actionCreate(): void
	{
		$this->requireAdmin();
	}


	public function actionEdit(int $id): void
	{
		$this->requireAdmin();
		$client = $this->clientRepository->findById($id);
		if (!$client) {
			$this->error('Client not found');
		}

		$this['clientForm']->setDefaults([
			'name' => $client->name,
			'description' => $client->description,
			'service_account_id' => $client->service_account_id,
			'is_active' => $client->is_active,
			'allowed_ips' => $client->allowed_ips ? implode("\n", json_decode($client->allowed_ips, true)) : '',
		]);

		// Set tool permissions
		$permissions = $this->permissionRepository->getPermittedToolIds($client->id);
		$this['clientForm']->setDefaults(['tools' => array_keys($permissions)]);
	}


	protected function createComponentClientForm(): Form
	{
		$form = new Form();

		$form->addText('name', 'Název:')
			->setRequired('Zadejte název klienta.');

		$form->addTextArea('description', 'Popis:');

		$serviceAccounts = $this->serviceAccountRepository->getTable()
			->where('is_active', true)
			->fetchPairs('id', 'name');
		$form->addSelect('service_account_id', 'Service Account:', $serviceAccounts)
			->setRequired('Vyberte service account.');

		$form->addCheckbox('is_active', 'Aktivní')
			->setDefaultValue(true);

		$form->addTextArea('allowed_ips', 'IP whitelist (CIDR, jeden na řádek):');

		$tools = $this->toolRepository->getTable()->fetchPairs('id', 'name');
		$form->addCheckboxList('tools', 'Oprávnění:', $tools);

		$form->addSubmit('send', 'Uložit');
		$form->onSuccess[] = $this->clientFormSucceeded(...);

		return $form;
	}


	public function clientFormSucceeded(Form $form, \stdClass $values): void
	{
		$this->requireAdmin();

		$allowedIps = trim($values->allowed_ips) !== ''
			? json_encode(array_filter(array_map('trim', explode("\n", $values->allowed_ips))))
			: null;

		$data = [
			'name' => $values->name,
			'description' => $values->description ?: null,
			'service_account_id' => $values->service_account_id,
			'is_active' => $values->is_active,
			'allowed_ips' => $allowedIps,
		];

		$id = $this->getParameter('id');

		if ($id) {
			$this->clientRepository->getTable()->where('id', $id)->update($data);
			$this->permissionRepository->syncPermissions((int) $id, $values->tools);
			$this->auditService->logAdminAction('client_updated', 'success', ['client_id' => $id]);
			$this->flashMessage('Klient upraven.');
		} else {
			$client = $this->clientRepository->getTable()->insert($data);
			$this->permissionRepository->syncPermissions($client->id, $values->tools);
			$this->auditService->logAdminAction('client_created', 'success', ['client_id' => $client->id]);
			$this->flashMessage('Klient vytvořen.');
			$id = $client->id;
		}

		$this->redirect('detail', ['id' => $id]);
	}
}
