<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddExportCsvReportAssignmentsTool extends AbstractMigration
{
	public function up(): void
	{
		$this->table('tools')->insert([
			[
				'name' => 'pm_export_csv_report_assignments',
				'description' => 'Export CSV z reportu přiřazených — provede export výstupu z reportu Přiřazené na základě filtru',
			],
		])->saveData();
	}


	public function down(): void
	{
		$this->execute("DELETE FROM tools WHERE name = 'pm_export_csv_report_assignments'");
	}
}
