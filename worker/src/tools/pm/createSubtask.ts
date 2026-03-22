import type { ToolContext, ToolOutput } from '../types.js';
import { safeClick, safeWaitFor, fail } from './helpers.js';

/**
 * pm_create_subtask: Create a subtask on the currently opened task.
 *
 * Input: {
 *   name: string, assignee?: string, label?: string,
 *   schedule?: string, schedule_from?: string, schedule_to?: string,
 *   estimate?: string
 * }
 *
 * Form fields use dynamic IDs: [id$="_summary_field"], select[name$="[assignee_id]"], etc.
 */
export async function pmCreateSubtask(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const name = String(input.name ?? '');
	if (!name) {
		return fail('Chybí povinný parametr: name');
	}

	ctx.log('Otevírám formulář pro nový podúkol...');

	const clicked = await safeClick(
		ctx, ctx.page.getByRole('link', { name: 'Nový podúkol' }),
		'Odkaz "Nový podúkol" — jste na stránce úkolu? Máte oprávnění k této akci?',
	);
	if (!clicked) {
		return fail('Odkaz "Nový podúkol" nenalezen — jste na stránce úkolu? Máte oprávnění k této akci na daném projektu?');
	}
	await ctx.page.waitForTimeout(800);

	// Fill name
	ctx.log(`Vyplňuji název: "${name}"`);
	const nameField = ctx.page.locator('[id$="_summary_field"]');
	if (!await safeWaitFor(ctx, nameField, 'Pole pro název podúkolu')) {
		return fail('Pole pro název podúkolu nenalezeno ve formuláři');
	}
	await nameField.fill(name);

	// Resolve shortcuts via lookup API
	type Lookups = Record<string, { value: string; description: string | null }>;
	const empty: Lookups = {};
	const [peopleLookups, labelLookups, scheduleLookups] = await Promise.all([
		input.assignee ? ctx.api.getLookups('people') : Promise.resolve(empty),
		input.label ? ctx.api.getLookups('labels') : Promise.resolve(empty),
		input.schedule ? ctx.api.getLookups('schedule') : Promise.resolve(empty),
	]);

	// Assignee — resolve shortcut (PS → Petr Simonides), then select by label
	if (input.assignee) {
		const raw = String(input.assignee);
		const upper = raw.toUpperCase();
		const resolved = peopleLookups[upper]?.value ?? raw;
		ctx.log(`Nastavuji zodpovědnou osobu: "${resolved}"${resolved !== raw ? ` (zkratka ${upper})` : ''}`);
		const assigneeSelect = ctx.page.locator('select[name$="[assignee_id]"], [id$="_select_assignee"]');
		if (await assigneeSelect.count() > 0) {
			try {
				await assigneeSelect.first().selectOption({ label: resolved });
			} catch {
				ctx.log(`Osoba "${resolved}" nenalezena v selectu — přeskakuji`);
			}
		} else {
			ctx.log('Select pro zodpovědnou osobu nenalezen — přeskakuji');
		}
	}

	// Label — resolve shortcut (RESIT → ŘEŠIT), then select by label
	if (input.label) {
		const raw = String(input.label);
		const upper = raw.toUpperCase();
		const resolved = labelLookups[upper]?.value ?? raw;
		ctx.log(`Nastavuji štítek: "${resolved}"${resolved !== raw ? ` (zkratka ${upper})` : ''}`);
		const labelSelect = ctx.page.locator('select[name$="[label_id]"]');
		if (await labelSelect.count() > 0) {
			try {
				await labelSelect.first().selectOption({ label: resolved });
			} catch {
				ctx.log(`Štítek "${resolved}" nenalezen v selectu — přeskakuji`);
			}
		} else {
			ctx.log('Select pro štítek nenalezen — přeskakuji');
		}
	}

	// Schedule — resolve shortcut (TT → this_week, PT → next_week)
	let schedule = input.schedule as string | undefined;
	if (schedule) {
		const upper = schedule.toUpperCase();
		if (scheduleLookups[upper]?.value) {
			const resolved = scheduleLookups[upper].value;
			ctx.log(`Plánování: ${resolved} (zkratka ${upper})`);
			schedule = resolved;
		}

		// Resolve relative dates to YYYY/MM/DD
		const relDays: Record<string, number> = { today: 0, tomorrow: 1, day_after_tomorrow: 2 };
		if (schedule in relDays) {
			const d = new Date();
			d.setDate(d.getDate() + relDays[schedule]);
			schedule = `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}`;
			ctx.log(`Datum: ${schedule}`);
		}
	}
	if (schedule) {
		if (schedule === 'this_week') {
			ctx.log('Plánuji na tento týden');
			await ctx.page.locator('#scheduleThisWeek').click().catch(() => ctx.log('Tlačítko #scheduleThisWeek nenalezeno'));
		} else if (schedule === 'next_week') {
			ctx.log('Plánuji na příští týden');
			await ctx.page.locator('#scheduleNextWeek').click().catch(() => ctx.log('Tlačítko #scheduleNextWeek nenalezeno'));
		} else if (schedule === 'after_next_week') {
			ctx.log('Plánuji na přespříští týden');
			await ctx.page.locator('#scheduleAfterNextWeek').click().catch(() => ctx.log('Tlačítko #scheduleAfterNextWeek nenalezeno'));
		} else {
			ctx.log(`Nastavuji termín: ${schedule}`);
			const dueField = ctx.page.locator('[id$="_due_on"]');
			if (await dueField.count() > 0) {
				await dueField.fill(schedule);
			} else {
				ctx.log('Pole pro termín nenalezeno — přeskakuji');
			}
		}
	}

	if (input.schedule_from) {
		const fromField = ctx.page.locator('[id$="_start_on"]');
		if (await fromField.count() > 0) await fromField.fill(String(input.schedule_from));
	}
	if (input.schedule_to) {
		const toField = ctx.page.locator('[id$="_due_on"]');
		if (await toField.count() > 0) await toField.fill(String(input.schedule_to));
	}

	// Estimate
	if (input.estimate) {
		ctx.log(`Nastavuji odhad: ${input.estimate}`);
		const estimateField = ctx.page.locator('[id$="_estimate"]');
		if (await estimateField.count() > 0) {
			await estimateField.fill(String(input.estimate));
		} else {
			ctx.log('Pole pro odhad nenalezeno — přeskakuji');
		}
	}

	// Submit
	ctx.log('Odesílám formulář...');
	const submitted = await safeClick(
		ctx, ctx.page.getByRole('button', { name: 'Přidat úkol' }),
		'Tlačítko "Přidat úkol"',
	);
	if (!submitted) {
		return fail('Tlačítko "Přidat úkol" nenalezeno');
	}
	await ctx.page.waitForLoadState('networkidle');

	// Extract subtask ID
	const subtaskId = await ctx.page.evaluate(() => {
		const links = document.querySelectorAll('a[href*="subtasks"]');
		const last = links[links.length - 1];
		if (!last) return null;
		const href = last.getAttribute('href') ?? '';
		const match = /subtasks[/%]2[fF](\d+)/.exec(href) || /subtasks\/(\d+)/.exec(href);
		return match ? match[1] : null;
	});

	ctx.log(`Podúkol vytvořen${subtaskId ? ` (ID: ${subtaskId})` : ''}`);
	return { success: true, subtask_id: subtaskId };
}
