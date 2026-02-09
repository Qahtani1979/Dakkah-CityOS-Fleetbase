<?php

namespace Fleetbase\CityOS\Services;

use Fleetbase\CityOS\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TemporalService
{
    protected string $address;
    protected string $namespace;
    protected string $apiKey;
    protected string $cmsBaseUrl;
    protected string $cmsApiKey;

    public function __construct()
    {
        $this->address = config('cityos.temporal.address', env('TEMPORAL_ADDRESS', ''));
        $this->namespace = config('cityos.temporal.namespace', env('TEMPORAL_NAMESPACE', ''));
        $this->apiKey = config('cityos.temporal.api_key', env('TEMPORAL_API_KEY', ''));
        $this->cmsBaseUrl = config('cityos.cms.base_url', env('CITYOS_CMS_BASE_URL', ''));
        $this->cmsApiKey = config('cityos.cms.api_key', env('CITYOS_CMS_API_KEY', ''));
    }

    public function getConnectionInfo(): array
    {
        return [
            'address' => $this->address,
            'namespace' => $this->namespace,
            'connected' => !empty($this->address) && !empty($this->namespace) && !empty($this->apiKey),
            'tls' => true,
            'region' => 'ap-northeast-1',
        ];
    }

    public function startWorkflow(string $workflowType, string $workflowId, array $input = [], string $taskQueue = 'cityos-default'): array
    {
        $correlationId = (string) Str::uuid();
        $startTime = microtime(true);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'X-Namespace' => $this->namespace,
            ])->timeout(30)->post("https://{$this->address}/api/v1/namespaces/{$this->namespace}/workflows/{$workflowId}", [
                'workflowType' => ['name' => $workflowType],
                'taskQueue' => ['name' => $taskQueue],
                'input' => ['payloads' => [['metadata' => ['encoding' => base64_encode('json/plain')], 'data' => base64_encode(json_encode($input))]]],
                'workflowId' => $workflowId,
                'requestId' => $correlationId,
            ]);

            $result = [
                'success' => $response->successful(),
                'workflow_id' => $workflowId,
                'workflow_type' => $workflowType,
                'run_id' => $response->json('workflowRunId') ?? $response->json('runId'),
                'correlation_id' => $correlationId,
                'status_code' => $response->status(),
            ];

            IntegrationLog::logRequest('temporal', 'start_workflow', [
                'correlation_id' => $correlationId,
                'request_data' => ['workflow_type' => $workflowType, 'workflow_id' => $workflowId, 'task_queue' => $taskQueue],
                'response_data' => $result,
                'response_code' => $response->status(),
                'duration_ms' => (microtime(true) - $startTime) * 1000,
                'status' => $response->successful() ? 'success' : 'error',
            ]);

            return $result;
        } catch (\Exception $e) {
            IntegrationLog::logRequest('temporal', 'start_workflow', [
                'correlation_id' => $correlationId,
                'request_data' => ['workflow_type' => $workflowType, 'workflow_id' => $workflowId],
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'duration_ms' => (microtime(true) - $startTime) * 1000,
            ]);

            return ['success' => false, 'error' => $e->getMessage(), 'correlation_id' => $correlationId];
        }
    }

    public function queryWorkflow(string $workflowId, string $runId = null): array
    {
        $correlationId = (string) Str::uuid();
        $startTime = microtime(true);

        try {
            $url = "https://{$this->address}/api/v1/namespaces/{$this->namespace}/workflows/{$workflowId}";
            if ($runId) {
                $url .= "?execution.runId={$runId}";
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'X-Namespace' => $this->namespace,
            ])->timeout(30)->get($url);

            $result = [
                'success' => $response->successful(),
                'workflow_id' => $workflowId,
                'data' => $response->json(),
                'status_code' => $response->status(),
            ];

            IntegrationLog::logRequest('temporal', 'query_workflow', [
                'correlation_id' => $correlationId,
                'request_data' => ['workflow_id' => $workflowId, 'run_id' => $runId],
                'response_data' => $result,
                'response_code' => $response->status(),
                'duration_ms' => (microtime(true) - $startTime) * 1000,
            ]);

            return $result;
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function signalWorkflow(string $workflowId, string $signalName, array $payload = [], string $runId = null): array
    {
        $correlationId = (string) Str::uuid();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'X-Namespace' => $this->namespace,
            ])->timeout(30)->post("https://{$this->address}/api/v1/namespaces/{$this->namespace}/workflows/{$workflowId}/signal/{$signalName}", [
                'signalName' => $signalName,
                'input' => ['payloads' => [['metadata' => ['encoding' => base64_encode('json/plain')], 'data' => base64_encode(json_encode($payload))]]],
                'workflowExecution' => ['workflowId' => $workflowId, 'runId' => $runId ?? ''],
            ]);

            IntegrationLog::logRequest('temporal', 'signal_workflow', [
                'correlation_id' => $correlationId,
                'request_data' => ['workflow_id' => $workflowId, 'signal' => $signalName],
                'response_code' => $response->status(),
                'status' => $response->successful() ? 'success' : 'error',
            ]);

            return ['success' => $response->successful(), 'status_code' => $response->status()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function listWorkflows(int $pageSize = 20, string $query = ''): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'X-Namespace' => $this->namespace,
            ])->timeout(30)->get("https://{$this->address}/api/v1/namespaces/{$this->namespace}/workflows", [
                'query' => $query,
                'maximumPageSize' => $pageSize,
            ]);

            return ['success' => $response->successful(), 'data' => $response->json(), 'status_code' => $response->status()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function triggerCMSSync(int $limit = 100): array
    {
        $correlationId = (string) Str::uuid();

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->cmsApiKey,
                'X-CityOS-Correlation-Id' => $correlationId,
            ])->timeout(60)->post("{$this->cmsBaseUrl}/api/sync/temporal/run", [
                'limit' => $limit,
            ]);

            IntegrationLog::logRequest('temporal_cms_sync', 'trigger_sync', [
                'correlation_id' => $correlationId,
                'request_data' => ['limit' => $limit],
                'response_data' => $response->json(),
                'response_code' => $response->status(),
                'status' => $response->successful() ? 'success' : 'error',
            ]);

            return ['success' => $response->successful(), 'data' => $response->json()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getSyncStatus(): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->cmsApiKey,
            ])->timeout(15)->get("{$this->cmsBaseUrl}/api/sync/temporal/status");

            return ['success' => $response->successful(), 'data' => $response->json()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getWorkflowRegistry(string $domain = null): array
    {
        try {
            $url = "{$this->cmsBaseUrl}/api/workflow-registry";
            if ($domain) {
                $url .= "?domain={$domain}";
            }

            $response = Http::withHeaders([
                'X-API-Key' => $this->cmsApiKey,
            ])->timeout(15)->get($url);

            return ['success' => $response->successful(), 'data' => $response->json()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getWorkflowRegistryStats(): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->cmsApiKey,
            ])->timeout(15)->get("{$this->cmsBaseUrl}/api/workflow-registry/stats");

            return ['success' => $response->successful(), 'data' => $response->json()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
