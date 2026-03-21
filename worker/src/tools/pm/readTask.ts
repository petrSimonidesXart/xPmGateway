import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_read_task: Read details of the currently opened task.
 * Input: — (operates on current page)
 * Output: { success, description, status, assignee, dates, ... }
 */
export async function pmReadTask(ctx: ToolContext, _input: Record<string, unknown>): Promise<ToolOutput> {
	const taskData = await ctx.page.evaluate(() => {
		const getText = (selector: string): string | null => {
			const el = document.querySelector(selector);
			return el?.textContent?.trim() || null;
		};

		const getFieldValue = (label: string): string | null => {
			const labels = document.querySelectorAll('label, .field-label, dt, th');
			for (const lbl of labels) {
				if (lbl.textContent?.trim().toLowerCase().includes(label.toLowerCase())) {
					const next = lbl.nextElementSibling;
					if (next) return next.textContent?.trim() || null;
				}
			}
			return null;
		};

		return {
			title: getText('h1, h2, .task-title, .task-name, .detail-title'),
			description: getText('.task-description, .description, .detail-description'),
			status: getFieldValue('stav') || getFieldValue('status'),
			assignee: getFieldValue('řešitel') || getFieldValue('assignee') || getFieldValue('přiřazeno'),
			project: getFieldValue('projekt') || getFieldValue('project'),
			due_date: getFieldValue('termín') || getFieldValue('due') || getFieldValue('deadline'),
			priority: getFieldValue('priorita') || getFieldValue('priority'),
			created: getFieldValue('vytvořeno') || getFieldValue('created'),
			url: window.location.href,
		};
	});

	return { success: true, ...taskData };
}
