import type { ToolContext, ToolOutput } from '../types.js';
import { safeClick, safeWaitFor, fail } from './helpers.js';

/**
 * pm_create_subtask: Create a subtask on the currently opened task.
 *
 * Input: {
 *   name: string          — název podúkolu (povinný)
 *   assignee?: string     — jméno zodpovědné osoby (vybere se z selectu podle label)
 *   label?: string        — štítek: "Řešit", "Schůzka" atd. (vybere se podle label)
 *   schedule?: string     — "this_week" | "next_week" | "after_next_week" | "YYYY/MM/DD"
 *   schedule_from?: string — datum od (YYYY/MM/DD), pro vícedenní úkoly
 *   schedule_to?: string  — datum do (YYYY/MM/DD), pro vícedenní úkoly
 *   estimate?: string     — odhad: "1" (hodina), "0,5" (půl hodiny), ":30" (30 min)
 * }
 *
 * Output: { success, subtask_id? }
 *
 * Form fields use dynamic IDs like ActiveCollab_element_*_summary_field.
 * We match by id suffix: [id$="_summary_field"], [id$="_select_assignee"], etc.
 */
export async function pmCreateSubtask(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const name = String(input.name ?? '');
	if (!name) {
		return fail('Chybí povinný parametr: name');
	}

	ctx.log('Otevírám formulář pro nový podúkol...');

	// Click "Nový podúkol" link
	const clicked = await safeClick(
		ctx, ctx.page.getByRole('link', { name: 'Nový podúkol' }), 'Odkaz "Nový podúkol"',
	);
	if (!clicked) {
		return fail('Odkaz "Nový podúkol" nenalezen — jste na stránce úkolu?');
	}
	await ctx.page.waitForTimeout(500); // wait for form to render

	// Fill name (summary_field)
	ctx.log(`Vyplňuji název: "${name}"`);
	const nameField = ctx.page.locator('[id$="_summary_field"]');
	const nameReady = await safeWaitFor(ctx, nameField, 'Pole pro název podúkolu');
	if (!nameReady) {
		return fail('Pole pro název podúkolu nenalezeno ve formuláři');
	}
	await nameField.fill(name);

	// Assignee (select by label text)
	if (input.assignee) {
		ctx.log(`Nastavuji zodpovědnou osobu: "${input.assignee}"`);
		const assigneeSelect = ctx.page.locator('[id$="_select_assignee"]');
		if (await assigneeSelect.count() > 0) {
			await assigneeSelect.selectOption({ label: String(input.assignee) });
		} else {
			ctx.log('Select pro zodpovědnou osobu nenalezen');
		}
	}

	// Label/tag (select by label text)
	if (input.label) {
		ctx.log(`Nastavuji štítek: "${input.label}"`);
		const labelSelect = ctx.page.locator('[id$="_label"]').first();
		if (await labelSelect.count() > 0) {
			await labelSelect.selectOption({ label: String(input.label) });
		} else {
			ctx.log('Select pro štítek nenalezen');
		}
	}

	// Schedule — quick links or specific date
	const schedule = input.schedule as string | undefined;
	if (schedule) {
		if (schedule === 'this_week') {
			ctx.log('Plánuji na tento týden');
			await ctx.page.locator('#scheduleThisWeek').click().catch(() => {
				ctx.log('Tlačítko #scheduleThisWeek nenalezeno');
			});
		} else if (schedule === 'next_week') {
			ctx.log('Plánuji na příští týden');
			await ctx.page.locator('#scheduleNextWeek').click().catch(() => {
				ctx.log('Tlačítko #scheduleNextWeek nenalezeno');
			});
		} else if (schedule === 'after_next_week') {
			ctx.log('Plánuji na přespříští týden');
			await ctx.page.locator('#scheduleAfterNextWeek').click().catch(() => {
				ctx.log('Tlačítko #scheduleAfterNextWeek nenalezeno');
			});
		} else {
			// Specific date — fill into due_on field (format YYYY/MM/DD)
			ctx.log(`Nastavuji termín: ${schedule}`);
			const dueField = ctx.page.locator('[id$="_due_on"]');
			if (await dueField.count() > 0) {
				await dueField.fill(schedule);
			}
		}
	}

	// Date range (from/to) for multi-day tasks
	if (input.schedule_from) {
		const fromField = ctx.page.locator('[id$="_start_on"], [id$="_due_on"]').first();
		if (await fromField.count() > 0) {
			await fromField.fill(String(input.schedule_from));
		}
	}
	if (input.schedule_to) {
		const toField = ctx.page.locator('[id$="_due_on"]');
		if (await toField.count() > 0) {
			await toField.fill(String(input.schedule_to));
		}
	}

	// Estimate
	if (input.estimate) {
		ctx.log(`Nastavuji odhad: ${input.estimate}`);
		const estimateField = ctx.page.locator('[id$="_estimate"]');
		if (await estimateField.count() > 0) {
			await estimateField.fill(String(input.estimate));
		}
	}

	// Submit — "Přidat úkol" button
	ctx.log('Odesílám formulář...');
	const submitted = await safeClick(
		ctx, ctx.page.getByRole('button', { name: 'Přidat úkol' }), 'Tlačítko "Přidat úkol"',
	);
	if (!submitted) {
		return fail('Tlačítko "Přidat úkol" nenalezeno');
	}
	await ctx.page.waitForLoadState('networkidle');

	// Try to extract subtask ID from the page
	const subtaskId = await ctx.page.evaluate(() => {
		// Look for the last subtask link on the page
		const links = document.querySelectorAll('a[href*="subtasks"]');
		const last = links[links.length - 1];
		if (!last) return null;
		const href = last.getAttribute('href') ?? '';
		const match = /subtasks[/%]2[fF](\d+)/.exec(href) || /subtasks\/(\d+)/.exec(href);
		return match ? match[1] : null;
	});

	ctx.log(`Podúkol vytvořen${subtaskId ? ` (ID: ${subtaskId})` : ''}`);
	return {
		success: true,
		subtask_id: subtaskId,
	};
}
