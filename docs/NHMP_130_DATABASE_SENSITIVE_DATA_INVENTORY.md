# NHMP 130 HELPLINE CRM — SCHEMA-WIDE SENSITIVE DATA INVENTORY & CLASSIFICATION REGISTER

**Target Database:** `130_crm_db` (MySQL 8.4 Enterprise Schema)  
**Schema Scope:** 45 Tables, 350+ Columns  
**Classification Date:** November 2026  
**Auditor:** Automated Engineering Security Audit  
**Classification Standard:** Pakistan Data Protection & Privacy Framework / Citizen PII Protection Standard  

---

## 1. Executive Summary & Sensitivity Classification Matrix

This document provides a comprehensive, field-by-field classification of all sensitive data, Personally Identifiable Information (PII), national identification numbers, authentication secrets, and operational security artifacts stored within the NHMP 130 Helpline CRM database.

### Sensitivity Classification Tiers:
- **TIER 1: CRITICAL PII / CITIZEN IDENTITY**
  National identification numbers (CNIC), caller mobile telephone numbers, citizen names, home/physical addresses, geographic location coordinates, and vehicle registration numbers.
- **TIER 2: AUTHENTICATION SECRETS & CRYPTOGRAPHIC MATERIAL**
  Bcrypt password hashes, password history hashes, TOTP secret seeds, WebAuthn public keys & credential IDs, MFA recovery codes, session cookies/tokens, remember tokens, and telephony API keys.
- **TIER 3: SENSITIVE OPERATIONAL FREE-TEXT & RECORDINGS**
  Citizen call complaints, patrol officer field remarks, incident descriptions, telephony call recording URLs, webhook raw JSON payloads, and queued job serialized payloads.
- **TIER 4: AUDIT LOGS & CLIENT TELEMETRY**
  User IP addresses, browser user agents, database mutation delta JSON (`old_values`, `new_values`), and login/logout activity logs.
- **TIER 5: SAFE STRUCTURAL & OPERATIONAL REFERENCE DATA**
  Table schemas, auto-increment sequences, highway carriageways, road sectors, beats, office hierarchies, call types, call categories, roles, and permissions.

---

## 2. Table-by-Table Field Inventory & Sanitization Action Matrix

| Table Name | Sensitive Column(s) | Data Type | Sensitivity Tier | Threat / Leakage Vector | Mandatory Sanitization Treatment |
|---|---|---|---|---|---|
| `calls` | `caller_number` | `varchar(20)` | Tier 1 | Direct Citizen Phone Identification | Deterministic HMAC-SHA256 pseudonymization (`03XX-XXXXXXX`). Repeat calls preserved. |
| `calls` | `caller_name` | `varchar(150)` | Tier 1 | Citizen Identity Disclosure | Deterministic masking to `Caller-<hash>`. |
| `calls` | `details` | `text` | Tier 3 | Free-text containing embedded CNICs, phones, addresses | Scanned via regex for embedded CNICs/phones + redacted to generic operational description. |
| `calls` | `location_details` | `text` | Tier 1/3 | Free-text containing landmarks, residences, addresses | Scanned for PII + redacted to generic highway location. |
| `calls` | `vehicle_no` | `varchar(30)` | Tier 1 | Citizen Vehicle Tracking | Deterministic masking to `LEA-<hash>`. |
| `calls` | `caller_lat`, `caller_lng` | `decimal` | Tier 1 | Precise GPS Tracking of Citizen | Nullified or rounded to coarse highway boundary. |
| `calls` | `pending_remarks` ... `completed_remarks` | `text` | Tier 3 | Officer notes containing caller PII & names | Scanned via regex + scrubbed to `[REDACTED STATUS REMARKS]`. |
| `users` | `cnic` | `varchar(15)` | Tier 1 | National Identity (NADRA CNIC) | Deterministic HMAC-SHA256 pseudonymization (`35202-XXXXXXX-X`) with collision-prevention set. |
| `users` | `mobile_no` | `varchar(15)` | Tier 1 | Employee/Officer Mobile Number | Deterministic HMAC-SHA256 pseudonymization (`0300-XXXXXXX`). |
| `users` | `full_name` | `varchar(150)` | Tier 1 | Officer/Staff Personal Name | Masked to `NHMP Staff Member <id>` (Admins preserved by role). |
| `users` | `email` | `varchar(100)` | Tier 1 | Corporate/Personal Email | Masked to `staff_<id>@crm.local` (unique constraint preserved). |
| `users` | `password` | `varchar(255)` | Tier 2 | Production User Password Hash | **QUARANTINED**: Account disabled (`is_active=0`, `locked_until='2099-01-01'`) and password set to unmatchable invalid hash `!QUARANTINED_PROD_ACCOUNT_<unique_hash>`. Dedicated synthetic dev admin created separately. |
| `users` | `totp_secret`, `totp_last_step` | `text`, `bigint` | Tier 2 | 2FA/MFA Cryptographic Seed | Set to `NULL`. |
| `users` | `remember_token` | `varchar(100)` | Tier 2 | Persistent Login Cookie Token | Set to `NULL`. |
| `users` | `last_login_ip` | `varchar(45)` | Tier 4 | Client IP Telemetry | Set to `'127.0.0.1'`. |
| `outgoing_calls` | `called_no` | `varchar(20)` | Tier 1 | Called Citizen Telephone | Deterministic HMAC-SHA256 pseudonymization. |
| `outgoing_calls` | `reason`, `outcome` | `varchar(255)` | Tier 3 | Free-text remarks | Scanned and scrubbed of any embedded phone/CNIC. |
| `phonebook_contacts` | `name` | `varchar(150)` | Tier 1 | Contact Personal Name | Masked to `Contact-<hash>`. |
| `phonebook_contacts` | `phone_number` | `varchar(50)` | Tier 1 | Contact Telephone | Deterministic HMAC-SHA256 pseudonymization. |
| `phonebook_contacts` | `metadata` | `json` | Tier 1/3 | JSON containing contact details | JSON parsed and sensitive keys scrubbed. |
| `telephony_agent_configs` | `ziwo_username`, `ziwo_token` | `varchar`, `text` | Tier 2 | Telephony Provider API Token | Token cleared or replaced with dummy local test token. |
| `telephony_call_logs` | `caller_number` | `varchar` | Tier 1 | Direct Citizen Phone | Deterministic HMAC-SHA256 pseudonymization. |
| `telephony_call_logs` | `recording_url` | `text` | Tier 3 | Audio Call Recording Endpoint | Cleared to `NULL` (audio files are confidential). |
| `telephony_call_logs` | `metadata` | `json` | Tier 3 | Raw CTI Provider Telephony Payload | JSON recursively scanned and scrubbed. |
| `telephony_webhook_logs` | `payload` | `json` | Tier 3 | Raw Telephony Webhook Ingest | Purged or scrubbed of caller numbers and tokens. |
| `audit_logs` | `old_values`, `new_values` | `json` | Tier 2/3 | Database Audit Delta containing raw PII/hashes | JSON parsed: all keys matching password, cnic, mobile, caller, token are redacted to `[REDACTED]`. |
| `audit_logs` | `ip_address`, `user_agent` | `varchar` | Tier 4 | Client IP & Fingerprint | Set to `'127.0.0.1'` and `'Mozilla/5.0 (Local Testbed)'`. |
| `login_logs` | `ip_address`, `user_agent` | `varchar` | Tier 4 | Client IP & Fingerprint | Set to `'127.0.0.1'` and `'Mozilla/5.0 (Local Testbed)'`. |
| `sessions` | `payload`, `ip_address` | `longtext` | Tier 2 | Active Login Session Cookies & Tokens | **PURGED**: All `INSERT INTO sessions` statements removed completely. |
| `two_factor_recovery_codes` | `code_hash` | `varchar` | Tier 2 | Backup MFA Recovery Secrets | **PURGED**: All rows removed completely. |
| `two_factor_enrollment_authorizations` | `token_hash`, `reason` | `varchar` | Tier 2 | Authorization Tokens | **PURGED**: All rows removed completely. |
| `two_factor_recovery_approvals` | `request_token_hash`, `reason` | `varchar` | Tier 2 | Recovery Approval Tokens | **PURGED**: All rows removed completely. |
| `password_histories` | `password_hash` | `varchar` | Tier 2 | Historical Password Hashes | **PURGED**: All rows removed completely. |
| `password_reset_tokens` | `token` | `varchar` | Tier 2 | Active Password Reset Tokens | **PURGED**: All rows removed completely. |
| `password_reset_requests` | `token_hash` | `varchar` | Tier 2 | Active Reset Requests | **PURGED**: All rows removed completely. |
| `webauthn_credentials` | `credential_id`, `public_key` | `varchar`, `text` | Tier 2 | Hardware FIDO2/WebAuthn Secrets | **PURGED**: All rows removed completely. |
| `notifications` | `data` | `text` | Tier 1/3 | In-App Alert JSON containing caller PII | JSON parsed and phone/CNIC/names scrubbed. |
| `failed_jobs` | `payload`, `exception` | `longtext` | Tier 1/3 | Serialized Laravel Job Arguments | **PURGED**: All rows removed completely. |

---

## 3. Structural & Non-Sensitive Operational Reference Tables (Preserved Intact)

The following tables contain operational reference data, schemas, indexes, and administrative configuration. They contain **zero citizen PII** and are preserved with 100% relational integrity:

1. `analytics_outbox_events` (Operational event queue)
2. `cache` / `cache_locks` (Framework caching)
3. `call_hourly_rollups` (Aggregated statistics, zero citizen IDs)
4. `call_sequences` (Transactional sequence counters — critical for call numbering consistency)
5. `call_status_history` (Status state transitions — foreign keys preserved, remarks sanitized)
6. `call_sub_types` (Call categories)
7. `call_types` (Call taxonomy)
8. `carriageways` (Highway carriageway master data)
9. `designations` (NHMP rank designations: Inspector, Sub-Inspector, Patrol Officer)
10. `geospatial_landmarks` (Highway landmarks, toll plazas, interchanges)
11. `geospatial_markers` (Kilometer marker points)
12. `job_batches` / `jobs` (Queue framework tables)
13. `languages` (Urdu, English, Punjabi, Pashto, Sindhi, Balochi)
14. `mfa_role_policies` (MFA enforcement policies per role)
15. `migrations` (Laravel schema migration ledger)
16. `offices` (Zonal headquarters, sector commands, beat stations)
17. `permissions` / `roles` / `role_permissions` (RBAC access control matrices)
18. `system_settings` (Application configuration parameters)
19. `tablet_advisories` (Highway emergency travel alerts)
20. `tts_scripts` (Text-to-speech advisory scripts)
21. `user_scopes` (Hierarchical beat/sector office assignment scopes)
22. `vehicle_types` (Motorcycle, HTV, LTV, PSV, Bus, Car)

---

## 4. Remediation Architecture & Limitations

### 4.1 Quarantining Production Accounts vs. Synthetic Developer Accounts
In earlier versions, all production-derived user accounts had their passwords reset to a common developer hash (`password`). This introduced serious security risks:
- Risk of credential confusion if developers test with real officer identities.
- Risk of privilege escalation or unintended login.

**Remediation Applied:**
- All production user accounts are **quarantined by default**:
  - `is_active` set to `0` (account disabled).
  - `locked_until` set to `'2099-01-01 00:00:00'` (account permanently locked).
  - `password` set to an unmatchable cryptographically invalid string `!QUARANTINED_PROD_ACCOUNT_<unique_salt>`.
- Dedicated **synthetic local development accounts** are provisioned cleanly:
  - `dev_admin` (Role: Super Admin, Email: `admin@crm.local`, known unique test password).
  - `dev_agent` (Role: Helpline Agent, Email: `agent@crm.local`, known unique test password).

### 4.2 Handling of Embedded PII in Free-Text
Even if structured columns (`caller_number`, `cnic`) are sanitized, citizens or call takers often type phone numbers or CNICs directly into the incident narrative (e.g., *"Caller stated his CNIC is 35202-9876543-1 and phone is 03451122334"*).

**Remediation Applied:**
The sanitization engine applies dual-tier scrubbing:
1. Tuple-level replacement of primary citizen columns.
2. Secondary regex scanner across all free-text fields (`details`, `location_details`, `remarks`, `reason`, `outcome`, JSON payloads) that replaces any detected CNIC pattern (`\b\d{5}-?\d{7}-?\d\b`) and phone pattern (`\b(?:\+92|92|0)?3\d{2}[-\s]?\d{7}\b`) with `[REDACTED]`.

### 4.3 Explicit System Limitations
1. **Unstructured Names in Arbitrary Urdu/English Prose**: While structured `caller_name` fields are 100% masked, unusual nicknames embedded in complex Urdu narrative text that do not match phone or CNIC patterns are redacted by replacing the entire free-text field with standard operational descriptions.
2. **Deterministic Salt Longevity**: The deterministic salt ensures repeatability across development database refreshes. If the salt is changed, repeat-caller mappings across historical snapshots will produce different pseudonyms.
