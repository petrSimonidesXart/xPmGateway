<?php
/**
 * Seeds a test client with API token and permissions.
 * Outputs the plain-text token to stdout.
 *
 * Usage: php scripts/seed-test-client.php
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$container = App\Bootstrap::boot()->createContainer();

/** @var Nette\Database\Explorer $db */
$db = $container->getByType(Nette\Database\Explorer::class);

/** @var App\Model\Service\EncryptionService $encryption */
$encryption = $container->getByType(App\Model\Service\EncryptionService::class);

/** @var App\Model\Service\AuthService $auth */
$auth = $container->getByType(App\Model\Service\AuthService::class);


// Check if test client already exists
$existingClient = $db->table('clients')->where('name', 'Test MCP Client')->fetch();

if ($existingClient) {
	// Reuse existing — generate a fresh token
	$token = $auth->generateToken($existingClient->id, 'test-script-' . date('His'));
	echo $token;
	exit(0);
}

// 1. Create service account with encrypted password
$encryptedPassword = $encryption->encrypt('test-password-123');
$sa = $db->table('service_accounts')->insert([
	'name' => 'Test Service Account',
	'username' => 'test_user',
	'password_encrypted' => $encryptedPassword,
]);
$serviceAccountId = $sa->id;

// 2. Create client
$client = $db->table('clients')->insert([
	'name' => 'Test MCP Client',
	'description' => 'Auto-created by test-mcp.sh script',
	'service_account_id' => $serviceAccountId,
	'allowed_ips' => null, // no IP restriction
]);
$clientId = $client->id;

// 3. Grant permissions for all tools
$tools = $db->table('tools')->fetchAll();
foreach ($tools as $tool) {
	$db->table('client_permissions')->insert([
		'client_id' => $clientId,
		'tool_id' => $tool->id,
	]);
}

// 4. Generate API token
$token = $auth->generateToken($clientId, 'test-script');

// Output only the token
echo $token;
