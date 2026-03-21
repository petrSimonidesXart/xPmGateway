import type { ToolContext, ToolOutput } from '../types.js';
import { safeClick, safeWaitFor, fail } from './helpers.js';

/**
 * pm_open_task: Search for a task in the current project and open it.
 * Input: { query: string } OR { path_info: string }
 * Output: { success, found, task_id, name, path_info, count, results[] }
 *
 * Assumes the page is already on a project page (after pm_open_project).
 */
export async function pmOpenTask(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const pathInfo = input.path_info as string | undefined;
	const query = String(input.query ?? '');

	// Direct navigation by path_info
	if (pathInfo) {
		ctx.log(`Otevírám úkol přímo: ${pathInfo}`);
		await ctx.page.goto(`${ctx.baseUrl}?path_info=${encodeURIComponent(pathInfo)}`);
		await ctx.page.waitForLoadState('networkidle');

		const title = await ctx.page.locator('h1').textContent().catch(() => pathInfo);
		const titleClean = title?.replace(/^#\d+:\s*/, '').trim() ?? pathInfo;
		const taskIdMatch = /tasks\/(\d+)/.exec(pathInfo);

		ctx.log(`Úkol "${titleClean}" otevřen`);
		return {
			success: true,
			found: true,
			task_id: taskIdMatch?.[1] ?? '',
			name: titleClean,
			path_info: pathInfo,
			count: 1,
			results: [{ task_id: taskIdMatch?.[1] ?? '', name: titleClean, path_info: pathInfo }],
		};
	}

	if (!query) {
		return fail('Chybí povinný parametr: query nebo path_info');
	}

	ctx.log(`Hledám úkol "${query}"...`);

	// Click "Úkoly" in project submenu
	const tasksClicked = await safeClick(
		ctx, ctx.page.getByRole('link', { name: 'Úkoly', exact: true }), 'Odkaz "Úkoly" v menu projektu',
	);
	if (!tasksClicked) {
		return fail('Odkaz "Úkoly" nenalezen — jste na stránce projektu?');
	}
	await ctx.page.waitForLoadState('networkidle');

	// Type in task search/filter within #tasks section
	const taskFilter = ctx.page.locator('#tasks').getByRole('textbox');
	const filterReady = await safeWaitFor(ctx, taskFilter, 'Filtr úkolů v #tasks');
	if (filterReady) {
		await taskFilter.pressSequentially(query, { delay: 50 });
		await ctx.page.waitForTimeout(500);
	}

	// Scrape visible task rows
	const results = await ctx.page.evaluate((searchQuery: string) => {
		const tasks: Array<{ task_id: string; name: string; path_info: string }> = [];
		const links = document.querySelectorAll('#tasks a[href*="tasks%2F"], #tasks a[href*="tasks/"]');
		for (const link of links) {
			const name = link.textContent?.trim() ?? '';
			const href = link.getAttribute('href') ?? '';
			const pathMatch = /path_info=([^&]+)/.exec(href);
			const pi = pathMatch ? decodeURIComponent(pathMatch[1]) : '';
			const taskIdMatch = /tasks\/(\d+)/.exec(pi);
			const taskId = taskIdMatch ? taskIdMatch[1] : '';

			if (name && taskId && name.toLowerCase().includes(searchQuery.toLowerCase())) {
				if (!tasks.some((t) => t.task_id === taskId)) {
					tasks.push({ task_id: taskId, name, path_info: pi });
				}
			}
		}
		return tasks;
	}, query);

	if (results.length === 0) {
		// Try clicking text match directly (popup-style filter)
		const directLink = ctx.page.getByText(query, { exact: false });
		if (await directLink.count() > 0) {
			await directLink.first().click();
			await ctx.page.waitForLoadState('networkidle');

			const url = ctx.page.url();
			const pathMatch = /path_info=([^&]+)/.exec(url);
			const pi = pathMatch ? decodeURIComponent(pathMatch[1]) : '';
			const taskIdMatch = /tasks\/(\d+)/.exec(pi);

			if (taskIdMatch) {
				const title = await ctx.page.locator('h1').textContent();
				const titleClean = title?.replace(/^#\d+:\s*/, '').trim() ?? query;
				ctx.log(`Úkol "${titleClean}" nalezen a otevřen`);
				return {
					success: true,
					found: true,
					task_id: taskIdMatch[1],
					name: titleClean,
					path_info: pi,
					count: 1,
					results: [{ task_id: taskIdMatch[1], name: titleClean, path_info: pi }],
				};
			}
		}

		ctx.log(`Úkol "${query}" nenalezen`);
		return {
			success: true,
			found: false,
			count: 0,
			results: [],
			message: `Úkol "${query}" nenalezen v aktuálním projektu`,
		};
	}

	if (results.length > 1) {
		ctx.log(`Nalezeno ${results.length} úkolů — potřebuji upřesnění`);
		return {
			success: false,
			needs_input: true,
			input_prompt: `Nalezeno ${results.length} úkolů pro "${query}". Vyberte úkol:`,
			options: results,
			count: results.length,
			results,
		};
	}

	// Exactly 1 result — click it
	ctx.log(`Nalezen úkol "${results[0].name}", otevírám...`);
	await ctx.page.getByText(results[0].name).first().click();
	await ctx.page.waitForLoadState('networkidle');

	ctx.log(`Úkol "${results[0].name}" otevřen`);
	return {
		success: true,
		found: true,
		task_id: results[0].task_id,
		name: results[0].name,
		path_info: results[0].path_info,
		count: 1,
		results,
	};
}
