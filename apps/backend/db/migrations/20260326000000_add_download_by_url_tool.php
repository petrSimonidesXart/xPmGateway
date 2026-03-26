<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDownloadByUrlTool extends AbstractMigration
{
	public function up(): void
	{
		$this->table('tools')->insert([
			[
				'name' => 'pm_download_by_url',
				'description' => 'Stáhne soubor z PM systému přes přímý odkaz — přihlásí se a vrátí soubor jako artefakt',
			],
		])->saveData();
	}


	public function down(): void
	{
		$this->execute("DELETE FROM tools WHERE name = 'pm_download_by_url'");
	}
}
