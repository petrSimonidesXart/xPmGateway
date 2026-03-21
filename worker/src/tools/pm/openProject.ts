import type { ToolContext, ToolOutput } from '../types.js';
import { safeClick, safeWaitFor, safeFill, fail } from './helpers.js';

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

	// Wait for filter input in popup
	const filterInput = ctx.page.locator('#menu_popup_projects_filter');
	const filterVisible = await safeWaitFor(ctx, filterInput, 'Filtr projektů (#menu_popup_projects_filter)');
	if (!filterVisible) {
		return fail('Popup s filtrem projektů se neotevřel');
	}

	await safeFill(ctx, filterInput, query, 'Filtr projektů');
	await ctx.page.waitForTimeout(800); // wait for JS filtering

	// Collect results from #menu_navigation_row
	const resultList = ctx.page.locator('#menu_navigation_row li a');
	const count = await resultList.count();

	if (count === 0) {
		ctx.log(`Projekt "${query}" nenalezen`);
		return {
			success: false,
			error: `Projekt "${query}" nenalezen`,
			count: 0,
			results: [],
		};
	}

	const results = await resultList.evaluateAll((links) => {
		return links
			.map((a) => {
				const name = a.textContent?.trim() ?? '';
				const href = a.getAttribute('href') ?? '';
				const pathMatch = /path_info=([^&]+)/.exec(href);
				const pathInfo = pathMatch ? decodeURIComponent(pathMatch[1]) : '';
				return { name, path_info: pathInfo };
			})
			.filter((r) => r.name && r.path_info);
	});

	if (results.length === 0) {
		ctx.log(`Projekt "${query}" nenalezen (žádné výsledky po filtrování)`);
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
	await resultList.first().click();
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
