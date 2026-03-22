-- Worker heartbeats: tracks when the worker process last polled for jobs.
-- Used by the admin UI status bar to show worker online/offline/busy state.

CREATE TABLE `worker_heartbeats` (
    `worker_id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `last_seen_at` DATETIME NOT NULL,
    `started_at` DATETIME NOT NULL COMMENT 'When the worker started (or restarted)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
