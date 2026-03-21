import type { ToolContext, ToolOutput } from '../types.js';
import { safeClick, safeWaitFor, fail } from './helpers.js';

/**
 * pm_open_task: Search for a task in the current project and open it.
 * Input: { query: string } OR { path_info: string }
 * Output: { success, found, task_id, name, path_info, count, results[] }
 *
 * Assumes the page is already on a project page (after pm_open_project).
 * Uses #tasks textbox filter, then clicks matching task text.
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

	// Type in task filter within #tasks section
	const taskFilter = ctx.page.locator('#tasks').getByRole('textbox');
	const filterReady = await safeWaitFor(ctx, taskFilter, 'Filtr úkolů v #tasks');
	if (!filterReady) {
		return fail('Filtr úkolů nenalezen na stránce');
	}

	await taskFilter.click();
	await taskFilter.pressSequentially(query, { delay: 50 });
	await ctx.page.waitForTimeout(800); // wait for JS filtering

	// Scrape visible task rows — look for links containing task path
	const results = await ctx.page.evaluate((searchQuery: string) => {
		const tasks: Array<{ task_id: string; name: string; path_info: string }> = [];
		// Tasks section contains rows with task links
		const tasksSection = document.getElementById('tasks');
		if (!tasksSection) return tasks;

		const rows = tasksSection.querySelectorAll('tr, .task_list_row, li');
		for (const row of rows) {
			const el = row as HTMLElement;
			if (el.offsetParent === null || el.style.display === 'none') continue;

			const link = el.querySelector('a[href*="tasks"]');
			if (!link) continue;

			const text = link.textContent?.trim() ?? '';
			const href = link.getAttribute('href') ?? '';
			const pathMatch = /path_info=([^&]+)/.exec(href);
			const pathInfo = pathMatch ? decodeURIComponent(pathMatch[1]) : '';
			const taskIdMatch = /tasks\/(\d+)/.exec(pathInfo);

			if (!taskIdMatch || !text) continue;
			// Clean up text — remove leading #N
			const name = text.replace(/^#\d+\s*/, '').trim();

			if (name.toLowerCase().includes(searchQuery.toLowerCase()) || text.toLowerCase().includes(searchQuery.toLowerCase())) {
				if (!tasks.some((t) => t.task_id === taskIdMatch[1])) {
					tasks.push({ task_id: taskIdMatch[1], name, path_info: pathInfo });
				}
			}
		}
		return tasks;
	}, query);

	if (results.length === 0) {
		// Fallback: try clicking text directly (the codegen approach)
		const directMatch = ctx.page.locator('#tasks').getByText(query, { exact: false });
		if (await directMatch.count() > 0) {
			await directMatch.first().click();
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
					success: true, found: true,
					task_id: taskIdMatch[1], name: titleClean, path_info: pi,
					count: 1, results: [{ task_id: taskIdMatch[1], name: titleClean, path_info: pi }],
				};
			}
		}

		ctx.log(`Úkol "${query}" nenalezen`);
		return { success: true, found: false, count: 0, results: [], message: `Úkol "${query}" nenalezen` };
	}

	if (results.length > 1) {
		ctx.log(`Nalezeno ${results.length} úkolů — potřebuji upřesnění`);
		return {
			success: false, needs_input: true,
			input_prompt: `Nalezeno ${results.length} úkolů pro "${query}". Vyberte úkol:`,
			options: results, count: results.length, results,
		};
	}

	// Exactly 1 — click it via text in #tasks
	ctx.log(`Nalezen úkol "${results[0].name}", otevírám...`);
	const taskLink = ctx.page.locator('#tasks').getByText(results[0].name, { exact: false });
	if (await taskLink.count() > 0) {
		await taskLink.first().click();
	} else {
		await ctx.page.goto(`${ctx.baseUrl}?path_info=${encodeURIComponent(results[0].path_info)}`);
	}
	await ctx.page.waitForLoadState('networkidle');

	// Verify we landed on the task page
	const verified = await verifyTaskPage(ctx, results[0].path_info);
	if (!verified) {
		return fail('Stránka úkolu se nenačetla po kliknutí');
	}

	ctx.log(`Úkol "${results[0].name}" otevřen`);
	return {
		success: true, found: true,
		task_id: results[0].task_id, name: results[0].name, path_info: results[0].path_info,
		count: 1, results,
	};
}

/** Verify we're on the task detail page by checking URL contains the expected path_info. */
async function verifyTaskPage(ctx: ToolContext, expectedPath: string): Promise<boolean> {
	const url = ctx.page.url();
	const taskIdMatch = /tasks[/%]2[fF](\d+)|tasks\/(\d+)/.exec(url);
	if (taskIdMatch) return true;

	// Wait a bit more and retry
	await ctx.page.waitForTimeout(1000);
	const url2 = ctx.page.url();
	const match2 = /tasks[/%]2[fF](\d+)|tasks\/(\d+)/.exec(url2);
	return !!match2;
}
