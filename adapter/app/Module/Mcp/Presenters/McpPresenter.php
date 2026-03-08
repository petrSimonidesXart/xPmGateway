<?php
declare(strict_types=1);

namespace App\Module\Mcp\Presenters;

use App\Model\Facade\McpException;
use App\Model\Facade\McpFacade;
use App\Model\Repository\ToolRepository;
use App\Model\Service\AuthService;
use App\Model\Service\AuditService;
use App\Model\Service\RateLimitService;
use Nette\Application\UI\Presenter;
use Nette\Http\IResponse;
use Nette\Utils\Json;

/**
 * MCP Gateway endpoint.
 * Implements JSON-RPC over SSE transport for MCP protocol.
 */
class McpPresenter extends Presenter
{
	public function __construct(
		private AuthService $authService,
		private McpFacade $mcpFacade,
		private AuditService $auditService,
		private RateLimitService $rateLimitService,
		private ToolRepository $toolRepository,
	) {
		parent::__construct();
	}


	public function actionDefault(): void
	{
		$method = $this->getHttpRequest()->getMethod();

		if ($method === 'GET') {
			$this->processSse();
			return;
		}

		if ($method === 'POST') {
			$this->processJsonRpc();
			return;
		}

		$this->sendJsonResponse(['error' => 'Method not allowed'], IResponse::S405_MethodNotAllowed);
	}


	private function processSse(): void
	{
		$response = $this->getHttpResponse();
		$response->setContentType('text/event-stream');
		$response->setHeader('Cache-Control', 'no-cache');
		$response->setHeader('Connection', 'keep-alive');
		$response->setHeader('X-Accel-Buffering', 'no');

		// Send initial SSE event with endpoint URL
		$endpointUrl = $this->link('//default');
		echo "event: endpoint\n";
		echo "data: {$endpointUrl}\n\n";
		flush();

		// Keep connection alive for SSE
		$timeout = 300; // 5 minutes
		$start = time();
		while (time() - $start < $timeout) {
			echo ": keepalive\n\n";
			flush();
			if (connection_aborted()) {
				break;
			}
			sleep(15);
		}

		$this->terminate();
	}


	private function processJsonRpc(): void
	{
		$body = file_get_contents('php://input');
		$request = Json::decode($body, forceArrays: true);

		if (!isset($request['method'])) {
			$this->sendJsonRpcError(null, -32600, 'Invalid request');
			return;
		}

		$id = $request['id'] ?? null;
		$method = $request['method'];
		$params = $request['params'] ?? [];

		try {
			$result = match ($method) {
				'initialize' => $this->processInitialize($params),
				'tools/list' => $this->processToolsList(),
				'tools/call' => $this->processToolsCall($params),
				default => throw new McpException("Method not found: {$method}", 404),
			};

			$this->sendJsonRpcResponse($id, $result);
		} catch (McpException $e) {
			$code = match ($e->getHttpCode()) {
				401 => -32001,
				403 => -32003,
				404 => -32601,
				422 => -32602,
				429 => -32029,
				default => -32000,
			};
			$this->sendJsonRpcError($id, $code, $e->getMessage());
		} catch (\Throwable $e) {
			\Tracy\Debugger::log($e);
			$this->sendJsonRpcError($id, -32603, 'Internal error');
		}
	}


	private function processInitialize(array $params): array
	{
		return [
			'protocolVersion' => '2024-11-05',
			'capabilities' => [
				'tools' => new \stdClass(),
			],
			'serverInfo' => [
				'name' => 'pm-gateway',
				'version' => '1.0.0',
			],
		];
	}


	private function processToolsList(): array
	{
		$tools = $this->toolRepository->findAllActive();
		$result = [];

		foreach ($tools as $tool) {
			$schemaFile = __DIR__ . '/../../../../../packages/contracts/'
				. str_replace('_', '-', $tool->name) . '.input.json';

			$inputSchema = is_file($schemaFile)
				? json_decode(file_get_contents($schemaFile), true)
				: ['type' => 'object', 'properties' => new \stdClass()];

			$result[] = [
				'name' => $tool->name,
				'description' => $tool->description,
				'inputSchema' => $inputSchema,
			];
		}

		return ['tools' => $result];
	}


	private function processToolsCall(array $params): array
	{
		$toolName = $params['name'] ?? '';
		$arguments = $params['arguments'] ?? [];

		// Authenticate
		$authHeader = $this->getHttpRequest()->getHeader('Authorization');
		if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
			throw new McpException('Missing or invalid authorization', 401);
		}

		$bearerToken = substr($authHeader, 7);
		$clientIp = $this->getHttpRequest()->getRemoteAddress() ?? '0.0.0.0';

		// Check IP ban
		if ($this->rateLimitService->isIpBanned($clientIp)) {
			$this->auditService->logSecurityEvent('ip_banned', 'denied', null, ['ip' => $clientIp]);
			throw new McpException('Too many failed attempts', 429);
		}

		$auth = $this->authService->authenticateByToken($bearerToken);
		if (!$auth) {
			$this->rateLimitService->recordFailedAuth($clientIp);
			$this->auditService->logSecurityEvent('auth_failed', 'denied', null, ['ip' => $clientIp]);
			throw new McpException('Invalid token', 401);
		}

		[$client, $token] = $auth;

		$result = $this->mcpFacade->handleToolCall($client, $token, $toolName, $arguments, $clientIp);

		return [
			'content' => [
				[
					'type' => 'text',
					'text' => Json::encode($result),
				],
			],
		];
	}


	private function sendJsonRpcResponse(?string $id, array $result): void
	{
		$this->sendJsonResponse([
			'jsonrpc' => '2.0',
			'id' => $id,
			'result' => $result,
		]);
	}


	private function sendJsonRpcError(?string $id, int $code, string $message): void
	{
		$this->sendJsonResponse([
			'jsonrpc' => '2.0',
			'id' => $id,
			'error' => [
				'code' => $code,
				'message' => $message,
			],
		]);
	}


	private function sendJsonResponse(array $data, int $httpCode = 200): never
	{
		$this->getHttpResponse()->setCode($httpCode);
		$this->sendJson($data);
	}
}
