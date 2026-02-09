# Dakkah-CityOS-Fleetbase — Integration Strategy

**Version:** 1.0
**Date:** 2026-02-09
**Status:** Design Phase

---

## 1. Integration Philosophy

All CityOS integrations follow these principles:

1. **Contracts-first:** Every integration boundary is defined by Zod schemas and OpenAPI specifications before implementation begins.
2. **Event-driven:** Systems communicate through reliable events with the outbox pattern, not direct database coupling.
3. **Idempotent:** Every write operation supports idempotency keys to handle retries and network failures.
4. **Context-aware:** Every cross-system call carries NodeContext for tenant isolation and compliance enforcement.
5. **Stub-ready:** Integration adapters are implemented as interfaces first, with stubs that can be replaced with real implementations incrementally.

---

## 2. Payload CMS Integration (Content & Geography)

### 2.1 Purpose

Payload CMS is the CityOS content management system and source of truth for:
- **POIs (Points of Interest):** Hubs, warehouses, pickup points, service centers
- **Zones:** Delivery zones, service areas, pricing zones
- **Service Providers:** Provider profiles and metadata
- **Operational Content:** Operating hours, service descriptions, zone policies

### 2.2 Data Flow

```
Payload CMS                                    Fleetbase
┌──────────────┐                              ┌──────────────┐
│ POI created/ │  ──── webhook ────────────>  │ Sync hub/    │
│ updated      │                              │ pickup point │
├──────────────┤                              ├──────────────┤
│ Zone policy  │  ──── webhook ────────────>  │ Update SLA   │
│ changed      │                              │ references   │
├──────────────┤                              ├──────────────┤
│ Provider     │  ──── webhook ────────────>  │ Sync provider│
│ metadata     │                              │ profile      │
└──────────────┘                              └──────────────┘

                    Fleetbase (on-demand)       Payload CMS
                    ┌──────────────┐           ┌──────────────┐
                    │ Resolve POI  │  ──────>  │ Return addr, │
                    │ for delivery │           │ geo, hours   │
                    └──────────────┘           └──────────────┘
```

### 2.3 Integration Points

| Operation | Direction | Method | Contract |
|---|---|---|---|
| Resolve POI by ID | Fleetbase → Payload | REST GET | `POIResolution { poiId, address, geo, operatingHours }` |
| Resolve zone policies | Fleetbase → Payload | REST GET | `ZonePolicy { zoneId, slaRules, constraints, pricingTier }` |
| Sync hub metadata | Payload → Fleetbase | Webhook | `HubSyncEvent { hubId, name, address, geo, capacity }` |
| Sync provider profile | Payload → Fleetbase | Webhook | `ProviderSyncEvent { providerId, name, capabilities, serviceArea }` |

### 2.4 POI Resolution

When a delivery request includes a `poiId` instead of a full address:

1. Fleetbase API facade calls Payload CMS to resolve the POI
2. Returns: address, geo coordinates, operating hours, access instructions
3. Result is cached (with TTL) to reduce load on Payload CMS
4. Cache invalidated on webhook notification of POI update

### 2.5 Zone → SLA Mapping

Zones defined in Payload CMS carry metadata that maps to SLA policies:

```
Zone (Payload CMS)                    SLA Policy (Fleetbase)
─────────────────                    ──────────────────────
zone.deliveryTier = "express"    →   maxDeliveryTime = 60 min
zone.deliveryTier = "standard"   →   maxDeliveryTime = 240 min
zone.deliveryTier = "economy"    →   maxDeliveryTime = 1440 min
zone.restrictions = ["cold_chain"] → constraints.coldChain = true
```

---

## 3. Medusa Commerce Integration (Orders & Fulfillment)

### 3.1 Purpose

Medusa Commerce is the CityOS e-commerce engine. Fleetbase acts as the fulfillment execution layer for commerce orders.

### 3.2 Data Flow

```
Medusa Commerce                    Fleetbase
┌──────────────────┐              ┌──────────────────┐
│ Order placed     │  ──event──>  │ Create delivery  │
│                  │              │ (pickup_dropoff)  │
├──────────────────┤              ├──────────────────┤
│ Return requested │  ──event──>  │ Create reverse   │
│                  │              │ logistics pickup  │
├──────────────────┤              ├──────────────────┤
│ Order canceled   │  ──event──>  │ Cancel delivery  │
│ (pre-dispatch)   │              │ if not dispatched│
└──────────────────┘              └──────────────────┘

Fleetbase                          Medusa Commerce
┌──────────────────┐              ┌──────────────────┐
│ Delivery status  │  ──event──>  │ Update shipment  │
│ changed          │              │ status           │
├──────────────────┤              ├──────────────────┤
│ Delivery         │  ──event──>  │ Mark order       │
│ completed        │              │ fulfilled        │
├──────────────────┤              ├──────────────────┤
│ Delivery failed  │  ──event──>  │ Trigger refund   │
│                  │              │ evaluation       │
└──────────────────┘              └──────────────────┘
```

### 3.3 Order → Delivery Mapping

| Commerce Field | Delivery Field | Transformation |
|---|---|---|
| `order.id` | `sourceRef.entityId` | Direct mapping |
| `"medusa"` | `sourceRef.systemId` | Constant |
| `"order"` | `sourceRef.entityType` | Constant |
| `order.shipping_address` | `dropoff.address` | Address format conversion |
| `order.items` | `items` | Map SKU, name, qty, weight |
| `order.shipping_total` | `fees.deliveryFee` | Currency conversion if needed |
| `order.total - order.shipping_total` | N/A | Not mapped (commerce concern) |
| `order.payments[cod]` | `fees.codAmount` | If payment method is COD |

### 3.4 Idempotency

Commerce → Delivery creation uses idempotency key:
```
key = hash(tenantId + "medusa" + "order" + orderId)
```

This ensures:
- Retried webhooks don't create duplicate deliveries
- The same order always maps to the same delivery
- Re-processing events is safe

### 3.5 Return/Reverse Logistics

When a return is requested:
1. Commerce emits `RETURN_REQUESTED` event
2. Fleetbase creates a `pickup_dropoff` delivery with reversed addresses:
   - Pickup = customer's address (original dropoff)
   - Dropoff = return warehouse/hub (resolved from POI)
3. Items include return reason metadata
4. Delivery tagged with `sourceRef.entityType = "return"`

---

## 4. ERPNext Integration (Accounting & Settlement)

### 4.1 Purpose

ERPNext handles financial accounting, provider payouts, and fee reconciliation for completed deliveries.

### 4.2 Data Flow

```
Fleetbase                          ERPNext
┌──────────────────┐              ┌──────────────────┐
│ Delivery         │  ──event──>  │ Create journal   │
│ completed        │              │ entry: fees,     │
│                  │              │ payout readiness │
├──────────────────┤              ├──────────────────┤
│ COD collected    │  ──event──>  │ Record cash      │
│                  │              │ collection       │
├──────────────────┤              ├──────────────────┤
│ SLA penalty      │  ──event──>  │ Create debit     │
│ applied          │              │ memo for provider│
├──────────────────┤              ├──────────────────┤
│ Settlement       │  ──event──>  │ Generate payout  │
│ period closed    │              │ batch            │
└──────────────────┘              └──────────────────┘
```

### 4.3 Settlement Event Schema

```
DeliverySettlementEvent {
  deliveryId:       string
  tenantId:         string
  nodeContext:      NodeContext
  completedAt:      datetime

  provider: {
    providerId:     string
    legalName:      string
  }

  financials: {
    deliveryFee:    Money          // Platform charges to customer
    serviceFee:     Money          // Platform service fee
    providerPayout: Money          // Amount owed to provider
    platformFee:    Money          // Platform's take
    tip:            Money?         // Tip for agent (passthrough)
    codCollected:   Money?         // Cash collected on delivery
    penalties:      Money?         // SLA penalty deductions
  }

  references: {
    commerceOrderId: string?
    invoiceNumber:   string?       // Generated by ERP
  }
}
```

### 4.4 Settlement Cycle

1. **Real-time posting:** Each delivery completion emits a settlement event
2. **Period aggregation:** Weekly/monthly settlement period closes
3. **Payout generation:** ERP aggregates events into provider payout batch
4. **Reconciliation:** Platform fee and COD reconciliation reports generated

---

## 5. Temporal Workflow Integration (Orchestration)

### 5.1 Purpose

Temporal provides durable, long-running workflow orchestration for complex fulfillment scenarios that span multiple steps, require human approval, or need retry/compensation logic.

### 5.2 Workflow Catalog

| Workflow ID | Name | Trigger | Steps |
|---|---|---|---|
| WF-FLT-001 | Delivery Dispatch Orchestration | Delivery created | 1. Evaluate zone policies → 2. Find eligible providers → 3. Select provider (rules/geo) → 4. Assign agent → 5. Notify agent → 6. Monitor acceptance (timeout → reassign) → 7. Confirm dispatch |
| WF-FLT-002 | Delivery Exception Escalation | Delivery failed | 1. Categorize exception → 2. Evaluate retry eligibility → 3. If retriable: reschedule → 4. If not: notify ops → 5. Wait for resolution signal → 6. Apply resolution (refund/return/close) |
| WF-FLT-003 | Delivery Completion Settlement | Delivery delivered | 1. Validate POD → 2. Calculate fees → 3. Emit settlement event to ERP → 4. Update commerce order status → 5. Archive delivery |
| WF-FLT-004 | Provider Onboarding Approval | Provider registered | 1. Validate documents → 2. Queue for review → 3. Wait for human approval signal → 4. If approved: activate provider → 5. If rejected: notify with reason |

### 5.3 Workflow Architecture

```
┌─────────────────┐      ┌──────────────┐      ┌─────────────────┐
│ Fleetbase API   │      │  CityBus     │      │ Temporal        │
│ (event source)  │─────>│  (events)    │─────>│ (orchestration) │
└─────────────────┘      └──────────────┘      └────────┬────────┘
                                                        │
                              ┌──────────────────────────┤
                              │                          │
                    ┌─────────▼─────────┐     ┌─────────▼─────────┐
                    │ Activity Workers  │     │ Signal Handlers   │
                    │ (execute steps)   │     │ (human approval)  │
                    └───────────────────┘     └───────────────────┘
```

### 5.4 Trigger Mechanism

Fleetbase does not call Temporal directly. Instead:

1. API facade emits an outbox event (e.g., `DELIVERY_CREATED`)
2. Outbox dispatcher publishes the event to CityBus
3. A dedicated Temporal trigger worker subscribes to relevant events
4. The trigger worker starts the appropriate Temporal workflow with:
   - Workflow ID: deterministic from deliveryId (prevents duplicates)
   - Input: full delivery data + NodeContext
   - Task queue: tenant-specific or shared (configurable)

### 5.5 Signals for Human Interaction

```
Provider Onboarding Signal:
  signalName: "approval_decision"
  payload: {
    decision: "approved" | "rejected"
    reviewer: string
    reason: string?
    conditions: string[]?        // Conditional approval notes
  }

Exception Resolution Signal:
  signalName: "exception_resolved"
  payload: {
    action: "retry" | "return_to_sender" | "refund" | "cancel"
    resolvedBy: string
    notes: string?
    rescheduleWindow: TimeWindow?
  }
```

---

## 6. CityBus Event System

### 6.1 Event Envelope

Every event published through CityBus follows a standard envelope:

```
CityBusEnvelope {
  eventId:          UUID           // Unique event identifier
  eventType:        string         // e.g., "DELIVERY_CREATED"
  version:          string         // Event schema version (e.g., "1.0")
  timestamp:        datetime       // When event occurred (ISO 8601)

  source: {
    system:         string         // "fleetbase"
    service:        string         // "fleetbase-api"
    instance:       string?        // Instance identifier
  }

  nodeContext: {
    tenant:         string
    country:        string
    locale:         string
    processingRegion: string
    residencyClass: string
  }

  correlation: {
    correlationId:  string         // Distributed trace ID
    causationId:    string?        // ID of event that caused this one
    requestId:      string?        // Original request ID
  }

  payload:          object         // Event-specific data

  metadata: {
    retryCount:     number         // Number of delivery attempts
    firstPublished: datetime       // Original publish time
    idempotencyKey: string?        // For deduplication
  }
}
```

### 6.2 Outbox Pattern

```
┌──────────────────────────────────────────────────────┐
│                   Database Transaction               │
│                                                      │
│  1. Write business data (delivery, status change)    │
│  2. Write outbox entry (same transaction)            │
│                                                      │
└──────────────────────────┬───────────────────────────┘
                           │
                           │ Commit
                           ▼
┌──────────────────────────────────────────────────────┐
│                  Outbox Dispatcher                    │
│                                                      │
│  1. Poll outbox table for unpublished entries        │
│  2. Publish to event adapter (webhook/kafka)         │
│  3. Mark entry as published                          │
│  4. On failure: retry with exponential backoff       │
│  5. After max retries: move to dead letter           │
│                                                      │
└──────────────────────────────────────────────────────┘
```

### 6.3 Outbox Table Schema

```sql
CREATE TABLE cityos_outbox (
  id              BIGSERIAL PRIMARY KEY,
  event_id        UUID NOT NULL UNIQUE,
  event_type      VARCHAR(100) NOT NULL,
  tenant_id       VARCHAR(100) NOT NULL,
  payload         JSONB NOT NULL,
  correlation_id  VARCHAR(100),
  status          VARCHAR(20) DEFAULT 'pending',  -- pending, published, failed, dead_letter
  retry_count     INT DEFAULT 0,
  max_retries     INT DEFAULT 5,
  created_at      TIMESTAMPTZ DEFAULT NOW(),
  published_at    TIMESTAMPTZ,
  next_retry_at   TIMESTAMPTZ,
  error_message   TEXT
);

CREATE INDEX idx_outbox_status ON cityos_outbox(status, next_retry_at);
CREATE INDEX idx_outbox_tenant ON cityos_outbox(tenant_id);
```

---

## 7. Payments & Wallet Integration (Stub)

### 7.1 Scope

Payments integration is stub-only in the initial phases. The following interfaces are defined for future implementation:

### 7.2 Payment Flows

| Flow | Description | Status |
|---|---|---|
| Delivery fee collection | Charge customer for delivery | Stub (via Commerce) |
| COD handling | Cash collected by agent, reconciled | Stub (event-based) |
| Tip processing | Customer tip passed to agent | Stub (passthrough) |
| Provider payout | Periodic payout to service providers | Stub (via ERP) |
| Penalty deduction | SLA breach penalty from provider payout | Stub (via ERP) |

### 7.3 Payment Event Stubs

```
PAYMENT_COLLECTED { deliveryId, amount, method: "card"|"cod"|"wallet", reference }
TIP_ADDED { deliveryId, agentId, amount, currency }
PENALTY_APPLIED { deliveryId, providerId, amount, reason, slaId }
PAYOUT_REQUESTED { providerId, periodStart, periodEnd, totalAmount, deliveryCount }
```

---

## 8. Integration Testing Strategy

### 8.1 Contract Testing

Each integration adapter includes contract tests that verify:
- Request payloads match the Zod schema
- Response payloads match expected shapes
- Error responses follow CityOS error model
- Idempotency keys prevent duplicate operations

### 8.2 Integration Test Scenarios

| # | Scenario | Systems Involved | Expected Outcome |
|---|---|---|---|
| 1 | Commerce order → delivery | Medusa → Fleetbase | Delivery created with correct mapping |
| 2 | POI resolution | Fleetbase → Payload | Address and geo returned |
| 3 | Delivery completion → settlement | Fleetbase → ERPNext | Fee posting event emitted |
| 4 | Return order → reverse delivery | Medusa → Fleetbase | Reverse delivery with swapped addresses |
| 5 | Provider onboarding | Fleetbase → Temporal | Approval workflow started |
| 6 | SLA breach → exception | Fleetbase → Temporal | Escalation workflow triggered |
| 7 | Duplicate order → idempotent | Medusa → Fleetbase | Same delivery returned, no duplicate |

### 8.3 Stub Behavior

All integration stubs:
- Log the operation that would occur
- Return realistic mock responses
- Respect idempotency keys
- Include NodeContext in all operations
- Are replaceable via dependency injection
