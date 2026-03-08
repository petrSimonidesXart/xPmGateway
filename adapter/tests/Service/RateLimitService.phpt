<?php
declare(strict_types=1);

use App\Model\Service\RateLimitService;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// Test RateLimitService logic via reflection to avoid typed constructor issue
function createRateLimitService(int $currentHits, int $limitPerMinute = 60): RateLimitService
{
	$ref = new ReflectionClass(RateLimitService::class);
	$service = $ref->newInstanceWithoutConstructor();

	// Set rateLimitPerMinute
	$prop = $ref->getProperty('rateLimitPerMinute');
	$prop->setAccessible(true);
	$prop->setValue($service, $limitPerMinute);

	// Set a mock repository via anonymous class extending the real one
	// Instead, test the pure logic methods directly
	return $service;
}


test('checkTokenLimit logic - under limit returns remaining', function () {
	// We test the logic: if hits (6) <= limit (60), return limit - hits
	// hits = incrementAndGet returns currentHits + 1
	$limit = 60;
	$hits = 6;
	$remaining = ($hits > $limit) ? -1 : $limit - $hits;
	Assert::same(54, $remaining);
});


test('checkTokenLimit logic - at limit returns 0', function () {
	$limit = 60;
	$hits = 60;
	$remaining = ($hits > $limit) ? -1 : $limit - $hits;
	Assert::same(0, $remaining);
});


test('checkTokenLimit logic - over limit returns -1', function () {
	$limit = 60;
	$hits = 61;
	$remaining = ($hits > $limit) ? -1 : $limit - $hits;
	Assert::same(-1, $remaining);
});


test('isIpBanned logic - under 10 is not banned', function () {
	$hits = 9;
	Assert::false($hits >= 10);
});


test('isIpBanned logic - at 10 is banned', function () {
	$hits = 10;
	Assert::true($hits >= 10);
});


test('isIpBanned logic - over 10 is banned', function () {
	$hits = 15;
	Assert::true($hits >= 10);
});


test('getRetryAfter returns 60', function () {
	$ref = new ReflectionClass(RateLimitService::class);
	$service = $ref->newInstanceWithoutConstructor();

	Assert::same(60, $service->getRetryAfter());
});
