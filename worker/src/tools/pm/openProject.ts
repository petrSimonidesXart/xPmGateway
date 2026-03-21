import type { ToolContext, ToolOutput } from '../types.js';
import { safeClick, safeFill, fail } from './helpers.js';

/**
 * pm_open_project: Search for a project by name and open it.
 * Input: { query: string }
 * Output: { success, name, path_info, count, results[] }
 */
export async function pmOpenProject(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const query = String(input.query ?? '');
	if (!query) {
		return fail('Chybí povinný parametr: query');
	}

	ctx.log(`Hledám projekt "${query}"...`);

	// Click on Projects menu item
	const menuClicked = await safeClick(
		ctx, ctx.page.locator('#menu_item_projects a'), 'Menu položka Projekty',
	);
	if (!menuClicked) {
		return fail('Menu položka "Projekty" nenalezena — možná nejste přihlášeni');
	}

	// Wait for project list to load via AJAX — poll DOM until elements appear
	ctx.log('Čekám na načtení seznamu projektů...');
	try {
		await ctx.page.waitForFunction(
			() => document.querySelectorAll('.menu_navigation_row a').length > 0,
			{ timeout: 15_000 },
		);
	} catch {
		// Capture what's on the page for debugging
		const html = await ctx.page.locator('#menu_popup').innerHTML().catch(() => 'N/A');
		ctx.log(`Popup HTML (zkráceno): ${html.substring(0, 300)}`);
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
		// Use pressSequentially to trigger JS keyup/input events for filtering
		await filterInput.click();
		await filterInput.pressSequentially(query, { delay: 50 });
	}

	ctx.log(`Filtruju projekty podle "${query}"...`);

	// Wait for filter to apply — JS hides non-matching rows via display:none
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
			const pathInfo = pathMatch ? decodeURIComponent(pathMatch[1]) : '';
			if (name && pathInfo) {
				items.push({ name, path_info: pathInfo });
			}
		}
		return items;
	});

	if (results.length === 0) {
		ctx.log(`Projekt "${query}" nenalezen`);
		return { success: false, error: `Projekt "${query}" nenalezen`, count: 0, results: [] };
	}

	if (results.length > 1) {
		ctx.log(`Nalezeno ${results.length} projektů: ${results.map((r) => r.name).join(', ')}`);
		return {
			success: false,
			error: `Nalezeno ${results.length} projektů pro "${query}". Upřesněte název.`,
			count: results.length,
			results,
		};
	}

	// Exactly 1 result — click it
	ctx.log(`Nalezen projekt "${results[0].name}", otevírám...`);

	// Click the visible link matching the result
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
