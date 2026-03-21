import { describe, it, expect, vi, beforeEach } from 'vitest';
import { AdapterApi } from '../lib/api.js';

const mockFetch = vi.fn();
vi.stubGlobal('fetch', mockFetch);

describe('AdapterApi', () => {
    let api: AdapterApi;

    beforeEach(() => {
        api = new AdapterApi('http://localhost:8080/internal', 'test-secret');
        mockFetch.mockReset();
    });

    describe('getNextJob', () => {
        it('sends GET with correct URL and auth header', async () => {
            mockFetch.mockResolvedValue({
                ok: true,
                json: () => Promise.resolve({ job: null }),
            });

            await api.getNextJob();

            expect(mockFetch).toHaveBeenCalledWith(
                'http://localhost:8080/internal/jobs/next',
                {
                    method: 'GET',
                    headers: { Authorization: 'Bearer test-secret' },
                },
            );
        });

        it('returns job data when available', async () => {
            const job = { id: 'abc-123', tool_name: 'create_task', payload: {} };
            mockFetch.mockResolvedValue({
                ok: true,
                json: () => Promise.resolve({ job }),
            });

            const result = await api.getNextJob();
            expect(result.job).toEqual(job);
        });

        it('throws on non-ok response', async () => {
            mockFetch.mockResolvedValue({
                ok: false,
                status: 500,
                statusText: 'Internal Server Error',
            });

            await expect(api.getNextJob()).rejects.toThrow('API error: 500 Internal Server Error');
        });
    });

    describe('submitResult', () => {
        it('sends POST with JSON body', async () => {
            mockFetch.mockResolvedValue({ ok: true });

            await api.submitResult('job-1', {
                status: 'success',
                result: { task_id: '42' },
            });

            expect(mockFetch).toHaveBeenCalledWith(
                'http://localhost:8080/internal/jobs/job-1/result',
                expect.objectContaining({
                    method: 'POST',
                    body: JSON.stringify({
                        status: 'success',
                        result: { task_id: '42' },
                    }),
                }),
            );
        });

        it('throws on non-ok response', async () => {
            mockFetch.mockResolvedValue({
                ok: false,
                status: 404,
                statusText: 'Not Found',
            });

            await expect(api.submitResult('x', { status: 'failed', error: 'err' }))
                .rejects.toThrow('API error: 404');
        });
    });

    describe('uploadArtifactContent', () => {
        it('sends multipart form with content', async () => {
            mockFetch.mockResolvedValue({
                ok: true,
                json: () => Promise.resolve({ artifact_id: 'art-1', filename: 'export.csv', size_bytes: 100 }),
            });

            const result = await api.uploadArtifactContent(
                'job-1',
                'col1,col2\nval1,val2',
                'export.csv',
                'text/csv',
                { rows: 1 },
            );

            expect(result.artifact_id).toBe('art-1');
            expect(mockFetch).toHaveBeenCalledWith(
                'http://localhost:8080/internal/jobs/job-1/artifacts',
                expect.objectContaining({
                    method: 'POST',
                    headers: { Authorization: 'Bearer test-secret' },
                }),
            );
        });

        it('throws on upload failure', async () => {
            mockFetch.mockResolvedValue({
                ok: false,
                status: 413,
                statusText: 'Payload Too Large',
            });

            await expect(api.uploadArtifactContent('j', 'x', 'f.txt', 'text/plain'))
                .rejects.toThrow('Artifact upload error: 413');
        });
    });
});
