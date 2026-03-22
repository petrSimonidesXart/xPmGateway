import type { Locator } from 'playwright';
import type { ToolContext } from '../types.js';

/** Default timeout for individual UI operations (not the whole job). */
const STEP_TIMEOUT = 10_000;

/**
 * Safely click a locator. Returns false + logs if not found within timeout.
 */
export async function safeClick(
	ctx: ToolContext,
	locator: Locator,
	description: string,
	timeout = STEP_TIMEOUT,
): Promise<boolean> {
	try {
		await locator.waitFor({ state: 'visible', timeout });
		await locator.click();
		return true;
	} catch {
		ctx.log(`Prvek nenalezen: ${description}`);
		return false;
	}
}

/**
 * Safely wait for a locator to appear. Returns false + logs if not found.
 */
export async function safeWaitFor(
	ctx: ToolContext,
	locator: Locator,
	description: string,
	timeout = STEP_TIMEOUT,
): Promise<boolean> {
	try {
		await locator.waitFor({ state: 'visible', timeout });
		return true;
	} catch {
		ctx.log(`Prvek nenalezen: ${description}`);
		return false;
	}
}

/**
 * Safely fill a field. Returns false if not found.
 */
export async function safeFill(
	ctx: ToolContext,
	locator: Locator,
	value: string,
	description: string,
	timeout = STEP_TIMEOUT,
): Promise<boolean> {
	try {
		await locator.waitFor({ state: 'visible', timeout });
		await locator.fill(value);
		return true;
	} catch {
		ctx.log(`Pole nenalezeno: ${description}`);
		return false;
	}
}

/**
 * Create a failure ToolOutput with consistent format.
 */
export function fail(error: string): { success: false; error: string } {
	return { success: false, error };
}
