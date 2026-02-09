# Dakkah-CityOS-Fleetbase — System Architecture Document

**Version:** 1.0
**Date:** 2026-02-09
**Status:** Design Phase

---

## 1. Executive Summary

Dakkah-CityOS-Fleetbase is a modular, production-grade fulfillment, delivery, mobility, and on-demand service execution system. It wraps the open-source Fleetbase platform inside the CityOS governance framework, enabling multi-tenant, multi-country, plugin-driven operations publishable as reusable packages across 100+ CityOS systems and 40+ BFF (Backend-for-Frontend) services.

The system separates concerns into two layers:

- **Fleetbase (Execution Engine):** Owns task execution states, driver/vehicle dispatch, and operational job lifecycle.
- **CityOS Wrapper (Governance Layer):** Owns NodeContext, policies, tenancy, compliance, contract-first APIs, event publishing, workflow triggers, storage abstraction, and multi-tenant isolation guarantees.

---

## 2. System Boundary Document

### 2.1 Fleetbase Ownership (Execution Engine)

Fleetbase serves as the operational execution core. It is responsible for:

| Capability | Description |
|---|---|
| Task Execution States | Managing the lifecycle of delivery/service tasks from creation to completion |
| Driver/Vehicle Dispatch | Assigning drivers, vehicles, and assets to tasks |
| Job Lifecycle | State transitions: created → scheduled → dispatched → enroute → arrived → delivered/failed/canceled |
| Fleet Management | Vehicle tracking, maintenance schedules, fuel reporting |
| Route Optimization | Basic routing and distance calculations |
| Operational Dashboard | Real-time operations monitoring via Fleetbase Console |

**Current Runtime Stack:**
- Laravel/PHP API on port 8000
- Ember.js Console on port 5000
- PostgreSQL + PostGIS database
- In-memory array cache (Redis planned for production)

### 2.2 CityOS Wrapper Ownership (Governance Layer)

The CityOS layer wraps Fleetbase with enterprise-grade governance:

| Capability | Description |
|---|---|
| NodeContext | Per-request context carrying tenant, locale, country, channel, surface, persona, and compliance metadata |
| Policy Enforcement | RBAC and attribute-based access control via Cerbos-ready hooks |
| Contract-First APIs | Stable, versioned Zod schemas + OpenAPI endpoints for all integration surfaces |
| Event Publishing | Reliable event delivery via outbox pattern with CityBus envelope |
| Workflow Triggers | Temporal workflow integration for long-running orchestration |
| Storage Abstraction | MinIO-backed S3-compatible storage for POD photos, documents |
| Tenant Isolation | Query-level and policy-level multi-tenancy guarantees |
| Compliance/Residency | Data residency classification and processing region enforcement |

### 2.3 Ownership Matrix

```
┌─────────────────────────────┬──────────────┬──────────────┐
│ Capability                  │ Fleetbase    │ CityOS       │
├─────────────────────────────┼──────────────┼──────────────┤
│ Task state machine          │ ●            │              │
│ Driver assignment           │ ●            │              │
│ Vehicle tracking            │ ●            │              │
│ Route calculation           │ ●            │              │
│ NodeContext resolution      │              │ ●            │
│ Tenant isolation            │              │ ●            │
│ Policy enforcement          │              │ ●            │
│ Contract validation         │              │ ●            │
│ Event publishing            │              │ ●            │
│ Workflow orchestration      │              │ ●            │
│ Storage abstraction         │              │ ●            │
│ BFF surface shaping         │              │ ●            │
│ Compliance classification   │              │ ●            │
│ Observability/metrics       │ ○            │ ●            │
│ API façade (external)       │              │ ●            │
│ API internal (ops)          │ ●            │              │
│ Database (PostGIS)          │ ●            │ ○            │
│ Geo utilities (H3, zones)   │              │ ●            │
└─────────────────────────────┴──────────────┴──────────────┘
● = Primary owner   ○ = Secondary/shared
```

---

## 3. Tenancy Model

### 3.1 Decision: Single Instance with Tenant Scoping

The system uses a **single Fleetbase instance** with CityOS tenant scoping applied at the wrapper layer.

**Rationale:**
- Operational simplicity: one deployment, one database cluster
- Cost efficiency: shared infrastructure with logical isolation
- Fleetbase already supports company-level separation internally
- CityOS NodeContext adds the additional tenant/country/region dimensions

**Tradeoffs:**

| Factor | Single Instance (Chosen) | Multi-Instance |
|---|---|---|
| Operational complexity | Low | High |
| Deployment cost | Lower | Higher (per-tenant infra) |
| Data isolation | Logical (query-level) | Physical |
| Compliance (data residency) | Requires processingRegion routing | Native per region |
| Noisy neighbor risk | Managed via rate limiting | None |
| Customization per tenant | Via config/policies | Full control |

**Mitigation for Single Instance:**
- Query-level tenant filtering on every database operation
- NodeContext validation rejects cross-tenant access
- Processing region metadata enables future data residency routing
- SLA policies scoped per tenant

### 3.2 Tenant Resolution Strategy

NodeContext is resolved from three sources (in priority order):

1. **Path-based tenancy (UI routes):** `/t/{tenant}/{locale}/...`
2. **Header-based (internal/API calls):** `X-CityOS-Tenant`, `X-CityOS-Country`, `X-CityOS-Locale`, etc.
3. **Cookie fallback (browser navigation):** `cityos_tenant`, `cityos_locale`

Any request missing required NodeContext fields is rejected with HTTP 400 on both BFF and internal API routes.

---

## 4. Integration Architecture

### 4.1 Integration Contract Plan

All integrations are **contracts-first**: defined as Zod schemas with OpenAPI baseline, versioned via changesets, and published as npm packages.

```
┌──────────────────────────────────────────────────────────────────┐
│                    CityOS Integration Layer                      │
│                                                                  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │ Payload CMS │  │   Medusa    │  │  ERPNext    │             │
│  │  (POIs,     │  │ (Orders,    │  │ (Settlement,│             │
│  │   Zones,    │  │  Shipments, │  │  Accounting)│             │
│  │   Hubs)     │  │  Returns)   │  │             │             │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘             │
│         │                │                │                      │
│         ▼                ▼                ▼                      │
│  ┌──────────────────────────────────────────────┐               │
│  │         cityos-integrations (adapters)        │               │
│  └──────────────────────┬───────────────────────┘               │
│                         │                                        │
│  ┌──────────────────────▼───────────────────────┐               │
│  │            cityos-contracts (Zod)             │               │
│  └──────────────────────┬───────────────────────┘               │
│                         │                                        │
│  ┌──────────────────────▼───────────────────────┐               │
│  │     CityOS Fleetbase API Façade              │               │
│  │  (NodeContext + Policy + Tenant Isolation)    │               │
│  └──────────────────────┬───────────────────────┘               │
│                         │                                        │
│  ┌──────────────────────▼───────────────────────┐               │
│  │          Fleetbase Core (Execution)           │               │
│  └──────────────────────────────────────────────┘               │
│                                                                  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │  CityBus    │  │  Temporal   │  │   Outbox    │             │
│  │  (Events)   │  │ (Workflows) │  │ (Reliable   │             │
│  │             │  │             │  │  Delivery)  │             │
│  └─────────────┘  └─────────────┘  └─────────────┘             │
└──────────────────────────────────────────────────────────────────┘
```

### 4.2 Integration Triggers

| Trigger Event | Source | Target System | Action |
|---|---|---|---|
| Order placed | Medusa Commerce | Fleetbase API | Create delivery request |
| Return requested | Medusa Commerce | Fleetbase API | Create reverse logistics delivery |
| Delivery created | Fleetbase | Temporal | Trigger WF-FLT-001 dispatch orchestration |
| Delivery failed | Fleetbase | Temporal | Trigger WF-FLT-002 exception escalation |
| Delivery completed | Fleetbase | Temporal | Trigger WF-FLT-003 settlement hook |
| Provider registered | Fleetbase | Temporal | Trigger WF-FLT-004 onboarding approval |
| POI updated | Payload CMS | Fleetbase | Sync hub/provider metadata |
| Zone policy changed | Payload CMS | Fleetbase | Update SLA policy references |
| Delivery completed | Fleetbase | ERPNext | Post fees, mark payout readiness |
| SLA breached | Fleetbase | CityBus | Emit SLA_BREACH_DETECTED event |

### 4.3 Event Catalog

| Event Name | Description | Payload Includes |
|---|---|---|
| DELIVERY_CREATED | New delivery order created | deliveryId, tenantId, NodeContext, type, sourceRef |
| DELIVERY_STATUS_CHANGED | Status transition occurred | deliveryId, previousStatus, newStatus, actor, timestamp |
| DELIVERY_LOCATION_UPDATED | Agent location update received | deliveryId, agentId, geoPoint, timestamp, heading, speed |
| DELIVERY_FAILED | Delivery could not be completed | deliveryId, failureReason, exceptionType |
| POD_UPLOADED | Proof of delivery submitted | deliveryId, podType (signature/photo), storageRef |
| SLA_BREACH_DETECTED | SLA threshold violated | deliveryId, slaId, breachType, severity |

---

## 5. Capabilities Map

### 5.1 Core Capabilities

| # | Capability | Description | Status |
|---|---|---|---|
| 1 | Fulfillment Execution | Pickup → hub → last-mile → dropoff | Fleetbase core (active) |
| 2 | On-Demand Services | Appointments, home services, field ops | CityOS contracts (planned) |
| 3 | Driver/Courier Management | Onboarding, verification, shifts | Fleetbase core (active) |
| 4 | Dispatch & Assignment | Routing, ETA calculations | Skeleton planned |
| 5 | Geo Tracking | Vehicles, couriers, assets + geofencing | Skeleton planned |
| 6 | Proof of Delivery | Signatures, photos via storage abstraction | Planned |
| 7 | Exception Management | Failed delivery, reschedule, refund triggers | Planned |
| 8 | SLA Enforcement | Time windows, penalties, performance metrics | Planned |
| 9 | Multi-Vendor Splitting | Order splitting and delivery batching | Planned |
| 10 | CityOS Integration | CMS, Commerce, Temporal, Payments | Stubs planned |
| 11 | Publishable Packages | SDK, BFF surfaces, policy hooks, geo utilities | Planned |

### 5.2 Delivery Types Supported

| Type | Use Case |
|---|---|
| `pickup_dropoff` | Standard A-to-B delivery |
| `multi_stop` | Multi-point delivery route |
| `appointment` | Scheduled service at specific time window |
| `field_service` | On-site maintenance, installation, inspection |

---

## 6. Security Architecture

### 6.1 Authentication Model

| Layer | Mechanism |
|---|---|
| External API consumers | API keys + JWT tokens |
| Internal service-to-service | Service accounts with signed tokens |
| Console operators | Session-based auth via Fleetbase |
| BFF consumers | NodeContext-validated JWT |

### 6.2 Authorization (RBAC)

| Role | Permissions |
|---|---|
| `cityos_admin` | Full system access across all tenants |
| `city_ops_manager` | Manage deliveries, providers, agents within city/zone |
| `provider_admin` | Manage own provider's agents, vehicles, jobs |
| `dispatcher` | Assign agents, update job status |
| `agent` | View assigned jobs, update status, upload POD |
| `customer_support` | View deliveries, handle exceptions |

### 6.3 Policy Enforcement

- Cerbos-ready policy hooks at every API endpoint
- Tenant isolation enforced at query level (every DB query scoped to tenant)
- NodeContext validation rejects unauthorized cross-tenant access
- Audit trail for all state transitions

---

## 7. Observability

### 7.1 Health Endpoints

| Endpoint | Purpose |
|---|---|
| `/health` | Basic liveness check |
| `/ready` | Readiness check (DB, cache, external services) |
| `/metrics` | Prometheus-compatible metrics |

### 7.2 Metrics

| Metric | Type | Description |
|---|---|---|
| `deliveries_created_total` | Counter | Total deliveries created |
| `deliveries_failed_total` | Counter | Total failed deliveries |
| `deliveries_completed_total` | Counter | Total successful deliveries |
| `average_delivery_time_seconds` | Histogram | Time from dispatch to delivery |
| `sla_breaches_total` | Counter | SLA violations detected |

### 7.3 Logging

- Structured JSON logs with correlationId propagation
- NodeContext fields included in every log entry
- Log levels: DEBUG, INFO, WARN, ERROR
- Sensitive data (PII, tokens) redacted from logs

---

## 8. Infrastructure Requirements

### 8.1 Required Services

| Service | Purpose | Current State |
|---|---|---|
| PostgreSQL + PostGIS | Primary database with geospatial support | Active |
| Redis | Hot ETA/dispatch caching, idempotency keys | Planned (using array cache) |
| MinIO | S3-compatible storage for POD media | Planned |
| Temporal | Long-running workflow orchestration | Planned (stubs) |
| Kafka/Redpanda | Event streaming (optional) | Planned (webhook fallback) |

### 8.2 Current Replit Environment

| Component | Status | Notes |
|---|---|---|
| Fleetbase API (Laravel) | Running on port 8000 | PostgreSQL-adapted |
| Console (Ember.js) | Running on port 5000 | Proxies to API |
| PostgreSQL + PostGIS | Active | 252 migrations completed |
| Redis | Not available | Using array cache driver |
| SocketCluster | Not available | Real-time push disabled |

---

## 9. Data Flow Diagrams

### 9.1 Delivery Creation Flow

```
Consumer App                CityOS BFF              Fleetbase API           Fleetbase Core
    │                          │                        │                       │
    │  POST /deliveries/create │                        │                       │
    │─────────────────────────>│                        │                       │
    │                          │  Validate NodeContext   │                       │
    │                          │  Enforce policies       │                       │
    │                          │  Generate idempotency   │                       │
    │                          │                        │                       │
    │                          │  POST /delivery/create  │                       │
    │                          │───────────────────────>│                       │
    │                          │                        │  Create task           │
    │                          │                        │─────────────────────>│
    │                          │                        │                       │
    │                          │                        │  Return deliveryId     │
    │                          │                        │<─────────────────────│
    │                          │                        │                       │
    │                          │  Write outbox event     │                       │
    │                          │  DELIVERY_CREATED       │                       │
    │                          │                        │                       │
    │                          │  Trigger WF-FLT-001     │                       │
    │                          │  (dispatch workflow)    │                       │
    │                          │                        │                       │
    │  Return deliveryId       │                        │                       │
    │<─────────────────────────│                        │                       │
```

### 9.2 Exception Handling Flow

```
Agent App              Fleetbase Core          CityOS Events           Temporal
    │                       │                      │                      │
    │  Report: delivery     │                      │                      │
    │  failed (no_answer)   │                      │                      │
    │──────────────────────>│                      │                      │
    │                       │                      │                      │
    │                       │  Emit DELIVERY_FAILED │                      │
    │                       │─────────────────────>│                      │
    │                       │                      │                      │
    │                       │                      │  Trigger WF-FLT-002  │
    │                       │                      │─────────────────────>│
    │                       │                      │                      │
    │                       │                      │  Evaluate SLA breach │
    │                       │                      │  Notify ops manager  │
    │                       │                      │  Schedule retry/     │
    │                       │                      │  escalate            │
    │                       │                      │                      │
```

---

## 10. Deterministic ID Strategy

Every delivery/task carries two identifiers:

| ID | Format | Purpose |
|---|---|---|
| `cityosDeliveryId` | UUID v4 | CityOS-global unique identifier |
| `sourceSystemRef` | `{ systemId, entityType, entityId }` | Traceability back to origin system |

**Idempotency Key Formula:**
```
createDeliveryIdempotencyKey = hash(tenant + sourceRef + type)
```

This prevents duplicate deliveries when the same commerce order or service request triggers multiple creation attempts.
