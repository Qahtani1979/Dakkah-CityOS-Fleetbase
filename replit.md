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

## Workflows
- **API Server**: `cd api && php artisan serve --host=0.0.0.0 --port=8000`
- **Console Frontend**: `cd console && npx ember serve --port 5000 --host 0.0.0.0 --proxy http://localhost:8000 --environment development`

## Recent Changes (2026-02-07)
- Initial project import and configuration
- All 252 database migrations completed successfully
- PostgreSQL compatibility patches applied
- Both API and Console servers running
- CORS configured for Replit domain
