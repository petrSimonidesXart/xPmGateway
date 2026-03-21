import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_update_task: Update fields of the currently opened task.
 * Input: { fields: { description?: string, assignee?: string, due_date?: string, status?: string, ... } }
 * Output: { success }
 */
export async function pmUpdateTask(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const fields = input.fields as Record<string, string> | undefined;
	if (!fields || Object.keys(fields).length === 0) {
		return { success: false, error: 'Missing required parameter: fields' };
	}

	// Click edit button if needed
	const editBtn = ctx.page.locator(
		'a:has-text("Upravit"), button:has-text("Upravit"), '
		+ 'a:has-text("Edit"), button:has-text("Edit"), '
		+ '.edit-task, [data-action="edit"]',
	);

	if (await editBtn.count() > 0) {
		await editBtn.first().click();
		await ctx.page.waitForLoadState('networkidle');
	}

	// Fill each field
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

	// Submit
	const submitBtn = ctx.page.locator(
		'button[type="submit"], input[type="submit"], '
		+ 'button:has-text("Uložit"), button:has-text("Save")',
	);
	if (await submitBtn.count() > 0) {
		await submitBtn.first().click();
		await ctx.page.waitForLoadState('networkidle');
	}

	return { success: true };
}
