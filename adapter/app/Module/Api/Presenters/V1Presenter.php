<?php
declare(strict_types=1);

namespace App\Module\Api\Presenters;

use App\Model\Facade\McpException;
use App\Model\Facade\McpFacade;
use App\Model\Repository\ClientPermissionRepository;
use App\Model\Repository\ToolRepository;
use App\Model\Service\ArtifactService;
use App\Model\Service\AuthService;
use Nette\Application\UI\Presenter;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Json;

/**
 * REST API v1 for external integrations (ChatGPT Actions, Make.com, n8n, Zapier).
 * Thin layer over McpFacade — all auth, validation, rate limiting is handled there.
 */
class V1Presenter extends Presenter
{
	public function __construct(
		private AuthService $authService,
		private McpFacade $mcpFacade,
		private ArtifactService $artifactService,
		private ToolRepository $toolRepository,
		private ClientPermissionRepository $permissionRepository,
	) {
		parent::__construct();
	}


	/**
	 * GET /api/v1/openapi.json
	 * Dynamic OpenAPI spec based on the authenticated client's permissions.
	 * Generates a ChatGPT Actions-compatible schema with only the tools
	 * this client has permission to use. Updates automatically when
	 * permissions change in Admin UI.
	 */
	public function actionOpenapi(): void
	{
		$this->requireMethod('GET');
		[$client, $token] = $this->authenticate();

		$baseUrl = rtrim($this->getHttpRequest()->getUrl()->getBaseUrl(), '/');

		// Get tools this client has access to
		$permittedToolIds = $this->permissionRepository->getPermittedToolIds($client->id);
		$allTools = $this->toolRepository->findAllActive();

		$tools = [];
		foreach ($allTools as $tool) {
			if (isset($permittedToolIds[$tool->id])) {
				$tools[] = $tool;
			}
		}

		// Build paths for permitted tools
		$paths = [];
		$contractsDir = __DIR__ . '/../../../../../packages/contracts';
		$hasArtifactTools = false;

		foreach ($tools as $tool) {
			// Skip meta-tools — these are handled by dedicated endpoints
			if (in_array($tool->name, ['get_job_status', 'list_my_recent_jobs'], true)) {
				continue;
			}

			// Check if tool produces artifacts (has artifact in output schema)
			$outputFile = $contractsDir . '/' . str_replace('_', '-', $tool->name) . '.output.json';
			if (is_file($outputFile) && str_contains(file_get_contents($outputFile), 'artifact')) {
				$hasArtifactTools = true;
			}

			$schemaFile = $contractsDir . '/' . str_replace('_', '-', $tool->name) . '.input.json';
			// Decode as object tree to preserve {} vs [] distinction in OpenAPI JSON
			$inputSchema = is_file($schemaFile)
				? json_decode(file_get_contents($schemaFile))
				: (object) ['type' => 'object', 'properties' => new \stdClass];

			$operationId = lcfirst(str_replace('_', '', ucwords($tool->name, '_')));

			$paths['/api/v1/tools/' . $tool->name] = [
				'post' => [
					'operationId' => $operationId,
					'summary' => $tool->description,
					'description' => 'Execute the ' . $tool->name . ' tool. If response has mode=queued, poll getJobStatus with the returned job_id.',
					'security' => [['bearerAuth' => []]],
					'requestBody' => [
						'required' => true,
						'content' => [
							'application/json' => [
								'schema' => $inputSchema,
							],
						],
					],
					'responses' => [
						'200' => [
							'description' => 'Tool execution result',
							'content' => [
								'application/json' => [
									'schema' => ['$ref' => '#/components/schemas/JobResult'],
								],
							],
						],
						'401' => ['description' => 'Invalid or missing API token'],
						'403' => ['description' => 'Permission denied for this tool'],
						'422' => ['description' => 'Input validation error'],
						'429' => ['description' => 'Rate limit exceeded'],
					],
				],
			];
		}

		// Always include job status endpoint
		$paths['/api/v1/jobs/{id}'] = [
			'get' => [
				'operationId' => 'getJobStatus',
				'summary' => 'Check job status and retrieve results',
				'description' => 'Use when a tool call returns mode=queued. Poll this endpoint until status is success or failed.',
				'security' => [['bearerAuth' => []]],
				'parameters' => [[
					'name' => 'id',
					'in' => 'path',
					'required' => true,
					'schema' => ['type' => 'string'],
					'description' => 'Job UUID from the previous tool call',
				]],
				'responses' => [
					'200' => [
						'description' => 'Current job status with result data',
						'content' => [
							'application/json' => [
								'schema' => ['$ref' => '#/components/schemas/JobStatus'],
							],
						],
					],
					'404' => ['description' => 'Job not found'],
				],
			],
		];

		// Include artifact download only if client has artifact-producing tools
		if ($hasArtifactTools) {
			$paths['/api/v1/artifacts/{id}/download'] = [
				'get' => [
					'operationId' => 'downloadArtifact',
					'summary' => 'Download an artifact file',
					'description' => 'Download a file produced by a tool (CSV export, report, etc).',
					'security' => [['bearerAuth' => []]],
					'parameters' => [[
						'name' => 'id',
						'in' => 'path',
						'required' => true,
						'schema' => ['type' => 'string'],
						'description' => 'Artifact UUID from the job result',
					]],
					'responses' => [
						'200' => [
							'description' => 'File content with appropriate Content-Type header',
							'content' => [
								'application/octet-stream' => [
									'schema' => ['type' => 'string', 'format' => 'binary'],
								],
							],
						],
						'404' => ['description' => 'Artifact not found'],
					],
				],
			];
		}

		// Build component schemas — all fields have descriptions for ChatGPT compatibility
		$schemas = [
			'JobResult' => [
				'type' => 'object',
				'description' => 'Result of a tool execution',
				'properties' => [
					'mode' => [
						'type' => 'string',
						'enum' => ['done', 'queued'],
						'description' => 'done = result is ready, queued = use getJobStatus to poll',
					],
					'job_id' => [
						'type' => 'string',
						'description' => 'UUID of the created job',
					],
					'status' => [
						'type' => 'string',
						'enum' => ['pending', 'processing', 'success', 'failed'],
						'description' => 'Current job status',
					],
					'result' => [
						'type' => 'object',
						'description' => 'Tool-specific output data',
						'properties' => [
							'message' => [
								'type' => 'string',
								'description' => 'Human-readable result message',
							],
						],
					],
				],
			],
			'JobStatus' => [
				'type' => 'object',
				'description' => 'Status of a previously submitted job',
				'properties' => [
					'status' => [
						'type' => 'string',
						'enum' => ['pending', 'processing', 'success', 'failed', 'timeout'],
						'description' => 'Current job status',
					],
					'result' => [
						'type' => 'object',
						'description' => 'Tool-specific output data',
						'properties' => [
							'message' => [
								'type' => 'string',
								'description' => 'Human-readable result message',
							],
						],
					],
					'error' => [
						'type' => 'string',
						'description' => 'Error message if status is failed',
					],
					'finished_at' => [
						'type' => 'string',
						'description' => 'ISO 8601 timestamp when the job finished',
					],
				],
			],
		];

		// Add Artifact schema only if needed
		if ($hasArtifactTools) {
			$schemas['JobResult']['properties']['artifacts'] = [
				'type' => 'array',
				'description' => 'Output files produced by the tool',
				'items' => ['$ref' => '#/components/schemas/Artifact'],
			];
			$schemas['JobStatus']['properties']['artifacts'] = [
				'type' => 'array',
				'description' => 'Output files produced by the tool',
				'items' => ['$ref' => '#/components/schemas/Artifact'],
			];
			$schemas['Artifact'] = [
				'type' => 'object',
				'description' => 'A downloadable file produced by a tool',
				'properties' => [
					'id' => ['type' => 'string', 'description' => 'Artifact UUID'],
					'filename' => ['type' => 'string', 'description' => 'Original filename'],
					'mime_type' => ['type' => 'string', 'description' => 'MIME type of the file'],
					'size_bytes' => ['type' => 'integer', 'description' => 'File size in bytes'],
					'download_url' => ['type' => 'string', 'description' => 'URL to download the file'],
				],
			];
		}

		$spec = [
			'openapi' => '3.1.0',
			'info' => [
				'title' => 'PM Gateway — ' . $client->name,
				'description' => 'API for client "' . $client->name . '". Shows only tools this client has permission to use.',
				'version' => '1.0.0',
			],
			'servers' => [
				['url' => $baseUrl],
			],
			'components' => [
				'securitySchemes' => [
					'bearerAuth' => [
						'type' => 'http',
						'scheme' => 'bearer',
					],
				],
				'schemas' => $schemas,
			],
			'paths' => $paths ?: new \stdClass,
		];

		$this->getHttpResponse()->setContentType('application/json');
		$this->sendJson($spec);
	}


	/**
	 * POST /api/v1/tools/{toolName}
	 * Universal endpoint: execute any tool by name.
	 */
	public function actionTool(string $toolName): void
	{
		$this->requireMethod('POST');
		[$client, $token] = $this->authenticate();
		$params = $this->readJsonBody();

		try {
			$result = $this->mcpFacade->handleToolCall(
				$client, $token, $toolName, $params, $this->getClientIp(),
			);
			$this->enrichWithArtifacts($result);
			$this->sendJson($result);
		} catch (McpException $e) {
			$this->sendErrorJson($e->getMessage(), $e->getHttpCode());
		}
	}


	/**
	 * GET /api/v1/jobs/{id}
	 * Get job status with artifacts.
	 */
	public function actionJobStatus(string $id): void
	{
		$this->requireMethod('GET');
		[$client, $token] = $this->authenticate();

		try {
			$result = $this->mcpFacade->handleToolCall(
				$client, $token, 'get_job_status', ['job_id' => $id], $this->getClientIp(),
			);
			$this->enrichWithArtifacts($result, $id);
			$this->sendJson($result);
		} catch (McpException $e) {
			$this->sendErrorJson($e->getMessage(), $e->getHttpCode());
		}
	}


	/**
	 * GET /api/v1/jobs
	 * List recent jobs.
	 */
	public function actionJobs(): void
	{
		$this->requireMethod('GET');
		[$client, $token] = $this->authenticate();

		$params = [
			'limit' => (int) ($this->getHttpRequest()->getQuery('limit') ?? 10),
		];
		$status = $this->getHttpRequest()->getQuery('status');
		if ($status) {
			$params['status'] = $status;
		}
		$toolName = $this->getHttpRequest()->getQuery('tool_name');
		if ($toolName) {
			$params['tool_name'] = $toolName;
		}

		try {
			$result = $this->mcpFacade->handleToolCall(
				$client, $token, 'list_my_recent_jobs', $params, $this->getClientIp(),
			);
			$this->sendJson($result);
		} catch (McpException $e) {
			$this->sendErrorJson($e->getMessage(), $e->getHttpCode());
		}
	}


	/**
	 * GET /api/v1/artifacts/{id}/download
	 * Download an artifact file.
	 */
	public function actionArtifactDownload(string $id): void
	{
		$this->requireMethod('GET');
		[$client, $token] = $this->authenticate();

		$artifact = $this->artifactService->findById($id);
		if (!$artifact) {
			$this->sendErrorJson('Artifact not found', 404);
			return;
		}

		// Verify the artifact belongs to a job owned by this client
		$job = $artifact->ref('jobs', 'job_id');
		if (!$job || $job->client_id !== $client->id) {
			$this->sendErrorJson('Artifact not found', 404);
			return;
		}

		$fullPath = $this->artifactService->getFullPath($artifact);
		if (!is_file($fullPath)) {
			$this->sendErrorJson('Artifact file missing', 404);
			return;
		}

		$response = $this->getHttpResponse();
		$response->setContentType($artifact->mime_type);
		$response->setHeader('Content-Disposition', 'attachment; filename="' . $artifact->filename . '"');
		$response->setHeader('Content-Length', (string) $artifact->size_bytes);

		readfile($fullPath);
		$this->terminate();
	}


	/**
	 * Enrich a result with artifact info when a job_id is available.
	 */
	private function enrichWithArtifacts(array &$result, ?string $jobId = null): void
	{
		$jobId ??= $result['job_id'] ?? null;
		if (!$jobId) {
			return;
		}

		$artifacts = $this->artifactService->findByJobId($jobId);
		if ($artifacts) {
			$baseUrl = rtrim($this->getHttpRequest()->getUrl()->getBaseUrl(), '/');
			$result['artifacts'] = $this->artifactService->formatForResponse($artifacts, $baseUrl);
		}
	}


	/**
	 * @return array{ActiveRow, ActiveRow}
	 */
	private function authenticate(): array
	{
		$authHeader = $this->getHttpRequest()->getHeader('Authorization');
		if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
			$this->sendErrorJson('Missing or invalid Authorization header', 401);
		}

		$bearerToken = substr($authHeader, 7);
		$auth = $this->authService->authenticateByToken($bearerToken);
		if (!$auth) {
			$this->sendErrorJson('Invalid token', 401);
		}

		return $auth;
	}


	private function readJsonBody(): array
	{
		$body = file_get_contents('php://input');
		if (!$body) {
			return [];
		}

		try {
			return Json::decode($body, forceArrays: true);
		} catch (\Throwable) {
			$this->sendErrorJson('Invalid JSON body', 400);
		}
	}


	private function requireMethod(string $method): void
	{
		if ($this->getHttpRequest()->getMethod() !== $method) {
			$this->sendErrorJson('Method not allowed', 405);
		}
	}


	private function getClientIp(): string
	{
		return $this->getHttpRequest()->getRemoteAddress() ?? '0.0.0.0';
	}


	private function sendErrorJson(string $message, int $httpCode): never
	{
		$this->getHttpResponse()->setCode($httpCode);
		$this->sendJson(['error' => $message]);
	}
}
