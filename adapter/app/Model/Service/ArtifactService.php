<?php
declare(strict_types=1);

namespace App\Model\Service;

use App\Model\Repository\ArtifactRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Http\FileUpload;
use Nette\Utils\FileSystem;

class ArtifactService
{
	private string $storageDir;


	public function __construct(
		private ArtifactRepository $artifactRepository,
		string $storageDir,
	) {
		$this->storageDir = rtrim($storageDir, '/') . '/artifacts';
	}


	/**
	 * Store an uploaded artifact file and create a DB record.
	 */
	public function storeFromUpload(string $jobId, FileUpload $file, ?string $mimeType = null, ?array $metadata = null): ActiveRow
	{
		if (!$file->isOk()) {
			throw new \RuntimeException('File upload failed: ' . $file->getError());
		}

		$filename = $file->getSanitizedName();
		$mime = $mimeType ?? $file->getContentType() ?? 'application/octet-stream';
		$size = $file->getSize();

		// Store file: storage/artifacts/{job_id}/{uuid}_{filename}
		$artifactDir = $this->storageDir . '/' . $jobId;
		FileSystem::createDir($artifactDir);

		$storageName = bin2hex(random_bytes(8)) . '_' . $filename;
		$storagePath = $jobId . '/' . $storageName;
		$fullPath = $this->storageDir . '/' . $storagePath;

		$file->move($fullPath);

		return $this->artifactRepository->create([
			'job_id' => $jobId,
			'filename' => $filename,
			'mime_type' => $mime,
			'size_bytes' => $size,
			'storage_path' => $storagePath,
			'metadata' => $metadata ? json_encode($metadata) : null,
		]);
	}


	/**
	 * Store an artifact from raw content (for inline data from worker JSON).
	 */
	public function storeFromContent(string $jobId, string $content, string $filename, string $mimeType, ?array $metadata = null): ActiveRow
	{
		$artifactDir = $this->storageDir . '/' . $jobId;
		FileSystem::createDir($artifactDir);

		$storageName = bin2hex(random_bytes(8)) . '_' . $filename;
		$storagePath = $jobId . '/' . $storageName;
		$fullPath = $this->storageDir . '/' . $storagePath;

		FileSystem::write($fullPath, $content);

		return $this->artifactRepository->create([
			'job_id' => $jobId,
			'filename' => $filename,
			'mime_type' => $mimeType,
			'size_bytes' => strlen($content),
			'storage_path' => $storagePath,
			'metadata' => $metadata ? json_encode($metadata) : null,
		]);
	}


	/**
	 * Get full filesystem path for an artifact.
	 */
	public function getFullPath(ActiveRow $artifact): string
	{
		return $this->storageDir . '/' . $artifact->storage_path;
	}


	public function findById(string $id): ?ActiveRow
	{
		return $this->artifactRepository->findById($id);
	}


	/**
	 * @return ActiveRow[]
	 */
	public function findByJobId(string $jobId): array
	{
		return $this->artifactRepository->findByJobId($jobId);
	}


	/**
	 * Format artifacts for API response.
	 */
	public function formatForResponse(array $artifacts, string $baseUrl = ''): array
	{
		$result = [];
		foreach ($artifacts as $artifact) {
			$result[] = [
				'id' => $artifact->id,
				'filename' => $artifact->filename,
				'mime_type' => $artifact->mime_type,
				'size_bytes' => $artifact->size_bytes,
				'download_url' => $baseUrl . '/api/v1/artifacts/' . $artifact->id . '/download',
				'created_at' => $artifact->created_at->format('c'),
			];
		}
		return $result;
	}
}
