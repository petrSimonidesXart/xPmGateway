import { readFileSync } from 'node:fs';
import { basename } from 'node:path';

export interface JobResultPayload {
    status: 'success' | 'failed';
    result?: Record<string, unknown>;
    error?: string;
    screenshots?: Array<{ step: string; file: string }>;
}

export interface ArtifactUploadResult {
    artifact_id: string;
    filename: string;
    size_bytes: number;
}

export class AdapterApi {
    constructor(
        private baseUrl: string,
        private secret: string,
    ) {}

    async getNextJob(): Promise<{ job: any | null }> {
        const response = await fetch(`${this.baseUrl}/jobs/next`, {
            method: 'GET',
            headers: this.getHeaders(),
        });

        if (!response.ok) {
            throw new Error(`API error: ${response.status} ${response.statusText}`);
        }

        return response.json() as Promise<{ job: any | null }>;
    }

    async submitResult(jobId: string, payload: JobResultPayload): Promise<void> {
        const response = await fetch(`${this.baseUrl}/jobs/${jobId}/result`, {
            method: 'POST',
            headers: {
                ...this.getHeaders(),
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            throw new Error(`API error: ${response.status} ${response.statusText}`);
        }
    }

    /**
     * Upload a file artifact for a job.
     * Use this when a handler produces output files (CSV, PDF, images, etc.)
     */
    async uploadArtifact(
        jobId: string,
        filePath: string,
        options?: {
            filename?: string;
            mimeType?: string;
            metadata?: Record<string, unknown>;
        },
    ): Promise<ArtifactUploadResult> {
        const fileContent = readFileSync(filePath);
        const filename = options?.filename ?? basename(filePath);

        const form = new FormData();
        form.append('file', new Blob([fileContent]), filename);

        if (options?.mimeType) {
            form.append('mime_type', options.mimeType);
        }
        if (options?.metadata) {
            form.append('metadata', JSON.stringify(options.metadata));
        }

        const response = await fetch(`${this.baseUrl}/jobs/${jobId}/artifacts`, {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${this.secret}`,
            },
            body: form,
        });

        if (!response.ok) {
            throw new Error(`Artifact upload error: ${response.status} ${response.statusText}`);
        }

        return response.json() as Promise<ArtifactUploadResult>;
    }

    /**
     * Upload artifact from in-memory content (string or Buffer).
     */
    async uploadArtifactContent(
        jobId: string,
        content: string | Buffer,
        filename: string,
        mimeType: string,
        metadata?: Record<string, unknown>,
    ): Promise<ArtifactUploadResult> {
        const bytes = typeof content === 'string'
            ? new TextEncoder().encode(content)
            : content;

        const form = new FormData();
        form.append('file', new Blob([bytes as BlobPart]), filename);
        form.append('mime_type', mimeType);

        if (metadata) {
            form.append('metadata', JSON.stringify(metadata));
        }

        const response = await fetch(`${this.baseUrl}/jobs/${jobId}/artifacts`, {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${this.secret}`,
            },
            body: form,
        });

        if (!response.ok) {
            throw new Error(`Artifact upload error: ${response.status} ${response.statusText}`);
        }

        return response.json() as Promise<ArtifactUploadResult>;
    }

    private getHeaders(): Record<string, string> {
        return {
            Authorization: `Bearer ${this.secret}`,
        };
    }
}
