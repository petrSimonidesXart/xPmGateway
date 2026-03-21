import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_open_project: Search for a project by name and open it.
 * Input: { query: string }
 * Output: { success, name, path_info, count, results[] }
 *
 * If exactly 1 match → opens project and returns its details.
 * If 0 or >1 matches → returns success=false with list for disambiguation.
 */
export async function pmOpenProject(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const query = String(input.query ?? '');
	if (!query) {
		return { success: false, error: 'Missing required parameter: query' };
	}

	// Navigate to projects list
	await ctx.page.goto(`${ctx.baseUrl}?path_info=projects`);
	await ctx.page.waitForLoadState('networkidle');

	// Use the search/filter if available
	const searchInput = ctx.page.locator(
		'input[name*="search"], input[name*="filter"], input[type="search"], '
		+ 'input[placeholder*="Hledat"], input[placeholder*="hled"]',
	);

	if (await searchInput.count() > 0) {
		await searchInput.first().fill(query);
		// Trigger search — may be via button click or Enter key
		const searchBtn = ctx.page.locator(
			'button:has-text("Hledat"), button:has-text("Filtrovat"), '
			+ 'button[type="submit"]',
		);
		if (await searchBtn.count() > 0) {
			await searchBtn.first().click();
		} else {
			await searchInput.first().press('Enter');
		}
		await ctx.page.waitForLoadState('networkidle');
	}

	// Scrape project list
	const results = await ctx.page.evaluate((searchQuery: string) => {
		const projects: Array<{ name: string; path_info: string }> = [];
		// Look for project links
		const links = document.querySelectorAll('a[href*="path_info=projects"]');
		for (const link of links) {
			const name = link.textContent?.trim() ?? '';
			const href = link.getAttribute('href') ?? '';
			const pathMatch = /path_info=([^&]+)/.exec(href);
			const pathInfo = pathMatch ? decodeURIComponent(pathMatch[1]) : '';

			if (name && pathInfo && pathInfo !== 'projects') {
				// Filter by search query (case-insensitive partial match)
				if (name.toLowerCase().includes(searchQuery.toLowerCase())) {
					projects.push({ name, path_info: pathInfo });
				}
			}
		}
		return projects;
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

	// Exactly 1 result — navigate to it
	const project = results[0];
	await ctx.page.goto(`${ctx.baseUrl}?path_info=${encodeURIComponent(project.path_info)}`);
	await ctx.page.waitForLoadState('networkidle');

	return {
		success: true,
		name: project.name,
		path_info: project.path_info,
		count: 1,
		results,
	};
}
