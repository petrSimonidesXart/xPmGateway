<?php
declare(strict_types=1);

namespace App\Model\Service;

use Nette\InvalidStateException;

class EncryptionService
{
	private string $key;


	public function __construct(string $encryptionKey)
	{
		if (str_starts_with($encryptionKey, 'base64:')) {
			$this->key = base64_decode(substr($encryptionKey, 7), true)
				?: throw new InvalidStateException('Invalid encryption key');
		} else {
			$this->key = $encryptionKey;
		}

		if (strlen($this->key) < 16) {
			throw new InvalidStateException('Encryption key must be at least 16 bytes');
		}
	}


	public function encrypt(string $plaintext): string
	{
		$iv = random_bytes(16);
		$ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $iv);

		if ($ciphertext === false) {
			throw new InvalidStateException('Encryption failed');
		}

		return base64_encode($iv . $ciphertext);
	}


	public function decrypt(string $encrypted): string
	{
		$data = base64_decode($encrypted, true);
		if ($data === false || strlen($data) < 17) {
			throw new InvalidStateException('Invalid encrypted data');
		}

		$iv = substr($data, 0, 16);
		$ciphertext = substr($data, 16);

		$plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $iv);

		if ($plaintext === false) {
			throw new InvalidStateException('Decryption failed');
		}

		return $plaintext;
	}
}
