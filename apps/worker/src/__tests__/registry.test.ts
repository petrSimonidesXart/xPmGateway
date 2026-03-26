import { describe, it, expect } from 'vitest';
import { toolRegistry } from '../tools/registry.js';

const EXPECTED_TOOLS = [
	'pm_login',
	'pm_open_project',
	'pm_open_task',
	'pm_read_task',
	'pm_create_comment',
	'pm_create_subtask',
	'pm_close_task',
	'pm_update_task',
	'pm_search_subtasks',
	'pm_search_comments',
	'pm_close_subtask',
	'pm_update_subtask',
	'pm_time_track',
	'pm_export_csv',
	'pm_export_csv_report_assignments',
	'pm_download_by_url',
];

describe('Tool Registry', () => {
	it('contains all expected PM tools', () => {
		for (const tool of EXPECTED_TOOLS) {
			expect(toolRegistry).toHaveProperty(tool);
		}
	});

	it('has exactly 16 tools registered', () => {
		expect(Object.keys(toolRegistry)).toHaveLength(16);
	});

	it('all registered tools are functions', () => {
		for (const [name, handler] of Object.entries(toolRegistry)) {
			expect(typeof handler, `${name} should be a function`).toBe('function');
		}
	});

	it('tool names use snake_case with pm_ prefix', () => {
		for (const name of Object.keys(toolRegistry)) {
			expect(name).toMatch(/^pm_[a-z_]+$/);
		}
	});
});
