import 'dotenv/config';
import { AdapterApi } from './lib/api.js';
import { handleCreateTask } from './handlers/createTask.js';
import { handleExportFilteredTasks } from './handlers/exportFilteredTasks.js';
import { handleExportTasks } from './handlers/exportTasks.js';
import { handleGetTask } from './handlers/getTask.js';
import { handleVerifyCredentials } from './handlers/verifyCredentials.js';

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

const handlers: Record<string, JobHandler> = {
    create_task: handleCreateTask,
    export_filtered_tasks: handleExportFilteredTasks,
    export_tasks: handleExportTasks,
    get_task: handleGetTask,
    verify_credentials: handleVerifyCredentials,
};

async function pollLoop(): Promise<void> {
    console.log(`[Worker] Started. Polling ${process.env.ADAPTER_API_URL} every ${POLL_INTERVAL}ms`);

    while (true) {
        try {
            const response = await api.getNextJob();
            const job: Job | null = response?.job ?? null;

            if (job) {
                console.log(`[Worker] Processing job ${job.id} (${job.tool_name}), attempt ${job.attempt}`);
                const handler = handlers[job.tool_name];

                if (!handler) {
                    await api.submitResult(job.id, {
                        status: 'failed',
                        error: `No handler for tool: ${job.tool_name}`,
                    });
                    continue;
                }

                try {
                    await handler(job, api);
                } catch (error) {
                    const message = error instanceof Error ? error.message : String(error);
                    console.error(`[Worker] Job ${job.id} failed:`, message);
                    await api.submitResult(job.id, {
                        status: 'failed',
                        error: message,
                    });
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
