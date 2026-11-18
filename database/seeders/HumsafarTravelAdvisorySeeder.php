<?php

namespace Database\Seeders;

use App\Models\Carriageway;
use App\Models\Office;
use App\Models\TabletAdvisory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class HumsafarTravelAdvisorySeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $adminUser = User::where('role_id', 1)->first() ?? User::first();
        $adminId = $adminUser ? $adminUser->id : 1;

        // Clean previous stale advisory data
        TabletAdvisory::truncate();

        // Helper to resolve Carriageway by short or full road code
        $getCarriagewayId = function (string $code): ?int {
            return Carriageway::where('road', $code)
                ->orWhere('road_short', $code)
                ->orWhere('road', str_replace(['-', ' '], '', $code))
                ->value('id');
        };

        // Helper to resolve precise physical GPS coordinates from Carriageway markers
        $resolveGps = function (string $routeName, float $km): array {
            $clean = strtoupper(str_replace(['-', ' '], '', $routeName));
            $cw = Carriageway::where('road', $clean)->orWhere('road_short', $routeName)->first();
            if ($cw) {
                $marker = $cw->markers()
                    ->whereNotNull('lat')
                    ->whereNotNull('lng')
                    ->orderByRaw('ABS(km - ?)', [$km])
                    ->first();
                if ($marker) {
                    return [(float)$marker->lat, (float)$marker->lng];
                }
            }
            $defaults = TabletAdvisory::getRouteDefaultCoordinates($routeName);
            return $defaults ? [(float)$defaults[0][0], (float)$defaults[0][1]] : [31.5204, 74.3587];
        };

        // Helper to resolve real Zone, Sector, and Beat offices
        $resolveOfficeHierarchy = function (string $sectorSnippet, ?string $beatSnippet = null): array {
            $sector = Office::where('type', 'sector')
                ->where('name', 'LIKE', '%' . $sectorSnippet . '%')
                ->first();

            $zoneId = $sector?->parent_id;
            $beat = null;

            if ($sector) {
                if ($beatSnippet) {
                    $beat = Office::where('parent_id', $sector->id)
                        ->where('name', 'LIKE', '%' . $beatSnippet . '%')
                        ->first();
                }
                $beat = $beat ?? Office::where('parent_id', $sector->id)->first();
            }

            return [
                'zone_id'   => $zoneId,
                'sector_id' => $sector?->id,
                'office_id' => $beat?->id,
            ];
        };

        $officesM1       = $resolveOfficeHierarchy('M1', 'Beat-01');
        $officesM2North  = $resolveOfficeHierarchy('M2 North', 'Beat-05');
        $officesM2South  = $resolveOfficeHierarchy('M2 South', 'Beat-09');
        $officesM3       = $resolveOfficeHierarchy('M3', 'Beat-13');
        $officesM4       = $resolveOfficeHierarchy('M4', 'Beat-17');
        $officesM5       = $resolveOfficeHierarchy('M5-1', 'Beat-23');
        $officesM9       = $resolveOfficeHierarchy('South1', 'Beat-25');
        $officesM11      = $resolveOfficeHierarchy('LSM', 'Beat-01');
        $officesM14      = $resolveOfficeHierarchy('M14', 'Beat-01');
        $officesN5       = $resolveOfficeHierarchy('Central1', 'Beat-12');
        $officesE35      = $resolveOfficeHierarchy('E35', 'Beat-01');
        $officesN25      = $resolveOfficeHierarchy('Kalat', 'Beat-11');
        $officesN75      = $resolveOfficeHierarchy('IMDC', 'IMDC');

        $advisories = [
            // =========================================================================
            // CATEGORY 1: FOG & VISIBILITY (5 active records)
            // =========================================================================

            // 1. M-2 Motorway: Dense Fog Kot Abdul Malik to Sheikhupura
            [
                'title'             => 'Dense Fog Alert - Kot Abdul Malik to Sheikhupura',
                'category'          => 'fog',
                'severity'          => 'advisory',
                'route_name'        => 'M-2',
                'carriageway'       => 'both',
                'carriageway_id'    => $getCarriagewayId('M-2'),
                'office_id'         => $officesM2South['office_id'],
                'sector_id'         => $officesM2South['sector_id'],
                'zone_id'           => $officesM2South['zone_id'],
                'start_km'          => 15.0,
                'end_km'            => 35.0,
                'start_landmark'    => 'Kot Abdul Malik Interchange',
                'end_landmark'      => 'Sheikhupura Interchange',
                'visibility_meters' => 25,
                'speed_limit_kmh'   => 40,
                'closure_type'      => null,
                'corridor_key'      => 'M2-KM015-KM035-FOG',
                'message'           => 'Heavy fog between Kot Abdul Malik and Sheikhupura. Visibility is down to 25 meters. Drive slowly below 40 km/h, keep fog lights and low beams on, and keep safe distance from vehicles ahead. In case of help, call 130.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(15),
                'approved_at'       => $now->copy()->subMinutes(15),
                'expires_at'        => $now->copy()->addHours(12),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // 2. M-2 Motorway: Salt Range Fog (Kallar Kahar to Bhera)
            [
                'title'             => 'Dense Fog Alert - Kallar Kahar to Bhera',
                'category'          => 'fog',
                'severity'          => 'warning',
                'route_name'        => 'M-2',
                'carriageway'       => 'both',
                'carriageway_id'    => $getCarriagewayId('M-2'),
                'office_id'         => $officesM2North['office_id'],
                'sector_id'         => $officesM2North['sector_id'],
                'zone_id'           => $officesM2North['zone_id'],
                'start_km'          => 210.0,
                'end_km'            => 245.0,
                'start_landmark'    => 'Kallar Kahar Interchange',
                'end_landmark'      => 'Bhera Service Area',
                'visibility_meters' => 35,
                'speed_limit_kmh'   => 50,
                'closure_type'      => null,
                'corridor_key'      => 'M2-KM210-KM245-FOG',
                'message'           => 'Thick fog in Salt Range area between Kallar Kahar and Bhera. Visibility is reduced to 35 meters. Please drive below 50 km/h, use fog lights, and avoid overtaking. NHMP patrol is escorting traffic in convoys.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(30),
                'approved_at'       => $now->copy()->subMinutes(30),
                'expires_at'        => $now->copy()->addHours(12),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // 3. M-3 Motorway: Low Visibility Fog near Samundri to Rajana
            [
                'title'             => 'Dense Fog Alert - Samundri to Rajana',
                'category'          => 'fog',
                'severity'          => 'warning',
                'route_name'        => 'M-3',
                'carriageway'       => 'both',
                'carriageway_id'    => $getCarriagewayId('M-3'),
                'office_id'         => $officesM3['office_id'],
                'sector_id'         => $officesM3['sector_id'],
                'zone_id'           => $officesM3['zone_id'],
                'start_km'          => 960.0,
                'end_km'            => 995.0,
                'start_landmark'    => 'Samundri Interchange',
                'end_landmark'      => 'Rajana Interchange',
                'visibility_meters' => 50,
                'speed_limit_kmh'   => 50,
                'closure_type'      => null,
                'corridor_key'      => 'M3-KM960-KM995-FOG',
                'message'           => 'Dense fog reported between Samundri and Rajana. Visibility is around 50 meters. Keep fog lights on, avoid unnecessary travel, and maintain safe distance. Convoys leaving Samundri Toll Plaza.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(45),
                'approved_at'       => $now->copy()->subMinutes(45),
                'expires_at'        => $now->copy()->addHours(12),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // 4. M-5 Motorway: Night Fog Convoy between Multan South and Jalalpur Pirwala
            [
                'title'             => 'Dense Fog Alert - Multan to Jalalpur Pirwala',
                'category'          => 'fog',
                'severity'          => 'warning',
                'route_name'        => 'M-5',
                'carriageway'       => 'both',
                'carriageway_id'    => $getCarriagewayId('M-5'),
                'office_id'         => $officesM5['office_id'],
                'sector_id'         => $officesM5['sector_id'],
                'zone_id'           => $officesM5['zone_id'],
                'start_km'          => 410.0,
                'end_km'            => 460.0,
                'start_landmark'    => 'Multan South Interchange',
                'end_landmark'      => 'Jalalpur Pirwala Interchange',
                'visibility_meters' => 60,
                'speed_limit_kmh'   => 60,
                'closure_type'      => null,
                'corridor_key'      => 'M5-KM410-KM460-FOG',
                'message'           => 'Heavy fog between Multan South and Jalalpur Pirwala. Visibility is 50 to 60 meters. Stay in your lane, drive behind police patrol convoys, and do not speed.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(20),
                'approved_at'       => $now->copy()->subMinutes(20),
                'expires_at'        => $now->copy()->addHours(12),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // 5. M-11 Motorway: Lahore-Sialkot Dense Fog
            [
                'title'             => 'Dense Fog Alert - Kamoke to Gujranwala',
                'category'          => 'fog',
                'severity'          => 'warning',
                'route_name'        => 'M-11',
                'carriageway'       => 'both',
                'carriageway_id'    => $getCarriagewayId('M-11'),
                'office_id'         => $officesM11['office_id'],
                'sector_id'         => $officesM11['sector_id'],
                'zone_id'           => $officesM11['zone_id'],
                'start_km'          => 25.0,
                'end_km'            => 50.0,
                'start_landmark'    => 'Kamoke Interchange',
                'end_landmark'      => 'Gujranwala East Interchange',
                'visibility_meters' => 30,
                'speed_limit_kmh'   => 40,
                'closure_type'      => null,
                'corridor_key'      => 'M11-KM025-KM050-FOG',
                'message'           => 'Thick fog on Lahore-Sialkot Motorway between Kamoke and Gujranwala East. Visibility is only 30 meters. Turn on fog lights, drive below 40 km/h, and avoid overtaking.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(10),
                'approved_at'       => $now->copy()->subMinutes(10),
                'expires_at'        => $now->copy()->addHours(12),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // =========================================================================
            // CATEGORY 2: ROAD CLOSURES (3 active records)
            // =========================================================================

            // 6. M-2 Motorway: Critical Road Closure Kot Momin to Pindi Bhattian
            [
                'title'             => 'Road Closed - Kot Momin to Pindi Bhattian',
                'category'          => 'road_closure',
                'severity'          => 'critical',
                'route_name'        => 'M-2',
                'carriageway'       => 'both',
                'carriageway_id'    => $getCarriagewayId('M-2'),
                'office_id'         => $officesM2South['office_id'],
                'sector_id'         => $officesM2South['sector_id'],
                'zone_id'           => $officesM2South['zone_id'],
                'start_km'          => 130.0,
                'end_km'            => 135.0,
                'start_landmark'    => 'Kot Momin Interchange',
                'end_landmark'      => 'Pindi Bhattian Interchange',
                'visibility_meters' => 800,
                'speed_limit_kmh'   => 0,
                'closure_type'      => 'full',
                'corridor_key'      => 'M2-KM130-KM135-CLOSURE',
                'message'           => 'M-2 is completely closed between Kot Momin and Pindi Bhattian due to an overturned truck. Traffic diverted to GT Road (N-5) via Pindi Bhattian exit. Teams are clearing the road.',
                'status'            => 'published',
                'is_emergency'      => true,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(25),
                'approved_at'       => $now->copy()->subMinutes(25),
                'expires_at'        => $now->copy()->addHours(12),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // 7. M-4 Motorway: Temporary Accident Recovery Closure near Sangla Hill
            [
                'title'             => 'Road Closed - Sangla Hill Northbound',
                'category'          => 'road_closure',
                'severity'          => 'warning',
                'route_name'        => 'M-4',
                'carriageway'       => 'northbound',
                'carriageway_id'    => $getCarriagewayId('M-4'),
                'office_id'         => $officesM4['office_id'],
                'sector_id'         => $officesM4['sector_id'],
                'zone_id'           => $officesM4['zone_id'],
                'start_km'          => 70.0,
                'end_km'            => 74.0,
                'start_landmark'    => 'Sangla Hill Interchange',
                'end_landmark'      => 'Pindi Bhattian South Approach',
                'visibility_meters' => 1000,
                'speed_limit_kmh'   => 0,
                'closure_type'      => 'full',
                'corridor_key'      => 'M4-KM070-KM074-CLOSURE',
                'message'           => 'Northbound track towards Pindi Bhattian is temporarily closed near Sangla Hill due to an accident. Traffic towards Faisalabad is normal. Road expected to reopen in 45 minutes.',
                'status'            => 'published',
                'is_emergency'      => true,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(18),
                'approved_at'       => $now->copy()->subMinutes(18),
                'expires_at'        => $now->copy()->addHours(12),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // 8. N-25 National Highway: Mountain Pass Clearance between Khuzdar and Kalat
            [
                'title'             => 'Road Closed - Khuzdar to Kalat',
                'category'          => 'road_closure',
                'severity'          => 'critical',
                'route_name'        => 'N-25',
                'carriageway'       => 'both',
                'carriageway_id'    => $getCarriagewayId('N-25'),
                'office_id'         => $officesN25['office_id'],
                'sector_id'         => $officesN25['sector_id'],
                'zone_id'           => $officesN25['zone_id'],
                'start_km'          => 240.0,
                'end_km'            => 260.0,
                'start_landmark'    => 'Khuzdar Valley North',
                'end_landmark'      => 'Surab Pass Approach',
                'visibility_meters' => 600,
                'speed_limit_kmh'   => 0,
                'closure_type'      => 'full',
                'corridor_key'      => 'N25-KM240-KM260-CLOSURE',
                'message'           => 'N-25 RCD Highway is closed between Khuzdar and Kalat due to landslides and fallen rocks. Machinery is clearing the road. Drivers are advised to wait at Kalat or Khuzdar.',
                'status'            => 'published',
                'is_emergency'      => true,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(50),
                'approved_at'       => $now->copy()->subMinutes(50),
                'expires_at'        => $now->copy()->addHours(12),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // =========================================================================
            // CATEGORY 3: ROAD WORKS (4 active records)
            // =========================================================================

            // 9. M-2 Motorway: Salt Range Bridge Expansion Joint Rehabilitation
            [
                'title'             => 'Road Work - Kallar Kahar Bridge',
                'category'          => 'road_works',
                'severity'          => 'advisory',
                'route_name'        => 'M-2',
                'carriageway'       => 'northbound',
                'carriageway_id'    => $getCarriagewayId('M-2'),
                'office_id'         => $officesM2North['office_id'],
                'sector_id'         => $officesM2North['sector_id'],
                'zone_id'           => $officesM2North['zone_id'],
                'start_km'          => 221.0,
                'end_km'            => 224.0,
                'start_landmark'    => 'Near Kallar Kahar Salt Range Descent',
                'end_landmark'      => 'Balkasar Approach',
                'visibility_meters' => 800,
                'speed_limit_kmh'   => 60,
                'closure_type'      => 'lane_1',
                'corridor_key'      => 'M2-KM221-KM224-WORKS',
                'message'           => 'Repair work underway on Bridge #14 near Kallar Kahar towards Islamabad. Left lane is closed; middle and fast lanes are open. Drive slowly at 60 km/h and follow traffic signs.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subHours(2),
                'approved_at'       => $now->copy()->subHours(2),
                'expires_at'        => $now->copy()->addDays(4),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // 10. M-1 Motorway: Indus River Bridge Deck Repair near Swabi
            [
                'title'             => 'Road Work - Indus River Bridge Swabi',
                'category'          => 'road_works',
                'severity'          => 'advisory',
                'route_name'        => 'M-1',
                'carriageway'       => 'northbound',
                'carriageway_id'    => $getCarriagewayId('M-1'),
                'office_id'         => $officesM1['office_id'],
                'sector_id'         => $officesM1['sector_id'],
                'zone_id'           => $officesM1['zone_id'],
                'start_km'          => 385.0,
                'end_km'            => 388.0,
                'start_landmark'    => 'Indus River Bridge',
                'end_landmark'      => 'Swabi Interchange Approach',
                'visibility_meters' => 1000,
                'speed_limit_kmh'   => 60,
                'closure_type'      => 'lane_2',
                'corridor_key'      => 'M1-KM385-KM388-WORKS',
                'message'           => 'Road maintenance on Indus River Bridge near Swabi towards Peshawar. Fast lane is closed. Please drive in the left and middle lanes at 60 km/h.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subHours(4),
                'approved_at'       => $now->copy()->subHours(4),
                'expires_at'        => $now->copy()->addDays(5),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // 11. M-9 Motorway: Culvert Construction near Nooriabad
            [
                'title'             => 'Road Work - Nooriabad Area',
                'category'          => 'road_works',
                'severity'          => 'advisory',
                'route_name'        => 'M-9',
                'carriageway'       => 'southbound',
                'carriageway_id'    => $getCarriagewayId('M-9'),
                'office_id'         => $officesM9['office_id'],
                'sector_id'         => $officesM9['sector_id'],
                'zone_id'           => $officesM9['zone_id'],
                'start_km'          => 65.0,
                'end_km'            => 70.0,
                'start_landmark'    => 'Nooriabad Industrial Zone',
                'end_landmark'      => 'Thana Bula Khan Approach',
                'visibility_meters' => 1200,
                'speed_limit_kmh'   => 50,
                'closure_type'      => 'lane_1',
                'corridor_key'      => 'M9-KM065-KM070-WORKS',
                'message'           => 'Culvert construction on M-9 towards Karachi near Nooriabad. Left slow lane and shoulder are closed. Please drive with care at 50 km/h.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subHours(1),
                'approved_at'       => $now->copy()->subHours(1),
                'expires_at'        => $now->copy()->addDays(4),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // 12. N-5 National Highway: Asphalt Overlay near Gujranwala Bypass
            [
                'title'             => 'Road Work - Gujranwala Bypass',
                'category'          => 'road_works',
                'severity'          => 'advisory',
                'route_name'        => 'N-5',
                'carriageway'       => 'both',
                'carriageway_id'    => $getCarriagewayId('N-5'),
                'office_id'         => $officesN5['office_id'],
                'sector_id'         => $officesN5['sector_id'],
                'zone_id'           => $officesN5['zone_id'],
                'start_km'          => 1180.0,
                'end_km'            => 1185.0,
                'start_landmark'    => 'Chann Da Qila',
                'end_landmark'      => 'Gujranwala Bypass North',
                'visibility_meters' => 1000,
                'speed_limit_kmh'   => 40,
                'closure_type'      => 'lane_1',
                'corridor_key'      => 'N5-KM1180-KM1185-WORKS',
                'message'           => 'Road carpet work on GT Road near Gujranwala Bypass (Chann Da Qila). Single lane open each way. Expect delays and avoid overtaking on shoulders.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subHours(3),
                'approved_at'       => $now->copy()->subHours(3),
                'expires_at'        => $now->copy()->addDays(5),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // =========================================================================
            // CATEGORY 4: TRAFFIC & CONGESTION (3 active records)
            // =========================================================================

            // 13. M-2 Motorway: Peak Commuter Flow approaching Ravi Toll Plaza
            [
                'title'             => 'Traffic Congestion - Ravi Toll Plaza Lahore',
                'category'          => 'traffic',
                'severity'          => 'info',
                'route_name'        => 'M-2',
                'carriageway'       => 'southbound',
                'carriageway_id'    => $getCarriagewayId('M-2'),
                'office_id'         => $officesM2South['office_id'],
                'sector_id'         => $officesM2South['sector_id'],
                'zone_id'           => $officesM2South['zone_id'],
                'start_km'          => 1.0,
                'end_km'            => 12.0,
                'start_landmark'    => 'Faizpur Interchange',
                'end_landmark'      => 'Ravi Toll Plaza (Lahore Approach)',
                'visibility_meters' => 600,
                'speed_limit_kmh'   => 40,
                'closure_type'      => null,
                'corridor_key'      => 'M2-KM001-KM012-TRAFFIC',
                'message'           => 'Slow traffic approaching Lahore Ravi Toll Plaza from Faizpur. Expect 10-15 minutes delay. Please stay in line and keep your M-Tag recharged.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(12),
                'approved_at'       => $now->copy()->subMinutes(12),
                'expires_at'        => $now->copy()->addHours(8),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // 14. M-9 Motorway: Port Freight & Commuter Congestion near Karachi Toll Plaza
            [
                'title'             => 'Heavy Traffic - Kathore to Karachi Toll Plaza',
                'category'          => 'traffic',
                'severity'          => 'info',
                'route_name'        => 'M-9',
                'carriageway'       => 'southbound',
                'carriageway_id'    => $getCarriagewayId('M-9'),
                'office_id'         => $officesM9['office_id'],
                'sector_id'         => $officesM9['sector_id'],
                'zone_id'           => $officesM9['zone_id'],
                'start_km'          => 16.0,
                'end_km'            => 26.0,
                'start_landmark'    => 'Kathore Interchange',
                'end_landmark'      => 'Karachi Main Toll Plaza',
                'visibility_meters' => 1200,
                'speed_limit_kmh'   => 40,
                'closure_type'      => null,
                'corridor_key'      => 'M9-KM016-KM026-TRAFFIC',
                'message'           => 'Heavy rush and long vehicle queues on M-9 entering Karachi near Kathore. M-Tag lanes are open. Heavy trucks must stay in the extreme left lane.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(35),
                'approved_at'       => $now->copy()->subMinutes(35),
                'expires_at'        => $now->copy()->addHours(8),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // 15. N-5 National Highway: Mixed Traffic Bottleneck near Wazirabad
            [
                'title'             => 'Slow Traffic - Wazirabad Bypass',
                'category'          => 'traffic',
                'severity'          => 'info',
                'route_name'        => 'N-5',
                'carriageway'       => 'both',
                'carriageway_id'    => $getCarriagewayId('N-5'),
                'office_id'         => $officesN5['office_id'],
                'sector_id'         => $officesN5['sector_id'],
                'zone_id'           => $officesN5['zone_id'],
                'start_km'          => 1220.0,
                'end_km'            => 1230.0,
                'start_landmark'    => 'Wazirabad Railway Crossing Approach',
                'end_landmark'      => 'Chenab River Toll Plaza',
                'visibility_meters' => 1000,
                'speed_limit_kmh'   => 45,
                'closure_type'      => null,
                'corridor_key'      => 'N5-KM1220-KM1230-TRAFFIC',
                'message'           => 'Slow traffic on GT Road near Wazirabad Bypass towards Gujrat. Drive with patience, do not break queues, and follow traffic police directions.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(40),
                'approved_at'       => $now->copy()->subMinutes(40),
                'expires_at'        => $now->copy()->addHours(8),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // =========================================================================
            // CATEGORY 5: WEATHER (3 active records)
            // =========================================================================

            // 16. N-75 (IMDC): Continuous Hill Drizzle & Fog in Murree Hills
            [
                'title'             => 'Rain Alert - Murree Expressway (N-75)',
                'category'          => 'weather',
                'severity'          => 'warning',
                'route_name'        => 'N-75',
                'carriageway'       => 'both',
                'carriageway_id'    => $getCarriagewayId('N-75'),
                'office_id'         => $officesN75['office_id'],
                'sector_id'         => $officesN75['sector_id'],
                'zone_id'           => $officesN75['zone_id'],
                'start_km'          => 18.0,
                'end_km'            => 38.0,
                'start_landmark'    => 'Barakahu Toll Plaza',
                'end_landmark'      => 'Ghora Gali Hill Section',
                'visibility_meters' => 450,
                'speed_limit_kmh'   => 50,
                'closure_type'      => null,
                'corridor_key'      => 'N75-KM018-KM038-WEATHER',
                'message'           => 'Continuous rain and slippery turns between Barakahu and Ghora Gali on Murree Expressway. Drive in low gears, keep speed below 50 km/h, and do not overtake on blind turns.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(42),
                'approved_at'       => $now->copy()->subMinutes(42),
                'expires_at'        => $now->copy()->addHours(12),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // 17. E-35 Motorway: Heavy Rainfall Havelian to Abbottabad
            [
                'title'             => 'Heavy Rain Alert - Hazara Motorway (E-35)',
                'category'          => 'weather',
                'severity'          => 'warning',
                'route_name'        => 'E-35',
                'carriageway'       => 'both',
                'carriageway_id'    => $getCarriagewayId('E-35'),
                'office_id'         => $officesE35['office_id'],
                'sector_id'         => $officesE35['sector_id'],
                'zone_id'           => $officesE35['zone_id'],
                'start_km'          => 45.0,
                'end_km'            => 80.0,
                'start_landmark'    => 'Havelian Interchange',
                'end_landmark'      => 'Abbottabad / Mansehra Tunnel Section',
                'visibility_meters' => 350,
                'speed_limit_kmh'   => 60,
                'closure_type'      => null,
                'corridor_key'      => 'E35-KM045-KM080-WEATHER',
                'message'           => 'Heavy rain between Havelian and Abbottabad on Hazara Motorway. Roads are very slippery with water puddles. Drive below 60 km/h and turn on your wipers and headlights.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(55),
                'approved_at'       => $now->copy()->subMinutes(55),
                'expires_at'        => $now->copy()->addHours(12),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // 18. M-14 Motorway: Crosswinds and Dust Gusts Hakla to Daud Khel
            [
                'title'             => 'Strong Winds Alert - Tarap to Daud Khel',
                'category'          => 'weather',
                'severity'          => 'advisory',
                'route_name'        => 'M-14',
                'carriageway'       => 'both',
                'carriageway_id'    => $getCarriagewayId('M-14'),
                'office_id'         => $officesM14['office_id'],
                'sector_id'         => $officesM14['sector_id'],
                'zone_id'           => $officesM14['zone_id'],
                'start_km'          => 110.0,
                'end_km'            => 145.0,
                'start_landmark'    => 'Tarap Interchange',
                'end_landmark'      => 'Daud Khel / Mianwali Section',
                'visibility_meters' => 250,
                'speed_limit_kmh'   => 70,
                'closure_type'      => null,
                'corridor_key'      => 'M14-KM110-KM145-WEATHER',
                'message'           => 'High dust winds between Tarap and Daud Khel on M-14. Vans and loaded trucks should slow down to 70 km/h, hold the steering wheel firmly, and avoid sudden turns.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(60),
                'approved_at'       => $now->copy()->subMinutes(60),
                'expires_at'        => $now->copy()->addHours(12),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // =========================================================================
            // CATEGORY 6: DIVERSIONS (2 active records)
            // =========================================================================

            // 19. M-2 Motorway: Planned Diversion for Flyover Construction near Sukheki
            [
                'title'             => 'Route Diversion - Sukheki (M-2)',
                'category'          => 'diversion',
                'severity'          => 'warning',
                'route_name'        => 'M-2',
                'carriageway'       => 'southbound',
                'carriageway_id'    => $getCarriagewayId('M-2'),
                'office_id'         => $officesM2South['office_id'],
                'sector_id'         => $officesM2South['sector_id'],
                'zone_id'           => $officesM2South['zone_id'],
                'start_km'          => 88.0,
                'end_km'            => 92.0,
                'start_landmark'    => 'Sukheki Service Area Approach',
                'end_landmark'      => 'Pindi Bhattian South',
                'visibility_meters' => 800,
                'speed_limit_kmh'   => 45,
                'closure_type'      => 'diversion',
                'corridor_key'      => 'M2-KM088-KM092-DIVERSION',
                'message'           => 'Southbound traffic on M-2 near Sukheki is diverted to the service road due to flyover construction. Follow diversion signs and drive below 45 km/h.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subHours(2),
                'approved_at'       => $now->copy()->subHours(2),
                'expires_at'        => $now->copy()->addDays(4),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // 20. M-5 Motorway: Contra-Flow Diversion near Uch Sharif Interchange
            [
                'title'             => 'Route Diversion - Uch Sharif (M-5)',
                'category'          => 'diversion',
                'severity'          => 'warning',
                'route_name'        => 'M-5',
                'carriageway'       => 'both',
                'carriageway_id'    => $getCarriagewayId('M-5'),
                'office_id'         => $officesM5['office_id'],
                'sector_id'         => $officesM5['sector_id'],
                'zone_id'           => $officesM5['zone_id'],
                'start_km'          => 540.0,
                'end_km'            => 545.0,
                'start_landmark'    => 'Uch Sharif Interchange',
                'end_landmark'      => 'Taranda Muhammad Panah Approach',
                'visibility_meters' => 1000,
                'speed_limit_kmh'   => 50,
                'closure_type'      => 'diversion',
                'corridor_key'      => 'M5-KM540-KM545-DIVERSION',
                'message'           => 'Traffic near Uch Sharif on M-5 is diverted to a single track in two-way mode due to bridge work. Speed limit is 50 km/h. Overtaking is strictly forbidden.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subHours(3),
                'approved_at'       => $now->copy()->subHours(3),
                'expires_at'        => $now->copy()->addDays(4),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],

            // =========================================================================
            // CATEGORY 7: PENDING APPROVAL ADVISORIES (2 records for Supervisor Queue)
            // =========================================================================

            // 21. M-2 Motorway: Pending Review Smog Advisory near Bhera
            [
                'title'             => 'Smog Advisory - Bhera Service Area',
                'category'          => 'fog',
                'severity'          => 'warning',
                'route_name'        => 'M-2',
                'carriageway'       => 'both',
                'carriageway_id'    => $getCarriagewayId('M-2'),
                'office_id'         => $officesM2North['office_id'],
                'sector_id'         => $officesM2North['sector_id'],
                'zone_id'           => $officesM2North['zone_id'],
                'start_km'          => 197.0,
                'end_km'            => 202.0,
                'start_landmark'    => 'Bhera Service Area',
                'end_landmark'      => 'River Jehlum Bridge',
                'visibility_meters' => 75,
                'speed_limit_kmh'   => 60,
                'closure_type'      => null,
                'corridor_key'      => 'M2-KM197-KM202-PENDING-FOG',
                'message'           => 'Winter smog reported near Bhera Service Area. Visibility is around 75 meters. Drive with low beams and keep safe distance from other cars.',
                'status'            => 'pending_approval',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => null,
                'approved_at'       => null,
                'expires_at'        => $now->copy()->addHours(12),
                'created_by'        => $adminId,
                'approved_by'       => null,
            ],

            // 22. M-4 Motorway: Pending Review Maintenance Lane Closure near Shorkot
            [
                'title'             => 'Road Work - Shorkot Interchange',
                'category'          => 'road_works',
                'severity'          => 'advisory',
                'route_name'        => 'M-4',
                'carriageway'       => 'southbound',
                'carriageway_id'    => $getCarriagewayId('M-4'),
                'office_id'         => $officesM4['office_id'],
                'sector_id'         => $officesM4['sector_id'],
                'zone_id'           => $officesM4['zone_id'],
                'start_km'          => 180.0,
                'end_km'            => 185.0,
                'start_landmark'    => 'Shorkot Interchange',
                'end_landmark'      => 'Abdul Hakeem Junction Approach',
                'visibility_meters' => 1200,
                'speed_limit_kmh'   => 70,
                'closure_type'      => 'shoulder',
                'corridor_key'      => 'M4-KM180-KM185-PENDING-WORKS',
                'message'           => 'Maintenance work on the road shoulder near Shorkot Interchange. Main lanes are open. Reduce speed to 70 km/h near work machinery.',
                'status'            => 'pending_approval',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => null,
                'approved_at'       => null,
                'expires_at'        => $now->copy()->addDays(4),
                'created_by'        => $adminId,
                'approved_by'       => null,
            ],

            // =========================================================================
            // CATEGORY 8: RESOLVED ARCHIVE (1 record for Archive tab)
            // =========================================================================

            // 23. N-5 National Highway: Resolved Obstruction near Okara
            [
                'title'             => 'Resolved: Hazard Cleared - Okara Bypass',
                'category'          => 'traffic',
                'severity'          => 'advisory',
                'route_name'        => 'N-5',
                'carriageway'       => 'both',
                'carriageway_id'    => $getCarriagewayId('N-5'),
                'office_id'         => $officesN5['office_id'],
                'sector_id'         => $officesN5['sector_id'],
                'zone_id'           => $officesN5['zone_id'],
                'start_km'          => 1080.0,
                'end_km'            => 1082.0,
                'start_landmark'    => 'Okara Bypass North',
                'end_landmark'      => 'Renala Khurd Approach',
                'visibility_meters' => 1500,
                'speed_limit_kmh'   => 90,
                'closure_type'      => null,
                'corridor_key'      => 'N5-KM1080-KM1082-RESOLVED',
                'message'           => 'Cattle cleared off the road near Okara Bypass by Motorway Police. Traffic is running normally.',
                'status'            => 'resolved',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subHours(6),
                'approved_at'       => $now->copy()->subHours(6),
                'expires_at'        => $now->copy()->subHours(2),
                'created_by'        => $adminId,
                'approved_by'       => $adminId,
            ],
        ];

        foreach ($advisories as $advData) {
            // Automatically resolve exact physical GPS coordinates from Carriageway markers
            [$startLat, $startLng] = $resolveGps($advData['route_name'], (float)$advData['start_km']);
            [$endLat, $endLng]     = $resolveGps($advData['route_name'], (float)$advData['end_km']);

            $advData['start_lat'] = $startLat;
            $advData['start_lng'] = $startLng;
            $advData['end_lat']   = $endLat;
            $advData['end_lng']   = $endLng;
            $advData['uuid']      = (string) Str::uuid();

            TabletAdvisory::create($advData);
        }

        TabletAdvisory::flushAdvisoryCache();
        Cache::flush();
    }
}
