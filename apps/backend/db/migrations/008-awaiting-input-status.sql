-- Add 'awaiting_input' status for tools that need disambiguation
ALTER TABLE `jobs`
    MODIFY COLUMN `status` ENUM('pending', 'processing', 'success', 'failed', 'timeout', 'awaiting_input') NOT NULL DEFAULT 'pending',
    ADD COLUMN `awaiting_input_context` JSON NULL COMMENT 'Options for disambiguation when status=awaiting_input' AFTER `step_results`;
