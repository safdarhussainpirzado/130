<?php

namespace Database\Seeders;

use App\Models\SurveyCategory;
use App\Models\SurveyService;
use Illuminate\Database\Seeder;

/** 8 generic, admin-editable survey categories with services. Idempotent. */
class SurveyCategorySeeder extends Seeder
{
    public const CATALOG = [
        ['Medical', 'fa-solid fa-hospital', ['Hospital', 'Clinic', 'Pharmacy', 'Laboratory', 'Emergency', 'OPD', 'ICU', 'Ambulance', 'Maternity']],
        ['Service / Rest Area', 'fa-solid fa-square-parking', ['Parking', 'Washrooms', 'Prayer area', 'Food court', 'Drinking water', 'Fuel']],
        ['Fuel & EV Charging', 'fa-solid fa-gas-pump', ['Petrol', 'Diesel', 'CNG', 'EV fast charging', 'EV standard charging']],
        ['Food & Refreshment', 'fa-solid fa-utensils', ['Restaurant', 'Cafe', 'Fast food', 'Takeaway', '24-hour service']],
        ['Vehicle Workshop & Towing', 'fa-solid fa-screwdriver-wrench', ['Mechanical', 'Electrical', 'Battery', 'Tyre / Puncture', 'Towing & Recovery', 'Roadside assistance']],
        ['Accommodation', 'fa-solid fa-bed', ['Hotel', 'Motel', 'Guest house', 'Parking']],
        ['Public Amenities', 'fa-solid fa-mosque', ['Mosque / Prayer', 'Public restroom', 'ATM / Banking', 'Grocery / Convenience', 'Parking']],
        ['Police & Rescue', 'fa-solid fa-shield-halved', ['Police post', 'Rescue 1122', 'Highway patrol point']],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $i => [$name, $icon, $services]) {
            $cat = SurveyCategory::updateOrCreate(['name' => $name], ['icon' => $icon, 'sort_order' => $i + 1, 'is_active' => true]);
            foreach ($services as $j => $svc) {
                SurveyService::firstOrCreate(['category_id' => $cat->id, 'name' => $svc], ['sort_order' => $j + 1, 'is_active' => true]);
            }
        }
    }
}
