import type { ToolContext, ToolOutput } from '../types.js';
import { safeClick, safeWaitFor, safeFill, fail } from './helpers.js';

/**
 * pm_open_project: Search for a project by name and open it.
 * Input: { query: string } OR { path_info: string } (direct navigation, skips search)
 * Output: { success, name, path_info, count, results[] }
 */
export async function pmOpenProject(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const pathInfo = input.path_info as string | undefined;
	const query = String(input.query ?? '');

	// Direct navigation by path_info — no search, no ambiguity
	if (pathInfo) {
		ctx.log(`Otevírám projekt přímo: ${pathInfo}`);
		await ctx.page.goto(`${ctx.baseUrl}?path_info=${encodeURIComponent(pathInfo)}`);
		await ctx.page.waitForLoadState('networkidle');

		const title = await ctx.page.locator('h1, h2').first().textContent().catch(() => pathInfo);
		ctx.log(`Projekt "${title?.trim()}" otevřen`);
		return {
			success: true,
			name: title?.trim() ?? pathInfo,
			path_info: pathInfo,
			count: 1,
			results: [{ name: title?.trim() ?? pathInfo, path_info: pathInfo }],
		};
	}

	if (!query) {
		return fail('Chybí povinný parametr: query nebo path_info');
	}

	ctx.log(`Hledám projekt "${query}"...`);

	// Click on Projects menu item
	const menuClicked = await safeClick(
		ctx, ctx.page.locator('#menu_item_projects a'), 'Menu položka Projekty',
	);
	if (!menuClicked) {
		return fail('Menu položka "Projekty" nenalezena — možná nejste přihlášeni');
	}

	// Wait for project list to load via AJAX
	ctx.log('Čekám na načtení seznamu projektů...');
	try {
		await ctx.page.waitForFunction(
			() => document.querySelectorAll('.menu_navigation_row a').length > 0,
			{ timeout: 15_000 },
		);
	} catch {
		return fail('Seznam projektů se nenačetl (AJAX timeout)');
	}

	ctx.log('Seznam projektů načten');

	// Wait for filter input
	const filterInput = ctx.page.locator('#menu_popup_projects_filter');
	const filterReady = await filterInput.isVisible().catch(() => false);
	if (!filterReady) {
		ctx.log('Filtr #menu_popup_projects_filter nenalezen, hledám alternativu...');
		const altFilter = ctx.page.locator('#menu_popup input[type="text"], #menu_popup input[type="search"]');
		if (await altFilter.count() === 0) {
			return fail('Filtr projektů nenalezen v popup');
		}
		await altFilter.first().click();
		await altFilter.first().pressSequentially(query, { delay: 50 });
	} else {
		await filterInput.click();
		await filterInput.pressSequentially(query, { delay: 50 });
	}

	ctx.log(`Filtruju projekty podle "${query}"...`);

	// Wait for filter to apply — poll until visible count stabilizes
	await ctx.page.waitForTimeout(500);
	let prevCount = -1;
	for (let i = 0; i < 15; i++) {
		const currentCount = await ctx.page.evaluate(() => {
			const rows = document.querySelectorAll('.menu_navigation_row');
			let visible = 0;
			for (const row of rows) {
				const el = row as HTMLElement;
				if (el.offsetParent !== null && el.style.display !== 'none') {
					visible++;
				}
			}
			return visible;
		});
		if (currentCount === prevCount && prevCount >= 0) break;
		prevCount = currentCount;
		await ctx.page.waitForTimeout(300);
	}

	// Collect visible results
	const results = await ctx.page.evaluate(() => {
		const items: Array<{ name: string; path_info: string }> = [];
		const rows = document.querySelectorAll('.menu_navigation_row');
		for (const row of rows) {
			const el = row as HTMLElement;
			if (el.offsetParent === null || el.style.display === 'none') continue;
			const link = el.querySelector('a');
			if (!link) continue;
			const name = link.textContent?.trim() ?? '';
			const href = link.getAttribute('href') ?? '';
			const pathMatch = /path_info=([^&]+)/.exec(href);
			const pathInfoVal = pathMatch ? decodeURIComponent(pathMatch[1]) : '';
			if (name && pathInfoVal) {
				items.push({ name, path_info: pathInfoVal });
			}
		}
		return items;
	});

	if (results.length === 0) {
		ctx.log(`Projekt "${query}" nenalezen`);
		return { success: true, found: false, count: 0, results: [], message: `Projekt "${query}" nenalezen` };
	}

	if (results.length > 1) {
		ctx.log(`Nalezeno ${results.length} projektů — potřebuji upřesnění`);
		return {
			success: false,
			needs_input: true,
			input_prompt: `Nalezeno ${results.length} projektů pro "${query}". Vyberte projekt:`,
			options: results,
			count: results.length,
			results,
		};
	}

	// Exactly 1 result — click it
	ctx.log(`Nalezen projekt "${results[0].name}", otevírám...`);
	await ctx.page.evaluate((projectName) => {
		const rows = document.querySelectorAll('.menu_navigation_row');
		for (const row of rows) {
			const el = row as HTMLElement;
			if (el.offsetParent === null || el.style.display === 'none') continue;
			const link = el.querySelector('a');
			if (link && link.textContent?.trim() === projectName) {
				link.click();
				return;
			}
		}
	}, results[0].name);

	await ctx.page.waitForLoadState('networkidle');

	ctx.log(`Projekt "${results[0].name}" otevřen`);
	return {
		success: true,
		name: results[0].name,
		path_info: results[0].path_info,
		count: 1,
		results,
	};
}
