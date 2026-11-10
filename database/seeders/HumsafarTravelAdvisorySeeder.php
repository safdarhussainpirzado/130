<?php

namespace Database\Seeders;

use App\Models\Carriageway;
use App\Models\Office;
use App\Models\TabletAdvisory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HumsafarTravelAdvisorySeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $adminUser = User::where('role_id', 1)->first() ?? User::first();
        $adminId = $adminUser ? $adminUser->id : 1;

        // Lookup Carriageways
        $m2 = Carriageway::where('road', 'M2')->orWhere('road_short', 'M-2')->first();
        $m1 = Carriageway::where('road', 'M1')->orWhere('road_short', 'M-1')->first();
        $m3 = Carriageway::where('road', 'M3')->orWhere('road_short', 'M-3')->first();
        $m4 = Carriageway::where('road', 'M4')->orWhere('road_short', 'M-4')->first();
        $m5 = Carriageway::where('road', 'M5')->orWhere('road_short', 'M-5')->first();
        $n5 = Carriageway::where('road', 'N5')->orWhere('road_short', 'N-5')->first();

        // Offices (Offices: Zone, Sector, Beat)
        $beatKallar = Office::where('name', 'LIKE', '%Beat%')->where('name', 'LIKE', '%49%')->first()
            ?? Office::where('name', 'LIKE', '%Kallar%')->first()
            ?? Office::where('type', 'beat')->first();

        $beatBhera = Office::where('name', 'LIKE', '%Beat%')->where('name', 'LIKE', '%50%')->first()
            ?? Office::where('name', 'LIKE', '%Bhera%')->first()
            ?? $beatKallar;

        $beatRavi = Office::where('name', 'LIKE', '%Beat%')->where('name', 'LIKE', '%65%')->first()
            ?? Office::where('name', 'LIKE', '%Ravi%')->first()
            ?? $beatKallar;

        $advisories = [
            // 1. EXACT MOCKUP ITEM 1: Fog & visibility - Reduced visibility on M-2 Kallar Kahar to Bhera
            [
                'title'             => 'Reduced visibility',
                'category'          => 'fog',
                'advisory_type'     => 'fog',
                'severity'          => 'warning',
                'route_name'        => 'M-2',
                'carriageway'       => 'both',
                'carriageway_id'    => $m2?->id,
                'office_id'         => $beatKallar?->id,
                'sector_id'         => $beatKallar?->parent_id,
                'zone_id'           => $beatKallar?->parent?->parent_id,
                'start_km'          => 210.0,
                'end_km'            => 245.0,
                'start_landmark'    => 'Kallar Kahar Interchange',
                'end_landmark'      => 'Bhera Service Area',
                'visibility_meters' => 35,
                'speed_limit_kmh'   => 50,
                'closure_type'      => null,
                'corridor_key'      => 'M2-KM210-KM245-FOG',
                'start_lat'         => 32.5756071,
                'start_lng'         => 72.8017680,
                'end_lat'           => 32.1879025,
                'end_lng'           => 73.0370925,
                'message'           => 'Fog advisory for the selected section. Dense fog patches in the Salt Range and low lying canal sections. Keep low beams on, maintain safe headway, and follow convoy speed guidance.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(10),
                'expires_at'        => $now->copy()->addHours(6),
                'created_by'        => $adminId,
            ],

            // 2. EXACT MOCKUP ITEM 2: Road works - Lane restrictions Near Kallar Kahar
            [
                'title'             => 'Lane restrictions',
                'category'          => 'maintenance',
                'advisory_type'     => 'road_works',
                'severity'          => 'advisory',
                'route_name'        => 'M-2',
                'carriageway'       => 'northbound', // Islamabad-bound
                'carriageway_id'    => $m2?->id,
                'office_id'         => $beatKallar?->id,
                'sector_id'         => $beatKallar?->parent_id,
                'zone_id'           => $beatKallar?->parent?->parent_id,
                'start_km'          => 221.0,
                'end_km'            => 224.0,
                'start_landmark'    => 'Near Kallar Kahar Salt Range Descent',
                'end_landmark'      => 'Balkasar Approach',
                'visibility_meters' => 800,
                'speed_limit_kmh'   => 60,
                'closure_type'      => 'lane_1',
                'corridor_key'      => 'M2-KM221-KM224-WORKS',
                'start_lat'         => 32.7845100,
                'start_lng'         => 72.7089200,
                'end_lat'           => 32.8120000,
                'end_lng'           => 72.6950000,
                'message'           => 'Follow lane signs through the work zone. Surface expansion joint rehabilitation on Bridge #14. Right and center lanes open; slow lane restricted. Use caution.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(20),
                'expires_at'        => $now->copy()->addDays(2),
                'created_by'        => $adminId,
            ],

            // 3. EXACT MOCKUP ITEM 3: Traffic - Slow-moving traffic on M-2 Lahore approaches
            [
                'title'             => 'Slow-moving traffic',
                'category'          => 'congestion',
                'advisory_type'     => 'traffic',
                'severity'          => 'info',
                'route_name'        => 'M-2',
                'carriageway'       => 'southbound', // Lahore-bound
                'carriageway_id'    => $m2?->id,
                'office_id'         => $beatRavi?->id,
                'sector_id'         => $beatRavi?->parent_id,
                'zone_id'           => $beatRavi?->parent?->parent_id,
                'start_km'          => 1.0,
                'end_km'            => 12.0,
                'start_landmark'    => 'Faizpur Interchange',
                'end_landmark'      => 'Ravi Toll Plaza (Lahore Approach)',
                'visibility_meters' => 600,
                'speed_limit_kmh'   => 40,
                'closure_type'      => null,
                'corridor_key'      => 'M2-KM001-KM012-TRAFFIC',
                'start_lat'         => 31.5540000,
                'start_lng'         => 74.1950000,
                'end_lat'           => 31.4663225,
                'end_lng'           => 74.2291966,
                'message'           => 'Allow extra time near the exit. Peak hour commuter volume at Ravi Toll Plaza. 14 electronic e-tag booths active. Normal flow anticipated shortly.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'sector',
                'published_at'      => $now->copy()->subMinutes(25),
                'expires_at'        => $now->copy()->addHours(2),
                'created_by'        => $adminId,
            ],

            // 4. M-1 Motorway: Fog & Visibility (Swabi - Burhan)
            [
                'title'             => 'Patchy fog advisory',
                'category'          => 'fog',
                'advisory_type'     => 'fog',
                'severity'          => 'warning',
                'route_name'        => 'M-1',
                'carriageway'       => 'both',
                'carriageway_id'    => $m1?->id,
                'office_id'         => null,
                'sector_id'         => null,
                'zone_id'           => null,
                'start_km'          => 45.0,
                'end_km'            => 75.0,
                'start_landmark'    => 'Swabi Interchange',
                'end_landmark'      => 'Burhan Interchange',
                'visibility_meters' => 50,
                'speed_limit_kmh'   => 60,
                'closure_type'      => null,
                'corridor_key'      => 'M1-KM045-KM075-FOG',
                'start_lat'         => 34.0200000,
                'start_lng'         => 72.4500000,
                'end_lat'           => 33.8180000,
                'end_lng'           => 72.7100000,
                'message'           => 'Indus River basin fog impacting visibility between Swabi and Burhan. Switch on fog lights, avoid overtaking.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'sector',
                'published_at'      => $now->copy()->subMinutes(45),
                'expires_at'        => $now->copy()->addHours(4),
                'created_by'        => $adminId,
            ],

            // 5. M-3 Motorway: Dense Fog & Diversion (Samundri - Rajana)
            [
                'title'             => 'Zero visibility closure & diversion',
                'category'          => 'road_closure',
                'advisory_type'     => 'road_closure',
                'severity'          => 'critical',
                'route_name'        => 'M-3',
                'carriageway'       => 'both',
                'carriageway_id'    => $m3?->id,
                'office_id'         => null,
                'sector_id'         => null,
                'zone_id'           => null,
                'start_km'          => 1016.0,
                'end_km'            => 966.0,
                'start_landmark'    => 'Samundri Interchange (Exit 5)',
                'end_landmark'      => 'Rajana Interchange',
                'visibility_meters' => 15,
                'speed_limit_kmh'   => 0,
                'closure_type'      => 'full',
                'corridor_key'      => 'M3-KM1016-KM966-CLOSURE',
                'start_lat'         => 31.0308,
                'start_lng'         => 73.0190,
                'end_lat'           => 30.8388,
                'end_lng'           => 72.5540,
                'message'           => 'Dense smog and fog envelope. Visibility critically drop to 15m. M-3 closed for public safety between Samundri and Rajana. Traffic diverted to alternate regional routes.',
                'status'            => 'published',
                'is_emergency'      => true,
                'reported_by_tier'  => 'zone',
                'published_at'      => $now->copy()->subMinutes(15),
                'expires_at'        => $now->copy()->addHours(5),
                'created_by'        => $adminId,
            ],

            // 6. M-4 Motorway: Smog & Weather Caution (Faisalabad - Gojra)
            [
                'title'             => 'Smog and air quality caution',
                'category'          => 'weather',
                'advisory_type'     => 'weather',
                'severity'          => 'advisory',
                'route_name'        => 'M-4',
                'carriageway'       => 'both',
                'carriageway_id'    => $m4?->id,
                'office_id'         => null,
                'sector_id'         => null,
                'zone_id'           => null,
                'start_km'          => 40.0,
                'end_km'            => 75.0,
                'start_landmark'    => 'Faisalabad West Interchange',
                'end_landmark'      => 'Gojra Interchange',
                'visibility_meters' => 90,
                'speed_limit_kmh'   => 70,
                'closure_type'      => null,
                'corridor_key'      => 'M4-KM040-KM075-SMOG',
                'start_lat'         => 31.4500000,
                'start_lng'         => 73.0200000,
                'end_lat'           => 31.1500000,
                'end_lng'           => 72.6800000,
                'message'           => 'Severe industrial and agricultural smog. Reduced atmospheric visibility. Maintain low beam headlights and wear protective masks inside vehicles.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'sector',
                'published_at'      => $now->copy()->subHours(1),
                'expires_at'        => $now->copy()->addHours(8),
                'created_by'        => $adminId,
            ],

            // 7. M-5 Motorway: Road Works & Lane closure (Rohri to Pano Aqil)
            [
                'title'             => 'Emergency pavement rehabilitation',
                'category'          => 'maintenance',
                'advisory_type'     => 'road_works',
                'severity'          => 'advisory',
                'route_name'        => 'M-5',
                'carriageway'       => 'southbound',
                'carriageway_id'    => $m5?->id,
                'office_id'         => null,
                'sector_id'         => null,
                'zone_id'           => null,
                'start_km'          => 545.0,
                'end_km'            => 552.0,
                'start_landmark'    => 'Pano Aqil Interchange',
                'end_landmark'      => 'Rohri Toll Plaza',
                'visibility_meters' => 1000,
                'speed_limit_kmh'   => 50,
                'closure_type'      => 'lane_2',
                'corridor_key'      => 'M5-KM545-KM552-WORKS',
                'start_lat'         => 27.8500000,
                'start_lng'         => 69.1100000,
                'end_lat'           => 27.6900000,
                'end_lng'           => 68.9100000,
                'message'           => 'Fast lane closed for asphalt resurfacing. High visibility cautionary cones and blinkers installed. Comply with posted speed limits.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subHours(2),
                'expires_at'        => $now->copy()->addDays(3),
                'created_by'        => $adminId,
            ],

            // 8. N-5 National Highway: Diversion at Muridke Bridge
            [
                'title'             => 'Bridge repair diversion',
                'category'          => 'road_closure',
                'advisory_type'     => 'diversion',
                'severity'          => 'warning',
                'route_name'        => 'N-5',
                'carriageway'       => 'both',
                'carriageway_id'    => $n5?->id,
                'office_id'         => null,
                'sector_id'         => null,
                'zone_id'           => null,
                'start_km'          => 1268.0,
                'end_km'            => 1272.0,
                'start_landmark'    => 'Muridke Bypass',
                'end_landmark'      => 'Kala Shah Kaku Flyover',
                'visibility_meters' => 400,
                'speed_limit_kmh'   => 30,
                'closure_type'      => 'diversion',
                'corridor_key'      => 'N5-KM1268-KM1272-DIVERSION',
                'start_lat'         => 31.8000000,
                'start_lng'         => 74.2500000,
                'end_lat'           => 31.7200000,
                'end_lng'           => 74.2700000,
                'message'           => 'Canal bridge expansion joint retrofitting. Heavy transport diverted via bypass slip roads. Police marshals deployed at both approaches.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'sector',
                'published_at'      => $now->copy()->subMinutes(50),
                'expires_at'        => $now->copy()->addDays(1),
                'created_by'        => $adminId,
            ],

            // 9. M-9 Motorway: Dense Fog & Smog Alert (Nooriabad to Thana Bula Khan)
            [
                'title'             => 'Dense fog & smog alert',
                'category'          => 'fog',
                'advisory_type'     => 'fog',
                'severity'          => 'warning',
                'route_name'        => 'M-9',
                'carriageway'       => 'both',
                'carriageway_id'    => Carriageway::where('road', 'M9')->orWhere('road_short', 'M-9')->value('id'),
                'office_id'         => null,
                'sector_id'         => null,
                'zone_id'           => null,
                'start_km'          => 68.0,
                'end_km'            => 85.0,
                'start_landmark'    => 'Nooriabad Industrial Estate',
                'end_landmark'      => 'Thana Bula Khan Interchange',
                'visibility_meters' => 45,
                'speed_limit_kmh'   => 50,
                'closure_type'      => null,
                'corridor_key'      => 'M9-KM068-KM085-FOG',
                'start_lat'         => 25.1700000,
                'start_lng'         => 68.0100000,
                'end_lat'           => 25.2600000,
                'end_lng'           => 68.1200000,
                'message'           => 'Dense early-morning fog & industrial smog between Nooriabad and Thana Bula Khan. Visibility drops below 50 meters. Use fog lamps and maintain convoy distance.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subMinutes(25),
                'expires_at'        => $now->copy()->addHours(6),
                'created_by'        => $adminId,
            ],

            // 10. M-9 Motorway: Road Works & Lane Restriction near Kathore
            [
                'title'             => 'Lane 1 maintenance works',
                'category'          => 'maintenance',
                'advisory_type'     => 'road_works',
                'severity'          => 'advisory',
                'route_name'        => 'M-9',
                'carriageway'       => 'northbound',
                'carriageway_id'    => Carriageway::where('road', 'M9')->orWhere('road_short', 'M-9')->value('id'),
                'office_id'         => null,
                'sector_id'         => null,
                'zone_id'           => null,
                'start_km'          => 32.0,
                'end_km'            => 36.0,
                'start_landmark'    => 'Near Kathore Interchange',
                'end_landmark'      => 'Bahria Town Karachi Gate',
                'visibility_meters' => 700,
                'speed_limit_kmh'   => 60,
                'closure_type'      => 'lane_1',
                'corridor_key'      => 'M9-KM032-KM036-WORKS',
                'start_lat'         => 25.0900000,
                'start_lng'         => 67.4500000,
                'end_lat'           => 25.0600000,
                'end_lng'           => 67.3400000,
                'message'           => 'Slow lane restricted for pavement joint repair. Middle and overtaking lanes are clear. Observe cautionary signs and reduce speed.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'beat',
                'published_at'      => $now->copy()->subHours(1),
                'expires_at'        => $now->copy()->addDays(2),
                'created_by'        => $adminId,
            ],

            // 11. M-9 Motorway: Traffic Congestion at Karachi Entry
            [
                'title'             => 'Heavy traffic at entry toll plaza',
                'category'          => 'congestion',
                'advisory_type'     => 'traffic',
                'severity'          => 'info',
                'route_name'        => 'M-9',
                'carriageway'       => 'southbound',
                'carriageway_id'    => Carriageway::where('road', 'M9')->orWhere('road_short', 'M-9')->value('id'),
                'office_id'         => null,
                'sector_id'         => null,
                'zone_id'           => null,
                'start_km'          => 2.0,
                'end_km'            => 6.0,
                'start_landmark'    => 'Karachi Sohrab Goth Toll Plaza',
                'end_landmark'      => 'Al-Asif Square Karachi',
                'visibility_meters' => 1000,
                'speed_limit_kmh'   => 40,
                'closure_type'      => null,
                'corridor_key'      => 'M9-KM002-KM006-TRAFFIC',
                'start_lat'         => 24.9575000,
                'start_lng'         => 67.0980000,
                'end_lat'           => 24.9450000,
                'end_lng'           => 67.0850000,
                'message'           => 'High traffic density at Sohrab Goth toll plaza approaches due to inbound freight and intercity buses. Electronic M-Tag booths operating normally.',
                'status'            => 'published',
                'is_emergency'      => false,
                'reported_by_tier'  => 'sector',
                'published_at'      => $now->copy()->subMinutes(15),
                'expires_at'        => $now->copy()->addHours(3),
                'created_by'        => $adminId,
            ],
        ];

        foreach ($advisories as $advData) {
            $existing = TabletAdvisory::where('corridor_key', $advData['corridor_key'])->first();
            if ($existing) {
                $existing->update($advData);
            } else {
                $advData['uuid'] = (string) Str::uuid();
                TabletAdvisory::create($advData);
            }
        }

        TabletAdvisory::flushAdvisoryCache();
    }
}
