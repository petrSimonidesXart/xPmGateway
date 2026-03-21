-- Update tool descriptions to Czech and more descriptive
UPDATE `tools` SET `description` = 'Přihlášení do PM aplikace (ověření credentials)' WHERE `name` = 'pm_login';
UPDATE `tools` SET `description` = 'Vyhledá projekt podle názvu a otevře ho' WHERE `name` = 'pm_open_project';
UPDATE `tools` SET `description` = 'Vyhledá úkol v aktuálním projektu a otevře ho' WHERE `name` = 'pm_open_task';
UPDATE `tools` SET `description` = 'Přečte detaily aktuálně otevřeného úkolu (název, popis, stav, přiřazení)' WHERE `name` = 'pm_read_task';
UPDATE `tools` SET `description` = 'Upraví pole aktuálně otevřeného úkolu (popis, přiřazení, termín)' WHERE `name` = 'pm_update_task';
UPDATE `tools` SET `description` = 'Dokončí (zavře) aktuálně otevřený úkol' WHERE `name` = 'pm_close_task';
UPDATE `tools` SET `description` = 'Přidá komentář k aktuálně otevřenému úkolu' WHERE `name` = 'pm_create_comment';
UPDATE `tools` SET `description` = 'Vyhledá v komentářích aktuálně otevřeného úkolu' WHERE `name` = 'pm_search_comments';
UPDATE `tools` SET `description` = 'Vytvoří nový podúkol v aktuálně otevřeném úkolu' WHERE `name` = 'pm_create_subtask';
UPDATE `tools` SET `description` = 'Vyhledá v podúkolech aktuálně otevřeného úkolu' WHERE `name` = 'pm_search_subtasks';
UPDATE `tools` SET `description` = 'Zavře (dokončí) podúkol podle jeho cesty' WHERE `name` = 'pm_close_subtask';
UPDATE `tools` SET `description` = 'Upraví podúkol podle jeho cesty' WHERE `name` = 'pm_update_subtask';
UPDATE `tools` SET `description` = 'Vykáže odpracovaný čas na úkolu nebo podúkolu' WHERE `name` = 'pm_time_track';
UPDATE `tools` SET `description` = 'Exportuje úkoly nebo výkazy z projektu jako CSV soubor' WHERE `name` = 'pm_export_csv';
UPDATE `tools` SET `description` = 'Spustí kompozitní scénář — řetěz nástrojů s podmínkami a cykly' WHERE `name` = 'run_scenario';

-- Update MCP meta-tools to Czech
UPDATE `tools` SET `description` = 'Zjistí stav dříve odeslaného jobu' WHERE `name` = 'get_job_status';
UPDATE `tools` SET `description` = 'Vypíše nedávné joby aktuálního klienta' WHERE `name` = 'list_my_recent_jobs';
