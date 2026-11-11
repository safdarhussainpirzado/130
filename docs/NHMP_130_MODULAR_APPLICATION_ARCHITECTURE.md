# NHMP 130 — MODULAR APPLICATION ARCHITECTURE & DOMAIN DESIGN SPECIFICATION

## 1. DOMAIN-DRIVEN MODULAR ARCHITECTURE

The NHMP 130 Helpline CRM is structured around 9 distinct business domains corresponding to national emergency helpline operations. Each module maintains strict isolation boundaries, clear interfaces, and explicit security contracts.

```
┌────────────────────────────────────────────────────────────────────────┐
│                          NHMP 130 DOMAINS                              │
├─────────────────┬───────────────────┬──────────────────────────────────┤
│ 1. IAM & Auth   │ 2. Calls & CRM    │ 3. Dispatch & Assistance (Tigers)│
├─────────────────┼───────────────────┼──────────────────────────────────┤
│ 4. Organization │ 5. Advisories     │ 6. Multi-Channel Notifications   │
├─────────────────┼───────────────────┼──────────────────────────────────┤
│ 7. Reporting    │ 8. Audit & Legal  │ 9. Administration & Settings     │
└─────────────────┴───────────────────┴──────────────────────────────────┘
```

---

## 2. NHMP 130 OPERATIONAL DOMAINS

### Domain 1: Identity and Access Management (IAM)
- **Scope:** Operator accounts, role hierarchies (Super Admin, IT Admin, Agent Supervisor, Call Center Agent, Zone Admin, Sector Admin, Beat Operator), WebAuthn/FIDO2 credentials, Time-based OTP (TOTP), session invalidation, temporary privilege assignments with expiration.
- **Key Models:** `User`, `Role`, `Permission`, `UserScope`, `Office`, `MfaRecoveryApproval`.
- **Security Invariant:** Server-side RBAC combined with Attribute-Based Access Control (ABAC) scoped to geographic zones, sectors, and beats.

### Domain 2: Calls and Helpline Management
- **Scope:** Ingress emergency and informational calls, caller identification, encrypted caller phone numbers, HMAC blind indexing (`caller_number_bidx`), historical call correlation, call categorization (Emergency, Traffic, Medical, Crime, Information), recording linkage.
- **Key Models:** `Call`, `CallType`, `CallSubType`, `CallerProfile`, `CallAudit`.
- **Security Invariant:** Citizen PII (phone number, name, CNIC) is encrypted at rest using AES-256-GCM. Unencrypted search is performed exclusively via keyed blind indexes.

### Domain 3: Dispatch and Field Assistance
- **Scope:** First-responder dispatch ("Tiger" vehicle patrols), status lifecycle transitions (`pending` → `inprogress` → `tiger_reached` → `completed`), response time tracking, GPS coordinates, location marker matching (Motorway/Highway Kilometer Markers).
- **Key Models:** `Dispatch`, `PatrolVehicle`, `VehicleType`, `TigerMovement`.
- **Security Invariant:** Dispatch operations are restricted to operators assigned to the specific Beat or Sector containing the incident.

### Domain 4: Organizational Hierarchy (Zone, Sector, Beat)
- **Scope:** Operational territorial command structure across Pakistan's national motorways and highways:
  - **Zone:** Strategic command (e.g., Motorway Central-I Zone).
  - **Sector:** Operational division (e.g., M-2 South Sector).
  - **Beat:** Ground patrol unit responsible for defined road segments (e.g., Beat 01, KM 000-035).
- **Key Models:** `Office`, `OfficeHierarchy`, `Road`, `BeatSegment`.
- **Security Invariant:** Multi-tier tenant isolation prevents cross-zone or cross-sector access. All spatial queries apply `applyScopeVisibility` before execution.

### Domain 5: Advisory and Highway Condition Management
- **Scope:** Public travel advisories, fog/smog warnings, lane closures, construction alerts, weather advisories, detour notices.
- **Key Models:** `TravelAdvisory`, `RoadClosure`, `WeatherAlert`.

### Domain 6: Notifications and Communications
- **Scope:** Automated SMS dispatches to callers, automated alerts to field patrol officers, system broadcast messages, push notifications.
- **Key Services:** `NotificationService`, `SmsGatewayService`.

### Domain 7: Reporting, Analytics and Keyset Exports
- **Scope:** Real-time operational dashboard, SLA compliance reports, agent performance tracking, high-volume CSV/Excel data exports.
- **Key Services:** `ReportService`, `AnalyticsService`, `StreamExportService`.
- **Performance Invariant:** Large exports use cursor-based keyset pagination with explicit date bounds (`date_from`/`date_to`) to prevent unbounded memory allocation and proxy timeouts.

### Domain 8: Audit, Compliance and Legal
- **Scope:** Immutable audit logs, privileged action logging, caller lookup logging, export auditing, cryptographic signature verification.
- **Key Models:** `ActivityLog`, `SecurityEvent`, `ExportLog`.

### Domain 9: System Administration and Security Settings
- **Scope:** Global rate limiting, IP allow-listing, MFA enforcement policies, TTS script configurations, system maintenance modes.

---

## 3. UNIFORM LAYERED SEPARATION PATTERN

Every domain follows a standardized, six-tier separation pattern:

```
Incoming Request
       │
       ▼
┌─────────────────────────┐
│     1. CONTROLLER       │  Accepts HTTP request, delegates immediately to Form Request & Policy
└──────────────┬──────────┘
               │
               ▼
┌─────────────────────────┐
│    2. FORM REQUEST      │  Validates input types, formats, string lengths, and parameter allow-lists
└──────────────┬──────────┘
               │
               ▼
┌─────────────────────────┐
│  3. AUTHORIZATION POLICY│  Enforces server-side authorization: role, active scope, record ownership
└──────────────┬──────────┘
               │
               ▼
┌─────────────────────────┐
│  4. APPLICATION SERVICE │  Orchestrates business transaction, coordinates domain events & caching
└──────────────┬──────────┘
               │
               ▼
┌─────────────────────────┐
│     5. DOMAIN RULES     │  Pure business logic (SLA calculation, status state-machine transitions)
└──────────────┬──────────┘
               │
               ▼
┌─────────────────────────┐
│   6. DATA ACCESS LAYER  │  Eloquent models, parameterized queries, blind indexes, transactions
└─────────────────────────┘
```

### Architectural Guarantees:
- **No Fat Controllers:** Controllers contain only orchestration logic (< 100 lines per action).
- **Mandatory Authorization:** Every single record lookup and mutation validates `authorize()` against an explicit Policy class.
- **Fail-Safe Defaults:** If an operator lacks an explicit scope assignment, access is denied by default (`return false`).
- **Cryptographic Separation:** Decryption of caller PII is performed in application memory only when explicitly authorized, never in database views or plain query logs.
