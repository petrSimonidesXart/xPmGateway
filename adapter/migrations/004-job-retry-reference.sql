-- Reference to the original job when retrying a failed/timeout job.
ALTER TABLE `jobs`
    ADD COLUMN `retry_of_job_id` CHAR(36) NULL AFTER `timeout_seconds`,
    ADD INDEX `idx_retry_of_job_id` (`retry_of_job_id`),
    ADD CONSTRAINT `fk_jobs_retry_of` FOREIGN KEY (`retry_of_job_id`) REFERENCES `jobs` (`id`) ON DELETE SET NULL;
