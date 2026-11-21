# Survey, Presence, Evidence, Feedback and Commuter API (v1) - STAGING ONLY

Base URL (staging): `http://100.108.239.107:8095/api/v1` . Auth: Laravel Sanctum bearer token from the patrol or commuter login. Never deployed to production.
All responses: `{ "success": bool, "data": ..., "meta": ... }`. Validation errors: HTTP 422 with Laravel `errors`. Throttled endpoints return 429 with `Retry-After` (honour it).

## Patrol (role patrol/beat operator/admin, `hamsafar.access:field`)
| Method | Path | Notes |
|---|---|---|
| GET | `/patrol/surveys/categories` | Active categories with active services (DB driven; do not hardcode). |
| GET | `/patrol/surveys?status=&per_page=` | Own surveys only. |
| POST | `/patrol/surveys` | Body: `category_id,name,address?,lat,lng,direction? (N/S/E/W),phone_numbers[]?,is_24h,opens_at,closes_at (H:i, required unless is_24h; closes<opens = overnight),service_ids[]?,submit?`. Server geo-enriches (marker within 300 m; opposite carriageways without `direction` => `geo_status=needs_verification`). |
| GET/PUT | `/patrol/surveys/{uuid}` | Approved surveys cannot be edited. |
| POST | `/patrol/heartbeat` | Every 60 s. Body `lat?,lng?,app_version?`. Online = seen within 3 min. Throttle 90/min. |
| GET | `/patrol/availability` | Own presence and availability. |
| PUT | `/patrol/availability` | `status` in available, prayer_break, meal_break, official_assignment, temporary_break, other; `reason?`. Audited. |
| POST | `/patrol/availability/return-to-duty` | Sets `available`. |
| POST | `/cases/{uuid}/evidence` | multipart `image` (jpeg/png/webp, max 5 MB, mime sniffed by content). One per call (409 on repeat). Private disk. |

## Commuter (any authenticated commuter)
| Method | Path | Notes |
|---|---|---|
| GET | `/commuter/nearby-locations?lat&lng&radius_m&direction&category&limit&page` | Approved + active only. Radius capped at 25 km, limit capped at 50, sorted by distance (same-direction first when `direction` is given). Refresh when moved > 1 km or every 2 min. Throttle 30/min. |
| POST | `/commuter/presence` | `lat,lng,heading?,speed?,sharing?`. Latest position only, no history. Throttle 30/min. |
| POST | `/cases/{uuid}/feedback` | Owner of the case only, once per case. `timeliness,behaviour,helpfulness` 1-5, `comment?`. `overall` = rounded average. |

## Admin pages (web, permission gated)
`/humsafar/surveys`, `/humsafar/catalog`, `/humsafar/officers`, `/humsafar/commuters` (masked mobile, audited reveal), `/humsafar/monitor` (audited, no history), `/humsafar/feedback`. Permissions: `humsafar.view`, `.survey.manage`, `.officers.view`, `.commuters.view`, `.commuters.reveal`, `.commuters.monitor`, `.feedback.view`.
Evidence is shown on the Call page in its own "Assistance Evidence" card (`/calls/{call}/evidence`, authorised users only).
