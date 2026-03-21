import 'dotenv/config';
import { AdapterApi } from './lib/api.js';
import { runToolStandalone } from './tools/standaloneRunner.js';
import { handleRunScenario } from './tools/scenarioRunner.js';
import { toolRegistry } from './tools/registry.js';

const api = new AdapterApi(
    process.env.ADAPTER_API_URL!,
    process.env.INTERNAL_API_SECRET!,
);

const POLL_INTERVAL = parseInt(process.env.POLL_INTERVAL_MS || '5000', 10);

export interface Job {
    id: string;
    tool_name: string;
    payload: Record<string, unknown>;
    service_account: {
        username: string;
        password: string;
    };
    attempt: number;
    timeout_seconds: number;
}

type JobHandler = (job: Job, api: AdapterApi) => Promise<void>;

async function pollLoop(): Promise<void> {
    console.log(`[Worker] Started. Polling ${process.env.ADAPTER_API_URL} every ${POLL_INTERVAL}ms`);
    console.log(`[Worker] Registered tools: ${Object.keys(toolRegistry).join(', ')}`);

    while (true) {
        try {
            const response = await api.getNextJob();
            const job: Job | null = response?.job ?? null;

            if (job) {
                console.log(`[Worker] Processing job ${job.id} (${job.tool_name}), attempt ${job.attempt}`);

                try {
                    if (job.tool_name === 'run_scenario') {
                        await handleRunScenario(job, api);
                    } else if (toolRegistry[job.tool_name]) {
                        await runToolStandalone(job, api);
                    } else {
                        await api.submitResult(job.id, {
                            status: 'failed',
                            error: `No handler for tool: ${job.tool_name}`,
                        });
                    }
                } catch (error) {
                    const message = error instanceof Error ? error.message : String(error);
                    console.error(`[Worker] Job ${job.id} failed:`, message);
                    // Only submit if not already submitted by handler
                    try {
                        await api.submitResult(job.id, {
                            status: 'failed',
                            error: message,
                        });
                    } catch {
                        // Result may have already been submitted by the handler
                    }
                }
            }
        } catch (error) {
            console.error('[Worker] Poll error:', error instanceof Error ? error.message : error);
        }

        await sleep(POLL_INTERVAL);
    }
}

function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

pollLoop().catch((err) => {
    console.error('[Worker] Fatal error:', err);
    process.exit(1);
});
