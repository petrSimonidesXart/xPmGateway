import type { ToolContext, ToolOutput } from '../types.js';
import { fail } from './helpers.js';

/**
 * pm_download_by_url — Download a file from the PM system by direct URL.
 *
 * Expects the browser to be already logged in (via pm_login step in scenario).
 * Navigates to the given URL and saves the downloaded file as artifact.
 *
 * Input: { url: string, download_timeout?: number }
 * Output: { success, artifact_id, filename, size_bytes }
 */
export async function pmDownloadByUrl(
	ctx: ToolContext,
	input: Record<string, unknown>,
): Promise<ToolOutput> {
	const url = typeof input.url === 'string' ? input.url.trim() : '';
	if (!url) {
		return fail('url is required');
	}

	const downloadTimeout = typeof input.download_timeout === 'number'
		? input.download_timeout
		: 60_000;

	const fullUrl = url.startsWith('http')
		? url
		: `${ctx.baseUrl}${url.startsWith('/') ? '' : '/'}${url}`;

	ctx.log(`Stahuji soubor z: ${fullUrl}`);

	// Start listening for download BEFORE navigation
	const downloadPromise = ctx.page.waitForEvent('download', { timeout: downloadTimeout });

	// Navigate — goto throws "Download is starting" when URL triggers a file download,
	// which is expected and not an error. Real HTTP errors are caught separately.
	try {
		const response = await ctx.page.goto(fullUrl, { waitUntil: 'commit' });
		if (response && !response.ok()) {
			return fail(`Server vrátil HTTP ${response.status()} pro ${fullUrl}`);
		}
	} catch (err: unknown) {
		const msg = err instanceof Error ? err.message : String(err);
		if (!msg.includes('Download is starting')) {
			return fail(`Navigace selhala: ${msg}`);
		}
		// "Download is starting" is expected — continue to await download
	}

	let download;
	try {
		download = await downloadPromise;
	} catch {
		return fail('Download nezačal v časovém limitu — URL pravděpodobně nevrací soubor ke stažení');
	}

	const filename = download.suggestedFilename() || 'download.bin';

	// Save to temp, upload as artifact, cleanup
	const { mkdtemp, rm, stat } = await import('node:fs/promises');
	const { tmpdir } = await import('node:os');
	const { join, extname } = await import('node:path');

	const tempDir = await mkdtemp(join(tmpdir(), 'pm-download-'));
	const filePath = join(tempDir, filename);
	await download.saveAs(filePath);

	const ext = extname(filename).toLowerCase();
	const mimeTypes: Record<string, string> = {
		'.csv': 'text/csv',
		'.xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'.xls': 'application/vnd.ms-excel',
		'.pdf': 'application/pdf',
		'.zip': 'application/zip',
		'.xml': 'application/xml',
		'.json': 'application/json',
		'.txt': 'text/plain',
	};
	const mimeType = mimeTypes[ext] || 'application/octet-stream';

	try {
		const fileStats = await stat(filePath);
		ctx.log(`Soubor stažen: ${filename} (${fileStats.size} B)`);

		const artifact = await ctx.api.uploadArtifact(ctx.job.id, filePath, {
			filename,
			mimeType,
			metadata: { source_url: url },
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
