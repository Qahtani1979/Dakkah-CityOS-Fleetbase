# Dakkah-CityOS-Fleetbase — Domain Model & Contracts Specification

**Version:** 1.0
**Date:** 2026-02-09
**Status:** Design Phase

---

## 1. NodeContext (CityOS Governance Context)

NodeContext is the foundational governance object attached to every request, shipment, task, and service job in the CityOS ecosystem. It ensures tenant isolation, compliance enforcement, and context-aware processing.

### 1.1 NodeContext Schema

| Field | Type | Required | Description |
|---|---|---|---|
| `country` | string (ISO 3166-1 alpha-2) | Yes | Operating country code (e.g., "SA", "AE") |
| `cityOrTheme` | string | Yes | City identifier or thematic context (e.g., "riyadh", "jeddah") |
| `sector` | string | Yes | Business sector (e.g., "logistics", "services", "mobility") |
| `category` | string | Yes | Primary category (e.g., "delivery", "field_service") |
| `subcategory` | string | No | Sub-category refinement (e.g., "food", "parcel", "installation") |
| `tenant` | string | Yes | Tenant identifier for multi-tenancy isolation |
| `channel` | string | Yes | Request channel (e.g., "web", "mobile", "api", "kiosk") |
| `surface` | string | Yes | BFF surface identifier (e.g., "consumer-app", "provider-portal", "ops-dashboard") |
| `persona` | string | Yes | Acting persona (e.g., "customer", "driver", "dispatcher", "admin") |
| `brand` | string | No | Brand identifier for white-labeling |
| `theme` | string | No | UI/UX theme identifier |
| `locale` | string (BCP 47) | Yes | Language/locale (e.g., "ar-SA", "en-US") |
| `processingRegion` | string | Yes | Data processing region for compliance (e.g., "me-central-1") |
| `residencyClass` | enum | Yes | Data residency level: `sovereign`, `regional`, `global` |
| `version` | string | No | API version for gradual rollout |

### 1.2 Resolution Priority

```
1. HTTP Headers (highest priority):
   X-CityOS-Tenant, X-CityOS-Country, X-CityOS-Locale, etc.

2. URL Path Parameters:
   /t/{tenant}/{locale}/deliveries/{id}

3. Cookies (fallback for browser navigation):
   cityos_tenant, cityos_locale, cityos_country
```

### 1.3 Validation Rules

- All required fields must be present; missing fields result in HTTP 400
- Tenant must exist in the tenant registry
- Country + processingRegion must be compatible (e.g., SA data must be processed in ME region)
- ResidencyClass determines which storage backends and processing nodes are eligible

---

## 2. Core Entities

### 2.1 DeliveryOrder (Shipment/Job)

The central entity representing any fulfillment task, delivery, or service job.

```
DeliveryOrder {
  deliveryId:        UUID            // CityOS-global unique ID
  tenantId:          string          // Tenant isolation key
  nodeContext:       NodeContext     // Full governance context

  type:              enum {
                       pickup_dropoff,   // Standard A→B delivery
                       multi_stop,       // Multi-point route
                       appointment,      // Scheduled service window
                       field_service     // On-site job
                     }

  sourceRef: {
    systemId:        string          // Origin system (e.g., "medusa", "payload")
    entityType:      string          // Source entity type (e.g., "order", "service_request")
    entityId:        string          // Source entity ID
  }

  pickup: {
    poiId:           string?         // Payload CMS POI reference
    address:         Address         // Structured address
    geo:             GeoPoint        // { lat, lng }
    contact:         Contact         // { name, phone, email? }
    window:          TimeWindow      // { start, end } ISO 8601
  }

  dropoff: {
    poiId:           string?
    address:         Address
    geo:             GeoPoint
    contact:         Contact
    window:          TimeWindow
  }

  items: [{
    sku:             string?         // Product SKU if applicable
    name:            string          // Item description
    qty:             number          // Quantity
    weight:          number?         // Weight in kg
    dims:            Dimensions?     // { length, width, height } in cm
    fragile:         boolean?        // Handling flag
  }]

  fees: {
    deliveryFee:     Money           // { amount, currency }
    serviceFee:      Money
    tip:             Money?
    codAmount:       Money?          // Cash-on-delivery amount
  }

  constraints: {
    coldChain:       boolean?        // Temperature-controlled
    ageRestricted:   boolean?        // Age verification required
    signatureRequired: boolean?      // Signature POD required
    photoRequired:   boolean?        // Photo POD required
  }

  status:            enum {
                       created,
                       scheduled,
                       dispatched,
                       enroute,
                       arrived,
                       delivered,
                       failed,
                       canceled
                     }

  assignedProviderId: string?        // Assigned service provider
  assignedAgentId:    string?        // Assigned driver/courier
  assignedVehicleId:  string?        // Assigned vehicle

  idempotencyKey:    string          // hash(tenant + sourceRef + type)

  timestamps: {
    createdAt:       datetime
    updatedAt:       datetime
    scheduledAt:     datetime?
    dispatchedAt:    datetime?
    pickedUpAt:      datetime?
    deliveredAt:     datetime?
    failedAt:        datetime?
    canceledAt:      datetime?
  }

  audit: {
    createdBy:       string          // Actor who created
    lastModifiedBy:  string          // Last modifier
    version:         number          // Optimistic locking version
  }
}
```

### 2.2 Provider (Service Provider / Vendor / Fleet Operator)

```
Provider {
  providerId:        UUID
  tenantId:          string
  nodeContext:       NodeContext

  name:              string
  legalName:         string?
  registrationNumber: string?

  capabilities:      enum[] {
                       delivery,
                       install,
                       maintenance,
                       tour,
                       transport,
                       cleaning
                     }

  serviceArea: {
    zones:           string[]        // Zone IDs from Payload CMS
    maxRadius:       number?         // Max service radius in km
    geo:             GeoPolygon?     // Service area polygon
  }

  verificationStatus: enum {
                       pending,
                       under_review,
                       verified,
                       suspended,
                       rejected
                     }

  rating: {
    average:         number          // 0.0 - 5.0
    totalRatings:    number
    completionRate:  number          // Percentage
    onTimeRate:      number          // Percentage
  }

  contact: {
    primaryPhone:    string
    email:           string
    address:         Address?
  }

  operatingHours: [{
    dayOfWeek:       number          // 0 (Sunday) - 6 (Saturday)
    openTime:        string          // HH:mm
    closeTime:       string          // HH:mm
  }]

  timestamps: {
    createdAt:       datetime
    updatedAt:       datetime
    verifiedAt:      datetime?
  }
}
```

### 2.3 Agent (Driver / Courier / Technician)

```
Agent {
  agentId:           UUID
  providerId:        string          // Parent provider
  tenantId:          string

  profile: {
    firstName:       string
    lastName:        string
    phone:           string
    email:           string?
    photoUrl:        string?
    nationalId:      string?         // Identity verification placeholder
  }

  identity: {
    verificationStatus: enum { pending, verified, expired, rejected }
    documentRefs:    string[]        // Storage references to ID documents
    verifiedAt:      datetime?
    expiresAt:       datetime?
  }

  capabilities:      string[]        // e.g., ["delivery", "cold_chain", "heavy_lift"]

  vehicle: {
    vehicleId:       string?         // Currently assigned vehicle
    licenseNumber:   string?
    licenseExpiry:   datetime?
  }

  shift: {
    status:          enum { off_duty, available, on_break, on_task }
    currentLocation: GeoPoint?
    lastLocationAt:  datetime?
    shiftStart:      datetime?
    shiftEnd:        datetime?
  }

  performance: {
    completedJobs:   number
    rating:          number          // 0.0 - 5.0
    onTimeRate:      number          // Percentage
    acceptanceRate:  number          // Percentage
  }

  timestamps: {
    createdAt:       datetime
    updatedAt:       datetime
    lastActiveAt:    datetime?
  }
}
```

### 2.4 Vehicle / Asset

```
Vehicle {
  vehicleId:         UUID
  providerId:        string
  tenantId:          string

  type:              enum {
                       motorcycle,
                       car,
                       van,
                       truck,
                       bicycle,
                       cargo_bike
                     }

  registration: {
    plateNumber:     string
    make:            string?
    model:           string?
    year:            number?
    color:           string?
  }

  capacity: {
    maxWeight:       number?         // kg
    maxVolume:       number?         // cubic meters
    maxItems:        number?
    passengers:      number?         // For mobility services
  }

  constraints: {
    coldChain:       boolean         // Has refrigeration
    hazmat:          boolean         // Hazmat certified
    oversized:       boolean         // Can carry oversized items
    accessibility:   boolean         // Wheelchair accessible
  }

  status:            enum { active, maintenance, inactive, retired }

  tracking: {
    currentLocation: GeoPoint?
    lastLocationAt:  datetime?
    odometer:        number?         // km
  }

  timestamps: {
    createdAt:       datetime
    updatedAt:       datetime
  }
}
```

### 2.5 TrackingEvent

```
TrackingEvent {
  eventId:           UUID
  deliveryId:        string          // Associated delivery
  tenantId:          string

  eventType:         enum {
                       location_update,
                       status_change,
                       exception,
                       pod_uploaded,
                       geofence_enter,
                       geofence_exit,
                       eta_updated,
                       note_added
                     }

  geo: {
    point:           GeoPoint        // { lat, lng }
    accuracy:        number?         // Meters
    heading:         number?         // Degrees (0-360)
    speed:           number?         // km/h
    altitude:        number?         // Meters
  }

  metadata: {
    previousStatus:  string?         // For status_change events
    newStatus:       string?
    exceptionType:   string?         // For exception events
    note:            string?
    podRef:          string?         // For pod_uploaded events
  }

  actor: {
    type:            enum { agent, system, customer, dispatcher }
    id:              string
    name:            string?
  }

  timestamp:         datetime         // When event occurred
  receivedAt:        datetime         // When system received it
  correlationId:     string          // For tracing
}
```

### 2.6 ProofOfDelivery (POD)

```
ProofOfDelivery {
  podId:             UUID
  deliveryId:        string
  tenantId:          string

  type:              enum { signature, photo, barcode_scan, pin_code }

  signature: {
    storageRef:      string?         // MinIO object reference
    signedBy:        string?         // Name of signer
    relationship:    string?         // e.g., "recipient", "neighbor"
  }

  photos: [{
    storageRef:      string          // MinIO object reference
    photoType:       enum { package, doorstep, recipient, damage }
    capturedAt:      datetime
  }]

  barcode: {
    value:           string?
    format:          string?         // e.g., "QR", "CODE128"
  }

  notes:             string?
  capturedBy:        string          // Agent ID
  capturedAt:        datetime
  location:          GeoPoint?       // Where POD was captured

  timestamps: {
    createdAt:       datetime
    uploadedAt:      datetime
  }
}
```

### 2.7 SLA Policy & Exceptions

```
SLAPolicy {
  slaId:             UUID
  tenantId:          string
  name:              string
  description:       string?

  scope: {
    deliveryTypes:   string[]        // Which delivery types this applies to
    zones:           string[]        // Which zones
    providers:       string[]?       // Specific providers (empty = all)
  }

  rules: {
    maxPickupTime:   number?         // Minutes from dispatch to pickup
    maxDeliveryTime: number?         // Minutes from pickup to delivery
    maxTotalTime:    number?         // Minutes from creation to delivery
    timeWindows: {
      enforcePickupWindow:   boolean
      enforceDeliveryWindow: boolean
      gracePeriodMinutes:    number  // Buffer before breach
    }
  }

  penalties: {
    latePickupPenalty:    Money?
    lateDeliveryPenalty:  Money?
    failedDeliveryPenalty: Money?
  }

  escalation: {
    breachNotifyRoles:   string[]    // Roles to notify on breach
    autoReassignOnBreach: boolean    // Auto-reassign to another agent
    maxReassignments:    number      // Max retry count
  }

  status:            enum { active, draft, archived }
  effectiveFrom:     datetime
  effectiveTo:       datetime?
}

DeliveryException {
  exceptionId:       UUID
  deliveryId:        string
  tenantId:          string

  type:              enum {
                       no_answer,
                       wrong_address,
                       address_not_found,
                       package_damaged,
                       package_lost,
                       refused_by_recipient,
                       late_delivery,
                       vehicle_breakdown,
                       weather_delay,
                       custom
                     }

  severity:          enum { low, medium, high, critical }
  description:       string?

  resolution: {
    status:          enum { open, investigating, resolved, escalated, closed }
    action:          enum? { retry, reschedule, return_to_sender, refund, cancel }
    resolvedBy:      string?
    resolvedAt:      datetime?
    notes:           string?
  }

  sla: {
    slaId:           string?         // Which SLA was affected
    breached:        boolean         // Whether SLA was breached
    breachDetails:   string?
    penaltyApplied:  Money?
  }

  timestamps: {
    createdAt:       datetime
    updatedAt:       datetime
    escalatedAt:     datetime?
  }
}
```

---

## 3. Supporting Types

### 3.1 Common Value Objects

```
Address {
  line1:             string
  line2:             string?
  city:              string
  state:             string?
  postalCode:        string?
  country:           string          // ISO 3166-1 alpha-2
  formattedAddress:  string?         // Human-readable full address
}

GeoPoint {
  lat:               number          // Latitude (-90 to 90)
  lng:               number          // Longitude (-180 to 180)
}

GeoPolygon {
  type:              "Polygon"
  coordinates:       number[][][]    // GeoJSON polygon coordinates
}

Contact {
  name:              string
  phone:             string
  email:             string?
  alternatePhone:    string?
}

TimeWindow {
  start:             datetime        // ISO 8601
  end:               datetime        // ISO 8601
}

Money {
  amount:            number          // Decimal (2 places)
  currency:          string          // ISO 4217 (e.g., "SAR", "AED", "USD")
}

Dimensions {
  length:            number          // cm
  width:             number          // cm
  height:            number          // cm
}
```

### 3.2 Status Enums

```
DeliveryStatus:
  created      → Order received, not yet scheduled
  scheduled    → Scheduled for future pickup
  dispatched   → Agent assigned and notified
  enroute      → Agent en route to pickup/dropoff
  arrived      → Agent arrived at location
  delivered    → Successfully delivered with POD
  failed       → Delivery attempt failed
  canceled     → Canceled by customer, dispatcher, or system

AgentShiftStatus:
  off_duty     → Not working
  available    → Working and ready for assignment
  on_break     → Temporarily unavailable
  on_task      → Currently executing a delivery/service

ProviderVerification:
  pending      → Application submitted
  under_review → Documents being reviewed
  verified     → Approved to operate
  suspended    → Temporarily suspended
  rejected     → Application denied

ExceptionResolution:
  open         → Exception reported, no action yet
  investigating → Under review
  resolved     → Action taken, issue closed
  escalated    → Escalated to higher authority
  closed       → Final state, no further action
```

---

## 4. Status Transition Rules

### 4.1 DeliveryOrder State Machine

```
                    ┌──────────┐
                    │ created  │
                    └────┬─────┘
                         │
              ┌──────────┼──────────┐
              ▼          ▼          ▼
        ┌──────────┐ ┌──────────┐
        │scheduled │ │dispatched│   canceled
        └────┬─────┘ └────┬─────┘   (from any
             │            │          pre-delivered
             └──────┬─────┘          state)
                    ▼
              ┌──────────┐
              │ enroute  │
              └────┬─────┘
                   │
              ┌────┼────┐
              ▼         ▼
        ┌──────────┐ ┌──────────┐
        │ arrived  │ │  failed  │
        └────┬─────┘ └──────────┘
             │
        ┌────┼────┐
        ▼         ▼
  ┌──────────┐ ┌──────────┐
  │delivered │ │  failed  │
  └──────────┘ └──────────┘
```

### 4.2 Transition Rules

| From | To | Trigger | Conditions |
|---|---|---|---|
| created | scheduled | System/dispatcher | Valid time window set |
| created | dispatched | System/dispatcher | Agent assigned |
| created | canceled | Customer/system | Before dispatch |
| scheduled | dispatched | System | Agent assigned at scheduled time |
| scheduled | canceled | Customer/system | Before dispatch |
| dispatched | enroute | Agent | Agent starts navigation |
| dispatched | canceled | Dispatcher | Agent reassigned or job canceled |
| enroute | arrived | Agent/geofence | Agent within geofence of location |
| enroute | failed | Agent/system | Exception reported |
| arrived | delivered | Agent | POD submitted (if required) |
| arrived | failed | Agent | Exception reported at location |

---

## 5. API Response Envelope

All API responses follow a standard CityOS envelope:

```
{
  "success":     boolean,
  "data":        T | null,
  "error": {
    "code":      string,           // Machine-readable error code
    "message":   string,           // Human-readable message
    "details":   object?           // Additional error context
  } | null,
  "meta": {
    "requestId":    string,        // Unique request identifier
    "correlationId": string,       // Distributed tracing ID
    "timestamp":    string,        // ISO 8601
    "nodeContext": {
      "tenant":    string,
      "country":   string,
      "locale":    string
    },
    "pagination": {
      "page":     number,
      "pageSize": number,
      "total":    number,
      "hasMore":  boolean
    } | null
  }
}
```

---

## 6. Entity Naming Cross-Reference

This table maps entity names across all documentation and the source specification to ensure consistency.

| Domain Model Entity | Contracts Package Type | Roadmap Reference | Source Spec Reference | Fleetbase Internal Model |
|---|---|---|---|---|
| `DeliveryOrder` | `DeliveryOrderSchema` | Phase 4, Phase 6 | Section F1: DeliveryOrder | Order / Payload |
| `Provider` | `ProviderSchema` | Phase 4, Phase 6 | Section F2: Provider | Company (vendor type) |
| `Agent` | `AgentSchema` | Phase 4, Phase 6 | Section F3: Agent | Driver |
| `Vehicle` | `VehicleSchema` | Phase 4 | Section F4: Vehicle/Asset | Vehicle |
| `TrackingEvent` | `TrackingEventSchema` | Phase 4, Phase 8 | Section F5: TrackingEvent | Tracking Status |
| `ProofOfDelivery` | `ProofOfDeliverySchema` | Phase 4, Phase 5 | Section F6: ProofOfDelivery | Proof |
| `SLAPolicy` | `SLAPolicySchema` | Phase 4, Phase 12 | Section F7: SLA + Exceptions | N/A (CityOS-only) |
| `DeliveryException` | `DeliveryExceptionSchema` | Phase 4, Phase 12 | Section F7: SLA + Exceptions | N/A (CityOS-only) |
| `NodeContext` | `NodeContextSchema` | Phase 3 | Section C: NodeContext | N/A (CityOS-only) |

### Terminology Notes

- **"Delivery" vs "DeliveryOrder":** The source spec uses both terms. In CityOS contracts, the canonical type is `DeliveryOrder` (the entity). "Delivery" is used colloquially in API paths (`/deliveries/`) and event names (`DELIVERY_CREATED`).
- **"SLAException" vs "DeliveryException":** The domain model uses `DeliveryException` as the entity name and `SLAPolicy` as the policy entity. The source spec's "SLA + Exceptions" section covers both. Events use `SLA_BREACH_DETECTED` for policy violations.
- **Workflow IDs:** All four workflow IDs (WF-FLT-001 through WF-FLT-004) are consistent across `docs/roadmap.md` (Phase 10), `docs/integration-strategy.md` (Section 5.2), and `docs/architecture.md` (Section 4.2).
