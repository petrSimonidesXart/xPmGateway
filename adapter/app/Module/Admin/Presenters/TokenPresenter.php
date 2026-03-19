<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Repository\ApiTokenRepository;
use App\Model\Repository\ClientRepository;
use App\Model\Service\AuthService;
use Nette\Application\UI\Form;

class TokenPresenter extends BasePresenter
{
	public function __construct(
		private ApiTokenRepository $apiTokenRepository,
		private ClientRepository $clientRepository,
		private AuthService $authService,
	) {
		parent::__construct();
	}


	public function renderDefault(int $id): void
	{
		$client = $this->clientRepository->findById($id);
		if (!$client) {
			$this->error('Client not found');
		}

		$this->template->client = $client;
		$this->template->tokens = $this->apiTokenRepository->getTable()
			->where('client_id', $id)
			->order('created_at DESC')
			->fetchAll();
	}


	protected function createComponentGenerateTokenForm(): Form
	{
		$form = new Form;
		$form->addText('label', 'Label:')
			->setNullable();
		$form->addText('expires_at', 'Expirace (volitelně):')
			->setNullable()
			->setHtmlAttribute('type', 'date');
		$form->addSubmit('send', 'Vygenerovat token');
		$form->onSuccess[] = $this->generateTokenFormSucceeded(...);
		return $form;
	}


	public function generateTokenFormSucceeded(Form $form, \stdClass $values): void
	{
		$this->requireAdmin();

		$clientId = (int) $this->getParameter('id');
		$expiresAt = $values->expires_at ? new \DateTime($values->expires_at) : null;

		$plainToken = $this->authService->generateToken($clientId, $values->label, $expiresAt);

		$this->auditService->logAdminAction('token_created', 'success', [
			'client_id' => $clientId,
			'label' => $values->label,
		]);

		$this->flashMessage("Token vygenerován. Zkopírujte si ho, zobrazí se pouze jednou: {$plainToken}", 'warning');
		$this->redirect('this');
	}


	public function handleRevoke(int $tokenId): void
	{
		$this->requireAdmin();

		$this->apiTokenRepository->revoke($tokenId);
		$this->auditService->logAdminAction('token_revoked', 'success', ['token_id' => $tokenId]);
		$this->flashMessage('Token revokován.');
		$this->redirect('this');
	}
}
