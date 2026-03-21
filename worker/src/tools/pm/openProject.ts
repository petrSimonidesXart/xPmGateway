import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_open_project: Search for a project by name and open it.
 * Input: { query: string }
 * Output: { success, name, path_info, count, results[] }
 *
 * Flow: Navigate to projects page → type in "Filtrovat projekty" filter
 * → select from popup results in #menu_popup_body.
 */
export async function pmOpenProject(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const query = String(input.query ?? '');
	if (!query) {
		return { success: false, error: 'Missing required parameter: query' };
	}

	// Navigate to projects via main menu
	await ctx.page.getByRole('link', { name: 'Projekty' }).click();
	await ctx.page.waitForLoadState('networkidle');

	// Type in project filter
	const filterInput = ctx.page.getByRole('textbox', { name: 'Filtrovat projekty' });
	await filterInput.fill(query);
	await ctx.page.waitForTimeout(500); // wait for popup to appear

	// Collect results from popup
	const popupBody = ctx.page.locator('#menu_popup_body');
	await popupBody.waitFor({ state: 'visible', timeout: 5000 }).catch(() => {});

	const results = await popupBody.locator('a').evaluateAll((links, q) => {
		return links
			.filter((a) => {
				const name = a.textContent?.trim() ?? '';
				return name.toLowerCase().includes(q.toLowerCase());
			})
			.map((a) => {
				const name = a.textContent?.trim() ?? '';
				const href = a.getAttribute('href') ?? '';
				const pathMatch = /path_info=([^&]+)/.exec(href);
				const pathInfo = pathMatch ? decodeURIComponent(pathMatch[1]) : '';
				return { name, path_info: pathInfo };
			});
	}, query);

	if (results.length === 0) {
		return {
			success: false,
			error: `Projekt "${query}" nenalezen`,
			count: 0,
			results: [],
		};
	}

	if (results.length > 1) {
		return {
			success: false,
			error: `Nalezeno ${results.length} projektů pro "${query}". Upřesněte název.`,
			count: results.length,
			results,
		};
	}

	// Exactly 1 result — click it
	const projectLink = popupBody.getByRole('link', { name: results[0].name });
	await projectLink.click();
	await ctx.page.waitForLoadState('networkidle');

	return {
		success: true,
		name: results[0].name,
		path_info: results[0].path_info,
		count: 1,
		results,
	};
}
