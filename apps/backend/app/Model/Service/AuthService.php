<?php
declare(strict_types=1);

namespace App\Model\Service;

use App\Model\Repository\ApiTokenRepository;
use App\Model\Repository\ClientPermissionRepository;
use App\Model\Repository\ClientRepository;
use Nette\Database\Table\ActiveRow;

class AuthService
{
	public function __construct(
		private ApiTokenRepository $apiTokenRepository,
		private ClientRepository $clientRepository,
		private ClientPermissionRepository $permissionRepository,
	) {
	}


	/**
	 * Authenticate a request by Bearer token.
	 * Returns [client, token] or null if invalid.
	 * @return array{ActiveRow, ActiveRow}|null
	 */
	public function authenticateByToken(string $bearerToken): ?array
	{
		$hash = hash('sha256', $bearerToken);
		$token = $this->apiTokenRepository->findByHash($hash);

		if (!$token) {
			return null;
		}

		// Check expiration
		if ($token->expires_at !== null && $token->expires_at < new \DateTime) {
			return null;
		}

		$client = $this->clientRepository->findById($token->client_id);
		if (!$client || !$client->is_active) {
			return null;
		}

		// Update last used
		$this->apiTokenRepository->updateLastUsed($token->id);

		return [$client, $token];
	}


	/**
	 * Check if client has permission to use a specific tool.
	 */
	public function hasToolPermission(int $clientId, int $toolId): bool
	{
		return $this->permissionRepository->hasPermission($clientId, $toolId);
	}


	/**
	 * Check if IP is allowed for the client.
	 */
	public function isIpAllowed(ActiveRow $client, string $ip): bool
	{
		$allowedIps = $client->allowed_ips;
		if ($allowedIps === null) {
			return true; // No restriction
		}

		$cidrs = json_decode($allowedIps, true);
		if (!is_array($cidrs) || $cidrs === []) {
			return true;
		}

		foreach ($cidrs as $cidr) {
			if ($this->ipMatchesCidr($ip, $cidr)) {
				return true;
			}
		}

		return false;
	}


	private function ipMatchesCidr(string $ip, string $cidr): bool
	{
		if (!str_contains($cidr, '/')) {
			return $ip === $cidr;
		}

		[$subnet, $bits] = explode('/', $cidr, 2);
		$bits = (int) $bits;

		$ip = ip2long($ip);
		$subnet = ip2long($subnet);

		if ($ip === false || $subnet === false) {
			return false;
		}

		$mask = -1 << (32 - $bits);
		return ($ip & $mask) === ($subnet & $mask);
	}


	/**
	 * Generate a new API token for a client.
	 * Returns the plain-text token (shown only once).
	 */
	public function generateToken(int $clientId, ?string $label = null, ?\DateTime $expiresAt = null): string
	{
		$plainToken = bin2hex(random_bytes(32));
		$hash = hash('sha256', $plainToken);
		$prefix = substr($plainToken, 0, 8);

		$this->apiTokenRepository->getTable()->insert([
			'client_id' => $clientId,
			'token_hash' => $hash,
			'token_prefix' => $prefix,
			'label' => $label,
			'expires_at' => $expiresAt,
		]);

		return $plainToken;
	}
}
