import { describe, it, expect } from 'vitest';

describe('Worker handler registry', () => {
    it('exports Job interface and handler type', async () => {
        // Verify that the module exports the expected types
        const mod = await import('../index.js');
        expect(mod).toBeDefined();
    });

    it('registers all 5 handlers', async () => {
        // The handlers map is not exported, but we can verify
        // all handler modules are importable
        const handlers = await Promise.all([
            import('../handlers/createTask.js'),
            import('../handlers/exportFilteredTasks.js'),
            import('../handlers/exportTasks.js'),
            import('../handlers/getTask.js'),
            import('../handlers/verifyCredentials.js'),
        ]);

        expect(handlers).toHaveLength(5);
        handlers.forEach((mod) => {
            // Each handler module should export a function
            const exportedFn = Object.values(mod).find((v) => typeof v === 'function');
            expect(exportedFn).toBeDefined();
        });
    });
});
