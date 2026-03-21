import type { ToolContext, ToolOutput } from '../types.js';
import { safeClick, safeWaitFor, fail } from './helpers.js';

/**
 * pm_create_comment: Create a comment on the currently opened task.
 * Input: { text: string }
 * Output: { success, verified }
 *
 * The comment form uses a Redactor rich-text editor (contenteditable div)
 * with AJAX submit. The actual textarea is hidden, content goes into
 * .redactor_editor[contenteditable="true"].
 *
 * Submit button: .comment_form_main_buttons button[type="submit"]
 * Verification: .comment.loaded appears after AJAX completes
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
		'Odkaz "Přidat komentář" — jste na stránce úkolu? Máte oprávnění k této akci na daném projektu?',
	);
	if (!clicked) {
		return fail('Odkaz "Přidat komentář" nenalezen — jste na stránce úkolu? Máte oprávnění k této akci na daném projektu?');
	}

	// Wait for contenteditable editor to appear
	const editor = ctx.page.locator('.redactor_editor[contenteditable="true"]');
	const editorReady = await safeWaitFor(ctx, editor, 'Redactor editor (contenteditable)', 5000);
	if (!editorReady) {
		return fail('Editor pro komentář se neotevřel');
	}

	// Clear existing content and type the comment
	ctx.log(`Vyplňuji komentář (${text.length} znaků)...`);
	await editor.click();
	await ctx.page.keyboard.press('Control+A');
	await ctx.page.keyboard.type(text);

	// Wait for editor to process the input
	await ctx.page.waitForTimeout(1000);

	// Click submit button in .comment_form_main_buttons
	ctx.log('Odesílám komentář...');
	const submitBtn = ctx.page.locator('.comment_form_main_buttons button[type="submit"]');
	const submitReady = await safeWaitFor(ctx, submitBtn, 'Tlačítko odeslání komentáře');
	if (!submitReady) {
		return fail('Tlačítko pro odeslání komentáře nenalezeno');
	}
	await submitBtn.click();
	ctx.log('Kliknuto na tlačítko odeslání');

	// Wait for AJAX — comment appears as .comment.loaded
	ctx.log('Čekám na uložení komentáře...');
	try {
		await ctx.page.locator('.comment.loaded').first().waitFor({ state: 'visible', timeout: 15000 });
	} catch {
		ctx.log('Element .comment.loaded se neobjevil — zkouším alternativní ověření');
	}

	// Verify the comment text is present in the first comment
	const latestComment = await ctx.page.locator('.object_comments .comment_content_container .body.formatted_content')
		.first().textContent().catch(() => null);

	if (latestComment && latestComment.includes(text.substring(0, 30))) {
		ctx.log('Komentář úspěšně vytvořen a ověřen');
		return { success: true, verified: true };
	}

	// Check if comment form disappeared (form is gone = probably submitted OK)
	const formGone = await editor.count() === 0;
	if (formGone) {
		ctx.log('Komentář odeslán (formulář zmizel)');
		return { success: true, verified: false };
	}

	ctx.log('Komentář se možná neuložil — formulář stále viditelný');
	return { success: true, verified: false };
}
