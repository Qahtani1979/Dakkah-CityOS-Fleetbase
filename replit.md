# Fleetbase - Modular Logistics & Supply Chain Operating System

## Overview
Fleetbase is a modular logistics and supply chain operating system consisting of:
- **API Backend** (Laravel/PHP) - Runs on port 8000
- **Console Frontend** (Ember.js) - Runs on port 5000, proxies API requests to backend

## Architecture

### Backend (api/)
- Laravel PHP application
- PostgreSQL database (adapted from original MySQL design)
- PostGIS extension for spatial data
- Key packages: fleetbase/core-api, fleetbase/fleetops-api, fleetbase/storefront-api, fleetbase/pallet-api, fleetbase/cityos-api

### Frontend (console/)
- Ember.js application (v5.4.1)
- Uses pnpm for package management
- Proxies API calls to localhost:8000 via Ember CLI proxy
- fleetbase.config.json configures API_HOST (empty = use proxy)
- Extensions discovered: dev-engine, fleetops-engine, iam-engine, registry-bridge-engine, pallet-engine, storefront-engine

### Database
- PostgreSQL with PostGIS extension
- 274+ migrations completed (252 core + 14 pallet + 8 cityos)
- Multiple database connections (storefront, sandbox, fleetops) all redirected to main pgsql connection via .env
- UUID columns use char(36)/varchar(36) instead of native PostgreSQL uuid type

### Custom Extensions (api/packages/)

#### fleetbase/pallet-api (Warehouse Management)
- Installed from fleetbase/pallet GitHub monorepo as local path package
- 12 database tables: pallet_audits, pallet_batches, pallet_inventories, pallet_purchase_orders, pallet_sales_orders, pallet_stock_adjustment, pallet_stock_transactions, pallet_warehouse_aisles, pallet_warehouse_bins, pallet_warehouse_docks, pallet_warehouse_racks, pallet_warehouse_sections
- Models: Audit, Batch, Inventory, Product, PurchaseOrder, SalesOrder, StockAdjustment, StockTransaction, Supplier, Warehouse, WarehouseAisle, WarehouseBin, WarehouseDock, WarehouseRack, WarehouseSection
- Routes: pallet/int/v1/* (CRUD for all resources)
- PostgreSQL fix: all `uuid()` calls in migrations replaced with `string('uuid', 36)` and existing native uuid columns ALTER'd to varchar(36)

#### fleetbase/cityos-api (CityOS Multi-Hierarchy)
- Custom extension for Dakkah CityOS platform
- 8 database tables: cityos_countries, cityos_cities, cityos_sectors, cityos_categories, cityos_channels, cityos_surfaces, cityos_tenants, cityos_portals
- Hierarchy: Country → City → Sector → Category(+subcategory) → Tenant → Channel → Surface → Portal
- Models: Country, City, Sector, Category, Tenant, Channel, Surface, Portal
- NodeContext support class for governance context resolution (from headers/cookies/path)
- ResolveNodeContext middleware
- Integration fields: medusa_tenant_id, payload_tenant_id, erpnext_company, medusa_sales_channel_id, medusa_store_id, payload_store_id
- Routes: cityos/int/v1/* (CRUD for all hierarchy entities) + cityos/v1/hierarchy/tree|resolve (public)

## PostgreSQL Compatibility Notes
The codebase was designed for MySQL. Key adaptations made:
1. `uuid()` calls converted to `char(36)` via PostgresCompatServiceProvider macro
2. `foreignUuid()` patched in PostgresCompatServiceProvider macro to create `char(36)` columns
3. Doctrine DBAL type mappings registered for PostGIS types (geography, geometry, etc.)
4. MySQL-specific SQL (`SHOW INDEX`, `ST_SRID(POINT(...))`, `MODIFY`) converted to PostgreSQL equivalents
5. Cross-database `Expression()` references removed (PostgreSQL doesn't support database.table syntax)
6. All uuid columns given unique constraints (required for foreign key references in PostgreSQL)
7. Spatial index naming conflicts resolved
8. Pallet extension migrations: `uuid()` replaced with `string('uuid', 36)` and existing columns ALTER'd

## Key Files
- `api/.env` - Backend environment configuration
- `api/config/database.php` - Database connection configuration
- `api/app/Providers/PostgresCompatServiceProvider.php` - PostgreSQL compatibility layer
- `api/packages/cityos/` - CityOS multi-hierarchy extension
- `api/packages/pallet-api/` - Pallet WMS extension (local copy)
- `console/fleetbase.config.json` - Console runtime configuration
- `console/environments/.env.development` - Console development environment

## Design Documentation
- `docs/architecture.md` - System boundary, tenancy model, ownership matrix, security, observability
- `docs/domain-model.md` - Core entities (DeliveryOrder, Provider, Agent, Vehicle, TrackingEvent, POD, SLA), NodeContext schema, status machines, API envelope
- `docs/roadmap.md` - 14-phase implementation roadmap (Phase 0-13) with deliverables, acceptance criteria, dependencies, and timeline
- `docs/integration-strategy.md` - Payload CMS, Medusa Commerce, ERPNext, Temporal workflows, CityBus events, outbox pattern
- `docs/repo-layout.md` - Target monorepo structure, 16 CityOS packages, dependency graph, migration strategy from current to target layout

## CityOS Multi-Hierarchy Model
Aligns with Payload CMS orchestrator collections and Medusa Commerce custom modules:
```
Country (SA, AE)
  └─ City/Theme (riyadh, jeddah)
       └─ Sector (logistics, services, mobility)
            └─ Category (delivery, field_service)
                 └─ Subcategory (food, parcel, installation)
                      └─ Tenant (linked to Fleetbase Company)
                           └─ Channel (web, mobile, api, kiosk)
                                └─ Surface (consumer-app, provider-portal)
                                     └─ Portal (storefront, admin dashboard)
```

### NodeContext Fields
| Field | Source | Description |
|---|---|---|
| country | Header/Path/Cookie | ISO 3166-1 alpha-2 code |
| cityOrTheme | Header/Path/Cookie | City slug or theme |
| sector | Header/Path/Cookie | Business sector |
| category | Header/Path/Cookie | Primary category |
| subcategory | Header/Path/Cookie | Sub-category |
| tenant | Header/Path/Cookie | Tenant handle/uuid |
| channel | Header/Path/Cookie | Request channel |
| surface | Header/Path/Cookie | BFF surface |
| persona | Header/Path/Cookie | Acting persona |
| locale | Header/Path/Cookie | BCP 47 locale |
| processingRegion | Header/Path/Cookie | Data processing region |
| residencyClass | Header/Path/Cookie | sovereign/regional/global |

## Workflows
- **API Server**: `cd api && php artisan serve --host=0.0.0.0 --port=8000`
- **Console Frontend**: `cd console && npx ember serve --port 5000 --host 0.0.0.0 --proxy http://localhost:8000 --environment development`

## Recent Changes (2026-02-09)
- Installed Pallet WMS extension (fleetbase/pallet-api) with 12 warehouse/inventory tables
- Installed @fleetbase/pallet-engine frontend (discovered by Ember build system)
- Created fleetbase/cityos-api custom extension with 8 hierarchy tables
- Implemented NodeContext middleware and tenant-scoping support
- Built full CRUD REST API for CityOS hierarchy entities
- All code pushed to GitHub: https://github.com/Qahtani1979/Dakkah-CityOS-Fleetbase

## Previous Changes (2026-02-09)
- Fixed cache driver: switched from `file` to `array`
- Disabled response cache (spatie/laravel-responsecache)
- Created permissions and roles
- Removed Redis dependency
- Added `mysql` connection alias in `database.php`
- Fixed various PostgreSQL compatibility issues
- Created comprehensive design documentation

## Known Limitations
- SocketCluster (WebSocket) not available - real-time push notifications won't work
- No Redis server - using array cache driver instead
- Response caching disabled due to no Redis
- Pallet extension uuid() macro doesn't apply during `artisan migrate` - migrations patched to use string('uuid', 36) directly
