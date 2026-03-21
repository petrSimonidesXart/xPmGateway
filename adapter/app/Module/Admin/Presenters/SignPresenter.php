<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Repository\AdminUserRepository;
use App\Model\Service\AuditService;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Security\SimpleIdentity;

class SignPresenter extends Presenter
{
	public function __construct(
		private AdminUserRepository $adminUserRepository,
		private AuditService $auditService,
	) {
		parent::__construct();
	}


	protected function createComponentSignInForm(): Form
	{
		$form = new Form;
		$form->addText('username', 'Uživatelské jméno:')
			->setRequired('Zadejte uživatelské jméno.');
		$form->addPassword('password', 'Heslo:')
			->setRequired('Zadejte heslo.');
		$form->addSubmit('send', 'Přihlásit');
		$form->onSuccess[] = $this->signInFormSucceeded(...);
		return $form;
	}


	public function signInFormSucceeded(Form $form, \stdClass $values): void
	{
		$user = $this->adminUserRepository->findByUsername($values->username);

		if (!$user || !password_verify($values->password, $user->password_hash)) {
			$this->auditService->logSecurityEvent('admin_login_failed', 'failed', null, [
				'username' => $values->username,
			]);
			$form->addError('Neplatné přihlašovací údaje.');
			return;
		}

		$identity = new SimpleIdentity(
			$user->id,
			$user->role,
			['username' => $user->username],
		);

		$this->getUser()->login($identity);
		$this->adminUserRepository->updateLastLogin($user->id);

		$this->auditService->logAdminAction('admin_login', 'success', ['username' => $user->username]);

		$this->redirect(':Admin:Dashboard:default');
	}


	public function actionOut(): void
	{
		$this->getUser()->logout(true);
		$this->flashMessage('Odhlášení proběhlo úspěšně.');
		$this->redirect('in');
	}
}
