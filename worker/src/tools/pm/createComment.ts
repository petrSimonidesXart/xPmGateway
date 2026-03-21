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

	// Submit — click the submit button in the comment form
	ctx.log('Odesílám komentář...');
	const submitted = await safeClick(
		ctx, ctx.page.getByRole('button', { name: 'Komentář' }), 'Tlačítko "Komentář"',
	);
	if (!submitted) {
		// Fallback: try any submit button near the editor
		const fallbackBtn = ctx.page.locator('.redactor_visual_editor_textarea').locator('..').locator('button[type="submit"], input[type="submit"]');
		if (await fallbackBtn.count() > 0) {
			await fallbackBtn.first().click();
		} else {
			return fail('Tlačítko pro odeslání komentáře nenalezeno');
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
