<?php

namespace Fleetbase\CityOS\Services;

use Fleetbase\CityOS\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TemporalService
{
    protected string $grpcAddress;
    protected string $namespace;
    protected string $apiKey;
    protected string $cloudOpsUrl = 'https://saas-api.tmprl.cloud';
    protected string $cmsBaseUrl;
    protected string $cmsApiKey;

    public function __construct()
    {
        $this->grpcAddress = config('cityos.temporal.address', env('TEMPORAL_ADDRESS', ''));
        $this->namespace = config('cityos.temporal.namespace', env('TEMPORAL_NAMESPACE', ''));
        $this->apiKey = config('cityos.temporal.api_key', env('TEMPORAL_API_KEY', ''));
        $this->cmsBaseUrl = config('cityos.cms.base_url', env('CITYOS_CMS_BASE_URL', ''));
        $this->cmsApiKey = config('cityos.cms.api_key', env('CITYOS_CMS_API_KEY', ''));
    }

    protected function httpApiBase(): string
    {
        $host = preg_replace('/:7233$/', '', $this->grpcAddress);
        return "https://{$host}";
    }

    protected function temporalHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    public function getConnectionInfo(): array
    {
        $connected = !empty($this->grpcAddress) && !empty($this->namespace) && !empty($this->apiKey);
        $info = [
            'grpc_address' => $this->grpcAddress,
            'http_api_base' => $this->httpApiBase(),
            'cloud_ops_api' => $this->cloudOpsUrl,
            'namespace' => $this->namespace,
            'configured' => $connected,
            'tls' => true,
            'region' => 'ap-northeast-1',
            'protocol' => 'gRPC (port 7233) + Cloud Ops REST API',
        ];

        if ($connected) {
            $info['health'] = $this->checkHealth();
        }

        return $info;
    }

    public function checkHealth(): array
    {
        $startTime = microtime(true);

        try {
            $response = Http::withHeaders($this->temporalHeaders())
                ->timeout(10)
                ->get("{$this->cloudOpsUrl}/api/v1/namespaces");

            $namespaces = $response->json('namespaces', []);
            $found = false;
            foreach ($namespaces as $ns) {
                if (($ns['namespace'] ?? '') === $this->namespace || ($ns['spec']['name'] ?? '') === $this->namespace) {
                    $found = true;
                    break;
                }
            }

            IntegrationLog::logRequest('temporal', 'health_check', [
                'response_code' => $response->status(),
                'duration_ms' => (microtime(true) - $startTime) * 1000,
                'response_data' => [
                    'namespace_found' => $found,
                    'total_namespaces' => count($namespaces),
                ],
                'status' => $response->successful() ? 'success' : 'error',
            ]);

            return [
                'reachable' => $response->successful(),
                'namespace_found' => $found,
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            return ['reachable' => false, 'error' => $e->getMessage()];
        }
    }

    public function getNamespaceInfo(): array
    {
        try {
            $namespaceParts = explode('.', $this->namespace);
            $nsName = $namespaceParts[0] ?? $this->namespace;

            $response = Http::withHeaders($this->temporalHeaders())
                ->timeout(15)
                ->get("{$this->cloudOpsUrl}/api/v1/namespaces/{$this->namespace}");

            return [
                'success' => $response->successful(),
                'data' => $response->json(),
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function startWorkflow(string $workflowType, string $workflowId, array $input = [], string $taskQueue = 'cityos-default'): array
    {
        $correlationId = (string) Str::uuid();
        $startTime = microtime(true);

        $workflowRequest = [
            'workflow_type' => $workflowType,
            'workflow_id' => $workflowId,
            'task_queue' => $taskQueue,
            'input' => $input,
            'namespace' => $this->namespace,
            'grpc_address' => $this->grpcAddress,
            'timestamp' => now()->toIso8601String(),
        ];

        IntegrationLog::logRequest('temporal', 'start_workflow', [
            'correlation_id' => $correlationId,
            'request_data' => $workflowRequest,
            'response_data' => [
                'message' => 'Workflow start request registered. Temporal Cloud gRPC connection required for execution.',
                'note' => 'Direct workflow execution requires a Temporal worker with gRPC client. This PHP service logs the intent and can trigger via CMS sync.',
            ],
            'status' => 'queued',
            'duration_ms' => (microtime(true) - $startTime) * 1000,
        ]);

        $syncResult = null;
        if (!empty($this->cmsBaseUrl)) {
            $syncResult = $this->triggerCMSSyncForWorkflow($workflowType, $workflowId, $input, $correlationId);
        }

        return [
            'success' => true,
            'workflow_id' => $workflowId,
            'workflow_type' => $workflowType,
            'task_queue' => $taskQueue,
            'correlation_id' => $correlationId,
            'mode' => 'queued_via_cms_sync',
            'cms_sync' => $syncResult,
            'note' => 'Workflow registered. Use Temporal worker (Python/TypeScript SDK with gRPC) for direct execution.',
        ];
    }

    protected function triggerCMSSyncForWorkflow(string $workflowType, string $workflowId, array $input, string $correlationId): ?array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->cmsApiKey,
                'X-CityOS-Correlation-Id' => $correlationId,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post("{$this->cmsBaseUrl}/api/sync/temporal/run", [
                'workflow_type' => $workflowType,
                'workflow_id' => $workflowId,
                'input' => $input,
                'namespace' => $this->namespace,
                'limit' => 1,
            ]);

            return ['success' => $response->successful(), 'status_code' => $response->status()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function queryWorkflow(string $workflowId, string $runId = null): array
    {
        $correlationId = (string) Str::uuid();

        try {
            $url = "{$this->httpApiBase()}/api/v1/namespaces/{$this->namespace}/workflow-executions/{$workflowId}";
            if ($runId) {
                $url .= "?execution.runId={$runId}";
            }

            $response = Http::withHeaders($this->temporalHeaders())
                ->timeout(30)->get($url);

            IntegrationLog::logRequest('temporal', 'query_workflow', [
                'correlation_id' => $correlationId,
                'request_data' => ['workflow_id' => $workflowId, 'run_id' => $runId],
                'response_data' => $response->json(),
                'response_code' => $response->status(),
                'status' => $response->successful() ? 'success' : 'error',
            ]);

            return [
                'success' => $response->successful(),
                'workflow_id' => $workflowId,
                'data' => $response->json(),
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function signalWorkflow(string $workflowId, string $signalName, array $payload = [], string $runId = null): array
    {
        $correlationId = (string) Str::uuid();

        IntegrationLog::logRequest('temporal', 'signal_workflow', [
            'correlation_id' => $correlationId,
            'request_data' => [
                'workflow_id' => $workflowId,
                'signal' => $signalName,
                'payload' => $payload,
                'run_id' => $runId,
            ],
            'status' => 'queued',
        ]);

        return [
            'success' => true,
            'workflow_id' => $workflowId,
            'signal_name' => $signalName,
            'correlation_id' => $correlationId,
            'mode' => 'queued',
            'note' => 'Signal registered. Requires Temporal gRPC worker for delivery.',
        ];
    }

    public function listWorkflows(int $pageSize = 20, string $query = ''): array
    {
        try {
            $response = Http::withHeaders($this->temporalHeaders())
                ->timeout(30)
                ->get("{$this->httpApiBase()}/api/v1/namespaces/{$this->namespace}/workflow-executions", [
                    'query' => $query ?: "ExecutionStatus = 'Running'",
                    'pageSize' => $pageSize,
                ]);

            return [
                'success' => $response->successful(),
                'data' => $response->json(),
                'status_code' => $response->status(),
            ];
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
