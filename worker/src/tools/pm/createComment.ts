import type { ToolContext, ToolOutput } from '../types.js';
import { safeClick, safeWaitFor, fail } from './helpers.js';

/**
 * pm_create_comment: Create a comment on the currently opened task.
 * Input: { text: string }
 * Output: { success }
 *
 * Clicks "Přidat komentář" link → fills .redactor_visual_editor_textarea → clicks "Komentář" button.
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
		return fail('Odkaz "Přidat komentář" nenalezen — jste na stránce úkolu?');
	}
	await ctx.page.waitForTimeout(500); // wait for editor to render

	// Fill the Redactor visual editor textarea
	ctx.log(`Vyplňuji komentář (${text.length} znaků)...`);
	const editor = ctx.page.locator('.redactor_visual_editor_textarea');
	const editorReady = await safeWaitFor(ctx, editor, 'Textarea komentáře (.redactor_visual_editor_textarea)');
	if (!editorReady) {
		return fail('Textarea pro komentář se neotevřela');
	}
	await editor.fill(text);

	// Submit — click "Komentář" button
	ctx.log('Odesílám komentář...');
	const submitted = await safeClick(
		ctx, ctx.page.getByRole('button', { name: 'Komentář' }), 'Tlačítko "Komentář"',
	);
	if (!submitted) {
		return fail('Tlačítko "Komentář" nenalezeno');
	}
	await ctx.page.waitForLoadState('networkidle');

	ctx.log('Komentář vytvořen');
	return { success: true };
}
