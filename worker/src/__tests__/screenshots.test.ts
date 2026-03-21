import { describe, it, expect, vi, beforeEach } from 'vitest';
import type { Page } from 'playwright';

vi.mock('fs/promises', () => ({
    mkdir: vi.fn().mockResolvedValue(undefined),
}));

import { ScreenshotManager } from '../lib/screenshots.js';

function createMockPage(): Page {
    return {
        screenshot: vi.fn().mockResolvedValue(Buffer.from('png')),
    } as unknown as Page;
}

describe('ScreenshotManager', () => {
    let manager: ScreenshotManager;

    beforeEach(() => {
        manager = new ScreenshotManager('job-123');
    });

    it('starts with empty screenshots list', () => {
        expect(manager.getScreenshots()).toEqual([]);
    });

    it('captures screenshot with padded counter', async () => {
        const mockPage = createMockPage();

        await manager.init();
        await manager.capture(mockPage, 'login');

        expect(mockPage.screenshot).toHaveBeenCalledWith(
            expect.objectContaining({
                fullPage: true,
                path: expect.stringContaining('01-login.png'),
            }),
        );

        const screenshots = manager.getScreenshots();
        expect(screenshots).toHaveLength(1);
        expect(screenshots[0]).toEqual({ step: 'login', file: '01-login.png' });
    });

    it('increments counter across multiple captures', async () => {
        const mockPage = createMockPage();

        await manager.init();
        await manager.capture(mockPage, 'login');
        await manager.capture(mockPage, 'form-filled');
        await manager.capture(mockPage, 'submitted');

        const screenshots = manager.getScreenshots();
        expect(screenshots).toHaveLength(3);
        expect(screenshots[0].file).toBe('01-login.png');
        expect(screenshots[1].file).toBe('02-form-filled.png');
        expect(screenshots[2].file).toBe('03-submitted.png');
    });
});
