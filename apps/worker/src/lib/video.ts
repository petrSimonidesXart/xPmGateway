import { mkdtemp, rm } from 'fs/promises';
import { join } from 'path';
import { tmpdir } from 'os';
import type { Video } from 'playwright';
import type { AdapterApi } from './api.js';

const RECORD_VIDEO = process.env.RECORD_VIDEO !== '0';

export class VideoRecorder {
	private videoDir: string | null = null;

	constructor(private jobId: string) {}

	get enabled(): boolean {
		return RECORD_VIDEO;
	}

	async init(): Promise<void> {
		if (!this.enabled) return;
		this.videoDir = await mkdtemp(join(tmpdir(), 'pw-video-'));
	}

	contextOptions(): { recordVideo?: { dir: string; size?: { width: number; height: number } } } {
		if (!this.enabled || !this.videoDir) return {};
		return {
			recordVideo: {
				dir: this.videoDir,
				size: { width: 1280, height: 720 },
			},
		};
	}

	/**
	 * Upload the recorded video as a job artifact.
	 * Must be called AFTER context.close() which finalizes the video file.
	 */
	async upload(video: Video | null, api: AdapterApi): Promise<void> {
		if (!this.enabled || !video) return;
		try {
			const videoPath = await video.path();
			await api.uploadArtifact(this.jobId, videoPath, {
				filename: 'recording.webm',
				mimeType: 'video/webm',
				metadata: { type: 'recording' },
			});
			console.log(`[Worker] Video uploaded for job ${this.jobId}`);
		} catch (error) {
			console.warn(`[Worker] Video upload failed for job ${this.jobId}:`, error instanceof Error ? error.message : error);
		}
	}

	async cleanup(): Promise<void> {
		if (this.videoDir) {
			await rm(this.videoDir, { recursive: true, force: true }).catch(() => {});
		}
	}
}
