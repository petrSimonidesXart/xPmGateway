<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Repository\AdminUserRepository;
use Nette\Application\UI\Form;

class UserPresenter extends BasePresenter
{
	public function __construct(
		private AdminUserRepository $adminUserRepository,
	) {
		parent::__construct();
	}


	protected function startup(): void
	{
		parent::startup();
		$this->requireAdmin();
	}


	public function renderDefault(): void
	{
		$this->template->users = $this->adminUserRepository->getTable()
			->order('username ASC')
			->fetchAll();
	}


	public function actionCreate(): void
	{
	}


	public function actionEdit(int $id): void
	{
		$user = $this->adminUserRepository->findById($id);
		if (!$user) {
			$this->error('User not found');
		}

		$this['userForm']->setDefaults([
			'username' => $user->username,
			'role' => $user->role,
			'is_active' => $user->is_active,
		]);
	}


	protected function createComponentUserForm(): Form
	{
		$form = new Form;

		$form->addText('username', 'Uživatelské jméno:')
			->setRequired('Zadejte uživatelské jméno.');

		$form->addPassword('password', 'Heslo:')
			->setRequired($this->getParameter('id') === null);

		$form->addSelect('role', 'Role:', [
			'admin' => 'Admin',
			'reader' => 'Reader',
		])->setRequired();

		$form->addCheckbox('is_active', 'Aktivní')
			->setDefaultValue(true);

		$form->addSubmit('send', 'Uložit');
		$form->onSuccess[] = $this->userFormSucceeded(...);

		return $form;
	}


	public function userFormSucceeded(Form $form, \stdClass $values): void
	{
		$data = [
			'username' => $values->username,
			'role' => $values->role,
			'is_active' => $values->is_active,
		];

		if ($values->password !== '') {
			$data['password_hash'] = password_hash($values->password, PASSWORD_BCRYPT);
		}

		$id = $this->getParameter('id');

		if ($id) {
			$this->adminUserRepository->getTable()->where('id', $id)->update($data);
			$this->auditService->logAdminAction('admin_user_updated', 'success', ['id' => $id]);
			$this->flashMessage('Uživatel upraven.');
		} else {
			if (!isset($data['password_hash'])) {
				$form->addError('Heslo je povinné při vytváření.');
				return;
			}
			$this->adminUserRepository->getTable()->insert($data);
			$this->auditService->logAdminAction('admin_user_created', 'success', ['username' => $values->username]);
			$this->flashMessage('Uživatel vytvořen.');
		}

		$this->redirect('default');
	}
}
