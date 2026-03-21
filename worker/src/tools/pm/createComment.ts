import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_create_comment: Create a comment on the currently opened task.
 * Input: { text: string }
 * Output: { success }
 *
 * "Přidat komentář" link opens a JS form/popup. We click it, fill the textarea, submit.
 */
export async function pmCreateComment(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const text = String(input.text ?? '');
	if (!text) {
		return { success: false, error: 'Missing required parameter: text' };
	}

	// Click "Přidat komentář" to open the comment form
	const addCommentLink = ctx.page.getByRole('link', { name: 'Přidat komentář' });
	if (await addCommentLink.count() === 0) {
		return { success: false, error: 'Comment link "Přidat komentář" not found on page' };
	}

	await addCommentLink.click();
	// Wait for the comment form to appear (JS popup/inline form)
	await ctx.page.waitForTimeout(500);

	// Find and fill the comment textarea
	const commentField = ctx.page.locator('textarea[name*="comment"], textarea[name*="body"], textarea[name*="note"], #comment_body, .comment-form textarea, textarea.body');

	if (await commentField.count() === 0) {
		// Try a broader search
		const anyTextarea = ctx.page.locator('textarea:visible');
		if (await anyTextarea.count() === 0) {
			return { success: false, error: 'Comment textarea not found after clicking "Přidat komentář"' };
		}
		await anyTextarea.last().fill(text);
	} else {
		await commentField.first().fill(text);
	}

	// Submit the comment
	const submitBtn = ctx.page.locator(
		'button:has-text("Odeslat"), button:has-text("Přidat"), '
		+ 'input[type="submit"][value*="Odeslat"], input[type="submit"][value*="Přidat"], '
		+ '.comment-form button[type="submit"], .comment-form input[type="submit"]',
	);

	if (await submitBtn.count() === 0) {
		return { success: false, error: 'Comment submit button not found' };
	}

	await submitBtn.first().click();
	await ctx.page.waitForLoadState('networkidle');

	return { success: true };
}
