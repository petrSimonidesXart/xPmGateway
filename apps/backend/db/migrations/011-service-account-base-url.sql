-- Move PM base URL from env to service_accounts (supports dev/prod instances)
ALTER TABLE `service_accounts`
    ADD COLUMN `base_url` VARCHAR(500) NULL COMMENT 'Base URL cílové PM aplikace' AFTER `password_encrypted`;

-- Set default URLs for existing accounts
UPDATE `service_accounts` SET `base_url` = 'https://hirola.xart.cz/pmdev/public/' WHERE `base_url` IS NULL;
