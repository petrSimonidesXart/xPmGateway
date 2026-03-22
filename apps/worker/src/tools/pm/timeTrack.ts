import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_time_track: Log time on the currently opened task or subtask.
 * Input: { hours: number, date: string, note?: string }
 * Output: { success }
 */
export async function pmTimeTrack(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const hours = input.hours;
	const date = String(input.date ?? '');

	if (hours === undefined || hours === null) {
		return { success: false, error: 'Missing required parameter: hours' };
	}
	if (!date) {
		return { success: false, error: 'Missing required parameter: date' };
	}

	// Click "Log time" / "Vykázat čas" button
	const logTimeBtn = ctx.page.locator(
		'a:has-text("Vykázat"), button:has-text("Vykázat"), '
		+ 'a:has-text("Log time"), button:has-text("Log time"), '
		+ 'a:has-text("Čas"), .log-time, [data-action="log-time"]',
	);

	if (await logTimeBtn.count() > 0) {
		await logTimeBtn.first().click();
		await ctx.page.waitForLoadState('networkidle');
	}

	// Fill hours
	const hoursField = ctx.page.locator(
		'input[name*="hours"], input[name*="time"], input[name*="duration"], '
		+ 'input[name*="hodiny"], #time-hours',
	);
	if (await hoursField.count() > 0) {
		await hoursField.first().fill(String(hours));
	}

	// Fill date
	const dateField = ctx.page.locator(
		'input[name*="date"], input[type="date"], '
		+ 'input[name*="datum"], #time-date',
	);
	if (await dateField.count() > 0) {
		await dateField.first().fill(date);
	}

	// Fill note (optional)
	if (input.note) {
		const noteField = ctx.page.locator(
			'textarea[name*="note"], textarea[name*="description"], '
			+ 'textarea[name*="pozn"], input[name*="note"]',
		);
		if (await noteField.count() > 0) {
			await noteField.first().fill(String(input.note));
		}
	}

	// Submit
	const submitBtn = ctx.page.locator(
		'button[type="submit"], input[type="submit"], '
		+ 'button:has-text("Uložit"), button:has-text("Vykázat")',
	);
	if (await submitBtn.count() > 0) {
		await submitBtn.first().click();
		await ctx.page.waitForLoadState('networkidle');
	}

	return { success: true };
}
