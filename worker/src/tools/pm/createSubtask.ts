import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_create_subtask: Create a subtask on the currently opened task.
 * Input: { name: string, assignee?: string, due_date?: string }
 * Output: { success, subtask_id?, path_info? }
 */
export async function pmCreateSubtask(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const name = String(input.name ?? '');
	if (!name) {
		return { success: false, error: 'Missing required parameter: name' };
	}

	// Click "New subtask" / "Nový podúkol" button
	const newSubtaskBtn = ctx.page.locator(
		'a:has-text("Nový podúkol"), button:has-text("Nový podúkol"), '
		+ 'a:has-text("Přidat podúkol"), button:has-text("Přidat podúkol"), '
		+ 'a:has-text("New subtask"), button:has-text("New subtask"), '
		+ '.add-subtask, .new-subtask',
	);

	if (await newSubtaskBtn.count() > 0) {
		await newSubtaskBtn.first().click();
		await ctx.page.waitForLoadState('networkidle');
	}

	// Fill the subtask form
	const nameField = ctx.page.locator(
		'input[name*="name"], input[name*="title"], input[name*="subject"], '
		+ '#subtask-name, #subtask-title',
	);

	if (await nameField.count() === 0) {
		return { success: false, error: 'Subtask name field not found' };
	}

	await nameField.first().fill(name);

	if (input.assignee) {
		const assigneeField = ctx.page.locator(
			'input[name*="assignee"], select[name*="assignee"], '
			+ 'input[name*="solver"], select[name*="solver"]',
		);
		if (await assigneeField.count() > 0) {
			await assigneeField.first().fill(String(input.assignee));
		}
	}

	if (input.due_date) {
		const dateField = ctx.page.locator(
			'input[name*="due"], input[name*="deadline"], input[name*="date"], '
			+ 'input[type="date"]',
		);
		if (await dateField.count() > 0) {
			await dateField.first().fill(String(input.due_date));
		}
	}

	// Submit
	const submitBtn = ctx.page.locator(
		'button[type="submit"], input[type="submit"], '
		+ 'button:has-text("Uložit"), button:has-text("Vytvořit")',
	);
	await submitBtn.first().click();
	await ctx.page.waitForLoadState('networkidle');

	// Try to extract subtask ID from the URL or page
	const currentUrl = ctx.page.url();
	const subtaskMatch = /subtasks[/](\d+)/.exec(currentUrl) || /subtasks%2F(\d+)/.exec(currentUrl);

	return {
		success: true,
		subtask_id: subtaskMatch?.[1] ?? null,
		path_info: null,
	};
}
