-- Scenarios: composable tool chains for PM automation
CREATE TABLE `scenarios` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Unique identifier, e.g. add_comment',
    `description` VARCHAR(500) NOT NULL,
    `input_schema` JSON NOT NULL COMMENT 'JSON Schema for scenario input parameters',
    `steps` JSON NOT NULL COMMENT 'Array of step definitions (tool, condition, loop)',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extend jobs table for scenario execution tracking
ALTER TABLE `jobs`
    ADD COLUMN `scenario_id` INT UNSIGNED NULL AFTER `tool_id`,
    ADD COLUMN `step_results` JSON NULL COMMENT '[{id, tool, status, output, duration_ms, screenshot}]' AFTER `screenshots`,
    ADD CONSTRAINT `fk_jobs_scenario` FOREIGN KEY (`scenario_id`) REFERENCES `scenarios` (`id`) ON DELETE SET NULL;

-- Register PM tools
INSERT INTO `tools` (`name`, `description`, `is_active`) VALUES
    ('pm_login', 'Přihlášení do PM aplikace', 1),
    ('pm_open_project', 'Vyhledá a otevře projekt podle názvu', 1),
    ('pm_open_task', 'Vyhledá a otevře úkol v projektu', 1),
    ('pm_read_task', 'Přečte detaily aktuálního úkolu', 1),
    ('pm_update_task', 'Upraví pole aktuálního úkolu', 1),
    ('pm_close_task', 'Zavře aktuální úkol', 1),
    ('pm_create_comment', 'Vytvoří komentář k aktuálnímu úkolu', 1),
    ('pm_search_comments', 'Vyhledá v komentářích aktuálního úkolu', 1),
    ('pm_create_subtask', 'Vytvoří podúkol k aktuálnímu úkolu', 1),
    ('pm_search_subtasks', 'Vyhledá v podúkolech aktuálního úkolu', 1),
    ('pm_close_subtask', 'Zavře podúkol podle path_info', 1),
    ('pm_update_subtask', 'Upraví podúkol podle path_info', 1),
    ('pm_time_track', 'Vykáže čas na úkolu/podúkolu', 1),
    ('pm_export_csv', 'Exportuje úkoly nebo výkazy jako CSV', 1),
    ('run_scenario', 'Spustí kompozitní scénář (řetěz toolů)', 1);
