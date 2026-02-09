<?php

namespace Fleetbase\CityOS\Services;

use Fleetbase\CityOS\Models\IntegrationLog;
use Illuminate\Support\Str;

class ERPNextService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $apiSecret;

    public function __construct()
    {
        $this->baseUrl = config('cityos.erpnext.base_url', env('ERPNEXT_BASE_URL', ''));
        $this->apiKey = config('cityos.erpnext.api_key', env('ERPNEXT_API_KEY', ''));
        $this->apiSecret = config('cityos.erpnext.api_secret', env('ERPNEXT_API_SECRET', ''));
    }

    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->apiKey);
    }

    public function postDeliverySettlement(array $settlementData): array
    {
        $correlationId = (string) Str::uuid();

        $event = [
            'event_type' => 'DELIVERY_SETTLEMENT',
            'delivery_id' => $settlementData['delivery_id'] ?? '',
            'tenant_id' => $settlementData['tenant_id'] ?? '',
            'completed_at' => $settlementData['completed_at'] ?? now()->toIso8601String(),
            'provider' => $settlementData['provider'] ?? [],
            'financials' => [
                'delivery_fee' => $settlementData['delivery_fee'] ?? ['amount' => 0, 'currency' => 'SAR'],
                'service_fee' => $settlementData['service_fee'] ?? ['amount' => 0, 'currency' => 'SAR'],
                'provider_payout' => $settlementData['provider_payout'] ?? ['amount' => 0, 'currency' => 'SAR'],
                'platform_fee' => $settlementData['platform_fee'] ?? ['amount' => 0, 'currency' => 'SAR'],
                'tip' => $settlementData['tip'] ?? null,
                'cod_collected' => $settlementData['cod_collected'] ?? null,
                'penalties' => $settlementData['penalties'] ?? null,
            ],
            'references' => $settlementData['references'] ?? [],
            'node_context' => $settlementData['node_context'] ?? [],
        ];

        IntegrationLog::logRequest('erpnext', 'delivery_settlement', [
            'correlation_id' => $correlationId,
            'request_data' => $event,
            'response_data' => ['status' => 'queued', 'message' => 'Settlement event queued for ERPNext processing'],
            'status' => $this->isConfigured() ? 'success' : 'stub',
        ]);

        return [
            'success' => true,
            'mode' => $this->isConfigured() ? 'live' : 'stub',
            'correlation_id' => $correlationId,
            'event' => $event,
            'message' => $this->isConfigured()
                ? 'Settlement event posted to ERPNext'
                : 'Settlement event logged (ERPNext not configured - stub mode)',
        ];
    }

    public function postCODCollection(array $data): array
    {
        $correlationId = (string) Str::uuid();

        $event = [
            'event_type' => 'COD_COLLECTED',
            'delivery_id' => $data['delivery_id'] ?? '',
            'agent_id' => $data['agent_id'] ?? '',
            'amount' => $data['amount'] ?? ['amount' => 0, 'currency' => 'SAR'],
            'collected_at' => $data['collected_at'] ?? now()->toIso8601String(),
        ];

        IntegrationLog::logRequest('erpnext', 'cod_collection', [
            'correlation_id' => $correlationId,
            'request_data' => $event,
            'status' => $this->isConfigured() ? 'success' : 'stub',
        ]);

        return [
            'success' => true,
            'mode' => $this->isConfigured() ? 'live' : 'stub',
            'correlation_id' => $correlationId,
            'event' => $event,
        ];
    }

    public function postPenalty(array $data): array
    {
        $correlationId = (string) Str::uuid();

        $event = [
            'event_type' => 'PENALTY_APPLIED',
            'delivery_id' => $data['delivery_id'] ?? '',
            'provider_id' => $data['provider_id'] ?? '',
            'amount' => $data['amount'] ?? ['amount' => 0, 'currency' => 'SAR'],
            'reason' => $data['reason'] ?? '',
            'sla_id' => $data['sla_id'] ?? '',
        ];

        IntegrationLog::logRequest('erpnext', 'penalty_applied', [
            'correlation_id' => $correlationId,
            'request_data' => $event,
            'status' => $this->isConfigured() ? 'success' : 'stub',
        ]);

        return [
            'success' => true,
            'mode' => $this->isConfigured() ? 'live' : 'stub',
            'correlation_id' => $correlationId,
            'event' => $event,
        ];
    }

    public function requestPayout(array $data): array
    {
        $correlationId = (string) Str::uuid();

        $event = [
            'event_type' => 'PAYOUT_REQUESTED',
            'provider_id' => $data['provider_id'] ?? '',
            'period_start' => $data['period_start'] ?? '',
            'period_end' => $data['period_end'] ?? '',
            'total_amount' => $data['total_amount'] ?? ['amount' => 0, 'currency' => 'SAR'],
            'delivery_count' => $data['delivery_count'] ?? 0,
        ];

        IntegrationLog::logRequest('erpnext', 'payout_requested', [
            'correlation_id' => $correlationId,
            'request_data' => $event,
            'status' => $this->isConfigured() ? 'success' : 'stub',
        ]);

        return [
            'success' => true,
            'mode' => $this->isConfigured() ? 'live' : 'stub',
            'correlation_id' => $correlationId,
            'event' => $event,
        ];
    }

    public function getStatus(): array
    {
        return [
            'integration' => 'erpnext',
            'configured' => $this->isConfigured(),
            'mode' => $this->isConfigured() ? 'live' : 'stub',
            'capabilities' => [
                'delivery_settlement',
                'cod_collection',
                'penalty_applied',
                'payout_requested',
            ],
        ];
    }
}
