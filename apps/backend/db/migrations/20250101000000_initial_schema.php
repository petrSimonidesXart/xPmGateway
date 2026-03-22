<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Consolidated initial schema — combines migrations 001–011.
 *
 * For existing databases that already have all SQL migrations applied,
 * mark this as done: vendor/bin/phinx migrate -e production
 * (Phinx will skip it if phinx_log already records it.)
 */
final class InitialSchema extends AbstractMigration
{
    public function up(): void
    {
        // admin_users
        $this->table('admin_users', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('username', 'string', ['limit' => 100])
            ->addColumn('password_hash', 'string', ['limit' => 255])
            ->addColumn('role', 'enum', ['values' => ['admin', 'reader'], 'default' => 'admin'])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('last_login_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['username'], ['unique' => true])
            ->create();

        // service_accounts
        $this->table('service_accounts', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('name', 'string', ['limit' => 200])
            ->addColumn('username', 'string', ['limit' => 200])
            ->addColumn('password_encrypted', 'text')
            ->addColumn('base_url', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->create();

        // clients
        $this->table('clients', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('name', 'string', ['limit' => 200])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('service_account_id', 'integer', ['signed' => false])
            ->addColumn('allowed_ips', 'json', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('service_account_id', 'service_accounts', 'id')
            ->create();

        // api_tokens
        $this->table('api_tokens', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('client_id', 'integer', ['signed' => false])
            ->addColumn('token_hash', 'string', ['limit' => 64])
            ->addColumn('token_prefix', 'string', ['limit' => 8])
            ->addColumn('label', 'string', ['limit' => 200, 'null' => true])
            ->addColumn('expires_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('revoked_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('last_used_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('client_id', 'clients', 'id')
            ->addIndex(['token_hash'])
            ->create();

        // tools
        $this->table('tools', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('description', 'string', ['limit' => 500])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        // client_permissions
        $this->table('client_permissions', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('client_id', 'integer', ['signed' => false])
            ->addColumn('tool_id', 'integer', ['signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('client_id', 'clients', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('tool_id', 'tools', 'id', ['delete' => 'CASCADE'])
            ->addIndex(['client_id', 'tool_id'], ['unique' => true, 'name' => 'uq_client_tool'])
            ->create();

        // scenarios (must be before jobs — FK dependency)
        $this->table('scenarios', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('description', 'string', ['limit' => 500])
            ->addColumn('input_schema', 'json')
            ->addColumn('steps', 'json')
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        // jobs
        $this->table('jobs', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('client_id', 'integer', ['signed' => false])
            ->addColumn('service_account_id', 'integer', ['signed' => false])
            ->addColumn('tool_id', 'integer', ['signed' => false])
            ->addColumn('scenario_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('payload', 'json')
            ->addColumn('status', 'enum', [
                'values' => ['pending', 'processing', 'success', 'failed', 'timeout', 'awaiting_input'],
                'default' => 'pending',
            ])
            ->addColumn('result', 'json', ['null' => true])
            ->addColumn('error_message', 'text', ['null' => true])
            ->addColumn('screenshots', 'json', ['null' => true])
            ->addColumn('step_results', 'json', ['null' => true])
            ->addColumn('awaiting_input_context', 'json', ['null' => true])
            ->addColumn('attempts', 'integer', ['default' => 0])
            ->addColumn('max_attempts', 'integer', ['default' => 3])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('started_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('finished_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('timeout_seconds', 'integer', ['default' => 120])
            ->addColumn('retry_of_job_id', 'char', ['limit' => 36, 'null' => true])
            ->addColumn('continued_from_job_id', 'char', ['limit' => 36, 'null' => true])
            ->addForeignKey('client_id', 'clients', 'id')
            ->addForeignKey('service_account_id', 'service_accounts', 'id')
            ->addForeignKey('tool_id', 'tools', 'id')
            ->addForeignKey('scenario_id', 'scenarios', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('retry_of_job_id', 'jobs', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('continued_from_job_id', 'jobs', 'id', ['delete' => 'SET_NULL'])
            ->addIndex(['status', 'created_at'], ['name' => 'idx_status_created'])
            ->addIndex(['retry_of_job_id'])
            ->addIndex(['continued_from_job_id'])
            ->create();

        // job_artifacts
        $this->table('job_artifacts', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('job_id', 'char', ['limit' => 36])
            ->addColumn('filename', 'string', ['limit' => 255])
            ->addColumn('mime_type', 'string', ['limit' => 100])
            ->addColumn('size_bytes', 'biginteger', ['signed' => false])
            ->addColumn('storage_path', 'string', ['limit' => 500])
            ->addColumn('metadata', 'json', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('job_id', 'jobs', 'id', ['delete' => 'CASCADE'])
            ->addIndex(['job_id'])
            ->create();

        // audit_log
        $this->table('audit_log', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('client_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('client_name', 'string', ['limit' => 200, 'default' => ''])
            ->addColumn('api_token_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('tool_name', 'string', ['limit' => 100, 'default' => ''])
            ->addColumn('action', 'string', ['limit' => 100])
            ->addColumn('payload', 'json', ['null' => true])
            ->addColumn('result_status', 'string', ['limit' => 50])
            ->addColumn('result_data', 'json', ['null' => true])
            ->addColumn('job_id', 'char', ['limit' => 36, 'null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45])
            ->addColumn('user_agent', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('duration_ms', 'integer', ['null' => true])
            ->addForeignKey('client_id', 'clients', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('api_token_id', 'api_tokens', 'id', ['delete' => 'SET_NULL'])
            ->addForeignKey('job_id', 'jobs', 'id', ['delete' => 'SET_NULL'])
            ->addIndex(['created_at'])
            ->addIndex(['client_id', 'action'])
            ->addIndex(['action'])
            ->create();

        // rate_limits
        $this->table('rate_limits', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('key', 'string', ['limit' => 200])
            ->addColumn('hits', 'integer', ['default' => 0])
            ->addColumn('window_start', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['key'])
            ->create();

        // worker_heartbeats
        $this->table('worker_heartbeats', ['id' => false, 'primary_key' => 'worker_id'])
            ->addColumn('worker_id', 'string', ['limit' => 64])
            ->addColumn('last_seen_at', 'datetime')
            ->addColumn('started_at', 'datetime')
            ->create();

        // pm_lookups
        $this->table('pm_lookups', ['id' => false, 'primary_key' => 'id'])
            ->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('category', 'string', ['limit' => 50])
            ->addColumn('shortcut', 'string', ['limit' => 20])
            ->addColumn('value', 'string', ['limit' => 200])
            ->addColumn('description', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('sort_order', 'integer', ['default' => 0])
            ->addIndex(['category', 'shortcut'], ['unique' => true, 'name' => 'uq_category_shortcut'])
            ->create();
    }

    public function down(): void
    {
        $this->table('pm_lookups')->drop()->save();
        $this->table('worker_heartbeats')->drop()->save();
        $this->table('audit_log')->drop()->save();
        $this->table('job_artifacts')->drop()->save();
        $this->table('jobs')->drop()->save();
        $this->table('client_permissions')->drop()->save();
        $this->table('scenarios')->drop()->save();
        $this->table('tools')->drop()->save();
        $this->table('api_tokens')->drop()->save();
        $this->table('clients')->drop()->save();
        $this->table('service_accounts')->drop()->save();
        $this->table('rate_limits')->drop()->save();
        $this->table('admin_users')->drop()->save();
    }
}
