import type { ToolContext, ToolOutput } from '../types.js';
import { safeClick, safeWaitFor, fail } from './helpers.js';

/**
 * pm_create_comment: Create a comment on the currently opened task.
 * Input: { text: string }
 * Output: { success, verified }
 *
 * ActiveCollab uses Redactor editor (contenteditable + hidden textarea)
 * with ajax_submit_enabled. We set the hidden textarea value directly
 * and submit the form via JS to bypass Redactor sync issues.
 */
export async function pmCreateComment(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const text = String(input.text ?? '');
	if (!text) {
		return fail('Chybí povinný parametr: text');
	}

	ctx.log('Otevírám formulář pro komentář...');

	// Click "Přidat komentář"
	const clicked = await safeClick(
		ctx, ctx.page.getByRole('link', { name: 'Přidat komentář' }),
		'Odkaz "Přidat komentář"',
	);
	if (!clicked) {
		return fail('Odkaz "Přidat komentář" nenalezen — jste na stránce úkolu? Máte oprávnění k této akci na daném projektu?');
	}

	// Wait for the comment form to appear
	const hiddenTextarea = ctx.page.locator('textarea[name="comment[body]"]');
	const formReady = await safeWaitFor(ctx, hiddenTextarea, 'Hidden textarea comment[body]', 5000);
	if (!formReady) {
		return fail('Formulář pro komentář se neotevřel');
	}

	ctx.log(`Vyplňuji komentář (${text.length} znaků)...`);

	// Set value in both contenteditable div AND hidden textarea via JS
	// This ensures Redactor's AJAX submit sends the correct content
	await ctx.page.evaluate((commentText) => {
		// Set contenteditable div
		const editor = document.querySelector('.redactor_editor[contenteditable="true"]') as HTMLElement;
		if (editor) {
			editor.innerHTML = '<p>' + commentText.replace(/\n/g, '</p><p>') + '</p>';
		}

		// Set hidden textarea (this is what actually gets submitted)
		const textarea = document.querySelector('textarea[name="comment[body]"]') as HTMLTextAreaElement;
		if (textarea) {
			textarea.value = '<p>' + commentText.replace(/\n/g, '</p><p>') + '</p>';
		}
	}, text);

	await ctx.page.waitForTimeout(500);

	// Click submit button
	ctx.log('Odesílám komentář...');
	const submitBtn = ctx.page.locator('.comment_form_main_buttons button[type="submit"]');
	const submitReady = await safeWaitFor(ctx, submitBtn, 'Tlačítko odeslání komentáře');
	if (!submitReady) {
		return fail('Tlačítko pro odeslání komentáře nenalezeno');
	}
	await submitBtn.click();
	ctx.log('Kliknuto na tlačítko odeslání');

	// Wait for AJAX response — new comment appears as .comment.loaded
	ctx.log('Čekám na uložení komentáře...');
	try {
		// Wait for a new comment to appear or the form to disappear
		await Promise.race([
			ctx.page.waitForResponse(
				(resp) => resp.url().includes('comments') && resp.status() === 200,
				{ timeout: 15000 },
			),
			ctx.page.waitForTimeout(10000),
		]);
	} catch {
		// Continue checking anyway
	}

	await ctx.page.waitForTimeout(1000);

	// Verify the comment text is present
	const latestComment = await ctx.page.locator('.object_comments .comment.loaded .body.formatted_content')
		.first().textContent().catch(() => null);

	if (latestComment && latestComment.includes(text.substring(0, 20))) {
		ctx.log('Komentář úspěšně vytvořen a ověřen');
		return { success: true, verified: true };
	}

	// Check if form disappeared (submitted via AJAX)
	const editorGone = await ctx.page.locator('.redactor_editor[contenteditable="true"]').count() === 0;
	if (editorGone) {
		ctx.log('Komentář odeslán (formulář zmizel)');
		return { success: true, verified: false };
	}

	// Last resort: check if any new comments appeared on page
	const commentCount = await ctx.page.locator('.comment.loaded').count();
	if (commentCount > 0) {
		ctx.log(`Komentář pravděpodobně vytvořen (${commentCount} komentářů na stránce)`);
		return { success: true, verified: false };
	}

	ctx.log('Komentář se nepodařilo ověřit');
	return { success: false, error: 'Komentář se nepodařilo odeslat — formulář stále viditelný' };
}
