<?php

namespace Fleetbase\CityOS\Services;

use Fleetbase\CityOS\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayloadCMSService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('cityos.cms.base_url', env('CITYOS_CMS_BASE_URL', ''));
        $this->apiKey = config('cityos.cms.api_key', env('CITYOS_CMS_API_KEY', ''));
    }

    protected function headers(array $extra = []): array
    {
        return array_merge([
            'X-API-Key' => $this->apiKey,
            'X-CityOS-Correlation-Id' => (string) Str::uuid(),
            'Accept' => 'application/json',
        ], $extra);
    }

    protected function logAndReturn(string $operation, $response, string $correlationId, float $startTime, array $requestData = []): array
    {
        $result = [
            'success' => $response->successful(),
            'data' => $response->json(),
            'status_code' => $response->status(),
            'correlation_id' => $correlationId,
        ];

        IntegrationLog::logRequest('payload_cms', $operation, [
            'correlation_id' => $correlationId,
            'request_data' => $requestData,
            'response_data' => $result['data'],
            'response_code' => $response->status(),
            'duration_ms' => (microtime(true) - $startTime) * 1000,
            'status' => $response->successful() ? 'success' : 'error',
        ]);

        return $result;
    }

    public function getHealth(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/health");
            return ['success' => $response->successful(), 'data' => $response->json()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getNodes(array $params = []): array
    {
        $correlationId = (string) Str::uuid();
        $startTime = microtime(true);

        try {
            $response = Http::withHeaders($this->headers(['X-CityOS-Correlation-Id' => $correlationId]))
                ->timeout(15)->get("{$this->baseUrl}/api/v1/nodes", $params);
            return $this->logAndReturn('get_nodes', $response, $correlationId, $startTime, $params);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getTenants(array $params = []): array
    {
        $correlationId = (string) Str::uuid();
        $startTime = microtime(true);

        try {
            $response = Http::withHeaders($this->headers(['X-CityOS-Correlation-Id' => $correlationId]))
                ->timeout(15)->get("{$this->baseUrl}/api/v1/tenants", $params);
            return $this->logAndReturn('get_tenants', $response, $correlationId, $startTime, $params);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getPOIs(array $params = []): array
    {
        $correlationId = (string) Str::uuid();
        $startTime = microtime(true);

        try {
            $response = Http::withHeaders($this->headers(['X-CityOS-Correlation-Id' => $correlationId]))
                ->timeout(15)->get("{$this->baseUrl}/api/v1/pois", $params);
            return $this->logAndReturn('get_pois', $response, $correlationId, $startTime, $params);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getCollections(array $params = []): array
    {
        $correlationId = (string) Str::uuid();
        $startTime = microtime(true);

        try {
            $response = Http::withHeaders($this->headers(['X-CityOS-Correlation-Id' => $correlationId]))
                ->timeout(15)->get("{$this->baseUrl}/api/v1/collections", $params);
            return $this->logAndReturn('get_collections', $response, $correlationId, $startTime, $params);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getGovernance(array $params = []): array
    {
        $correlationId = (string) Str::uuid();
        $startTime = microtime(true);

        try {
            $response = Http::withHeaders($this->headers(['X-CityOS-Correlation-Id' => $correlationId]))
                ->timeout(15)->get("{$this->baseUrl}/api/v1/governance", $params);
            return $this->logAndReturn('get_governance', $response, $correlationId, $startTime, $params);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getPersonas(array $params = []): array
    {
        $correlationId = (string) Str::uuid();
        $startTime = microtime(true);

        try {
            $response = Http::withHeaders($this->headers(['X-CityOS-Correlation-Id' => $correlationId]))
                ->timeout(15)->get("{$this->baseUrl}/api/v1/personas", $params);
            return $this->logAndReturn('get_personas', $response, $correlationId, $startTime, $params);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getWorkflowStatus(): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(15)->get("{$this->baseUrl}/api/v1/workflow-status");
            return ['success' => $response->successful(), 'data' => $response->json()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getIntegrationStatus(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/integration/workflow-status");
            return ['success' => $response->successful(), 'data' => $response->json()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function listBuckets(): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(15)->get("{$this->baseUrl}/api/storage");
            return ['success' => $response->successful(), 'data' => $response->json()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function listObjects(string $bucket = 'cityos-media', string $prefix = '', int $maxKeys = 100): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(15)->get("{$this->baseUrl}/api/storage/{$bucket}", [
                    'prefix' => $prefix,
                    'maxKeys' => $maxKeys,
                ]);
            return ['success' => $response->successful(), 'data' => $response->json()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function uploadObject(string $bucket, string $key, string $filePath, string $contentType = 'application/octet-stream'): array
    {
        $correlationId = (string) Str::uuid();
        $startTime = microtime(true);

        try {
            $response = Http::withHeaders(array_merge($this->headers(), [
                'Content-Type' => $contentType,
                'X-CityOS-Correlation-Id' => $correlationId,
            ]))->timeout(60)->withBody(file_get_contents($filePath), $contentType)
                ->put("{$this->baseUrl}/api/storage/{$bucket}/{$key}");

            IntegrationLog::logRequest('payload_cms', 'upload_object', [
                'correlation_id' => $correlationId,
                'request_data' => ['bucket' => $bucket, 'key' => $key, 'content_type' => $contentType],
                'response_code' => $response->status(),
                'duration_ms' => (microtime(true) - $startTime) * 1000,
                'status' => $response->successful() ? 'success' : 'error',
            ]);

            return [
                'success' => $response->successful(),
                'url' => "{$this->baseUrl}/api/storage/{$bucket}/{$key}",
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function downloadObject(string $bucket, string $key): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)->get("{$this->baseUrl}/api/storage/{$bucket}/{$key}");
            return [
                'success' => $response->successful(),
                'body' => $response->body(),
                'content_type' => $response->header('Content-Type'),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getStorageInfo(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/api/storage/info");
            return ['success' => $response->successful(), 'data' => $response->json()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
