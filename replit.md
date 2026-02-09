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
- Key packages: fleetbase/core-api, fleetbase/fleetops-api, fleetbase/storefront-api

### Frontend (console/)
- Ember.js application (v5.4.1)
- Uses pnpm for package management
- Proxies API calls to localhost:8000 via Ember CLI proxy
- fleetbase.config.json configures API_HOST (empty = use proxy)

### Database
- PostgreSQL with PostGIS extension
- 252 migrations completed
- Multiple database connections (storefront, sandbox, fleetops) all redirected to main pgsql connection via .env (STOREFRONT_DB_CONNECTION=pgsql, SANDBOX_DB_CONNECTION=pgsql)
- UUID columns use char(36)/varchar(191) instead of native PostgreSQL uuid type

## PostgreSQL Compatibility Notes
The codebase was designed for MySQL. Key adaptations made:
1. `uuid()` calls converted to `char(36)` via PostgresCompatServiceProvider macro
2. `foreignUuid()` patched in PostgresCompatServiceProvider macro to create `char(36)` columns
3. Doctrine DBAL type mappings registered for PostGIS types (geography, geometry, etc.)
4. MySQL-specific SQL (`SHOW INDEX`, `ST_SRID(POINT(...))`, `MODIFY`) converted to PostgreSQL equivalents
5. Cross-database `Expression()` references removed (PostgreSQL doesn't support database.table syntax)
6. All uuid columns given unique constraints (required for foreign key references in PostgreSQL)
7. Spatial index naming conflicts resolved

## Key Files
- `api/.env` - Backend environment configuration
- `api/config/database.php` - Database connection configuration
- `api/app/Providers/PostgresCompatServiceProvider.php` - PostgreSQL compatibility layer
- `console/fleetbase.config.json` - Console runtime configuration
- `console/environments/.env.development` - Console development environment

## Design Documentation
- `docs/architecture.md` - System boundary, tenancy model, ownership matrix, security, observability
- `docs/domain-model.md` - Core entities (DeliveryOrder, Provider, Agent, Vehicle, TrackingEvent, POD, SLA), NodeContext schema, status machines, API envelope
- `docs/roadmap.md` - 14-phase implementation roadmap (Phase 0-13) with deliverables, acceptance criteria, dependencies, and timeline
- `docs/integration-strategy.md` - Payload CMS, Medusa Commerce, ERPNext, Temporal workflows, CityBus events, outbox pattern
- `docs/repo-layout.md` - Target monorepo structure, 16 CityOS packages, dependency graph, migration strategy from current to target layout

## Workflows
- **API Server**: `cd api && php artisan serve --host=0.0.0.0 --port=8000`
- **Console Frontend**: `cd console && npx ember serve --port 5000 --host 0.0.0.0 --proxy http://localhost:8000 --environment development`

## Recent Changes (2026-02-09)
- Fixed cache driver: switched from `file` to `array` (Laravel 10 file driver doesn't support tagging required by Fleetbase's HasCacheableAttributes trait)
- Disabled response cache (spatie/laravel-responsecache) which was trying to use Redis - set RESPONSE_CACHE_ENABLED=false and RESPONSE_CACHE_DRIVER=array
- Created permissions and roles via `php artisan fleetbase:create-permissions` (required for account creation)
- Removed Redis dependency from .env (no Redis server available in this environment)
- Added `mysql` connection alias in `database.php` that redirects to PostgreSQL (Fleetbase models hardcode `$connection = 'mysql'`)
- Fixed Utils::clearCacheByPattern() to gracefully handle missing Redis (wraps in try/catch)
- Fixed getUserOrganizations query: replaced DISTINCT with whereIn to avoid PostgreSQL JSON equality operator error
- Fixed fuel_reports.amount column type: changed from varchar to numeric for SUM aggregation
- GitHub repository created: https://github.com/Qahtani1979/Dakkah-CityOS-Fleetbase

## Known Limitations
- SocketCluster (WebSocket) not available - real-time push notifications won't work (ERR_CONNECTION_REFUSED on port 38000 is expected)
- No Redis server - using array cache driver instead (cache is in-memory, cleared on restart)
- Response caching disabled due to no Redis

## Previous Changes (2026-02-07)
- Initial project import and configuration
- All 252 database migrations completed successfully
- PostgreSQL compatibility patches applied
- Both API and Console servers running
- CORS configured for Replit domain
