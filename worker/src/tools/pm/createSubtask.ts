import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_create_subtask: Create a subtask on the currently opened task.
 * Input: { name: string, assignee?: string, due_date?: string }
 * Output: { success, subtask_id?, path_info? }
 *
 * Uses "Nový podúkol" link → subtasks/add page → fill form → submit.
 */
export async function pmCreateSubtask(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const name = String(input.name ?? '');
	if (!name) {
		return { success: false, error: 'Missing required parameter: name' };
	}

	// Click "Nový podúkol" link
	const newSubtaskLink = ctx.page.getByRole('link', { name: 'Nový podúkol' });
	if (await newSubtaskLink.count() === 0) {
		return { success: false, error: 'Link "Nový podúkol" not found on page' };
	}

	await newSubtaskLink.click();
	await ctx.page.waitForLoadState('networkidle');

	// Fill subtask name
	const nameField = ctx.page.locator(
		'input[name*="name"], input[name*="title"], input[name*="subject"]',
	);
	if (await nameField.count() === 0) {
		return { success: false, error: 'Subtask name field not found on form' };
	}
	await nameField.first().fill(name);

	// Optional: assignee
	if (input.assignee) {
		const assigneeField = ctx.page.locator(
			'select[name*="assignee"], select[name*="responsible"], '
			+ 'input[name*="assignee"], input[name*="responsible"]',
		);
		if (await assigneeField.count() > 0) {
			const tagName = await assigneeField.first().evaluate((el) => el.tagName.toLowerCase());
			if (tagName === 'select') {
				await assigneeField.first().selectOption({ label: String(input.assignee) });
			} else {
				await assigneeField.first().fill(String(input.assignee));
			}
		}
	}

	// Optional: due date
	if (input.due_date) {
		const dateField = ctx.page.locator(
			'input[name*="due"], input[name*="deadline"], input[type="date"]',
		);
		if (await dateField.count() > 0) {
			await dateField.first().fill(String(input.due_date));
		}
	}

	// Submit form
	const submitBtn = ctx.page.locator(
		'button[type="submit"], input[type="submit"], '
		+ 'button:has-text("Vytvořit"), button:has-text("Uložit"), button:has-text("Přidat")',
	);
	if (await submitBtn.count() > 0) {
		await submitBtn.first().click();
		await ctx.page.waitForLoadState('networkidle');
	}

	// Extract subtask ID from resulting URL
	const currentUrl = ctx.page.url();
	const subtaskMatch = /subtasks[/%]2[fF](\d+)/.exec(currentUrl) || /subtasks\/(\d+)/.exec(currentUrl);

	return {
		success: true,
		subtask_id: subtaskMatch?.[1] ?? null,
		path_info: null,
	};
}
