import { mkdir } from 'fs/promises';
import { join } from 'path';
import type { Page } from 'playwright';

const SCREENSHOT_DIR = process.env.SCREENSHOT_DIR || '../adapter/storage/screenshots';

export interface ScreenshotEntry {
    step: string;
    file: string;
}

export class ScreenshotManager {
    private screenshots: ScreenshotEntry[] = [];
    private jobDir: string;
    private counter = 0;

    constructor(private jobId: string) {
        this.jobDir = join(SCREENSHOT_DIR, jobId);
    }

    async init(): Promise<void> {
        await mkdir(this.jobDir, { recursive: true });
    }

    async capture(page: Page, step: string): Promise<void> {
        this.counter++;
        const filename = `${String(this.counter).padStart(2, '0')}-${step}.png`;
        const filepath = join(this.jobDir, filename);

        await page.screenshot({ path: filepath, fullPage: true });

        this.screenshots.push({ step, file: filename });
    }

    getScreenshots(): ScreenshotEntry[] {
        return this.screenshots;
    }
}
