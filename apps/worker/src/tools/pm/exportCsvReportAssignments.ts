import type { ToolContext, ToolOutput } from '../types.js';
import { fail, safeClick } from './helpers.js';

const STEP_TIMEOUT = 15_000;
const AJAX_SETTLE = 2_000;

/**
 * pm_export_csv_report_assignments — Export CSV z reportu přiřazených.
 *
 * Provede export výstupu z reportu "Přiřazené" na základě filtru.
 *
 * Input: { user_filter: string }
 * Output: { success, artifact_id, filename, size_bytes }
 */
export async function pmExportCsvReportAssignments(
	ctx: ToolContext,
	input: Record<string, unknown>,
): Promise<ToolOutput> {
	const userFilter = typeof input.user_filter === 'string' ? input.user_filter : '';
	if (!userFilter) {
		return fail('user_filter is required');
	}

	// 1. Navigate to Reports section
	ctx.log('Otevírám sekci Reporty a filtry...');
	const reportsLink = ctx.page
		.locator('#menu_item_reports')
		.getByRole('link', { name: 'Reporty a filtry' });

	if (!await safeClick(ctx, reportsLink, 'Reporty a filtry menu link', STEP_TIMEOUT)) {
		return fail('Menu link "Reporty a filtry" not found');
	}
	await ctx.page.waitForLoadState('networkidle');

	// 2. Select report "Přiřazené"
	ctx.log('Vybírám report Přiřazené...');
	const reportLink = ctx.page.getByRole('link', { name: 'Přiřazené' });

	if (!await safeClick(ctx, reportLink, 'Report Přiřazené link', STEP_TIMEOUT)) {
		return fail('Report link "Přiřazené" not found');
	}
	await ctx.page.waitForLoadState('networkidle');

	// 3. Apply user filter
	ctx.log(`Nastavuji filtr user_filter: ${userFilter}...`);
	const filterSelect = ctx.page.locator('select[name="filter[user_filter]"]');

	try {
		await filterSelect.waitFor({ state: 'visible', timeout: STEP_TIMEOUT });
		await filterSelect.selectOption(userFilter);
	} catch {
		return fail(`Filter select "user_filter" not found or value "${userFilter}" invalid`);
	}

	// 4. Click filter button
	ctx.log('Klikám na tlačítko Filtrovat...');
	const filterBtn = ctx.page.locator(
		'button[type="submit"], input[type="submit"], '
		+ 'button:has-text("Filtrovat"), button:has-text("Filter"), '
		+ 'a:has-text("Filtrovat")',
	);

	if (!await safeClick(ctx, filterBtn.first(), 'Filter button', STEP_TIMEOUT)) {
		return fail('Filter button not found');
	}

	// 5. Wait for AJAX results to load
	await ctx.page.waitForLoadState('networkidle');
	await ctx.page.waitForTimeout(AJAX_SETTLE);

	// 6. Click CSV export (triggers popup + download)
	ctx.log('Klikám na CSV export...');
	const csvLink = ctx.page.getByRole('link', { name: 'CSV' });

	try {
		await csvLink.waitFor({ state: 'visible', timeout: STEP_TIMEOUT });
	} catch {
		return fail('CSV export link not found');
	}

	const downloadTimeout = typeof input.download_timeout === 'number'
		? input.download_timeout
		: 60_000;

	const popupPromise = ctx.page.waitForEvent('popup', { timeout: downloadTimeout });
	const downloadPromise = ctx.page.waitForEvent('download', { timeout: downloadTimeout });

	await csvLink.click();

	let download;
	let popup;
	try {
		[popup, download] = await Promise.all([popupPromise, downloadPromise]);
	} catch {
		return fail('Download did not start within timeout');
	}

	const filename = download.suggestedFilename() || 'report-assignments.csv';

	// 7. Save to temp, upload as artifact, cleanup
	const { mkdtemp, rm } = await import('node:fs/promises');
	const { tmpdir } = await import('node:os');
	const { join } = await import('node:path');

	const tempDir = await mkdtemp(join(tmpdir(), 'pm-report-'));
	const filePath = join(tempDir, filename);
	await download.saveAs(filePath);

	try {
		const artifact = await ctx.api.uploadArtifact(ctx.job.id, filePath, {
			filename,
			mimeType: 'text/csv',
			metadata: { report: 'assignments', user_filter: userFilter },
		});

		return {
			success: true,
			artifact_id: artifact.artifact_id,
			filename: artifact.filename,
			size_bytes: artifact.size_bytes,
		};
	} finally {
		await popup?.close().catch(() => {});
		await rm(tempDir, { recursive: true, force: true }).catch(() => {});
	}
}
