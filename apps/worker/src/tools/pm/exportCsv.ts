import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_export_csv: Export tasks or timesheets as CSV from the current project.
 * Input: { type: 'tasks' | 'timesheets', filters?: Record<string, string> }
 * Output: { success, artifact_id, filename }
 */
export async function pmExportCsv(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const exportType = String(input.type ?? 'tasks');

	// Find and click export button
	const exportBtn = ctx.page.locator(
		exportType === 'timesheets'
			? 'a:has-text("Export výkaz"), a:has-text("Export time"), a[href*="export"][href*="time"], '
			  + 'button:has-text("Export výkaz")'
			: 'a:has-text("Export"), a:has-text("CSV"), a[href*="export"], '
			  + 'button:has-text("Export"), button:has-text("CSV"), '
			  + '.export-btn, #export-csv',
	);

	if (await exportBtn.count() === 0) {
		return { success: false, error: `Export button not found for type: ${exportType}` };
	}

	// Wait for download
	const downloadPromise = ctx.page.waitForEvent('download', { timeout: 60_000 });
	await exportBtn.first().click();

	let download;
	try {
		download = await downloadPromise;
	} catch {
		return { success: false, error: 'Download did not start within timeout' };
	}

	const filename = download.suggestedFilename() || `export-${exportType}.csv`;

	// Save to temp and upload as artifact
	const { mkdtemp, rm } = await import('node:fs/promises');
	const { tmpdir } = await import('node:os');
	const { join } = await import('node:path');

	const tempDir = await mkdtemp(join(tmpdir(), 'pm-export-'));
	const filePath = join(tempDir, filename);
	await download.saveAs(filePath);

	try {
		const artifact = await ctx.api.uploadArtifact(ctx.job.id, filePath, {
			filename,
			mimeType: 'text/csv',
			metadata: { type: exportType },
		});

		return {
			success: true,
			artifact_id: artifact.artifact_id,
			filename: artifact.filename,
			size_bytes: artifact.size_bytes,
		};
	} finally {
		await rm(tempDir, { recursive: true, force: true }).catch(() => {});
	}
}
