import { chromium } from 'playwright';
import type { Job } from '../index.js';
import type { AdapterApi } from '../lib/api.js';
import { ScreenshotManager } from '../lib/screenshots.js';
import { VideoRecorder } from '../lib/video.js';
import { toolRegistry } from './registry.js';
import { resolveTemplates, evaluateCondition } from './templateParser.js';
import type {
	ToolContext,
	ScenarioDefinition,
	ScenarioStep,
	StepResult,
	ToolStep,
	ConditionStep,
	LoopStep,
} from './types.js';

const LEGACY_PM_BASE_URL = process.env.LEGACY_PM_BASE_URL || 'https://pm.interni-sit.cz';

/**
 * Run a complete scenario as a single job with shared browser session.
 * The scenario definition is passed in job.payload.scenario.
 */
export async function handleRunScenario(job: Job, api: AdapterApi): Promise<void> {
	const scenarioDef = job.payload.scenario as unknown as ScenarioDefinition;
	const scenarioInput = (job.payload.input ?? job.payload) as Record<string, unknown>;

	if (!scenarioDef?.steps) {
		await api.submitResult(job.id, {
			status: 'failed',
			error: 'Invalid scenario: missing steps',
		});
		return;
	}

	const screenshots = new ScreenshotManager(job.id);
	await screenshots.init();

	const recorder = new VideoRecorder(job.id);
	await recorder.init();

	const browser = await chromium.launch({ headless: true });
	const context = await browser.newContext({
		...recorder.contextOptions(),
	});
	const page = await context.newPage();
	page.setDefaultTimeout(job.timeout_seconds * 1000);

	const ctx: ToolContext = {
		page,
		baseUrl: LEGACY_PM_BASE_URL,
		job: {
			id: job.id,
			service_account: job.service_account,
			timeout_seconds: job.timeout_seconds,
		},
		api,
		screenshots,
	};

	// Context bag: stores input + step outputs
	const bag: Record<string, unknown> = {
		input: scenarioInput,
	};

	const stepResults: StepResult[] = [];
	let totalSteps = 0;

	try {
		await executeSteps(ctx, scenarioDef.steps, bag, stepResults, () => ++totalSteps);

		const allSuccess = stepResults.every((r) => r.status !== 'failed');

		await api.submitResult(job.id, {
			status: allSuccess ? 'success' : 'failed',
			result: {
				scenario: scenarioDef.name,
				steps_executed: stepResults.length,
				step_results: stepResults,
				// Collect all outputs from tool steps into a summary
				output: collectOutputs(stepResults),
			},
			error: allSuccess ? undefined : findFirstError(stepResults),
			screenshots: screenshots.getScreenshots(),
		});

		console.log(
			`[Worker] Scenario "${scenarioDef.name}" ${allSuccess ? 'completed' : 'failed'}`
			+ ` — ${stepResults.length} steps`,
		);
	} catch (error) {
		try { await screenshots.capture(page, 'scenario-error'); } catch { /* ignore */ }
		const message = error instanceof Error ? error.message : String(error);

		await api.submitResult(job.id, {
			status: 'failed',
			error: `Scenario failed at step ${totalSteps}: ${message}`,
			result: { step_results: stepResults },
			screenshots: screenshots.getScreenshots(),
		});
		throw error;
	} finally {
		const video = page.video();
		await context.close();
		await recorder.upload(video, api);
		await browser.close();
		await recorder.cleanup();
	}
}

/** Execute an array of steps, populating bag and stepResults. */
async function executeSteps(
	ctx: ToolContext,
	steps: ScenarioStep[],
	bag: Record<string, unknown>,
	stepResults: StepResult[],
	incrementCounter: () => number,
): Promise<void> {
	for (const step of steps) {
		switch (step.type) {
			case 'tool':
				await executeToolStep(ctx, step, bag, stepResults, incrementCounter);
				break;
			case 'condition':
				await executeConditionStep(ctx, step, bag, stepResults, incrementCounter);
				break;
			case 'loop':
				await executeLoopStep(ctx, step, bag, stepResults, incrementCounter);
				break;
			case 'scenario':
				// Nested scenario — for now, treat as unsupported
				stepResults.push({
					id: step.id,
					status: 'skipped',
					duration_ms: 0,
					error: 'Nested scenarios not yet supported',
				});
				break;
		}
	}
}

/** Execute a single tool step. */
async function executeToolStep(
	ctx: ToolContext,
	step: ToolStep,
	bag: Record<string, unknown>,
	stepResults: StepResult[],
	incrementCounter: () => number,
): Promise<void> {
	const stepNum = incrementCounter();
	const startTime = Date.now();

	const toolFn = toolRegistry[step.tool];
	if (!toolFn) {
		stepResults.push({
			id: step.id,
			tool: step.tool,
			status: 'failed',
			error: `Unknown tool: ${step.tool}`,
			duration_ms: 0,
		});
		throw new Error(`Unknown tool: ${step.tool}`);
	}

	// Resolve template expressions in input
	const resolvedInput = step.input
		? resolveTemplates(step.input, bag) as Record<string, unknown>
		: {};

	console.log(`[Scenario] Step ${stepNum} (${step.id}): ${step.tool}`);

	try {
		const output = await toolFn(ctx, resolvedInput);
		const durationMs = Date.now() - startTime;

		// Capture screenshot after step
		let screenshot: string | undefined;
		try {
			await ctx.screenshots.capture(ctx.page, `step-${step.id}`);
			const shots = ctx.screenshots.getScreenshots();
			screenshot = shots[shots.length - 1]?.file;
		} catch { /* ignore screenshot errors */ }

		// Store output in context bag
		bag[step.id] = { output };

		// Validate expect clause
		if (step.expect?.count !== undefined) {
			const count = (output as Record<string, unknown>).count;
			if (count !== step.expect.count) {
				const error = step.expect.error
					?? `Expected count=${step.expect.count}, got ${count}`;

				// Include results in error for disambiguation
				const results = (output as Record<string, unknown>).results;
				const detail = Array.isArray(results)
					? `. Found: ${results.map((r: Record<string, unknown>) => r.name || r.title || JSON.stringify(r)).join(', ')}`
					: '';

				stepResults.push({
					id: step.id,
					tool: step.tool,
					status: 'failed',
					output,
					error: error + detail,
					duration_ms: durationMs,
					screenshot,
				});
				throw new Error(error + detail);
			}
		}

		if (!output.success) {
			stepResults.push({
				id: step.id,
				tool: step.tool,
				status: 'failed',
				output,
				error: (output.error as string) ?? 'Tool returned success=false',
				duration_ms: durationMs,
				screenshot,
			});
			throw new Error(`Step ${step.id} (${step.tool}) failed: ${output.error ?? 'unknown error'}`);
		}

		stepResults.push({
			id: step.id,
			tool: step.tool,
			status: 'success',
			output,
			duration_ms: durationMs,
			screenshot,
		});
	} catch (error) {
		if (stepResults[stepResults.length - 1]?.id === step.id) {
			// Already recorded in stepResults above
			throw error;
		}
		const durationMs = Date.now() - startTime;
		const message = error instanceof Error ? error.message : String(error);
		stepResults.push({
			id: step.id,
			tool: step.tool,
			status: 'failed',
			error: message,
			duration_ms: durationMs,
		});
		throw error;
	}
}

/** Execute a condition step. */
async function executeConditionStep(
	ctx: ToolContext,
	step: ConditionStep,
	bag: Record<string, unknown>,
	stepResults: StepResult[],
	incrementCounter: () => number,
): Promise<void> {
	const resolved = resolveTemplates(step.if, bag) as string;
	const result = evaluateCondition(String(resolved), bag);

	console.log(`[Scenario] Condition (${step.id}): "${step.if}" → ${result}`);

	if (result) {
		await executeSteps(ctx, step.then, bag, stepResults, incrementCounter);
	} else if (step.else) {
		await executeSteps(ctx, step.else, bag, stepResults, incrementCounter);
	}
}

/** Execute a loop step. */
async function executeLoopStep(
	ctx: ToolContext,
	step: LoopStep,
	bag: Record<string, unknown>,
	stepResults: StepResult[],
	incrementCounter: () => number,
): Promise<void> {
	const items = resolveTemplates(step.over, bag);

	if (!Array.isArray(items)) {
		throw new Error(`Loop step "${step.id}": expected array for "over", got ${typeof items}`);
	}

	console.log(`[Scenario] Loop (${step.id}): ${items.length} iterations`);

	for (let i = 0; i < items.length; i++) {
		// Set loop variable in bag
		bag[step.as] = items[i];
		bag[`${step.id}_index`] = i;

		await executeSteps(ctx, step.steps, bag, stepResults, incrementCounter);
	}

	// Clean up loop variable
	delete bag[step.as];
	delete bag[`${step.id}_index`];
}

/** Collect all tool outputs into a summary object. */
function collectOutputs(stepResults: StepResult[]): Record<string, unknown> {
	const outputs: Record<string, unknown> = {};
	for (const result of stepResults) {
		if (result.output && result.status === 'success') {
			outputs[result.id] = result.output;
		}
	}
	return outputs;
}

/** Find the first error message in step results. */
function findFirstError(stepResults: StepResult[]): string | undefined {
	const failed = stepResults.find((r) => r.status === 'failed');
	return failed?.error;
}
