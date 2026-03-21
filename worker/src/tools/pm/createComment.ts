import type { ToolContext, ToolOutput } from '../types.js';
import { safeClick, safeWaitFor, fail } from './helpers.js';

/**
 * pm_create_comment: Create a comment on the currently opened task.
 * Input: { text: string }
 * Output: { success }
 *
 * Flow: Click "Přidat komentář" → fill .redactor_visual_editor_textarea →
 * click button[type="submit"] in the comment form → wait for .comment.loaded
 */
export async function pmCreateComment(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const text = String(input.text ?? '');
	if (!text) {
		return fail('Chybí povinný parametr: text');
	}

	ctx.log('Otevírám formulář pro komentář...');

	// Click "Přidat komentář"
	const clicked = await safeClick(
		ctx, ctx.page.getByRole('link', { name: 'Přidat komentář' }), 'Odkaz "Přidat komentář"',
	);
	if (!clicked) {
		return fail('Odkaz "Přidat komentář" nenalezen — jste na stránce úkolu? Máte oprávnění k této akci na daném projektu?');
	}

	// Wait for Redactor editor to appear
	const editor = ctx.page.locator('.redactor_visual_editor_textarea');
	const editorReady = await safeWaitFor(ctx, editor, 'Textarea komentáře (.redactor_visual_editor_textarea)', 5000);
	if (!editorReady) {
		return fail('Textarea pro komentář se neotevřela');
	}

	// Fill the comment text
	ctx.log(`Vyplňuji komentář (${text.length} znaků)...`);
	await editor.fill(text);

	// Submit — click the submit button in comment form
	ctx.log('Odesílám komentář...');
	const submitBtn = ctx.page.locator('.comment_form_main_buttons button[type="submit"], .comment_form_main_buttons input[type="submit"]');
	if (await submitBtn.count() > 0) {
		await submitBtn.first().click();
	} else {
		// Fallback: try role-based and other selectors
		const fallback = ctx.page.getByRole('button', { name: 'Komentář' });
		if (await fallback.count() > 0) {
			await fallback.first().click();
		} else {
			const anySubmit = ctx.page.locator('form button[type="submit"]').last();
			if (await anySubmit.count() > 0) {
				await anySubmit.click();
			} else {
				return fail('Tlačítko pro odeslání komentáře nenalezeno — jste na stránce úkolu? Máte oprávnění k této akci na daném projektu?');
			}
		}
	}

	// Wait for the comment to appear — AJAX loads it as .comment.loaded
	ctx.log('Čekám na uložení komentáře...');
	try {
		await ctx.page.locator('.comment.loaded').first().waitFor({ state: 'visible', timeout: 15000 });
	} catch {
		// Check if comment appeared anyway
		const commentExists = await ctx.page.locator('.object_comments .comment_content_container .body').count();
		if (commentExists === 0) {
			ctx.log('Komentář se možná neuložil — nepodařilo se ověřit');
		}
	}

	// Verify the comment text is present
	const latestComment = await ctx.page.locator('.object_comments .comment_content_container .body.formatted_content').first().textContent().catch(() => null);

	if (latestComment && latestComment.includes(text.substring(0, 30))) {
		ctx.log('Komentář úspěšně vytvořen a ověřen');
		return { success: true, verified: true };
	}

	ctx.log('Komentář odeslán (bez ověření obsahu)');
	return { success: true, verified: false };
}
