export interface JobResultPayload {
    status: 'success' | 'failed';
    result?: Record<string, unknown>;
    error?: string;
    screenshots?: Array<{ step: string; file: string }>;
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

    private getHeaders(): Record<string, string> {
        return {
            Authorization: `Bearer ${this.secret}`,
        };
    }
}
