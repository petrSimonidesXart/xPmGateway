import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_search_comments: Search in comments of the currently opened task.
 * Input: { query: string }
 * Output: { success, results[], count }
 */
export async function pmSearchComments(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const query = String(input.query ?? '');

	const results = await ctx.page.evaluate((searchQuery: string) => {
		const comments: Array<{ author: string; text: string; date: string }> = [];
		const commentEls = document.querySelectorAll(
			'.comment, .comment-item, .note, .note-item, '
			+ '[class*="comment"], [class*="note"]',
		);

		for (const el of commentEls) {
			const author = el.querySelector('.author, .user, .comment-author, .note-author')?.textContent?.trim() ?? '';
			const text = el.querySelector('.text, .body, .content, .comment-body, .note-body, p')?.textContent?.trim() ?? '';
			const date = el.querySelector('.date, .time, .comment-date, .note-date, time')?.textContent?.trim() ?? '';

			if (!text) continue;
			if (searchQuery && !text.toLowerCase().includes(searchQuery.toLowerCase())) continue;

			comments.push({ author, text, date });
		}

		return comments;
	}, query);

	return {
		success: true,
		results,
		count: results.length,
	};
}
