<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Seeds default data: admin user, tools, lookup tables.
 *
 * Run: vendor/bin/phinx seed:run -e production
 */
class DefaultSeeder extends AbstractSeed
{
    public function run(): void
    {
        // Admin user (password: admin123 - CHANGE IN PRODUCTION!)
        $this->table('admin_users')->insert([
            'username' => 'admin',
            'password_hash' => '$2y$12$AcwJtnrM8MzskTjS06fZv.YT2AL/oGleRxl2w.fIYFI1M2lGf3Nle',
            'role' => 'admin',
        ])->saveData();

        // Tools
        $this->table('tools')->insert([
            ['name' => 'get_job_status', 'description' => 'Zjistí stav dříve odeslaného jobu'],
            ['name' => 'list_my_recent_jobs', 'description' => 'Vypíše nedávné joby aktuálního klienta'],
            ['name' => 'pm_login', 'description' => 'Přihlášení do PM aplikace (ověření credentials)'],
            ['name' => 'pm_open_project', 'description' => 'Vyhledá projekt podle názvu a otevře ho'],
            ['name' => 'pm_open_task', 'description' => 'Vyhledá úkol v aktuálním projektu a otevře ho'],
            ['name' => 'pm_read_task', 'description' => 'Přečte detaily aktuálně otevřeného úkolu (název, popis, stav, přiřazení)'],
            ['name' => 'pm_update_task', 'description' => 'Upraví pole aktuálně otevřeného úkolu (popis, přiřazení, termín)'],
            ['name' => 'pm_close_task', 'description' => 'Dokončí (zavře) aktuálně otevřený úkol'],
            ['name' => 'pm_create_comment', 'description' => 'Přidá komentář k aktuálně otevřenému úkolu'],
            ['name' => 'pm_search_comments', 'description' => 'Vyhledá v komentářích aktuálně otevřeného úkolu'],
            ['name' => 'pm_create_subtask', 'description' => 'Vytvoří nový podúkol v aktuálně otevřeném úkolu'],
            ['name' => 'pm_search_subtasks', 'description' => 'Vyhledá v podúkolech aktuálně otevřeného úkolu'],
            ['name' => 'pm_close_subtask', 'description' => 'Zavře (dokončí) podúkol podle jeho cesty'],
            ['name' => 'pm_update_subtask', 'description' => 'Upraví podúkol podle jeho cesty'],
            ['name' => 'pm_time_track', 'description' => 'Vykáže odpracovaný čas na úkolu nebo podúkolu'],
            ['name' => 'pm_export_csv', 'description' => 'Exportuje úkoly nebo výkazy z projektu jako CSV soubor'],
            ['name' => 'pm_export_csv_report_assignments', 'description' => 'Export CSV z reportu přiřazených — provede export výstupu z reportu Přiřazené na základě filtru'],
            ['name' => 'pm_download_by_url', 'description' => 'Stáhne soubor z PM systému přes přímý odkaz — přihlásí se a vrátí soubor jako artefakt'],
            ['name' => 'run_scenario', 'description' => 'Spustí kompozitní scénář — řetěz nástrojů s podmínkami a cykly'],
        ])->saveData();

        // Lookup tables
        $this->table('pm_lookups')->insert([
            // People
            ['category' => 'people', 'shortcut' => 'AJ', 'value' => 'Aleš Janoušek', 'sort_order' => 1],
            ['category' => 'people', 'shortcut' => 'JC', 'value' => 'Jan Chmelíček', 'sort_order' => 2],
            ['category' => 'people', 'shortcut' => 'KM', 'value' => 'Karel Marek', 'sort_order' => 3],
            ['category' => 'people', 'shortcut' => 'PS', 'value' => 'Petr Simonides', 'sort_order' => 4],
            ['category' => 'people', 'shortcut' => 'SP', 'value' => 'Silvie Peková', 'sort_order' => 5],
            ['category' => 'people', 'shortcut' => 'UI', 'value' => 'Umělý Inteligent', 'description' => 'AI service account', 'sort_order' => 6],
            ['category' => 'people', 'shortcut' => 'ET', 'value' => 'El Tester', 'description' => 'Testovací účet', 'sort_order' => 7],
            // Labels
            ['category' => 'labels', 'shortcut' => 'NOVY', 'value' => 'NOVÝ', 'description' => 'Nově založený úkol, zatím nezpracovaný', 'sort_order' => 1],
            ['category' => 'labels', 'shortcut' => 'RESIT', 'value' => 'ŘEŠIT', 'description' => 'Úkol k aktivnímu řešení', 'sort_order' => 2],
            ['category' => 'labels', 'shortcut' => 'NERESIT', 'value' => 'NEŘEŠIT', 'description' => 'Odložený úkol, neřešit teď', 'sort_order' => 3],
            ['category' => 'labels', 'shortcut' => 'PREPLANOVAT', 'value' => 'PŘEPLÁNOVAT', 'description' => 'Přesunout na jiný termín', 'sort_order' => 4],
            ['category' => 'labels', 'shortcut' => 'ZKONTROLOVAT', 'value' => 'ZKONTROLOVAT', 'description' => 'Čeká na kontrolu/review', 'sort_order' => 5],
            ['category' => 'labels', 'shortcut' => 'KONZULTOVAT', 'value' => 'KONZULTOVAT', 'description' => 'Vyžaduje konzultaci s někým', 'sort_order' => 6],
            ['category' => 'labels', 'shortcut' => 'SCHUZKA', 'value' => 'SCHŮZKA', 'description' => 'Schůzka — vyžaduje termín a účastníky', 'sort_order' => 7],
            ['category' => 'labels', 'shortcut' => 'UZAVREN', 'value' => 'UZAVŘEN', 'description' => 'Dokončený/uzavřený úkol', 'sort_order' => 8],
            ['category' => 'labels', 'shortcut' => 'DUPLICITNI', 'value' => 'DUPLICITNÍ', 'description' => 'Duplicitní úkol — řeší se jinde', 'sort_order' => 9],
            ['category' => 'labels', 'shortcut' => 'STORNOVAN', 'value' => 'STORNOVÁN', 'description' => 'Zrušený úkol', 'sort_order' => 10],
            // Schedule
            ['category' => 'schedule', 'shortcut' => 'TT', 'value' => 'this_week', 'description' => 'Tento týden (pondělí–pátek)', 'sort_order' => 1],
            ['category' => 'schedule', 'shortcut' => 'PT', 'value' => 'next_week', 'description' => 'Příští týden', 'sort_order' => 2],
            ['category' => 'schedule', 'shortcut' => 'PPT', 'value' => 'after_next_week', 'description' => 'Přespříští týden', 'sort_order' => 3],
            ['category' => 'schedule', 'shortcut' => 'DNES', 'value' => 'today', 'description' => 'Dnes', 'sort_order' => 4],
            ['category' => 'schedule', 'shortcut' => 'ZITRA', 'value' => 'tomorrow', 'description' => 'Zítra', 'sort_order' => 5],
            ['category' => 'schedule', 'shortcut' => 'POZITRI', 'value' => 'day_after_tomorrow', 'description' => 'Pozítří', 'sort_order' => 6],
        ])->saveData();
    }
}
