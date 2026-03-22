-- Remap verify_credentials → pm_login (same functionality)
-- Update jobs referencing verify_credentials to point to pm_login
UPDATE `jobs` SET `tool_id` = (SELECT `id` FROM `tools` WHERE `name` = 'pm_login')
    WHERE `tool_id` = (SELECT `id` FROM `tools` WHERE `name` = 'verify_credentials');

-- Update client_permissions
UPDATE `client_permissions` SET `tool_id` = (SELECT `id` FROM `tools` WHERE `name` = 'pm_login')
    WHERE `tool_id` = (SELECT `id` FROM `tools` WHERE `name` = 'verify_credentials');

-- Remove legacy test tools (no jobs or permissions reference them after remap)
DELETE FROM `tools` WHERE `name` IN (
    'create_task',
    'export_tasks',
    'get_task',
    'export_filtered_tasks',
    'verify_credentials'
);
