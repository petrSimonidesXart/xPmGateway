-- Job artifacts: files produced by worker tasks (CSV exports, PDFs, etc.)

CREATE TABLE `job_artifacts` (
    `id` CHAR(36) NOT NULL PRIMARY KEY COMMENT 'UUID',
    `job_id` CHAR(36) NOT NULL,
    `filename` VARCHAR(255) NOT NULL COMMENT 'Original filename',
    `mime_type` VARCHAR(100) NOT NULL,
    `size_bytes` BIGINT UNSIGNED NOT NULL,
    `storage_path` VARCHAR(500) NOT NULL COMMENT 'Relative path within storage/artifacts/',
    `metadata` JSON NULL COMMENT 'Optional metadata (label, description...)',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
    INDEX `idx_job_id` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- New tools
INSERT INTO `tools` (`name`, `description`) VALUES
('export_tasks', 'Export tasks from a project as CSV or JSON file'),
('get_task', 'Look up a task by ID and return its details'),
('export_filtered_tasks', 'Apply a filter and export matching tasks to CSV');
