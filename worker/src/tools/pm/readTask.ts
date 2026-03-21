import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_read_task: Read details of the currently opened task.
 * Input: — (operates on current page)
 * Output: { success, task_id, title, description, created_by, category, milestone, assignees, ... }
 *
 * Scrapes data from the task detail page layout:
 * - Title from h1 (format: "#6: test-recipe")
 * - Metadata table (Vytvořil, Kategorie, ID, Milník, Zodpovědné osoby)
 * - Description from paragraph after action buttons
 * - Subtask count
 * - Action links (Dokončit, Upravit)
 */
export async function pmReadTask(ctx: ToolContext, _input: Record<string, unknown>): Promise<ToolOutput> {
	const taskData = await ctx.page.evaluate(() => {
		// Title from h1 (e.g. "#6: test-recipe")
		const h1 = document.querySelector('h1');
		const fullTitle = h1?.textContent?.trim() ?? '';
		const titleMatch = /^#(\d+):\s*(.+)$/.exec(fullTitle);
		const taskId = titleMatch ? titleMatch[1] : '';
		const title = titleMatch ? titleMatch[2].trim() : fullTitle;

		// URL for path_info
		const url = window.location.href;
		const pathMatch = /path_info=([^&]+)/.exec(url);
		const pathInfo = pathMatch ? decodeURIComponent(pathMatch[1]) : '';

		// Metadata — scan text content for known labels
		const bodyText = document.body.textContent ?? '';

		const extractAfterLabel = (label: string): string | null => {
			const regex = new RegExp(label + '\\s+(.+?)\\s+(?:Kategorie|ID|Milník|Plánováno|Související|Zodpovědné|$)', 'i');
			const match = regex.exec(bodyText);
			return match ? match[1].trim() : null;
		};

		// Created by — from link after "Vytvořil" and date
		const createdByLink = document.querySelector('td a[href*="users/"]');
		const createdBy = createdByLink?.textContent?.trim() ?? null;

		// Check if actions exist
		const canComplete = !!document.querySelector('a[href*="complete"]');
		const canEdit = !!document.querySelector('a[href*="edit"]');

		// Description — paragraph text in the task body
		const descriptionEl = document.querySelectorAll('p');
		let description = '';
		for (const p of descriptionEl) {
			const text = p.textContent?.trim() ?? '';
			// Skip very short paragraphs that are likely UI elements
			if (text.length > 3 && !text.includes('Více informací') && !text.includes('Žádné')) {
				description = text;
				break;
			}
		}

		// Subtask count
		const subtaskRows = document.querySelectorAll('a[href*="subtasks/"]');
		const subtaskLinks = Array.from(subtaskRows).filter((a) => {
			const href = a.getAttribute('href') ?? '';
			return /subtasks\/\d+/.test(href) || /subtasks%2F\d+/.test(href);
		});

		// Assignees
		const assigneeLink = document.querySelector('a[href*="assignees"]');
		const assigneeText = assigneeLink?.textContent?.trim() ?? '';
		const hasAssignee = !assigneeText.includes('Nikdo');

		return {
			task_id: taskId,
			title,
			description: description || null,
			path_info: pathInfo,
			url,
			created_by: createdBy,
			assignees: hasAssignee ? assigneeText.replace('Zodpovědné osoby', '').trim() : null,
			subtask_count: subtaskLinks.length,
			can_complete: canComplete,
			can_edit: canEdit,
		};
	});

	return { success: true, ...taskData };
}
