# NHMP 130 — shared dev API gateway (read-only, public data only)

Lets other developers consume the **public Travel Advisory APIs** from this workstation. Not production.

| Item | Value |
|---|---|
| Base URL (LAN / Wi-Fi) | `http://192.168.137.196:8480` |
| Base URL (Tailscale) | `http://100.93.33.77:8480` |
| Allowed | `GET` on `/api/v1/travel-advisory/{routes,query,ticker,geojson,landmarks}`, `/api/v1/advisories`, `/api/v1/advisories/emergency-alerts` |
| Everything else | `404` (login, admin UI, patrol/commuter/desk APIs); non-GET `405` |
| Limits | 20 req/s per IP (burst 40), 20 concurrent per IP, cookies/Authorization stripped |

Example: `curl "http://192.168.137.196:8480/api/v1/travel-advisory/query?from=Islamabad&to=Lahore&road=M-2"`

Postman: import `postman/collections/nhmp-hamsafar-ecosystem-apis` + `postman/environments/nhmp-dev-shared.environment.yaml`, run folders 4 and 5.

## Why not the authenticated APIs?
The local database is a copy of production (735k calls, 260 real users). Authenticated endpoints (commuter, patrol, women's-journey) are not exposed until a sanitized dataset is used (`scripts/sanitize_production_dump.php`).

## Operate
- Container: `docker run ... nhmp-dev-api-gw` (config `docker/dev-gateway/nginx.conf`, restart policy unless-stopped).
- Windows port-forward + firewall (LocalSubnet + Tailscale only): run `scripts/windows/expose-dev-api-gateway.ps1` as Administrator; re-run after a WSL restart (WSL IP changes). Remove with `-Remove`.
- Stop sharing: `docker rm -f nhmp-dev-api-gw` and run the script with `-Remove`.
