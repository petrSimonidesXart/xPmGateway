import type { ToolContext, ToolOutput } from '../types.js';
import { loginToLegacySystem } from '../../lib/auth.js';

/**
 * pm_login: Log in to the PM application.
 * Input: — (uses service_account credentials from job context)
 * Output: { success, logged_in }
 */
export async function pmLogin(ctx: ToolContext, _input: Record<string, unknown>): Promise<ToolOutput> {
	ctx.log('Přihlašuji se do PM aplikace...');

	await loginToLegacySystem(
		ctx.page,
		ctx.baseUrl,
		ctx.job.service_account.username,
		ctx.job.service_account.password,
	);

	ctx.log('Přihlášení úspěšné');
	return { success: true, logged_in: true };
}
