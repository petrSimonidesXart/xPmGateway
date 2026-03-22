<?php
declare(strict_types=1);

use App\Model\Service\AuthService;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// Test isIpAllowed via reflection (private method, but critical security logic)
function callIpMatchesCidr(string $ip, string $cidr): bool
{
	$auth = new ReflectionClass(AuthService::class);
	$method = $auth->getMethod('ipMatchesCidr');
	$method->setAccessible(true);

	// We need an instance - use ReflectionClass::newInstanceWithoutConstructor
	$instance = $auth->newInstanceWithoutConstructor();
	return $method->invoke($instance, $ip, $cidr);
}


test('ipMatchesCidr matches exact IP', function () {
	Assert::true(callIpMatchesCidr('10.0.0.1', '10.0.0.1'));
	Assert::false(callIpMatchesCidr('10.0.0.2', '10.0.0.1'));
});


test('ipMatchesCidr matches /32 (single host)', function () {
	Assert::true(callIpMatchesCidr('10.0.0.1', '10.0.0.1/32'));
	Assert::false(callIpMatchesCidr('10.0.0.2', '10.0.0.1/32'));
});


test('ipMatchesCidr matches /24 subnet', function () {
	Assert::true(callIpMatchesCidr('10.0.0.1', '10.0.0.0/24'));
	Assert::true(callIpMatchesCidr('10.0.0.254', '10.0.0.0/24'));
	Assert::false(callIpMatchesCidr('10.0.1.1', '10.0.0.0/24'));
});


test('ipMatchesCidr matches /16 subnet', function () {
	Assert::true(callIpMatchesCidr('192.168.0.1', '192.168.0.0/16'));
	Assert::true(callIpMatchesCidr('192.168.255.255', '192.168.0.0/16'));
	Assert::false(callIpMatchesCidr('192.169.0.1', '192.168.0.0/16'));
});


test('ipMatchesCidr matches /8 subnet', function () {
	Assert::true(callIpMatchesCidr('10.5.5.5', '10.0.0.0/8'));
	Assert::true(callIpMatchesCidr('10.255.255.255', '10.0.0.0/8'));
	Assert::false(callIpMatchesCidr('11.0.0.1', '10.0.0.0/8'));
});


test('ipMatchesCidr handles invalid inputs gracefully', function () {
	Assert::false(callIpMatchesCidr('not-an-ip', '10.0.0.0/24'));
	Assert::false(callIpMatchesCidr('10.0.0.1', 'not-a-cidr/24'));
});
