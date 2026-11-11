# National Highways & Motorway Police (NHMP)
## Humsafar Travel Advisory Platform — Technical Architecture & Integration Specification
**Document Version:** 1.0.0  
**Target Environments:** Staging (`crm.nhmp.gov.pk` / 130 CRM) & Commuter Portal (`nhmp.gov.pk/travel-advisory`)  
**Security Classification:** Official / Public API Specification  
**Focus Area:** Winter Fog/Smog Incident Management, Wireless Operator Entry & Multi-Tier Aggregation

---

### 1. Executive Summary

During the upcoming winter fog and smog season in Pakistan, National Highways and Motorways face critical visibility reductions (frequently dropping below 20–50 meters in low-lying riverine belts, canal plains, and the Salt Range). 

To ensure commuter safety, prevent fatal multi-vehicle pileups, and streamline traffic diversions, NHMP is implementing a **Next-Generation Humsafar Travel Advisory System** inspired by state-of-the-art intelligent transport portals such as the UK’s **Roadworks Radar**:
1. **130 CRM Command Hub (Central Authority):** Provides the database, geospatial coordinate engine (97,479 highway markers and 1,042 landmarks), and role-aware rapid data entry for **Beat Wireless Operators**, **Sector Wireless Control**, and **Zone Control**.
2. **Spatial Corridor Auto-Aggregation Engine:** Automatically stitches localized beat reports into unified motorway corridor advisories.
3. **High-Concurrency Public APIs (`/api/v1/travel-advisory/*`):** Sub-millisecond, Redis-cached REST and GeoJSON endpoints with ETag / HTTP 304 support for high commuter traffic.
4. **Commuter Portal (`nhmp.gov.pk/travel-advisory`):** Modern responsive web portal featuring a live top marquee ticker, interactive 3D extruded hazard pins, route corridor polyline maps, category filters, and safety advisories.

---

### 2. Operational Hierarchy & Data Entry Workflow

The system supports a 3-tier wireless operational hierarchy, matching NHMP's command structure:

```
+-----------------------------------------------------------------------------------------+
|                                  ZONE WIRELESS CONTROL                                  |
|   - National / Provincial Motorway closures (e.g. M-3 Samundri to Rajana zero-vis closure)|
|   - Emergency diversions across entire carriageways                                     |
+-----------------------------------------------------------------------------------------+
                                             |
                                             v
+-----------------------------------------------------------------------------------------+
|                                 SECTOR WIRELESS CONTROL                                 |
|   - Cross-beat incident coordination (e.g. Sector M-2 North overseeing Beats 46-51)     |
|   - Roadworks approvals, bridge expansion joint repairs, seasonal smog advisories       |
+-----------------------------------------------------------------------------------------+
                                             |
                                             v
+-----------------------------------------------------------------------------------------+
|                                  BEAT WIRELESS OPERATOR                                 |
|   - Rapid 15-second localized incident entry                                            |
|   - Real-time visibility measurements (e.g. Kallar Kahar Beat 49: Vis 35m)             |
|   - Lane closures (Lane 1 / Slow lane, Center, Fast lane, Shoulder)                     |
+-----------------------------------------------------------------------------------------+
```

#### Rapid 15-Second Entry Wizard (`/ops/travel-advisory/create`):
1. **Step 1: Category & Severity:** 6 visual cards (**Fog & Smog**, **Road Closure**, **Road Works**, **Traffic Congestion**, **Weather & Rain**, **Diversion**) + Severity (`Info`, `Advisory`, `Warning`, `Critical / Emergency`).
2. **Step 2: Road & Span Selection:** Route selection (`M-2`, `M-1`, `M-3`, `M-4`, `M-5`, `N-5`), Carriageway bound (`Both`, `Northbound`, `Southbound`), Start and End landmarks with auto-synchronized KM markers.
3. **Step 3: Fog Visibility & Speed Controls:** Dynamic fog visibility slider (5m to 1,000m) with real-time safety thresholds (0–50m Dense, 50–150m Moderate), recommended speed limits (0, 30, 40, 50, 60, 80 km/h), and lane closure status.
4. **Step 4: Template Fill & 1-Click Broadcast:** Pre-populated templates for fog, roadworks, toll queues, and full closures. Instant broadcast invalidates Redis caches and updates the live ticker and commuter portal within seconds.

---

### 3. Spatial Corridor Auto-Aggregation Engine

#### The Problem:
Commuters driving from Islamabad to Lahore do not want to read separate advisories for Beat 49 (KM 210–230) and Beat 50 (KM 230–245). They need a unified advisory: **"M-2 Motorway: Kallar Kahar to Bhera (KM 210–245) — Dense Fog Advisory (Vis < 40m)"**.

#### The Aggregation Algorithm:
When an advisory is submitted:
1. **Spatial Boundary Matching:** The engine computes the affected span `[start_km, end_km]` on the carriageway.
2. **Adjoining Span Detection:** The system queries active advisories on the same `route_name` and `advisory_type` where:
   $$\text{Gap} \le 5.0\text{ KM}$$
3. **Corridor Synthesis:** The contiguous segments are merged into a unified corridor advisory:
   - `start_landmark`: Lowest KM landmark (e.g. Kallar Kahar)
   - `end_landmark`: Highest KM landmark (e.g. Bhera)
   - `visibility_meters`: Minimum visibility recorded across the span (worst-case safety rating)
   - `speed_limit_kmh`: Minimum recommended speed limit
   - `corridor_key`: Synthesized key (e.g. `M2-KM210-KM245-FOG`)

---

### 4. Public API Specification for `nhmp.gov.pk`

All public endpoints are unauthenticated, support CORS (`Access-Control-Allow-Origin: *`), and implement HTTP ETag caching for traffic spikes.

#### Base URL:
- Staging: `https://crm.nhmp.gov.pk/api/v1/travel-advisory/` (or `https://localhost/api/v1/travel-advisory/`)

---

#### 4.1. Routes & Supported Corridors
- **Endpoint:** `GET /api/v1/travel-advisory/routes`
- **Cache TTL:** 300 seconds (Redis Tagged)
- **Response Format:**
```json
{
  "routes": [
    {
      "id": "M-2",
      "road_key": "M2",
      "name": "Motorway M-2",
      "short_name": "M-2",
      "type": "Motorway",
      "origin": "Lahore",
      "destination": "Islamabad",
      "total_km": 367.0,
      "active_alerts": 3,
      "fog_alerts": 1,
      "closure_alerts": 0
    },
    {
      "id": "M-1",
      "road_key": "M1",
      "name": "Motorway M-1",
      "short_name": "M-1",
      "type": "Motorway",
      "origin": "Islamabad",
      "destination": "Peshawar",
      "total_km": 155.0,
      "active_alerts": 1,
      "fog_alerts": 1,
      "closure_alerts": 0
    }
  ],
  "popular_journeys": [
    { "from": "Islamabad", "to": "Lahore", "road": "M-2", "label": "Islamabad ⇄ Lahore (M-2)" },
    { "from": "Peshawar", "to": "Islamabad", "road": "M-1", "label": "Peshawar ⇄ Islamabad (M-1)" }
  ],
  "timestamp": "2026-11-07T02:15:00+05:00"
}
```

---

#### 4.2. Query Route Advisories (Split-View Data)
- **Endpoint:** `GET /api/v1/travel-advisory/query`
- **Query Parameters:**
  - `road` (string, optional, default: `M-2`) — e.g. `M-2`, `M-1`, `M-3`, `N-5`
  - `from` (string, optional, default: `Islamabad`) — Origin city
  - `to` (string, optional, default: `Lahore`) — Destination city
  - `type` (string, optional, default: `all`) — `all`, `road_closure`, `fog`, `weather`, `traffic`, `road_works`, `diversion`
  - `direction` (string, optional, default: `both`) — `both`, `northbound`, `southbound`
- **Response Format:**
```json
{
  "route": {
    "code": "M-2",
    "name": "Motorway M-2",
    "from": "Islamabad",
    "to": "Lahore",
    "direction": "both",
    "advisories_count": 3,
    "summary_badge": "3 advisories on your selected route",
    "last_updated": "02:15 PKT"
  },
  "counters": {
    "all": 3,
    "road_closure": 0,
    "fog": 1,
    "weather": 0,
    "traffic": 1,
    "road_works": 1,
    "diversion": 0
  },
  "advisories": [
    {
      "id": 3,
      "uuid": "a3eb952f-c357-4030-9669-6e0e83abb00c",
      "title": "Reduced visibility",
      "advisory_type": "fog",
      "category": "fog",
      "category_display": "Fog & visibility",
      "severity": "warning",
      "route_name": "M-2",
      "location_display": "M-2 · Kallar Kahar Interchange to Bhera Service Area",
      "direction_label": "Both directions",
      "carriageway": "both",
      "start_landmark": "Kallar Kahar Interchange",
      "end_landmark": "Bhera Service Area",
      "start_km": 210.0,
      "end_km": 245.0,
      "visibility_meters": 35,
      "speed_limit_kmh": 50,
      "closure_type": null,
      "message": "Fog advisory for the selected section. Dense fog patches in the Salt Range and low lying canal sections. Keep low beams on, maintain safe headway, and follow convoy speed guidance.",
      "icon": "fog",
      "badge_color": "#d97706",
      "start_coords": [32.5756071, 72.801768],
      "end_coords": [32.1879025, 73.0370925],
      "updated_time": "Updated 01:54 PKT",
      "sample_tag": "Sample"
    },
    {
      "id": 4,
      "uuid": "5cb56136-8abc-4dee-805f-e1f4f6fdd018",
      "title": "Lane restrictions",
      "advisory_type": "road_works",
      "category": "maintenance",
      "category_display": "Road works",
      "severity": "advisory",
      "route_name": "M-2",
      "location_display": "M-2 · Near Kallar Kahar Salt Range Descent to Balkasar Approach",
      "direction_label": "Islamabad-bound",
      "carriageway": "northbound",
      "start_landmark": "Near Kallar Kahar Salt Range Descent",
      "end_landmark": "Balkasar Approach",
      "start_km": 221.0,
      "end_km": 224.0,
      "visibility_meters": 800,
      "speed_limit_kmh": 60,
      "closure_type": "lane_1",
      "message": "Follow lane signs through the work zone. Surface expansion joint rehabilitation on Bridge #14. Right and center lanes open; slow lane restricted. Use caution.",
      "icon": "road_works",
      "badge_color": "#f97316",
      "start_coords": [32.78451, 72.70892],
      "end_coords": [32.812, 72.695],
      "updated_time": "Updated 01:44 PKT",
      "sample_tag": "Sample"
    },
    {
      "id": 5,
      "uuid": "9418b6d6-9201-48fc-8f9e-ac854c1a5460",
      "title": "Slow-moving traffic",
      "advisory_type": "traffic",
      "category": "congestion",
      "category_display": "Traffic",
      "severity": "info",
      "route_name": "M-2",
      "location_display": "M-2 · Faizpur Interchange to Ravi Toll Plaza (Lahore Approach)",
      "direction_label": "Lahore-bound",
      "carriageway": "southbound",
      "start_landmark": "Faizpur Interchange",
      "end_landmark": "Ravi Toll Plaza (Lahore Approach)",
      "start_km": 1.0,
      "end_km": 12.0,
      "visibility_meters": 600,
      "speed_limit_kmh": 40,
      "closure_type": null,
      "message": "Allow extra time near the exit. Peak hour commuter volume at Ravi Toll Plaza. 14 electronic e-tag booths active. Normal flow anticipated shortly.",
      "icon": "traffic",
      "badge_color": "#ef4444",
      "start_coords": [31.554, 74.195],
      "end_coords": [31.4663225, 74.2291966],
      "updated_time": "Updated 01:39 PKT",
      "sample_tag": "Sample"
    }
  ],
  "sample_mode": true
}
```

---

#### 4.3. Live Marquee Ticker Feed (Roadworks Radar UK Style)
- **Endpoint:** `GET /api/v1/travel-advisory/ticker`
- **Cache TTL:** 60 seconds (Redis)
- **Response Format:**
```json
{
  "ticker_items": [
    {
      "id": 7,
      "badge": "Live on M-3",
      "type": "Road closed",
      "severity": "critical",
      "route_name": "M-3",
      "title": "Zero visibility closure & diversion",
      "text": "Samundri Interchange to Rajana Interchange - Zero visibility closure & diversion (Vis < 15m)",
      "full_text": "Live on M-3 | Zero visibility closure & diversion (Samundri Interchange to Rajana Interchange) - Road closed (Vis < 15m)",
      "link": "/travel-advisory?road=M-3"
    },
    {
      "id": 3,
      "badge": "Live on M-2",
      "type": "Fog advisory",
      "severity": "warning",
      "route_name": "M-2",
      "title": "Reduced visibility",
      "text": "Kallar Kahar Interchange to Bhera Service Area - Reduced visibility (Vis < 35m)",
      "full_text": "Live on M-2 | Reduced visibility (Kallar Kahar Interchange to Bhera Service Area) - Fog advisory (Vis < 35m)",
      "link": "/travel-advisory?road=M-2"
    }
  ],
  "count": 8,
  "updated_at": "02:16 PKT"
}
```

---

#### 4.4. GeoJSON Corridor & 3D Hazard Pins
- **Endpoint:** `GET /api/v1/travel-advisory/geojson?road=M-2`
- **Cache TTL:** 120 seconds
- **Response Format:** Standard RFC 7946 GeoJSON FeatureCollection with point hazard markers and line spans, ready to add directly to Leaflet: `L.geoJSON(data).addTo(map)`.

---

### 5. UI/UX Aesthetics & Design Tokens

To achieve the desired appearance matching the user's mockup and Roadworks Radar UK:
1. **Palette:**
   - National Navy: `#002855`
   - Dark Canvas: `#0a192f`
   - Sky Accent: `#0284c7` / `#38bdf8`
   - Dense Fog Amber: `#d97706` / `#f59e0b`
   - Roadworks Hazard Orange: `#ea580c` / `#c2410c`
   - Road Closure Crimson: `#dc2626` / `#991b1b`
2. **Typography:**
   - Primary: `Inter` (system-ui fallback) for dense data readability.
   - Headings & Badges: `Outfit` for modern authority.
3. **3D Pin Extrusion:**
   - Pins feature a 2px white border, radial/linear gradients, drop shadows (`0 6px 12px rgba(0,0,0,0.35)`), and smooth hover lift animations (`transform: translate(-50%, -115%) scale(1.1)`).

---

### 6. Verification Proof & Artifacts

1. **Live Commuter Portal:**
   - Route: `/travel-advisory`
   - Full Portal Screenshot: `scripts/test/travel_advisory_portal_full.png`
   - Split Corridor Map & Advisories Screenshot: `scripts/test/travel_advisory_split_view.png`
   - Card Focus State: `scripts/test/travel_advisory_card_selected.png`
2. **Wireless Operator Incident Hub:**
   - Route: `/ops/travel-advisory/create`
   - Operator Entry Form Screenshot: `scripts/test/operator_incident_form.png`
   - Fog Visibility Slider: Functional slider testing with real-time safety thresholds.
3. **Database Seeders:**
   - Class: `HumsafarTravelAdvisorySeeder` (successfully seeded 8 realistic advisories across M-1, M-2, M-3, M-4, M-5, and N-5).
