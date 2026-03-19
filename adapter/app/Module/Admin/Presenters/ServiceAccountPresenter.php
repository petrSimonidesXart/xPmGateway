<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Repository\ClientRepository;
use App\Model\Repository\ServiceAccountRepository;
use App\Model\Service\EncryptionService;
use Nette\Application\UI\Form;

class ServiceAccountPresenter extends BasePresenter
{
	public function __construct(
		private ServiceAccountRepository $serviceAccountRepository,
		private ClientRepository $clientRepository,
		private EncryptionService $encryptionService,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		$this->template->accounts = $this->serviceAccountRepository->getTable()
			->order('name ASC')
			->fetchAll();
	}


	public function actionEdit(int $id): void
	{
		$this->requireAdmin();
		$account = $this->serviceAccountRepository->findById($id);
		if (!$account) {
			$this->error('Service account not found');
		}

		$this['accountForm']->setDefaults([
			'name' => $account->name,
			'username' => $account->username,
			'is_active' => $account->is_active,
		]);
	}


	public function actionCreate(): void
	{
		$this->requireAdmin();
	}


	protected function createComponentAccountForm(): Form
	{
		$form = new Form;

		$form->addText('name', 'Název:')
			->setRequired('Zadejte název.');

		$form->addText('username', 'Uživatelské jméno:')
			->setRequired('Zadejte uživatelské jméno.');

		$form->addPassword('password', 'Heslo:')
			->setRequired($this->getParameter('id') === null);

		$form->addCheckbox('is_active', 'Aktivní')
			->setDefaultValue(true);

		$form->addSubmit('send', 'Uložit');
		$form->onSuccess[] = $this->accountFormSucceeded(...);

		return $form;
	}


	public function accountFormSucceeded(Form $form, \stdClass $values): void
	{
		$this->requireAdmin();

		$data = [
			'name' => $values->name,
			'username' => $values->username,
			'is_active' => $values->is_active,
		];

		if ($values->password !== '') {
			$data['password_encrypted'] = $this->encryptionService->encrypt($values->password);
		}

		$id = $this->getParameter('id');

		if ($id) {
			$this->serviceAccountRepository->getTable()->where('id', $id)->update($data);
			$this->auditService->logAdminAction('service_account_updated', 'success', ['id' => $id]);
			$this->flashMessage('Service account upraven.');
		} else {
			if (!isset($data['password_encrypted'])) {
				$form->addError('Heslo je povinné při vytváření.');
				return;
			}
			$account = $this->serviceAccountRepository->getTable()->insert($data);
			$this->auditService->logAdminAction('service_account_created', 'success', ['id' => $account->id]);
			$this->flashMessage('Service account vytvořen.');
		}

		$this->redirect('default');
	}


	public function handleDelete(int $id): void
	{
		$this->requireAdmin();

		$account = $this->serviceAccountRepository->findById($id);
		if (!$account) {
			$this->error('Service account not found');
		}

		// Check if any client uses this service account
		$clientCount = $this->clientRepository->getTable()
			->where('service_account_id', $id)
			->count('*');

		if ($clientCount > 0) {
			$this->flashMessage('Nelze smazat — tento service account používá ' . $clientCount . ' klient(ů).', 'warning');
			$this->redirect('this');
		}

		$this->serviceAccountRepository->getTable()->where('id', $id)->delete();
		$this->auditService->logAdminAction('service_account_deleted', 'success', ['id' => $id, 'name' => $account->name]);
		$this->flashMessage('Service account smazán.');
		$this->redirect('default');
	}
}
