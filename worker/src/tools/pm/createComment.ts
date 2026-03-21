import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_create_comment: Create a comment on the currently opened task.
 * Input: { text: string }
 * Output: { success }
 */
export async function pmCreateComment(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const text = String(input.text ?? '');
	if (!text) {
		return { success: false, error: 'Missing required parameter: text' };
	}

	// Find the comment textarea
	const commentField = ctx.page.locator(
		'textarea[name*="comment"], textarea[name*="note"], textarea[name*="text"], '
		+ 'textarea[placeholder*="koment"], textarea[placeholder*="poznám"], '
		+ '.comment-form textarea, #comment-text, .new-comment textarea',
	);

	if (await commentField.count() === 0) {
		return { success: false, error: 'Comment form not found on page' };
	}

	await commentField.first().fill(text);

	// Submit the comment
	const submitBtn = ctx.page.locator(
		'.comment-form button[type="submit"], '
		+ '.comment-form input[type="submit"], '
		+ 'button:has-text("Přidat komentář"), '
		+ 'button:has-text("Odeslat"), '
		+ 'button:has-text("Uložit komentář"), '
		+ '.new-comment button[type="submit"]',
	);

	if (await submitBtn.count() === 0) {
		return { success: false, error: 'Comment submit button not found' };
	}

	await submitBtn.first().click();
	await ctx.page.waitForLoadState('networkidle');

	return { success: true };
}
