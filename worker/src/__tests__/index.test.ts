import { describe, it, expect } from 'vitest';

describe('Worker handler registry', () => {
    it('exports Job interface and handler type', async () => {
        const mod = await import('../index.js');
        expect(mod).toBeDefined();
    });

    it('registers all PM tools in the registry', async () => {
        const { toolRegistry } = await import('../tools/registry.js');

        expect(Object.keys(toolRegistry).length).toBeGreaterThanOrEqual(14);

        // Verify key tools exist
        expect(toolRegistry.pm_login).toBeDefined();
        expect(toolRegistry.pm_open_project).toBeDefined();
        expect(toolRegistry.pm_open_task).toBeDefined();
        expect(toolRegistry.pm_read_task).toBeDefined();
        expect(toolRegistry.pm_create_comment).toBeDefined();
        expect(toolRegistry.pm_create_subtask).toBeDefined();
        expect(toolRegistry.pm_close_task).toBeDefined();
        expect(toolRegistry.pm_export_csv).toBeDefined();
        expect(toolRegistry.pm_time_track).toBeDefined();

        // All entries are functions
        for (const [name, fn] of Object.entries(toolRegistry)) {
            expect(typeof fn).toBe('function');
        }
    });
});
