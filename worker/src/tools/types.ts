import type { Page } from 'playwright';
import type { AdapterApi } from '../lib/api.js';
import type { ScreenshotManager } from '../lib/screenshots.js';

/** Shared context passed to every tool during execution. */
export interface ToolContext {
	page: Page;
	baseUrl: string;
	job: {
		id: string;
		service_account: { username: string; password: string };
		timeout_seconds: number;
	};
	api: AdapterApi;
	screenshots: ScreenshotManager;
	/** Log a progress message (visible in job detail + worker stdout). */
	log: (message: string) => void;
}

/** Standard output returned by every tool. */
export interface ToolOutput {
	success: boolean;
	/** When true, tool needs user to select/refine input before continuing. */
	needs_input?: boolean;
	/** Options for the user to select from (used with needs_input). */
	options?: Array<Record<string, unknown>>;
	/** Description of what input is needed. */
	input_prompt?: string;
	[key: string]: unknown;
}

/** A tool function: takes context + input, returns output. */
export type ToolFunction = (ctx: ToolContext, input: Record<string, unknown>) => Promise<ToolOutput>;

/** Scenario step types. */
export type ScenarioStep = ToolStep | ConditionStep | LoopStep | ScenarioRefStep;

export interface ToolStep {
	id: string;
	type: 'tool';
	tool: string;
	input?: Record<string, unknown>;
	expect?: {
		count?: number;
		error?: string;
	};
}

export interface ConditionStep {
	id: string;
	type: 'condition';
	if: string;
	then: ScenarioStep[];
	else?: ScenarioStep[];
}

export interface LoopStep {
	id: string;
	type: 'loop';
	over: string;
	as: string;
	steps: ScenarioStep[];
}

export interface ScenarioRefStep {
	id: string;
	type: 'scenario';
	scenario: string;
	input?: Record<string, unknown>;
}

/** Full scenario definition (as stored in DB). */
export interface ScenarioDefinition {
	name: string;
	description: string;
	input_schema: Record<string, unknown>;
	steps: ScenarioStep[];
}

/** Result of a single step execution. */
export interface StepResult {
	id: string;
	tool?: string;
	status: 'success' | 'failed' | 'skipped';
	output?: Record<string, unknown>;
	error?: string;
	duration_ms: number;
	screenshot?: string;
}
