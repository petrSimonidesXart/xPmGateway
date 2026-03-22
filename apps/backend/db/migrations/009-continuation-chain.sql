-- Separate continuation chain from retry chain
ALTER TABLE `jobs`
    ADD COLUMN `continued_from_job_id` CHAR(36) NULL AFTER `retry_of_job_id`,
    ADD CONSTRAINT `fk_jobs_continued_from` FOREIGN KEY (`continued_from_job_id`) REFERENCES `jobs` (`id`) ON DELETE SET NULL,
    ADD INDEX `idx_continued_from` (`continued_from_job_id`);
