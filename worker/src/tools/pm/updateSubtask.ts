import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_update_subtask: Navigate to a subtask and update its fields.
 * Input: { path_info: string, fields: { name?: string, assignee?: string, ... } }
 * Output: { success }
 */
export async function pmUpdateSubtask(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const pathInfo = String(input.path_info ?? '');
	const fields = input.fields as Record<string, string> | undefined;

	if (!pathInfo) {
		return { success: false, error: 'Missing required parameter: path_info' };
	}
	if (!fields || Object.keys(fields).length === 0) {
		return { success: false, error: 'Missing required parameter: fields' };
	}

	await ctx.page.goto(`${ctx.baseUrl}?path_info=${encodeURIComponent(pathInfo)}`);
	await ctx.page.waitForLoadState('networkidle');

	// Click edit if needed
	const editBtn = ctx.page.locator(
		'a:has-text("Upravit"), button:has-text("Upravit"), .edit-subtask',
	);
	if (await editBtn.count() > 0) {
		await editBtn.first().click();
		await ctx.page.waitForLoadState('networkidle');
	}

	for (const [key, value] of Object.entries(fields)) {
		const field = ctx.page.locator(
			`input[name*="${key}"], textarea[name*="${key}"], select[name*="${key}"]`,
		);
		if (await field.count() > 0) {
			const tagName = await field.first().evaluate((el) => el.tagName.toLowerCase());
			if (tagName === 'select') {
				await field.first().selectOption({ label: value });
			} else {
				await field.first().fill(value);
			}
		}
	}

	const submitBtn = ctx.page.locator(
		'button[type="submit"], input[type="submit"], '
		+ 'button:has-text("Uložit")',
	);
	if (await submitBtn.count() > 0) {
		await submitBtn.first().click();
		await ctx.page.waitForLoadState('networkidle');
	}

	return { success: true };
}
