<?php
declare(strict_types=1);

namespace App\Model\Service;

use App\Model\Repository\RateLimitRepository;

class RateLimitService
{
	public function __construct(
		private RateLimitRepository $rateLimitRepository,
		private int $rateLimitPerMinute = 60,
	) {
	}


	/**
	 * Check per-token rate limit. Returns remaining requests or -1 if exceeded.
	 */
	public function checkTokenLimit(int $tokenId): int
	{
		$key = "token:{$tokenId}";
		$hits = $this->rateLimitRepository->incrementAndGet($key, 60);

		if ($hits > $this->rateLimitPerMinute) {
			return -1;
		}

		return $this->rateLimitPerMinute - $hits;
	}


	/**
	 * Check per-IP brute-force limit (10 failed auths / 15 min).
	 * Returns true if IP is banned.
	 */
	public function isIpBanned(string $ip): bool
	{
		$key = "ip_fail:{$ip}";
		$hits = $this->rateLimitRepository->getHits($key, 900); // 15 minutes
		return $hits >= 10;
	}


	/**
	 * Record a failed authentication attempt for an IP.
	 */
	public function recordFailedAuth(string $ip): void
	{
		$key = "ip_fail:{$ip}";
		$this->rateLimitRepository->incrementAndGet($key, 900);
	}


	/**
	 * Get seconds until rate limit window resets.
	 */
	public function getRetryAfter(): int
	{
		return 60;
	}
}
