<?php
declare(strict_types=1);

namespace App\Model\Facade;

class McpException extends \RuntimeException
{
	public function __construct(
		string $message,
		private int $httpCode = 400,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, 0, $previous);
	}


	public function getHttpCode(): int
	{
		return $this->httpCode;
	}
}
