<?php
declare(strict_types=1);

namespace App\Model\Service;

use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ErrorFormatter;

class SchemaValidator
{
	private Validator $validator;
	private string $contractsDir;


	public function __construct()
	{
		$this->validator = new Validator();
		$this->contractsDir = __DIR__ . '/../../../../packages/contracts';
	}


	/**
	 * Validate data against a JSON Schema contract file.
	 * Returns null on success, or array of error messages.
	 */
	public function validate(array $data, string $schemaFile): ?array
	{
		$schemaPath = $this->contractsDir . '/' . $schemaFile;
		if (!is_file($schemaPath)) {
			return ["Schema file not found: {$schemaFile}"];
		}

		$schemaContent = json_decode(file_get_contents($schemaPath));
		$dataObject = json_decode(json_encode($data));

		$result = $this->validator->validate($dataObject, $schemaContent);

		if ($result->isValid()) {
			return null;
		}

		$formatter = new ErrorFormatter();
		$errors = $formatter->format($result->error());

		return array_values(array_map(
			fn($key, $messages) => $key . ': ' . implode(', ', $messages),
			array_keys($errors),
			array_values($errors),
		));
	}
}
