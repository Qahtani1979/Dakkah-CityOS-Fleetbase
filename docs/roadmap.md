# Dakkah-CityOS-Fleetbase — Implementation Phases Roadmap

**Version:** 1.0
**Date:** 2026-02-09
**Status:** Planning

---

## Overview

This roadmap defines **14 implementation phases** for evolving the Dakkah-CityOS-Fleetbase platform from its current state (running Fleetbase core with PostgreSQL) into a fully governed, multi-tenant, publishable CityOS fulfillment and service delivery system.

Each phase includes: purpose, deliverables, acceptance criteria, dependencies, estimated complexity, and implementation prompts.

---

## Current State Assessment

| Component | Status | Notes |
|---|---|---|
| Fleetbase API (Laravel/PHP) | Running | Port 8000, PostgreSQL-adapted |
| Fleetbase Console (Ember.js) | Running | Port 5000, proxying to API |
| PostgreSQL + PostGIS | Active | 252 migrations complete |
| Account/Auth | Working | Email verification via log file |
| FleetOps (dispatch/tracking) | Active | Basic functionality available |
| Storefront Engine | Active | Basic e-commerce/order support |
| Redis | Not available | Using array cache |
| SocketCluster (WebSocket) | Not available | Real-time push disabled |

---

## Phase 0 — Audit, Scope Lock, and Architecture Decisions

### Purpose
Lock the execution strategy: what Fleetbase owns, what CityOS wrappers own, and how integration with CMS/Commerce/ERP/Temporal works.

### Deliverables
1. **System Boundary Document** — Fleetbase = execution engine; CityOS wrapper = governance, contracts, integrations
2. **Tenancy Model Decision** — Single instance with tenant scoping (document tradeoffs vs. multi-instance)
3. **Integration Contract Plan** — Events + outbox + Temporal triggers with contract specifications
4. **Capabilities Map** — Delivery, service jobs, tracking, POD, exceptions, SLA, provider/agent management

### Acceptance Criteria
- [ ] `docs/architecture.md` created with complete ownership matrix
- [ ] Integration triggers and event list fully enumerated
- [ ] Tenancy model decision documented with tradeoff analysis
- [ ] Capabilities inventory maps to Fleetbase features vs. CityOS additions

### Dependencies
None (foundational phase)

### Estimated Effort
1-2 days documentation and analysis

### Prompts
- **FLT-P0.1:** Write boundary + tenancy + integration decision docs
- **FLT-P0.2:** Produce capability inventory + workflow trigger matrix

---

## Phase 1 — Monorepo Scaffold + DevOps Baseline

### Purpose
Create the CityOS-standard monorepo structure with proper tooling for a multi-package, publishable codebase.

### Deliverables

1. **Root Directory Structure:**
   ```
   /apps           — Application services
   /packages       — Publishable packages
   /infra          — Infrastructure configs
   /policies       — Security and SLA policies
   /tools          — Code generation and utilities
   /configs        — Shared configs (ESLint, TSConfig, Prettier)
   /.github        — CI/CD workflows
   ```

2. **Monorepo Tooling:**
   - `pnpm-workspace.yaml` defining workspace packages
   - `turbo.json` for task orchestration (build, lint, test, typecheck)
   - `changesets` configuration for versioning and publishing

3. **TypeScript Configuration:**
   - `tsconfig.base.json` — shared compiler options
   - Per-package `tsconfig.json` extending base

4. **Code Quality:**
   - ESLint configuration (TypeScript-aware)
   - Prettier configuration
   - `.editorconfig`

5. **Environment:**
   - `.env.example` with all required variables documented
   - `README.md` with setup instructions

### Acceptance Criteria
- [ ] `pnpm install` succeeds from workspace root
- [ ] `pnpm lint` runs (even with minimal rules)
- [ ] `pnpm build` succeeds across all packages
- [ ] CI workflow exists (`.github/workflows/ci.yml`)
- [ ] Changesets initialized and configured

### Dependencies
Phase 0 (architecture decisions inform package structure)

### Estimated Effort
2-3 days

### Prompts
- **FLT-P1.1:** Scaffold monorepo root layout + workspace configs + turbo.json
- **FLT-P1.2:** Add CI workflow + scripts + changesets setup

---

## Phase 2 — Local Infrastructure Stack

### Purpose
Establish a reproducible local development environment with all required services.

### Deliverables

1. **Docker Compose Stack** (`/infra/docker/docker-compose.yml`):
   - Fleetbase core services
   - PostgreSQL + PostGIS
   - Redis (for caching and idempotency)
   - MinIO (S3-compatible object storage)

2. **Bootstrap Scripts** (`/infra/scripts/`):
   - `bootstrap.sh` — Initialize database, enable extensions
   - `health-check.sh` — Verify all services are running
   - `seed.sh` — Seed initial data (tenants, providers, agents)

3. **SQL Scripts** (`/infra/sql/`):
   - `001-enable-extensions.sql` — Enable PostGIS, uuid-ossp
   - `002-create-schemas.sql` — Tenant-aware schema setup

4. **Replit Fallback Documentation:**
   - Document alternatives when Docker is unavailable
   - Current Replit environment configuration as baseline

### Acceptance Criteria
- [ ] `pnpm fleetbase:up` boots the complete stack
- [ ] Fleetbase responds on health endpoint
- [ ] PostGIS extension is enabled and queryable
- [ ] MinIO bucket created and accessible
- [ ] Redis accepting connections

### Dependencies
Phase 1 (monorepo structure exists)

### Estimated Effort
2-3 days

### Prompts
- **FLT-P2.1:** Implement Docker Compose stack + environment wiring
- **FLT-P2.2:** Add bootstrap SQL scripts and health check utilities

---

## Phase 3 — CityOS Core Governance Layer

### Purpose
Make NodeContext a first-class, enforceable concept across all Fleetbase API surfaces.

### Deliverables

1. **`/packages/cityos-core`:**
   - NodeContext Zod schema + TypeScript types
   - Context resolver (header/path/cookie extraction)
   - Validation guard middleware (Express/Fastify compatible)
   - CityOS ID generators (deterministic + UUID)
   - Invariant assertions and error model
   - Structured logger with correlationId

2. **`/packages/cityos-auth`:**
   - Service account definitions
   - API key validation stubs
   - JWT verification stubs
   - Policy hook interface

3. **`/packages/cityos-policies`:**
   - Cerbos-ready policy definitions
   - RBAC role mapping (cityos_admin, city_ops_manager, provider_admin, dispatcher, agent, customer_support)
   - Permission definitions (create delivery, view delivery, assign agent, update status, upload POD, view analytics)

4. **`/packages/cityos-observability`:**
   - CorrelationId propagation middleware
   - Structured JSON logging
   - Request/response logging (redacted)
   - Basic metrics collection interface

### Acceptance Criteria
- [ ] Any request without valid NodeContext is rejected with HTTP 400
- [ ] CorrelationId appears in all log entries
- [ ] Policy hook is invoked in the request pipeline (stub implementation OK)
- [ ] RBAC roles can be checked programmatically
- [ ] All packages build independently and pass TypeScript checks

### Dependencies
Phase 1 (monorepo structure)

### Estimated Effort
3-5 days

### Prompts
- **FLT-P3.1:** Implement NodeContext kernel + resolver + validation guards
- **FLT-P3.2:** Implement auth/policy stubs + observability middleware

---

## Phase 4 — Contracts-First Layer

### Purpose
Ensure all CityOS-Fleetbase integration surfaces are stable, versioned, and machine-verifiable.

### Deliverables

1. **`/packages/cityos-contracts`:**
   - DeliveryOrder Zod schema (full entity with all fields)
   - Provider Zod schema
   - Agent Zod schema
   - Vehicle Zod schema
   - TrackingEvent Zod schema
   - ProofOfDelivery Zod schema
   - SLAPolicy Zod schema
   - DeliveryException Zod schema
   - Standard response envelope schema
   - Common value objects (Address, GeoPoint, Contact, Money, TimeWindow)

2. **`/packages/cityos-types`:**
   - Shared enums: DeliveryStatus, AgentShiftStatus, ProviderVerification, ExceptionType, etc.
   - Type-safe status transition helpers
   - Slug generators

3. **OpenAPI Generation:**
   - `zod-to-openapi` pipeline
   - OpenAPI 3.1 JSON/YAML output
   - `pnpm openapi:gen` script

4. **`/tools/codegen`:**
   - Client code generation from OpenAPI specs
   - TypeScript client generation script

### Acceptance Criteria
- [ ] `pnpm openapi:gen` produces valid OpenAPI 3.1 specification
- [ ] All Zod schemas validate sample payloads successfully
- [ ] TypeScript types are inferred correctly from schemas
- [ ] Schemas are importable as `@cityos/contracts`

### Dependencies
Phase 3 (NodeContext types used in contracts)

### Estimated Effort
3-4 days

### Prompts
- **FLT-P4.1:** Create contracts package with all entity schemas + OpenAPI scaffolding
- **FLT-P4.2:** Add codegen scripts + type exports + documentation

---

## Phase 5 — Storage Abstraction (MinIO First)

### Purpose
Standardize media and document storage across CityOS for POD photos, signatures, and documents.

### Deliverables

1. **`/packages/cityos-storage`:**
   - `StorageProvider` interface:
     - `put(bucket, key, data, metadata): Promise<StorageRef>`
     - `get(bucket, key): Promise<Buffer>`
     - `delete(bucket, key): Promise<void>`
     - `signUrl(bucket, key, expiresIn): Promise<string>`
     - `list(bucket, prefix): Promise<StorageObject[]>`
   - MinIO implementation
   - Local filesystem implementation (for development)
   - Provider factory with configuration

2. **POD Storage Integration:**
   - Upload signature images
   - Upload delivery photos (multiple)
   - Generate signed URLs for retrieval
   - Tenant-scoped bucket/prefix strategy

### Acceptance Criteria
- [ ] Upload a file to MinIO via storage provider
- [ ] Retrieve a signed URL for the uploaded file
- [ ] Delete a file via storage provider
- [ ] Tenant isolation maintained in storage paths
- [ ] Package builds independently

### Dependencies
Phase 1 (monorepo), Phase 2 (MinIO running)

### Estimated Effort
2-3 days

### Prompts
- **FLT-P5.1:** Implement StorageProvider interface + MinIO adapter
- **FLT-P5.2:** Wire POD upload/retrieval flows to storage provider

---

## Phase 6 — Fleetbase API Facade (CityOS Stable REST)

### Purpose
Create the governed gateway between CityOS consumers and the Fleetbase execution engine.

### Deliverables

1. **`/apps/fleetbase-api`** (Node.js/TypeScript):
   - Express or Fastify server
   - NodeContext validation middleware
   - Policy enforcement middleware
   - Translation layer: CityOS contracts to/from Fleetbase internal models

2. **Core Endpoints:**
   - `POST /api/v1/deliveries` — Create delivery order
   - `GET /api/v1/deliveries/:id` — Get delivery details
   - `GET /api/v1/deliveries/:id/track` — Get tracking events
   - `POST /api/v1/deliveries/:id/cancel` — Cancel delivery
   - `GET /api/v1/providers/:id/jobs` — List provider's jobs
   - `POST /api/v1/providers/:id/jobs/:jobId/assign` — Assign agent
   - `POST /api/v1/providers/:id/jobs/:jobId/status` — Update job status

3. **Cross-Cutting Concerns:**
   - Idempotency key enforcement (prevent duplicate deliveries)
   - Audit logging for all write operations
   - Error model with CityOS standard codes
   - Rate limiting placeholders

### Acceptance Criteria
- [ ] `POST /api/v1/deliveries` creates a delivery end-to-end
- [ ] Requests without valid NodeContext are rejected
- [ ] Tenant isolation is enforced (tenant A cannot see tenant B's deliveries)
- [ ] Duplicate creation with same idempotency key returns existing delivery
- [ ] All responses follow CityOS envelope format

### Dependencies
Phase 3 (NodeContext), Phase 4 (contracts)

### Estimated Effort
5-7 days

### Prompts
- **FLT-P6.1:** Build fleetbase-api skeleton + core delivery endpoints
- **FLT-P6.2:** Add idempotency + error model + audit logging

---

## Phase 7 — BFF Kit + Fleetbase BFF Surfaces

### Purpose
Expose surface-optimized APIs for 40+ BFF services to consume, shaping responses based on the requesting surface, persona, and channel.

### Deliverables

1. **`/packages/cityos-bff-kit`:**
   - BFF router factory
   - Context-aware response shaping
   - Surface/persona/channel guards
   - Request/response logging middleware

2. **`/packages/cityos-fleetbase-bff`:**

   **Consumer/User-facing endpoints:**
   - `POST /api/bff/deliveries/create`
   - `GET /api/bff/deliveries/:id`
   - `GET /api/bff/deliveries/:id/track`
   - `POST /api/bff/deliveries/:id/cancel`

   **Provider/Operations endpoints:**
   - `GET /api/bff/providers/:providerId/jobs`
   - `POST /api/bff/providers/:providerId/jobs/:jobId/assign`
   - `POST /api/bff/providers/:providerId/jobs/:jobId/status`

   **Internal system endpoints:**
   - `POST /internal/fleetbase/delivery/from-commerce`
   - `POST /internal/fleetbase/delivery/from-service`
   - `POST /internal/fleetbase/events/ingest`

3. **Response Shaping:**
   - Consumer surface: simplified delivery view with ETA and status
   - Provider surface: full job details with assignment info
   - Ops surface: comprehensive view with audit trail and metrics

### Acceptance Criteria
- [ ] All BFF routes validate NodeContext
- [ ] Response shape varies by surface/persona
- [ ] Package builds and publishes independently
- [ ] Output conforms to contracts schema

### Dependencies
Phase 6 (API facade endpoints to call)

### Estimated Effort
4-5 days

### Prompts
- **FLT-P7.1:** Implement BFF kit primitives (guard, router, response shaping)
- **FLT-P7.2:** Implement Fleetbase BFF surfaces (deliveries, tracking, provider ops)

---

## Phase 8 — Geo Layer (PostGIS + H3 + Geofencing)

### Purpose
Support city-grade fulfillment operations: zone containment, distance calculations, nearest hub/provider queries, and geofencing.

### Deliverables

1. **`/packages/cityos-geo`:**
   - PostGIS query utilities:
     - Point-in-polygon containment
     - Distance between points
     - Nearest N entities within radius
     - Bounding box queries
   - H3 index helpers:
     - Point to H3 index at resolution
     - H3 neighbors and rings
     - H3 as cache key generator
   - Geohash utilities:
     - Encode/decode geohash
     - Proximity search via geohash prefix
   - Geofence skeleton:
     - Define geofence zones
     - Enter/exit event detection
     - Geofence-triggered status updates

2. **`/packages/cityos-routing`** (stub):
   - ETA calculation interface
   - Distance matrix interface
   - Route optimization interface
   - Stub implementations returning mock data

### Acceptance Criteria
- [ ] "Nearest provider within 5km" query works with PostGIS
- [ ] "Is this point inside zone X?" query returns correct boolean
- [ ] "Distance from A to B" returns meters
- [ ] H3 index computed for arbitrary lat/lng
- [ ] Geofence enter/exit events emitted (stub)

### Dependencies
Phase 2 (PostGIS running), Phase 4 (GeoPoint types)

### Estimated Effort
4-5 days

### Prompts
- **FLT-P8.1:** Implement PostGIS query utilities + H3 indexing
- **FLT-P8.2:** Implement geofencing skeleton + tracking enrichment hooks

---

## Phase 9 — Events + Outbox (Reliable Integration)

### Purpose
Make Fleetbase a first-class CityOS participant with reliable, at-least-once event delivery using the outbox pattern.

### Deliverables

1. **`/packages/cityos-events`:**
   - CityBus event envelope schema:
     ```
     {
       eventId, eventType, timestamp,
       nodeContext, correlationId,
       payload, metadata
     }
     ```
   - Event type registry
   - Event serialization/deserialization

2. **`/packages/cityos-outbox`:**
   - Outbox table schema (PostgreSQL)
   - Outbox writer (transactional with business operation)
   - Outbox dispatcher worker (polls and publishes)
   - Delivery tracking (at-least-once guarantee)
   - Dead letter handling

3. **Implemented Events:**
   - `DELIVERY_CREATED` — New delivery order created
   - `DELIVERY_STATUS_CHANGED` — Status transition occurred
   - `DELIVERY_LOCATION_UPDATED` — Agent location update
   - `DELIVERY_FAILED` — Delivery attempt failed
   - `POD_UPLOADED` — Proof of delivery submitted
   - `SLA_BREACH_DETECTED` — SLA threshold violated (stub)

4. **Event Adapters:**
   - Webhook publisher (development/simple deployments)
   - Kafka/Redpanda producer stub (production)
   - In-memory adapter (testing)

### Acceptance Criteria
- [ ] Creating a delivery writes an outbox entry in the same transaction
- [ ] Outbox dispatcher publishes events via webhook
- [ ] Events include NodeContext + correlationId
- [ ] Duplicate events are idempotent (same eventId)
- [ ] Failed delivery attempts are retried with backoff

### Dependencies
Phase 4 (event schemas), Phase 6 (API produces events)

### Estimated Effort
4-5 days

### Prompts
- **FLT-P9.1:** Implement outbox table + writer + dispatcher worker
- **FLT-P9.2:** Implement event envelope + adapters (webhook, kafka stub)

---

## Phase 10 — Temporal Workflow Triggers

### Purpose
Integrate fulfillment execution with Temporal for long-running orchestration, enabling complex multi-step workflows with human approval, retry logic, and cross-system coordination.

### Deliverables

1. **Temporal Trigger Adapter** (`/packages/cityos-integrations`):
   - Temporal client wrapper
   - Workflow trigger function
   - Signal and query interfaces
   - Configuration for Temporal namespace/task queue

2. **Workflow Definitions (stubs):**

   | ID | Name | Trigger | Purpose |
   |---|---|---|---|
   | WF-FLT-001 | delivery-dispatch-orchestration | Delivery created | Assign provider, dispatch agent, monitor progress |
   | WF-FLT-002 | delivery-exception-escalation | Delivery failed | Evaluate exception, notify, retry or escalate |
   | WF-FLT-003 | delivery-completion-settlement-hook | Delivery delivered | Trigger settlement in Commerce/ERP |
   | WF-FLT-004 | provider-onboarding-approval | Provider registered | Document review, approval workflow |

3. **Signals and Queries:**
   - Human approval signal for provider onboarding
   - Status query for workflow inspection
   - Cancel signal for workflow termination

### Acceptance Criteria
- [ ] Delivery created triggers WF-FLT-001 (or emits trigger request)
- [ ] Delivery failed triggers WF-FLT-002
- [ ] Delivery completed triggers WF-FLT-003
- [ ] Provider registration triggers WF-FLT-004
- [ ] Workflow stubs log their execution steps

### Dependencies
Phase 6 (API events trigger workflows), Phase 9 (events carry trigger data)

### Estimated Effort
3-4 days

### Prompts
- **FLT-P10.1:** Implement Temporal trigger adapter + workflow ID mapping
- **FLT-P10.2:** Implement provider onboarding approval flow (signal-based)

---

## Phase 11 — External System Integrations

### Purpose
Connect Fleetbase with CityOS truth layers: Payload CMS (content/POIs), Medusa Commerce (orders), and ERPNext (accounting).

### Deliverables

1. **Payload CMS Integration:**
   - POI resolver: `poiId` → address, geo coordinates, operating hours
   - Zone resolver: zone ID → SLA policy references, service constraints
   - Hub/provider metadata sync: keep Fleetbase providers aligned with CMS records
   - Webhook receiver for CMS updates

2. **Medusa Commerce Integration:**
   - Order → delivery creation adapter
   - Return/refund → reverse logistics delivery adapter
   - Shipment status sync back to commerce
   - COD amount passthrough
   - Tip aggregation placeholder

3. **ERPNext Integration:**
   - Delivery completion → fee posting (delivery fee, service fee, tips)
   - Provider settlement event (mark payout readiness)
   - Period-based settlement report generation (stub)

### Acceptance Criteria
- [ ] Commerce order creates a delivery deterministically (idempotent)
- [ ] POI resolver returns correct address and geo coordinates
- [ ] Settlement hook produces ERP posting event (stub)
- [ ] Return order creates a reverse logistics delivery
- [ ] All adapters use typed contracts (no untyped payloads)

### Dependencies
Phase 6 (API), Phase 9 (events for sync)

### Estimated Effort
5-7 days

### Prompts
- **FLT-P11.1:** Implement Payload CMS sync + POI/zone resolution adapters
- **FLT-P11.2:** Implement Commerce + ERP posting adapters

---

## Phase 12 — SLA, Exceptions, and Performance Metrics

### Purpose
Enable operator-grade reliability through SLA enforcement, structured exception handling, and performance observability.

### Deliverables

1. **SLA Policy Engine:**
   - `/policies/sla/` — SLA policy templates (YAML/JSON)
   - SLA evaluation hook (checks delivery against policy rules)
   - Breach detection and event emission
   - Grace period handling

2. **Exception Pipeline:**
   - Exception creation from agent/system reports
   - Auto-categorization by exception type
   - Escalation rules evaluation
   - Auto-reassignment on breach (configurable)
   - Resolution tracking

3. **Dispatch Policies:**
   - `/policies/dispatch/` — Dispatch rule templates
   - Nearest agent assignment (geo-based)
   - Capability matching (cold chain, heavy lift, etc.)
   - Load balancing across agents

4. **Metrics (Prometheus-compatible):**
   - `deliveries_created_total` (counter)
   - `deliveries_completed_total` (counter)
   - `deliveries_failed_total` (counter)
   - `sla_breaches_total` (counter)
   - `delivery_duration_seconds` (histogram)
   - `dispatch_to_pickup_seconds` (histogram)
   - `/metrics` endpoint

### Acceptance Criteria
- [ ] SLA breach event generated when delivery exceeds time window
- [ ] Exception workflow trigger created on delivery failure
- [ ] Metrics exposed on `/metrics` in Prometheus format
- [ ] SLA policies are configurable per tenant and zone
- [ ] Exception resolution is tracked and auditable

### Dependencies
Phase 6 (API), Phase 8 (geo for dispatch), Phase 9 (events for breach notification)

### Estimated Effort
5-7 days

### Prompts
- **FLT-P12.1:** Implement SLA policy templates + evaluation hooks
- **FLT-P12.2:** Implement exception pipeline + Prometheus metrics

---

## Phase 13 — Hardening, Release, and Documentation

### Purpose
Prepare the system for production deployment, package publishing, and team onboarding.

### Deliverables

1. **Release Pipeline:**
   - Changesets release workflow
   - Package versioning strategy (semver)
   - npm registry publishing configuration
   - Dependency graph validation (no circular deps)

2. **Documentation:**
   - Complete README.md:
     - NodeContext explanation and usage
     - How to create a delivery from commerce
     - How dispatch workflow triggers
     - How to add a new BFF surface
     - How to store POD via MinIO provider
   - API documentation (auto-generated from OpenAPI)
   - Architecture decision records (ADRs)
   - Runbook for operations

3. **Smoke Tests:**
   - End-to-end test script:
     1. Seed tenant + provider + agent
     2. Create delivery (BFF) with NodeContext
     3. Verify delivery stored and returns deliveryId
     4. Trigger workflow stub (WF-FLT-001)
     5. Get delivery tracking returns structured data
     6. Upload POD via storage provider
     7. Verify outbox event emitted

4. **Security Hardening:**
   - Input validation on all endpoints
   - Rate limiting configuration
   - CORS policy review
   - Secret rotation documentation

### Acceptance Criteria
- [ ] `pnpm build` succeeds across all packages
- [ ] `pnpm release --dry-run` completes successfully
- [ ] Smoke test script passes end-to-end
- [ ] No circular dependency warnings
- [ ] Documentation covers all core workflows

### Dependencies
All previous phases

### Estimated Effort
3-5 days

### Prompts
- **FLT-P13.1:** Release readiness + package metadata + dependency cleanup
- **FLT-P13.2:** Documentation + smoke tests + operational runbook

---

## Phase Summary & Timeline

| Phase | Name | Est. Effort | Dependencies | Priority |
|---|---|---|---|---|
| 0 | Audit & Architecture Decisions | 1-2 days | None | P0 (Done) |
| 1 | Monorepo Scaffold + DevOps | 2-3 days | Phase 0 | P0 |
| 2 | Local Infrastructure Stack | 2-3 days | Phase 1 | P0 |
| 3 | CityOS Core Governance | 3-5 days | Phase 1 | P0 |
| 4 | Contracts-First Layer | 3-4 days | Phase 3 | P0 |
| 5 | Storage Abstraction | 2-3 days | Phase 1, 2 | P1 |
| 6 | Fleetbase API Facade | 5-7 days | Phase 3, 4 | P0 |
| 7 | BFF Kit + Surfaces | 4-5 days | Phase 6 | P1 |
| 8 | Geo Layer | 4-5 days | Phase 2, 4 | P1 |
| 9 | Events + Outbox | 4-5 days | Phase 4, 6 | P1 |
| 10 | Temporal Workflows | 3-4 days | Phase 6, 9 | P2 |
| 11 | External Integrations | 5-7 days | Phase 6, 9 | P2 |
| 12 | SLA & Metrics | 5-7 days | Phase 6, 8, 9 | P2 |
| 13 | Hardening & Release | 3-5 days | All | P2 |
| **Total** | | **47-69 days** | | |

### Critical Path

```
Phase 0 → Phase 1 → Phase 3 → Phase 4 → Phase 6 → Phase 7
                  ↘ Phase 2 → Phase 5                ↘ Phase 9 → Phase 10
                                                            ↘ Phase 11
                             Phase 8 ────────────────────────→ Phase 12
                                                                   ↘ Phase 13
```

### Parallel Work Streams

After Phase 1, these can proceed in parallel:
- **Stream A (Core):** Phase 3 → Phase 4 → Phase 6 → Phase 7
- **Stream B (Infrastructure):** Phase 2 → Phase 5 → Phase 8
- **Stream C (Integration):** Phase 9 → Phase 10 → Phase 11

Phase 12 and 13 require convergence of all streams.

---

## Risk Register

| Risk | Impact | Likelihood | Mitigation |
|---|---|---|---|
| Fleetbase internal model changes | High | Medium | Pin Fleetbase version; translation layer isolates changes |
| PostgreSQL vs MySQL incompatibilities | Medium | High | PostgresCompatServiceProvider handles known issues; test thoroughly |
| No Redis in Replit | Medium | Certain | Array cache for dev; document Redis requirement for production |
| No Docker in Replit | Medium | Certain | Direct service installation; document Docker for other environments |
| Multi-tenant data leakage | Critical | Low | Query-level tenant filtering; integration tests per tenant |
| Package circular dependencies | Medium | Medium | Turborepo dependency graph checks; strict package boundaries |
| Temporal unavailability | Low | Medium | Event-based fallback; workflows are stubs initially |
