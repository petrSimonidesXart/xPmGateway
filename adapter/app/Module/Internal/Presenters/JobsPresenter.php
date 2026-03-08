<?php
declare(strict_types=1);

namespace App\Module\Internal\Presenters;

use App\Model\Facade\JobFacade;
use App\Model\Facade\McpException;
use Nette\Application\UI\Presenter;
use Nette\Utils\Json;

/**
 * Internal API for the Playwright worker.
 * Secured by shared secret token.
 */
class JobsPresenter extends Presenter
{
	public function __construct(
		private JobFacade $jobFacade,
		private string $internalApiSecret,
	) {
		parent::__construct();
	}


	protected function startup(): void
	{
		parent::startup();
		$this->authenticateInternal();
	}


	/**
	 * GET /api/internal/jobs/next
	 * Returns the next pending job for the worker.
	 */
	public function actionNext(): void
	{
		$job = $this->jobFacade->getNextJobForWorker();

		if (!$job) {
			$this->sendJson(['job' => null]);
			return;
		}

		$this->sendJson(['job' => $job]);
	}


	/**
	 * POST /api/internal/jobs/{id}/result
	 * Worker submits job result.
	 */
	public function actionResult(string $id): void
	{
		$body = Json::decode(file_get_contents('php://input'), forceArrays: true);

		$status = $body['status'] ?? 'failed';
		$result = $body['result'] ?? null;
		$error = $body['error'] ?? null;
		$screenshots = $body['screenshots'] ?? null;

		try {
			$this->jobFacade->handleJobResult($id, $status, $result, $error, $screenshots);
			$this->sendJson(['ok' => true]);
		} catch (McpException $e) {
			$this->getHttpResponse()->setCode($e->getHttpCode());
			$this->sendJson(['error' => $e->getMessage()]);
		}
	}


	private function authenticateInternal(): void
	{
		$authHeader = $this->getHttpRequest()->getHeader('Authorization');
		$expectedToken = 'Bearer ' . $this->internalApiSecret;

		if ($authHeader !== $expectedToken) {
			$this->getHttpResponse()->setCode(401);
			$this->sendJson(['error' => 'Unauthorized']);
		}
	}
}
