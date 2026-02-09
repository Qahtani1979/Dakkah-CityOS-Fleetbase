# Dakkah-CityOS-Fleetbase — Target Repository Layout

**Version:** 1.0
**Date:** 2026-02-09
**Status:** Design Phase

---

## 1. Overview

The target repository is a pnpm monorepo with turborepo orchestration and changesets for package versioning. It contains the existing Fleetbase core (Laravel/PHP + Ember.js) alongside new CityOS TypeScript packages and services.

---

## 2. Current State

The repository currently contains:

```
/
├── api/                          # Fleetbase Laravel API (PHP) - ACTIVE
│   ├── app/                      # Application code
│   ├── config/                   # Laravel configuration
│   ├── database/                 # Migrations and seeds
│   ├── vendor/                   # Composer dependencies
│   │   └── fleetbase/            # Fleetbase packages
│   │       ├── core-api/         # Core API (patched for PostgreSQL)
│   │       ├── fleetops-api/     # Fleet operations
│   │       └── storefront-api/   # Storefront/e-commerce
│   └── .env                      # Environment configuration
├── console/                      # Fleetbase Ember.js Console - ACTIVE
│   ├── app/                      # Ember application
│   ├── node_modules/             # npm dependencies
│   └── fleetbase.config.json     # Console configuration
├── docs/                         # Documentation (this directory)
└── replit.md                     # Project documentation
```

---

## 3. Target Layout

The following directory structure represents the fully built-out CityOS-Fleetbase monorepo:

```
/
├── .github/
│   └── workflows/
│       ├── ci.yml                    # Continuous integration
│       ├── release.yml               # Package release pipeline
│       └── lint.yml                  # Code quality checks
│
├── apps/
│   ├── fleetbase-core/               # Existing Fleetbase stack (Laravel + Ember)
│   │   ├── api/                      # Current /api directory (relocated)
│   │   └── console/                  # Current /console directory (relocated)
│   │
│   ├── fleetbase-api/                # CityOS API Facade (Node.js/TypeScript)
│   │   ├── src/
│   │   │   ├── server.ts             # Express/Fastify server
│   │   │   ├── routes/               # API route definitions
│   │   │   │   ├── deliveries.ts     # Delivery CRUD endpoints
│   │   │   │   ├── providers.ts      # Provider management
│   │   │   │   └── tracking.ts       # Tracking endpoints
│   │   │   ├── middleware/           # Request middleware
│   │   │   │   ├── node-context.ts   # NodeContext extraction/validation
│   │   │   │   ├── policy.ts         # Policy enforcement
│   │   │   │   ├── idempotency.ts    # Idempotency key handling
│   │   │   │   └── audit.ts          # Audit logging
│   │   │   ├── services/            # Business logic
│   │   │   │   ├── delivery.ts       # Delivery service
│   │   │   │   ├── provider.ts       # Provider service
│   │   │   │   └── translation.ts    # CityOS ↔ Fleetbase translation
│   │   │   └── config/              # App configuration
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── fleetbase-workers/            # Async Workers (Node.js/TypeScript)
│   │   ├── src/
│   │   │   ├── outbox-dispatcher.ts  # Outbox event publisher
│   │   │   ├── event-processor.ts    # Inbound event handler
│   │   │   ├── notification.ts       # Notification worker
│   │   │   └── sync.ts              # Data synchronization worker
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── fleetbase-operator-ui/        # Ops Dashboard (Next.js skeleton)
│   │   ├── src/
│   │   │   ├── app/                  # Next.js app router
│   │   │   └── components/           # UI components
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   └── docs-site/                    # Documentation Site (skeleton)
│       ├── src/
│       └── package.json
│
├── packages/
│   ├── cityos-core/                  # Core Governance Primitives
│   │   ├── src/
│   │   │   ├── index.ts              # Package exports
│   │   │   ├── node-context/
│   │   │   │   ├── schema.ts         # NodeContext Zod schema
│   │   │   │   ├── resolver.ts       # Context extraction (headers/path/cookie)
│   │   │   │   ├── validator.ts      # Context validation middleware
│   │   │   │   └── types.ts          # TypeScript types
│   │   │   ├── ids/
│   │   │   │   ├── generator.ts      # ID generation (UUID + deterministic)
│   │   │   │   └── idempotency.ts    # Idempotency key helpers
│   │   │   ├── errors/
│   │   │   │   ├── model.ts          # CityOS error model
│   │   │   │   └── codes.ts         # Standard error codes
│   │   │   ├── invariants.ts         # Runtime assertions
│   │   │   └── logger.ts            # Structured logger
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── cityos-contracts/             # Zod Schemas + OpenAPI
│   │   ├── src/
│   │   │   ├── index.ts
│   │   │   ├── entities/
│   │   │   │   ├── delivery-order.ts # DeliveryOrder schema
│   │   │   │   ├── provider.ts       # Provider schema
│   │   │   │   ├── agent.ts          # Agent schema
│   │   │   │   ├── vehicle.ts        # Vehicle schema
│   │   │   │   ├── tracking-event.ts # TrackingEvent schema
│   │   │   │   ├── pod.ts            # ProofOfDelivery schema
│   │   │   │   ├── sla-policy.ts     # SLA policy schema
│   │   │   │   └── exception.ts      # DeliveryException schema
│   │   │   ├── common/
│   │   │   │   ├── address.ts        # Address value object
│   │   │   │   ├── geo.ts            # GeoPoint, GeoPolygon
│   │   │   │   ├── money.ts          # Money value object
│   │   │   │   ├── contact.ts        # Contact value object
│   │   │   │   └── time-window.ts    # TimeWindow value object
│   │   │   ├── envelope.ts           # API response envelope
│   │   │   └── openapi.ts           # OpenAPI generation
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── cityos-config/                # Typed Configuration
│   │   ├── src/
│   │   │   ├── index.ts
│   │   │   ├── loader.ts            # Config loader
│   │   │   └── schema.ts            # Env schema validation
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── cityos-auth/                  # Authentication & Service Accounts
│   │   ├── src/
│   │   │   ├── index.ts
│   │   │   ├── service-accounts.ts   # Service account management
│   │   │   ├── api-keys.ts          # API key validation
│   │   │   ├── jwt.ts               # JWT verification stubs
│   │   │   └── policy-hook.ts       # Policy enforcement interface
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── cityos-policies/              # RBAC + Cerbos Policies
│   │   ├── src/
│   │   │   ├── index.ts
│   │   │   ├── roles.ts             # Role definitions
│   │   │   ├── permissions.ts       # Permission mappings
│   │   │   └── cerbos.ts           # Cerbos adapter stub
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── cityos-observability/         # Metrics, Tracing, Logging
│   │   ├── src/
│   │   │   ├── index.ts
│   │   │   ├── correlation.ts       # CorrelationId propagation
│   │   │   ├── metrics.ts           # Prometheus metrics
│   │   │   ├── tracing.ts           # Distributed tracing
│   │   │   └── logger.ts           # Structured JSON logger
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── cityos-events/                # Event System
│   │   ├── src/
│   │   │   ├── index.ts
│   │   │   ├── envelope.ts          # CityBus event envelope
│   │   │   ├── registry.ts          # Event type registry
│   │   │   ├── serializer.ts        # Event serialization
│   │   │   └── types.ts            # Event type definitions
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── cityos-outbox/                # Outbox Pattern Implementation
│   │   ├── src/
│   │   │   ├── index.ts
│   │   │   ├── writer.ts            # Transactional outbox writer
│   │   │   ├── dispatcher.ts        # Outbox polling dispatcher
│   │   │   ├── adapters/
│   │   │   │   ├── webhook.ts       # Webhook publisher
│   │   │   │   ├── kafka.ts         # Kafka producer stub
│   │   │   │   └── memory.ts       # In-memory adapter (testing)
│   │   │   └── schema.sql          # Outbox table DDL
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── cityos-storage/               # Object Storage Abstraction
│   │   ├── src/
│   │   │   ├── index.ts
│   │   │   ├── provider.ts          # StorageProvider interface
│   │   │   ├── minio.ts            # MinIO implementation
│   │   │   ├── local.ts            # Local filesystem (dev)
│   │   │   └── factory.ts          # Provider factory
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── cityos-geo/                   # Geospatial Utilities
│   │   ├── src/
│   │   │   ├── index.ts
│   │   │   ├── postgis/
│   │   │   │   ├── queries.ts       # PostGIS query builders
│   │   │   │   ├── containment.ts   # Point-in-polygon
│   │   │   │   ├── distance.ts      # Distance calculations
│   │   │   │   └── nearest.ts      # Nearest entity queries
│   │   │   ├── h3/
│   │   │   │   ├── index.ts         # H3 helpers
│   │   │   │   └── cache-key.ts    # H3-based cache keys
│   │   │   ├── geohash/
│   │   │   │   └── index.ts        # Geohash utilities
│   │   │   └── geofence/
│   │   │       ├── zone.ts          # Geofence zone management
│   │   │       └── events.ts       # Enter/exit event detection
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── cityos-routing/               # ETA & Routing (Stubs)
│   │   ├── src/
│   │   │   ├── index.ts
│   │   │   ├── interfaces.ts        # Routing provider interface
│   │   │   ├── eta.ts              # ETA calculation stub
│   │   │   └── distance-matrix.ts  # Distance matrix stub
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── cityos-fleetbase-sdk/         # Typed SDK for External Systems
│   │   ├── src/
│   │   │   ├── index.ts
│   │   │   ├── client.ts            # HTTP client with context propagation
│   │   │   ├── deliveries.ts        # Delivery operations
│   │   │   ├── providers.ts         # Provider operations
│   │   │   ├── tracking.ts          # Tracking operations
│   │   │   └── types.ts            # Re-exported types
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── cityos-bff-kit/               # BFF Framework Primitives
│   │   ├── src/
│   │   │   ├── index.ts
│   │   │   ├── router.ts            # BFF router factory
│   │   │   ├── guard.ts             # Context guards
│   │   │   ├── shaping.ts          # Response shaping by surface
│   │   │   └── middleware.ts       # Common BFF middleware
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── cityos-fleetbase-bff/         # Fleetbase BFF Surfaces
│   │   ├── src/
│   │   │   ├── index.ts
│   │   │   ├── consumer/            # Consumer-facing endpoints
│   │   │   │   ├── deliveries.ts
│   │   │   │   └── tracking.ts
│   │   │   ├── provider/            # Provider/ops endpoints
│   │   │   │   ├── jobs.ts
│   │   │   │   └── agents.ts
│   │   │   └── internal/            # Internal system endpoints
│   │   │       ├── from-commerce.ts
│   │   │       ├── from-service.ts
│   │   │       └── events.ts
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   ├── cityos-integrations/          # External System Adapters
│   │   ├── src/
│   │   │   ├── index.ts
│   │   │   ├── payload/             # Payload CMS adapter
│   │   │   │   ├── poi-resolver.ts
│   │   │   │   ├── zone-resolver.ts
│   │   │   │   └── sync.ts
│   │   │   ├── commerce/            # Medusa adapter
│   │   │   │   ├── order-mapper.ts
│   │   │   │   ├── return-mapper.ts
│   │   │   │   └── status-sync.ts
│   │   │   ├── erp/                 # ERPNext adapter
│   │   │   │   ├── settlement.ts
│   │   │   │   └── payout.ts
│   │   │   └── temporal/            # Temporal trigger adapter
│   │   │       ├── client.ts
│   │   │       ├── workflows.ts
│   │   │       └── signals.ts
│   │   ├── package.json
│   │   └── tsconfig.json
│   │
│   └── cityos-types/                 # Shared Enums & Types
│       ├── src/
│       │   ├── index.ts
│       │   ├── statuses.ts          # All status enums
│       │   ├── roles.ts             # Role enums
│       │   ├── surfaces.ts          # Surface identifiers
│       │   ├── slugs.ts             # Slug generators
│       │   └── capabilities.ts     # Provider capability enums
│       ├── package.json
│       └── tsconfig.json
│
├── infra/
│   ├── docker/
│   │   ├── docker-compose.yml        # Full local stack
│   │   ├── docker-compose.dev.yml    # Development overrides
│   │   └── Dockerfile.api           # CityOS API Dockerfile
│   ├── k8s/                          # Kubernetes manifests (skeleton)
│   │   ├── namespace.yml
│   │   ├── deployments/
│   │   └── services/
│   ├── sql/
│   │   ├── 001-enable-extensions.sql # PostGIS, uuid-ossp
│   │   ├── 002-create-outbox.sql     # Outbox table
│   │   └── 003-seed-data.sql        # Initial seed data
│   └── scripts/
│       ├── bootstrap.sh              # Full environment bootstrap
│       ├── health-check.sh           # Service health verification
│       ├── seed.sh                   # Seed tenants, providers, agents
│       └── reset.sh                 # Reset development data
│
├── policies/
│   ├── cerbos/
│   │   ├── delivery.yml              # Delivery access policies
│   │   ├── provider.yml              # Provider access policies
│   │   └── agent.yml                # Agent access policies
│   ├── rbac/
│   │   ├── roles.yml                 # Role definitions
│   │   └── permissions.yml          # Permission mappings
│   ├── sla/
│   │   ├── express.yml               # Express delivery SLA
│   │   ├── standard.yml             # Standard delivery SLA
│   │   └── economy.yml             # Economy delivery SLA
│   └── dispatch/
│       ├── nearest-agent.yml         # Nearest agent dispatch rule
│       └── load-balanced.yml        # Load-balanced dispatch rule
│
├── tools/
│   ├── codegen/
│   │   ├── openapi-gen.ts            # OpenAPI generation script
│   │   └── client-gen.ts            # Client code generation
│   ├── migrations/
│   │   └── README.md                # Migration strategy docs
│   └── sync/
│       ├── seed-tenants.ts           # Seed tenant data
│       └── seed-providers.ts        # Seed provider data
│
├── configs/
│   ├── eslint/
│   │   └── .eslintrc.js             # Shared ESLint config
│   ├── tsconfig/
│   │   └── tsconfig.base.json       # Base TypeScript config
│   ├── prettier/
│   │   └── .prettierrc              # Shared Prettier config
│   └── changesets/
│       └── config.json              # Changesets configuration
│
├── api/                              # Current Fleetbase API (stays during transition)
├── console/                          # Current Fleetbase Console (stays during transition)
├── docs/                             # Project documentation
│   ├── architecture.md              # System architecture
│   ├── domain-model.md              # Domain model & contracts
│   ├── roadmap.md                   # Implementation roadmap
│   ├── integration-strategy.md      # Integration patterns
│   └── repo-layout.md              # This file
│
├── package.json                      # Root package.json
├── pnpm-workspace.yaml              # Workspace definition
├── turbo.json                       # Turborepo configuration
├── tsconfig.base.json               # Base TypeScript config
├── .env.example                     # Environment template
├── .gitignore
├── .editorconfig
├── README.md
└── replit.md                        # Replit project documentation
```

---

## 3. Package Descriptions

### 3.1 Applications (`/apps`)

| Package | Language | Purpose | Port |
|---|---|---|---|
| `fleetbase-core` | PHP/Ember.js | Existing Fleetbase runtime (API + Console) | 8000/5000 |
| `fleetbase-api` | TypeScript | CityOS API facade with governance | 3001 |
| `fleetbase-workers` | TypeScript | Async event processing, sync, notifications | N/A |
| `fleetbase-operator-ui` | TypeScript (Next.js) | Operations dashboard (skeleton) | 3002 |
| `docs-site` | TypeScript | Documentation website (skeleton) | 3003 |

### 3.2 Core Packages (`/packages`)

| Package | npm Name | Purpose |
|---|---|---|
| `cityos-core` | `@cityos/core` | NodeContext, IDs, invariants, logging, error model |
| `cityos-contracts` | `@cityos/contracts` | Zod schemas + OpenAPI for all entities |
| `cityos-config` | `@cityos/config` | Typed config loader + env schema validation |
| `cityos-auth` | `@cityos/auth` | Service accounts, API keys, JWT stubs |
| `cityos-policies` | `@cityos/policies` | Cerbos-ready policies + RBAC mapping |
| `cityos-observability` | `@cityos/observability` | Metrics, tracing, structured logging |
| `cityos-events` | `@cityos/events` | CityBus event envelope + adapter stubs |
| `cityos-outbox` | `@cityos/outbox` | Outbox dispatcher skeleton |
| `cityos-storage` | `@cityos/storage` | MinIO provider + storage abstraction |
| `cityos-geo` | `@cityos/geo` | PostGIS utilities, H3/geohash, geofencing |
| `cityos-routing` | `@cityos/routing` | ETA/routing interfaces (stub implementations) |
| `cityos-fleetbase-sdk` | `@cityos/fleetbase-sdk` | Typed SDK for other systems to consume |
| `cityos-bff-kit` | `@cityos/bff-kit` | BFF framework primitives |
| `cityos-fleetbase-bff` | `@cityos/fleetbase-bff` | Fleetbase BFF surfaces (deliveries, tracking, ops) |
| `cityos-integrations` | `@cityos/integrations` | Adapters for Payload, Commerce, ERP, Temporal |
| `cityos-types` | `@cityos/types` | Shared enums: statuses, roles, surfaces, slugs |

### 3.3 Package Dependency Graph

```
cityos-types (no deps)
    ↑
cityos-core (depends on: cityos-types)
    ↑
cityos-contracts (depends on: cityos-core, cityos-types)
    ↑
├── cityos-auth (depends on: cityos-core)
├── cityos-policies (depends on: cityos-core, cityos-auth)
├── cityos-observability (depends on: cityos-core)
├── cityos-config (depends on: cityos-core)
├── cityos-events (depends on: cityos-core, cityos-contracts)
├── cityos-outbox (depends on: cityos-events)
├── cityos-storage (depends on: cityos-core, cityos-config)
├── cityos-geo (depends on: cityos-core, cityos-contracts)
├── cityos-routing (depends on: cityos-core, cityos-geo)
├── cityos-fleetbase-sdk (depends on: cityos-core, cityos-contracts)
├── cityos-bff-kit (depends on: cityos-core, cityos-auth, cityos-observability)
├── cityos-fleetbase-bff (depends on: cityos-bff-kit, cityos-fleetbase-sdk)
└── cityos-integrations (depends on: cityos-core, cityos-contracts, cityos-events)
```

---

## 4. Workspace Configuration

### 4.1 pnpm-workspace.yaml

```yaml
packages:
  - "apps/*"
  - "packages/*"
  - "tools/*"
```

### 4.2 turbo.json

```json
{
  "$schema": "https://turbo.build/schema.json",
  "globalDependencies": [".env"],
  "pipeline": {
    "build": {
      "dependsOn": ["^build"],
      "outputs": ["dist/**"]
    },
    "lint": {},
    "typecheck": {
      "dependsOn": ["^build"]
    },
    "test": {
      "dependsOn": ["build"]
    },
    "dev": {
      "cache": false,
      "persistent": true
    }
  }
}
```

### 4.3 Root Scripts

```json
{
  "scripts": {
    "build": "turbo run build",
    "dev": "turbo run dev",
    "lint": "turbo run lint",
    "typecheck": "turbo run typecheck",
    "test": "turbo run test",
    "openapi:gen": "turbo run openapi:gen --filter=@cityos/contracts",
    "fleetbase:up": "./infra/scripts/bootstrap.sh",
    "fleetbase:down": "docker compose -f infra/docker/docker-compose.yml down",
    "seed": "tsx tools/sync/seed-tenants.ts && tsx tools/sync/seed-providers.ts",
    "changeset": "changeset",
    "release": "changeset publish"
  }
}
```

---

## 5. Migration Strategy (Current → Target)

### 5.1 Phase 1: Coexistence

During initial phases, the existing `api/` and `console/` directories remain at the root. New CityOS packages are added alongside.

```
/api        → Existing Fleetbase API (unchanged)
/console    → Existing Fleetbase Console (unchanged)
/packages   → New CityOS packages
/apps       → New CityOS applications
```

### 5.2 Phase 2: Relocation

Once the CityOS API facade is operational, the existing Fleetbase directories move under `/apps/fleetbase-core/`:

```
/apps/fleetbase-core/api      ← moved from /api
/apps/fleetbase-core/console   ← moved from /console
```

### 5.3 Phase 3: Integration

The CityOS API facade (`/apps/fleetbase-api`) becomes the primary external interface, with Fleetbase core serving as the internal execution engine.

---

## 6. Environment Variables

### 6.1 .env.example

```env
# === CityOS Core ===
CITYOS_TENANT_DEFAULT=dakkah
CITYOS_COUNTRY_DEFAULT=SA
CITYOS_LOCALE_DEFAULT=ar-SA
CITYOS_PROCESSING_REGION=me-central-1
CITYOS_RESIDENCY_CLASS=sovereign

# === Fleetbase Core ===
FLEETBASE_API_URL=http://localhost:8000
FLEETBASE_API_KEY=
FLEETBASE_CONSOLE_URL=http://localhost:5000

# === Database ===
DATABASE_URL=postgresql://user:pass@localhost:5432/fleetbase
POSTGIS_ENABLED=true

# === Redis ===
REDIS_URL=redis://localhost:6379

# === MinIO (Object Storage) ===
MINIO_ENDPOINT=localhost
MINIO_PORT=9000
MINIO_ACCESS_KEY=minioadmin
MINIO_SECRET_KEY=minioadmin
MINIO_BUCKET=cityos-storage

# === Temporal ===
TEMPORAL_ADDRESS=localhost:7233
TEMPORAL_NAMESPACE=cityos-fleetbase
TEMPORAL_TASK_QUEUE=fleetbase-tasks

# === Event Adapters ===
EVENT_ADAPTER=webhook
WEBHOOK_URL=http://localhost:3001/events/webhook
KAFKA_BROKERS=localhost:9092

# === Observability ===
LOG_LEVEL=info
METRICS_ENABLED=true
TRACING_ENABLED=false
```
