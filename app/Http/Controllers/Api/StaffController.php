<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;
use App\Models\Attendance;
use App\Models\LeaveType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use OpenAI\Laravel\Facades\OpenAI;
use App\Services\Admin\AiFilterService;
use App\Services\EmbeddingService;
use App\Models\JobApplication;
use App\Models\Job;
use App\Models\Salary;
use App\Models\SubscriptionUser;
use App\Models\Subscription;
use Illuminate\Support\Facades\Schema;
use App\Models\UserWorkInfo;
use App\Models\Termination;
use App\Models\UserAddress;
use Illuminate\Support\Facades\DB;


class StaffController extends Controller
{
    private function normalizeStaffSearchText($queryText)
    {
        $queryText = trim((string) $queryText);
        $queryText = function_exists('mb_strtolower')
            ? mb_strtolower($queryText, 'UTF-8')
            : strtolower($queryText);

        $replacements = [
            '/\bhouse\s+keeper\b/' => 'housekeeper',
            '/\bhouse\s+keeping\b/' => 'housekeeping',
            '/\bbaby\s+sitter\b/' => 'babysitter',
            '/\bdog\s+walking\b/' => 'dog walker',
            '/\bpet\s+care\b/' => 'pet caretaker',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $queryText = preg_replace($pattern, $replacement, $queryText);
        }

        return preg_replace('/\s+/', ' ', $queryText);
    }

    private function roleAliases(): array
    {
        return [
            'driver' => ['driver', 'driving', 'chauffeur', 'ड्राइवर', 'चालक', 'డ్రైవర్', 'డ్రైవింగ్'],
            'cook' => ['cook', 'chef', 'cooking', 'kitchen', 'baker', 'रसोइया', 'बावर्ची', 'कुक', 'शेफ', 'వంటవాడు', 'వంటమనిషి', 'కుక్', 'చెఫ్'],
            'maid' => ['maid', 'cleaner', 'house cleaner', 'cleaning', 'नौकरानी', 'कामवाली', 'मेड', 'सफाई कर्मचारी', 'పనిమనిషి', 'మెయిడ్', 'క్లీనర్'],
            'nanny' => ['nanny', 'babysitter', 'baby sitter', 'childcare', 'आया', 'नैनी', 'बच्चों की देखभाल', 'ఆయా', 'నానీ', 'బేబీసిట్టర్'],
            'housekeeper' => ['housekeeper', 'housekeeping', 'house keeper', 'हाउसकीपर', 'गृह सहायक', 'హౌస్ కీపర్', 'గృహ సహాయకుడు'],
            'gardener' => ['gardener', 'gardening', 'माली', 'తోటమాలి'],
            'security' => ['security', 'guard', 'watchman', 'सुरक्षा गार्ड', 'चौकीदार', 'गार्ड', 'సెక్యూరిటీ', 'గార్డు', 'కాపలాదారు'],
            'nurse' => ['nurse', 'nursing', 'caretaker', 'नर्स', 'నర్సు', 'నర్స్'],
            'tutor' => ['tutor', 'teacher', 'teaching', 'ट्यूटर', 'शिक्षक', 'ట్యూటర్', 'ఉపాధ్యాయుడు'],
            'plumber' => ['plumber', 'plumbing', 'प्लंबर', 'ప్లంబర్'],
            'electrician' => ['electrician', 'electrical', 'इलेक्ट्रीशियन', 'बिजली मिस्त्री', 'ఎలక్ట్రీషియన్'],
            'carpenter' => ['carpenter', 'carpentry', 'बढ़ई', 'कारपेंटर', 'వడ్రంగి', 'కార్పెంటర్'],
            'painter' => ['painter', 'painting', 'पेंटर', 'పెయింటర్'],
            'sweeper' => ['sweeper', 'sweeping', 'स्वीपर', 'सफाईवाला', 'స్వీపర్'],
            'laundry' => ['laundry', 'washing', 'ironing', 'लॉन्ड्री', 'धोबी', 'లాండ్రీ', 'బట్టలు ఉతికే'],
            'dog walker' => ['dog walker', 'dog walking', 'pet walker', 'डॉग वॉकर', 'డాగ్ వాకర్', 'కుక్కను నడిపే'],
            'attendant' => ['attendant', 'helper', 'assistant', 'care attendant', 'अटेंडेंट', 'सहायक', 'అటెండెంట్', 'హెల్పర్'],
            'pet caretaker' => ['pet caretaker', 'pet care', 'pet sitter', 'animal care', 'पेट केयरटेकर', 'जानवरों की देखभाल', 'పెట్ కేర్‌టేకర్', 'జంతు సంరక్షకుడు'],
        ];
    }

    private function cityAliases(): array
    {
        return [
            'vizag' => ['visakhapatnam', 'vizag', 'waltair'],
            'visakhapatnam' => ['visakhapatnam', 'vizag', 'waltair'],
            'waltair' => ['visakhapatnam', 'vizag', 'waltair'],
            'bangalore' => ['bangalore', 'bengaluru', 'bengalooru'],
            'bengaluru' => ['bangalore', 'bengaluru', 'bengalooru'],
            'mumbai' => ['mumbai', 'bombay'],
            'bombay' => ['mumbai', 'bombay'],
            'chennai' => ['chennai', 'madras'],
            'madras' => ['chennai', 'madras'],
            'kolkata' => ['kolkata', 'calcutta'],
            'calcutta' => ['kolkata', 'calcutta'],
            'hyderabad' => ['hyderabad', 'secunderabad'],
            'secunderabad' => ['hyderabad', 'secunderabad'],
            'pune' => ['pune', 'poona'],
            'poona' => ['pune', 'poona'],
            'coimbatore' => ['coimbatore', 'kovai'],
            'kovai' => ['coimbatore', 'kovai'],
            'trivandrum' => ['trivandrum', 'thiruvananthapuram'],
            'thiruvananthapuram' => ['trivandrum', 'thiruvananthapuram'],
            'trichy' => ['trichy', 'tiruchirappalli'],
            'tiruchirappalli' => ['trichy', 'tiruchirappalli'],
            'madurai' => ['madurai', 'thoondamani'],
            'jaipur' => ['jaipur', 'pink city'],
            'agra' => ['agra', 'taj city'],
            'lucknow' => ['lucknow', 'lucknau'],
            'kanpur' => ['kanpur', 'cawnpore'],
            'nagpur' => ['nagpur', 'orange city'],
            'indore' => ['indore', 'indoor'],
            'bhopal' => ['bhopal', 'city of lakes'],
            'patna' => ['patna', 'patliputra'],
            'varanasi' => ['varanasi', 'banaras', 'kashi'],
            'banaras' => ['varanasi', 'banaras', 'kashi'],
            'kashi' => ['varanasi', 'banaras', 'kashi'],
            'allahabad' => ['allahabad', 'prayagraj'],
            'prayagraj' => ['allahabad', 'prayagraj'],
            'udaipur' => ['udaipur', 'city of lakes rajasthan'],
            'jodhpur' => ['jodhpur', 'sun city'],
            'ahmedabad' => ['ahmedabad', 'amdavad'],
            'amdavad' => ['ahmedabad', 'amdavad'],
            'surat' => ['surat', 'diamond city'],
            'rajkot' => ['rajkot'],
            'vadodara' => ['vadodara', 'baroda'],
            'baroda' => ['vadodara', 'baroda'],
            'goa' => ['goa', 'panaji', 'panjim'],
            'panaji' => ['goa', 'panaji', 'panjim'],
            'chandigarh' => ['chandigarh'],
            'ludhiana' => ['ludhiana'],
            'amritsar' => ['amritsar'],
            'dehradun' => ['dehradun', 'dehra'],
            'rishikesh' => ['rishikesh', 'hrishikesh'],
            ' shimla' => ['shimla', 'simla'],
            'simla' => ['shimla', 'simla'],
            'guwahati' => ['guwahati', 'gauhati'],
            'shillong' => ['shillong'],
            'imphal' => ['imphal'],
            'bhubaneswar' => ['bhubaneswar', 'bhubaneswar'],
            'cuttack' => ['cuttack', 'katak'],
            'rourkela' => ['rourkela'],
            'vijayawada' => ['vijayawada', 'bezwada'],
            'guntur' => ['guntur'],
            'tirupati' => ['tirupati', 'tirumala'],
            'nellore' => ['nellore', 'nelluru'],
            'kurnool' => ['kurnool'],
            'kadapa' => ['kadapa', 'cuddapah'],
            'cuddapah' => ['kadapa', 'cuddapah'],
            'warangal' => ['warangal', 'orangal'],
            'karimnagar' => ['karimnagar'],
            'nizamabad' => ['nizamabad'],
            'mahbubnagar' => ['mahbubnagar'],
            'adilabad' => ['adilabad'],
            'khammam' => ['khammam'],
            'nalgonda' => ['nalgonda'],
            'medak' => ['medak'],
            'nalgonda' => ['nalgonda'],
            'ongole' => ['ongole'],
            'chittoor' => ['chittoor'],
            'anantapur' => ['anantapur'],
            'east godavari' => ['east godavari', 'rajamahendravaram'],
            'rajamahendravaram' => ['rajamahendravaram', 'rajahmundry'],
            'rajahmundry' => ['rajamahendravaram', 'rajahmundry'],
            'west godavari' => ['west godavari', 'narsapur'],
            'krishna' => ['krishna', 'machilipatnam'],
            'guntur' => ['guntur'],
            'prakasam' => ['prakasam', 'ongole'],
            'srikakulam' => ['srikakulam'],
            'vizianagaram' => ['vizianagaram'],
        ];
    }

    private function expandLocationWithAliases(string $location): array
    {
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower(trim($location), 'UTF-8')
            : strtolower(trim($location));

        $aliases = $this->cityAliases();

        if (isset($aliases[$normalized])) {
            return array_unique($aliases[$normalized]);
        }

        foreach ($aliases as $canonical => $aliasList) {
            if (in_array($normalized, $aliasList, true)) {
                return array_unique($aliasList);
            }
        }

        return [$normalized];
    }

    private function detectCanonicalRole($text): ?string
    {
        $normalized = $this->normalizeStaffSearchText($text);
        $roleCandidates = [];

        foreach ($this->roleAliases() as $role => $aliases) {
            foreach ($aliases as $alias) {
                $roleCandidates[] = ['role' => $role, 'alias' => $alias];
            }
        }

        usort($roleCandidates, function ($left, $right) {
            return strlen($right['alias']) <=> strlen($left['alias']);
        });

        foreach ($roleCandidates as $candidate) {
            if (strpos($normalized, $candidate['alias']) !== false) {
                return $candidate['role'];
            }
        }

        return null;
    }

    private function applyStaffRoleFilter($query, $role)
    {
        $canonicalRole = $this->detectCanonicalRole($role)
            ?? $this->normalizeStaffSearchText($role);
        $aliases = $this->roleAliases()[$canonicalRole] ?? [$canonicalRole];

        return $query->whereHas('userWorkInfo', function ($workQuery) use ($aliases) {
            $workQuery->where(function ($roleQuery) use ($aliases) {
                foreach ($aliases as $alias) {
                    $roleQuery->orWhereRaw(
                        'LOWER(primary_role) LIKE ?',
                        ['%' . strtolower($alias) . '%']
                    );
                }
            });
        });
    }

    private function applyJobRoleFilter($query, $role)
    {
        $canonicalRole = $this->detectCanonicalRole($role)
            ?? $this->normalizeStaffSearchText($role);
        $aliases = $this->roleAliases()[$canonicalRole] ?? [$canonicalRole];

        return $query->where(function ($roleQuery) use ($aliases) {
            foreach ($aliases as $alias) {
                $roleQuery->orWhere('title', 'like', '%' . $alias . '%')
                    ->orWhere('description', 'like', '%' . $alias . '%')
                    ->orWhere('required_skills', 'like', '%' . $alias . '%');
            }
        });
    }

    private function applyStaffLocationFilter($query, $location)
    {
        $location = trim(preg_replace('/\s+/u', ' ', (string) $location), " \t\n\r\0\x0B,.;");
        if ($location === '') {
            return $query;
        }

        $searchTerms = $this->expandLocationWithAliases($location);

        return $query->where(function ($locationQuery) use ($searchTerms) {
            $locationQuery->whereHas('addresses', function ($addressQuery) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $addressQuery->orWhere('city', 'like', '%' . $term . '%')
                        ->orWhere('state', 'like', '%' . $term . '%')
                        ->orWhere('area_locality', 'like', '%' . $term . '%')
                        ->orWhere('street', 'like', '%' . $term . '%')
                        ->orWhere('pincode', 'like', '%' . $term . '%')
                        ->orWhere('google_location', 'like', '%' . $term . '%');
                }
            })->orWhereHas('userWorkInfo', function ($workQuery) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $workQuery->orWhere('preferred_work_location', 'like', '%' . $term . '%');
                }
                $workQuery->orWhere('preferred_work_location', 'like', '%all india%');
                $workQuery->orWhere('preferred_work_location', 'like', '%anywhere%');
                $workQuery->orWhere('preferred_work_location', 'like', '%pan india%');
            })->orWhere(function ($userQuery) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $userQuery->orWhere('location', 'like', '%' . $term . '%')
                        ->orWhere('current_city', 'like', '%' . $term . '%')
                        ->orWhere('current_state', 'like', '%' . $term . '%')
                        ->orWhere('current_street', 'like', '%' . $term . '%')
                        ->orWhere('current_pincode', 'like', '%' . $term . '%')
                        ->orWhere('area_locality', 'like', '%' . $term . '%')
                        ->orWhere('google_location', 'like', '%' . $term . '%');
                }
            });
        });
    }

    private function applyJobLocationFilter($query, $location)
    {
        $location = trim(preg_replace('/\s+/u', ' ', (string) $location), " \t\n\r\0\x0B,.;");
        if ($location === '') {
            return $query;
        }

        $searchTerms = $this->expandLocationWithAliases($location);

        return $query->where(function ($locationQuery) use ($searchTerms) {
            foreach ($searchTerms as $term) {
                $locationQuery->orWhere('city', 'like', '%' . $term . '%')
                    ->orWhere('state', 'like', '%' . $term . '%')
                    ->orWhere('area_locality', 'like', '%' . $term . '%')
                    ->orWhere('street_address', 'like', '%' . $term . '%')
                    ->orWhere('zip_code', 'like', '%' . $term . '%')
                    ->orWhere('google_location', 'like', '%' . $term . '%');
            }
        });
    }

    private function isNearbySearch($queryText): bool
    {
        return preg_match(
            '/\b(?:near\s+me|nearby|around\s+me|close\s+to\s+me|mere\s+paas)\b|पास|దగ్గర|సమీప/iu',
            (string) $queryText
        ) === 1;
    }

    private function generateMultilingualSearchFilters(string $queryText, string $type = 'staff'): array
    {
        if ($queryText === '' || preg_match('/[^\x00-\x7F]/u', $queryText) !== 1) {
            return [];
        }

        $filters = (new AiFilterService())->generateFilters(['query' => $queryText], $type);

        return is_array($filters) ? $filters : [];
    }

    private function resolveBasicSearchFilters(
        string $queryText,
        array $parsedFilters = [],
        string $roleKey = 'role'
    ): array {
        $parsedRole = trim((string) ($parsedFilters[$roleKey] ?? ''));
        $role = $this->detectCanonicalRole($parsedRole)
            ?? $this->detectCanonicalRole($queryText);

        $location = trim((string) (
            $parsedFilters['location']
            ?? $parsedFilters['city']
            ?? $parsedFilters['state']
            ?? ''
        ));

        if ($location === '' || in_array(strtolower($location), ['me', 'near me', 'nearby'], true)) {
            $location = $this->extractSearchLocation($queryText);
        }

        return [
            'role' => $role,
            'location' => $location ?: null,
        ];
    }

    private function extractSearchLocation($queryText): ?string
    {
        $normalized = $this->normalizeStaffSearchText($queryText);
        if ($this->isNearbySearch($normalized)) {
            return null;
        }

        if (preg_match(
            '/\b(?:in|at|from|near)\s+([\p{L}\p{N}][\p{L}\p{N}\s.\'-]*?)(?=\s+(?:with|having|who|that|and|for)\b|[,;]|$)/iu',
            $normalized,
            $matches
        )) {
            $location = trim($matches[1], " \t\n\r\0\x0B,.;");
            return in_array($location, ['me', 'near me', 'nearby'], true) ? null : $location;
        }

        $queryLength = function_exists('mb_strlen')
            ? mb_strlen($normalized, 'UTF-8')
            : strlen($normalized);
        if ($this->detectCanonicalRole($normalized) === null && $queryLength <= 80) {
            return trim($normalized, " \t\n\r\0\x0B,.;");
        }

        $canonicalRole = $this->detectCanonicalRole($normalized);
        if ($canonicalRole !== null) {
            $aliases = $this->roleAliases()[$canonicalRole] ?? [$canonicalRole];
            $remaining = $normalized;
            foreach ($aliases as $alias) {
                $remaining = preg_replace('/\b' . preg_quote($alias, '/') . '\b/iu', ' ', $remaining);
            }
            $remaining = trim(preg_replace('/\s+/', ' ', $remaining), " \t\n\r\0\x0B,.;");
            if ($remaining !== '' && $queryLength <= 80) {
                return $remaining;
            }
        }

        return null;
    }

    private function resolveNearbyLocation(Request $request): string
    {
        $requestedLocation = trim((string) (
            $request->input('user_city') ?: $request->input('user_state', '')
        ));
        if ($requestedLocation !== '') {
            return $requestedLocation;
        }

        $user = Auth::user();
        if (!$user) {
            return '';
        }

        $address = $user->addresses()
            ->orderByDesc('is_primary')
            ->first();

        return trim((string) (
            $address?->city
            ?: $address?->state
            ?: $user->current_city
            ?: $user->current_state
        ));
    }

    private function parseCoordinate($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function getUserCoordinates($user): ?array
    {
        if (!$user) {
            return null;
        }

        $addresses = $user->relationLoaded('addresses')
            ? $user->addresses
            : $user->addresses()->get();

        $primaryAddress = collect($addresses)->firstWhere('is_primary', true)
            ?? collect($addresses)->first();

        $latitude = $this->parseCoordinate($primaryAddress?->latitude ?? $user->lat ?? null);
        $longitude = $this->parseCoordinate($primaryAddress?->longitude ?? $user->long ?? null);

        if ($latitude === null || $longitude === null) {
            return null;
        }

        return [
            'lat' => $latitude,
            'long' => $longitude,
        ];
    }

    private function staffMatchesNearbyLocation($staff, string $location): bool
    {
        $normalize = static function ($value): string {
            $value = trim(preg_replace('/\s+/u', ' ', (string) $value), " \t\n\r\0\x0B,.;");

            return function_exists('mb_strtolower')
                ? mb_strtolower($value, 'UTF-8')
                : strtolower($value);
        };

        $target = $normalize($location);
        if ($target === '') {
            return false;
        }

        $searchTerms = array_map($normalize, $this->expandLocationWithAliases($location));

        $addresses = $staff->relationLoaded('addresses')
            ? $staff->addresses
            : $staff->addresses()->get();
        $workInfo = $staff->relationLoaded('userWorkInfo')
            ? $staff->userWorkInfo
            : $staff->userWorkInfo()->first();

        $values = collect($addresses)->flatMap(static function ($address) {
            return [
                $address?->city,
                $address?->state,
                $address?->area_locality,
                $address?->street,
                $address?->pincode,
            ];
        })->push(
            $workInfo?->preferred_work_location,
            $staff->location ?? null,
            $staff->current_city ?? null,
            $staff->current_state ?? null,
        );

        return $values->filter()->contains(static function ($value) use ($normalize, $target, $searchTerms) {
            $candidate = $normalize($value);
            if ($candidate === '') {
                return false;
            }
            if (str_contains($candidate, 'all india') || str_contains($candidate, 'anywhere') || str_contains($candidate, 'pan india')) {
                return true;
            }
            if (str_contains($candidate, $target) || str_contains($target, $candidate)) {
                return true;
            }
            foreach ($searchTerms as $term) {
                if ($term !== '' && (str_contains($candidate, $term) || str_contains($term, $candidate))) {
                    return true;
                }
            }
            return false;
        });
    }

    private function enrichStaffWithRatings($data)
    {
        if ($data->isEmpty()) {
            return $data;
        }

        $staffIds = $data->pluck('id')->filter()->values()->all();
        if (empty($staffIds)) {
            return $data;
        }

        $ratingMap = DB::table('reviews')
            ->select('received_by_id', DB::raw('AVG(rating) as avg_rating'), DB::raw('COUNT(*) as review_count'))
            ->where('received_by_type', 'user')
            ->whereIn('received_by_id', $staffIds)
            ->groupBy('received_by_id')
            ->pluck('avg_rating', 'received_by_id');

        $reviewCountMap = DB::table('reviews')
            ->select('received_by_id', DB::raw('COUNT(*) as cnt'))
            ->where('received_by_type', 'user')
            ->whereIn('received_by_id', $staffIds)
            ->groupBy('received_by_id')
            ->pluck('cnt', 'received_by_id');

        foreach ($data as $item) {
            $id = $item->id ?? $item['id'] ?? null;
            if ($id && isset($ratingMap[$id])) {
                $item->_average_rating = round((float) $ratingMap[$id], 1);
                $item->_review_count = (int) ($reviewCountMap[$id] ?? 0);
            } else {
                $item->_average_rating = 0;
                $item->_review_count = 0;
            }
        }

        return $data;
    }

    private function resolveSearchOrigin(Request $request): ?array
    {
        $latitude = $this->parseCoordinate($request->input('lat', $request->input('latitude')));
        $longitude = $this->parseCoordinate($request->input('long', $request->input('longitude')));

        if ($latitude !== null && $longitude !== null) {
            return [
                'lat' => $latitude,
                'long' => $longitude,
            ];
        }

        return $this->getUserCoordinates(Auth::user());
    }

    private function calculateDistanceKm(float $startLat, float $startLong, float $endLat, float $endLong): float
    {
        $earthRadius = 6371;

        $latDelta = deg2rad($endLat - $startLat);
        $longDelta = deg2rad($endLong - $startLong);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($startLat)) * cos(deg2rad($endLat))
            * sin($longDelta / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }

    private function applyNearbyStaffProximity(
        $collection,
        ?array $origin,
        ?float $radiusKm = null,
        string $fallbackLocation = ''
    )
    {
        $strictNearby = $radiusKm !== null;

        if (!$origin || !isset($origin['lat'], $origin['long'])) {
            if (!$strictNearby) {
                return $collection;
            }

            if (trim($fallbackLocation) === '') {
                return collect();
            }

            return collect($collection)
                ->filter(fn ($staff) => $this->staffMatchesNearbyLocation($staff, $fallbackLocation))
                ->values();
        }

        $ranked = collect($collection)->map(function ($staff) use (
            $origin,
            $radiusKm,
            $strictNearby,
            $fallbackLocation
        ) {
            $coords = $this->getUserCoordinates($staff);

            if (!$coords) {
                if (
                    $strictNearby
                    && !$this->staffMatchesNearbyLocation($staff, $fallbackLocation)
                ) {
                    return null;
                }

                $staff->setAttribute('_distance_km', null);
                return $staff;
            }

            $distance = $this->calculateDistanceKm(
                (float) $origin['lat'],
                (float) $origin['long'],
                (float) $coords['lat'],
                (float) $coords['long']
            );

            if ($radiusKm !== null && $distance > $radiusKm) {
                return null;
            }

            $staff->setAttribute('_distance_km', round($distance, 2));
            return $staff;
        })->filter()->sortBy(function ($staff) {
            return $staff->_distance_km ?? PHP_FLOAT_MAX;
        })->values();

        return $ranked;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id'
        ]);

        // If validation fails, return a 422 response with errors
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $role = Role::where('slug', 'staff')->first();
        if (!$role) {
            return response()->json(['success' => false, 'message' => 'Staff role not configured'], 500);
        }
        $terminatedUserIds = Termination::where('reported_by', $request->user_id)
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        $query = User::where('user_role_id', $role->id)
            ->where(function ($q) use ($request, $terminatedUserIds) {
                $q->where('added_by', $request->user_id);

                if ($terminatedUserIds->isNotEmpty()) {
                    $q->orWhereIn('id', $terminatedUserIds->all());
                }
            });
        // 🔍 Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // 🧩 User type / status filter
        if ($request->filled('user_type')) {
            $query->where('status', $request->user_type);
        }
        $staff = $query->latest()->paginate(10);
        $staff->getCollection()->transform(function ($item) use ($terminatedUserIds) {
            if ($terminatedUserIds->contains($item->id)) {
                $item->status = 'inactive';
                $item->is_active = 0;
            }

            return $item;
        });

        return response()->json([
            'success' => true,
            'message' => 'Staff retrieved successfully',
            'data'    => $staff,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $role = Role::where('slug', 'staff')->first();
        if (!$role) {
            return response()->json(['success' => false, 'message' => 'Staff role not configured'], 500);
        }
        $staff = User::with(['userWorkInfo', 'addresses', 'kycInformation', 'lastExp', 'addedByUser', 'petDetails', 'householdInformation', 'reviewsReceived'])
            ->where('id', $id)
            ->where('user_role_id', $role->id)
            ->first();
        if(empty($staff)) {
            return response()->json([
                'success' => false,
                'message' => 'Staff not found'
            ], 404);
        }

        $staff->setAttribute('current_subscription', SubscriptionUser::with('subscription')
            ->where('user_id', $staff->id)
            ->where('status', 'active')
            ->latest()
            ->first());

        return response()->json([
            'success' => true,
            'data' => $staff
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $role = Role::where('slug', 'staff')->first();
        $staff = User::where('id', $id)
            ->where('user_role_id', $role?->id)
            ->first();

        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|max:100',
            'email' => 'nullable|email|max:150|unique:users,email,' . $id,
            'phone_number' => 'nullable|max:20|unique:users,phone_number,' . $id,
            'dob' => 'nullable|max:50',
            'gender' => 'nullable|max:20',
            'status' => 'nullable|in:active,block,inactive',
            'occupation' => 'nullable|max:100',
            'service_category' => 'nullable|max:100',
            'area_locality' => 'required|max:255',
            'google_location' => 'required|string',
            'lat' => 'required|numeric',
            'long' => 'required|numeric',
            'current_city' => 'nullable|max:100',
            'current_state' => 'nullable|max:100',
            'current_pincode' => 'nullable|max:20',
            'salary' => 'nullable|numeric|min:0',
            'pay_frequency' => 'nullable|max:50',
            'primary_role' => 'nullable|max:100',
            'preferred_work_location' => 'nullable|max:255',
            'stay_type' => 'nullable|max:255',
            'relation' => 'nullable|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            $firstName = $data['first_name'] ?? $staff->first_name;
            $lastName = $data['last_name'] ?? $staff->last_name;

            $userData = collect($data)->except([
                'salary',
                'pay_frequency',
                'primary_role',
                'preferred_work_location',
                'stay_type',
                'occupation',
            ])->filter(function ($value) {
                return $value !== null && $value !== '';
            })->toArray();

            $userData['name'] = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));

        
            $staff->update($userData);

            $primaryAddress = $staff->addresses()->where('is_primary', true)->first()
                ?: $staff->addresses()->first();

            $addressData = array_filter([
                'street' => $primaryAddress?->street,
                'city' => $data['current_city'] ?? $primaryAddress?->city,
                'state' => $data['current_state'] ?? $primaryAddress?->state,
                'pincode' => $data['current_pincode'] ?? $primaryAddress?->pincode,
                'area_locality' => $data['area_locality'] ?? $primaryAddress?->area_locality,
                'google_location' => $data['google_location'] ?? $primaryAddress?->google_location,
                'latitude' => $data['lat'] ?? $primaryAddress?->latitude,
                'longitude' => $data['long'] ?? $primaryAddress?->longitude,
                'is_primary' => true,
            ], function ($value) {
                return $value !== null && $value !== '';
            });

            if ($primaryAddress) {
                $primaryAddress->update($addressData);
            } elseif (!empty($addressData)) {
                $staff->addresses()->create($addressData);
            }

            $workInfoData = collect($data)->only([
                'salary',
                'pay_frequency',
                'primary_role',
                'preferred_work_location',
                'stay_type',
                'occupation',
            ])->filter(function ($value) {
                return $value !== null && $value !== '';
            })->toArray();

            if (!empty($workInfoData)) {
                UserWorkInfo::updateOrCreate(
                    ['user_id' => $staff->id],
                    $workInfoData
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Staff updated successfully',
                'data' => $staff->fresh(['userWorkInfo', 'addresses', 'kycInformation', 'lastExp', 'addedByUser'])
            ]);
        } catch (\Throwable $e) {
            \Log::error('Staff update failed: ' . $e->getMessage(), ['id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
        $user->delete();
        return response()->json([
            'success' => true,
            'message' => 'Staff deleted successfully'
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:block,active',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Find staff/user
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Staff not found',
            ], 404);
        }

        // Update status
        $user->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'status'  => 'success',
            'data'    => $user,
            'message' => 'Staff status updated successfully',
        ]);
    }

    public function getAttendance(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'id'    => 'required|exists:users,id',
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $staffId = $request->id ?: Auth::id();
        $staff = User::find($staffId);

        if (!$staff) {
            return response()->json([
                'status' => false,
                'message' => 'Staff member not found',
            ], 404);
        }

        // Get first and last date of given month, with defaults if missing
        $year = (int)($request->year ?: date('Y'));
        $month = (int)($request->month ?: date('m'));

        try {
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate   = Carbon::create($year, $month, 1)->endOfMonth();
        } catch (\Exception $e) {
            $startDate = Carbon::now()->startOfMonth();
            $endDate   = Carbon::now()->endOfMonth();
        }

        // Get attendance records for that month
        $attendance = Attendance::whereBetween('date', [$startDate, $endDate])
            ->where('staff_id', $staff->id)
            ->get()
            ->mapWithKeys(function ($item) {
                // Ensure the date key is a string in Y-m-d format to match CarbonPeriod iteration
                $dateKey = $item->date instanceof \Carbon\Carbon 
                    ? $item->date->format('Y-m-d') 
                    : \Carbon\Carbon::parse($item->date)->format('Y-m-d');
                return [$dateKey => $item->status];
            });

        $period = CarbonPeriod::create($startDate, $endDate);

        $result = [];

        foreach ($period as $date) {
            $formattedDate = $date->format('Y-m-d');

            $result[] = [
                'date' => $formattedDate,
                'status' => isset($attendance[$formattedDate])
                    ? $attendance[$formattedDate]
                    : 'absent'
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Attendance retrieved successfully',
            'data' => $result
        ], 200);
    }

    public function getAiData(Request $request)
    {
        // Query is optional - if empty, return all staff without AI filtering
        $request->validate([
            'query' => 'nullable|string',
            'query_text' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'long' => 'nullable|numeric',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'radius_km' => 'nullable|numeric|min:0|max:500',
            'user_city' => 'nullable|string|max:150',
            'user_state' => 'nullable|string|max:150',
            'nearby' => 'nullable|boolean',
        ]);

        // Accept both 'query' and 'query_text' params
        $queryText = trim((string) ($request->input('query_text') ?: $request->input('query', '')));
        $searchOrigin = $this->resolveSearchOrigin($request);
        $searchRadiusKm = $this->parseCoordinate($request->input('radius_km'));
        $nearbyLocation = $this->resolveNearbyLocation($request);
        $isNearbySearch = $request->boolean('nearby') || $this->isNearbySearch($queryText);

        if ($isNearbySearch && $searchRadiusKm === null) {
            $searchRadiusKm = 50;
        }

        if ($isNearbySearch && $queryText === '') {
            $queryText = 'staff near me';
        }

        $nearbyFallbackLocation = $isNearbySearch ? $nearbyLocation : '';

        $basicQueryText = $queryText;
        if ($isNearbySearch && !$searchOrigin && $nearbyLocation !== '') {
            $withoutNearbyPhrase = preg_replace(
                '/\b(?:near\s+me|nearby|around\s+me|close\s+to\s+me|mere\s+paas)\b|पास|దగ్గర|సమీప/iu',
                ' ',
                $queryText
            );
            $basicQueryText = trim((string) $withoutNearbyPhrase) . ' in ' . $nearbyLocation;
        }

        try {
            // 🔹 Base query - all staff with their work info and addresses
            $baseQuery = User::with(['userWorkInfo', 'addresses', 'kycInformation'])
                ->where('user_role_id', 2)
                ->where('is_job_seeking', 1);

            // If no query text, just return all staff (no AI, no subscription needed)
            if ($queryText === '') {
                $data = $this->applyNearbyStaffProximity(
                    $baseQuery->get(),
                    $searchOrigin,
                    $searchRadiusKm,
                    $nearbyFallbackLocation,
                );
                $data = $this->enrichStaffWithRatings($data);
                return response()->json([
                    'success' => true,
                    'ai_filters' => null,
                    'data' => $data,
                ]);
            }

            $preparsedFilters = $this->generateMultilingualSearchFilters($queryText);

            // 🔹 AI path - check subscription
            $subscription = SubscriptionUser::where('user_id', Auth::guard('api')->id())->first();
            $plan = $subscription ? Subscription::find($subscription->subscription_id) : null;

            // Subscription limit check - fall back to non-AI list instead of hard failure
            $canUseAi = $subscription && $plan
                && ($plan->subscription_limit == 0 || $subscription->user_limit < $plan->subscription_limit);

            if (!$canUseAi) {
                // Even without AI, apply basic role/location filter from query text
                $data = $this->applyBasicFilters(
                    $baseQuery,
                    $basicQueryText,
                    $preparsedFilters,
                )->get();
                $data = $this->applyNearbyStaffProximity(
                    $data,
                    $searchOrigin,
                    $searchRadiusKm,
                    $nearbyFallbackLocation,
                );
                $data = $this->enrichStaffWithRatings($data);

                // Semantic ranking even without subscription (free feature)
                if ($data->isNotEmpty() && !$searchOrigin) {
                    try {
                        $embeddingService = new EmbeddingService();
                        $queryEmbedding = $embeddingService->generateEmbedding($queryText);
                        if ($queryEmbedding) {
                            $ranked = [];
                            foreach ($data as $staffMember) {
                                $embedding = null;
                                if ($staffMember->userWorkInfo && !empty($staffMember->userWorkInfo->embedding)) {
                                    $raw = $staffMember->userWorkInfo->embedding;
                                    $embedding = is_string($raw) ? json_decode($raw, true) : $raw;
                                }
                                $similarity = ($embedding && is_array($embedding))
                                    ? EmbeddingService::cosineSimilarity($queryEmbedding, $embedding)
                                    : 0.0;
                                $staffMember->_similarity = round($similarity, 4);
                                $ranked[] = $staffMember;
                            }
                            usort($ranked, fn($a, $b) => ($b->_similarity ?? 0) <=> ($a->_similarity ?? 0));
                            $data = collect($ranked);
                        }
                    } catch (\Throwable $e) {
                        // Ranking failed, keep filter-based order
                    }
                }

                return response()->json([
                    'success' => true,
                    'ai_filters' => null,
                    'message' => !$subscription
                        ? 'No active subscription - showing filtered staff.'
                        : 'AI search limit reached - showing filtered staff.',
                    'data' => $data->map(function ($item) {
                        $arr = $item->toArray();
                        $arr['_similarity'] = $item->_similarity ?? 0;
                        $arr['_distance_km'] = $item->_distance_km ?? null;
                        $arr['_average_rating'] = $item->_average_rating ?? 0;
                        $arr['_review_count'] = $item->_review_count ?? 0;
                        return $arr;
                    }),
                ]);
            }

            // 🔹 AI Generate Filters
            $filters = $preparsedFilters;
            if ($filters === []) {
                $filters = (new AiFilterService())->generateFilters(['query' => $queryText]);
            }

            if ($isNearbySearch) {
                if ($searchOrigin && $searchRadiusKm === null) {
                    $searchRadiusKm = 50;
                }
                unset($filters['location']);
                if (!$searchOrigin && $nearbyLocation !== '') {
                    $filters['location'] = $nearbyLocation;
                }
            }
            unset($filters['nearby']);

            if (empty($filters['role'])) {
                $detectedRole = $this->detectCanonicalRole($queryText);
                if ($detectedRole) {
                    $filters['role'] = $detectedRole;
                }
            }

            if (empty($filters['location']) && !$isNearbySearch) {
                $detectedLocation = $this->extractSearchLocation($queryText);
                if ($detectedLocation) {
                    $filters['location'] = $detectedLocation;
                }
            }
            
            \Log::info('Applied AI Filters:', ['filters' => $filters, 'query' => $queryText]);
            
            // ✅ Fix: Use user_role_id instead of non-existent role() scope
            // Clone base query for strict and relaxed searches
            $query = clone $baseQuery;

            $applyCoreFilters = function($q) use ($filters) {
                if (!empty($filters['name'])) {
                    $name = $filters['name'];
                    $q->where(function ($query) use ($name) {
                        $query->where('first_name', 'like', '%' . $name . '%')
                              ->orWhere('last_name', 'like', '%' . $name . '%');
                    });
                }

                if (!empty($filters['gender'])) {
                    $q->where('gender', $filters['gender']);
                }

                if (!empty($filters['location'])) {
                    $this->applyStaffLocationFilter($q, $filters['location']);
                }

                if (!empty($filters['role'])) {
                    $this->applyStaffRoleFilter($q, $filters['role']);
                }
            };

            // Apply core filters (Role, Location, Gender, Name)
            $applyCoreFilters($query);

            // Apply strict filters (Salary, Experience, Languages, Skills, Keywords)
            if (!empty($filters['salary']) && is_array($filters['salary'])) {
                $salary = $filters['salary'];
                $query->whereHas('userWorkInfo', function ($q) use ($salary) {
                    if (isset($salary['gt'])) $q->where('salary', '>', $salary['gt']);
                    if (isset($salary['lt'])) $q->where('salary', '<', $salary['lt']);
                });
            }

            if (!empty($filters['experience'])) {
                $exp = (int) $filters['experience'];
                $query->whereHas('userWorkInfo', function ($q) use ($exp) {
                    $q->where('total_experience', '>=', $exp);
                });
            }

            if (!empty($filters['languages']) && is_array($filters['languages'])) {
                $langs = $filters['languages'];
                $query->whereHas('userWorkInfo', function ($q) use ($langs) {
                    $q->where(function ($inner) use ($langs) {
                        foreach ($langs as $lang) {
                            $inner->orWhere('languages_spoken', 'like', '%' . $lang . '%');
                        }
                    });
                });
            }

            if (!empty($filters['skills']) && is_array($filters['skills'])) {
                $skills = $filters['skills'];
                $query->whereHas('userWorkInfo', function ($q) use ($skills) {
                    $q->where(function ($inner) use ($skills) {
                        foreach ($skills as $skill) {
                            $inner->orWhere('skills', 'like', '%' . $skill . '%')
                                  ->orWhere('primary_role', 'like', '%' . $skill . '%')
                                  ->orWhere('additional_info', 'like', '%' . $skill . '%');
                        }
                    });
                });
            }

            if (!empty($filters['general_keywords']) && is_array($filters['general_keywords'])) {
                $keywords = $filters['general_keywords'];
                $query->where(function ($q) use ($keywords) {
                    foreach ($keywords as $kw) {
                        $q->where(function ($inner) use ($kw) {
                            $inner->orWhere('first_name', 'like', '%' . $kw . '%')
                                  ->orWhere('last_name', 'like', '%' . $kw . '%')
                                  ->orWhereHas('userWorkInfo', function ($sub) use ($kw) {
                                      $sub->where('primary_role', 'like', '%' . $kw . '%')
                                          ->orWhere('skills', 'like', '%' . $kw . '%')
                                          ->orWhere('additional_info', 'like', '%' . $kw . '%')
                                          ->orWhere('education', 'like', '%' . $kw . '%');
                                  });
                        });
                    }
                });
            }

            // ✅ If query was provided but AI didn't find any filters, use basic keyword fallback
            if (empty($filters['role']) && empty($filters['name']) && empty($filters['location']) && empty($filters['gender']) && empty($filters['salary']) && empty($filters['experience']) && empty($filters['languages']) && empty($filters['skills']) && empty($filters['general_keywords'])) {
                $data = $this->applyBasicFilters($baseQuery, $basicQueryText, $filters)->get();
                $data = $this->applyNearbyStaffProximity(
                    $data,
                    $searchOrigin,
                    $searchRadiusKm,
                    $nearbyFallbackLocation,
                );
                return response()->json([
                    'success' => true,
                    'ai_filters' => $filters,
                    'message' => 'Showing keyword-matched results.',
                    'data' => $data
                ]);
            }

            $data = $query->get();

            // ✅ Fallback: If strict AI filters yield 0 results, run a relaxed query
            if ($data->isEmpty() && (!empty($filters['general_keywords']) || !empty($filters['skills']) || !empty($filters['experience']) || !empty($filters['languages']) || !empty($filters['salary']))) {
                $relaxedQuery = clone $baseQuery;
                $applyCoreFilters($relaxedQuery);
                $data = $relaxedQuery->get();
                $filters['_relaxed'] = true; // Inform frontend that strict filters were dropped
            }

            $data = $this->applyNearbyStaffProximity(
                $data,
                $searchOrigin,
                $searchRadiusKm,
                $nearbyFallbackLocation,
            );

            // ✅ Semantic ranking: Use embedding similarity to sort results by relevance
            if ($data->isNotEmpty() && !$searchOrigin) {
                try {
                    $embeddingService = new EmbeddingService();
                    $queryEmbedding = $embeddingService->generateEmbedding($queryText);

                    if ($queryEmbedding) {
                        $ranked = [];
                        foreach ($data as $staffMember) {
                            $embedding = null;
                            if ($staffMember->userWorkInfo && !empty($staffMember->userWorkInfo->embedding)) {
                                $raw = $staffMember->userWorkInfo->embedding;
                                $embedding = is_string($raw) ? json_decode($raw, true) : $raw;
                            }

                            $similarity = ($embedding && is_array($embedding))
                                ? EmbeddingService::cosineSimilarity($queryEmbedding, $embedding)
                                : 0.0;

                            $staffMember->_similarity = round($similarity, 4);
                            $ranked[] = $staffMember;
                        }

                        usort($ranked, fn($a, $b) => ($b->_similarity ?? 0) <=> ($a->_similarity ?? 0));
                        $data = collect($ranked);
                        $filters['_semantic_ranked'] = true;
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Embedding ranking failed, using filter-based order', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $subscription->increment('user_limit');

            // Enrich with ratings
            $data = $this->enrichStaffWithRatings($data);

            // Map to arrays including _similarity (dynamic property not included by toArray())
            $dataOut = $data->map(function ($item) {
                $arr = $item->toArray();
                $arr['_similarity'] = $item->_similarity ?? 0;
                $arr['_distance_km'] = $item->_distance_km ?? null;
                $arr['_average_rating'] = $item->_average_rating ?? 0;
                $arr['_review_count'] = $item->_review_count ?? 0;
                return $arr;
            });

            return response()->json([
                'success' => true,
                'ai_filters' => $filters,
                'remaining_limit' => $plan->subscription_limit > 0 ? $plan->subscription_limit - $subscription->user_limit : 'Unlimited',
                'data' => $dataOut
            ]);

        } catch (\Throwable $e) {
            \Log::error('getAiData failed, falling back to basic filters: ' . $e->getMessage(), [
                'query' => $queryText,
            ]);

            try {
                $fallbackQuery = User::with(['userWorkInfo', 'addresses', 'kycInformation'])
                    ->where('user_role_id', 2)
                    ->where('is_job_seeking', 1);

                $data = $this->applyBasicFilters(
                    $fallbackQuery,
                    $basicQueryText,
                    $preparsedFilters ?? [],
                )->get();
                $data = $this->applyNearbyStaffProximity(
                    $data,
                    $searchOrigin,
                    $searchRadiusKm,
                    $nearbyFallbackLocation,
                );

                // Semantic ranking in fallback too
                if ($data->isNotEmpty() && !$searchOrigin) {
                    try {
                        $embeddingService = new EmbeddingService();
                        $queryEmbedding = $embeddingService->generateEmbedding($queryText);
                        if ($queryEmbedding) {
                            $ranked = [];
                            foreach ($data as $staffMember) {
                                $embedding = null;
                                if ($staffMember->userWorkInfo && !empty($staffMember->userWorkInfo->embedding)) {
                                    $raw = $staffMember->userWorkInfo->embedding;
                                    $embedding = is_string($raw) ? json_decode($raw, true) : $raw;
                                }
                                $similarity = ($embedding && is_array($embedding))
                                    ? EmbeddingService::cosineSimilarity($queryEmbedding, $embedding)
                                    : 0.0;
                                $staffMember->_similarity = round($similarity, 4);
                                $ranked[] = $staffMember;
                            }
                            usort($ranked, fn($a, $b) => ($b->_similarity ?? 0) <=> ($a->_similarity ?? 0));
                            $data = collect($ranked);
                        }
                    } catch (\Throwable $e) {
                        // Ranking failed in fallback, keep filter-based order
                    }
                }

                $data = $this->enrichStaffWithRatings($data);

                return response()->json([
                    'success' => true,
                    'ai_filters' => null,
                    'message' => 'Showing filtered staff results.',
                    'fallback' => true,
                    'data' => $data->map(function ($item) {
                        $arr = $item->toArray();
                        $arr['_similarity'] = $item->_similarity ?? 0;
                        $arr['_distance_km'] = $item->_distance_km ?? null;
                        $arr['_average_rating'] = $item->_average_rating ?? 0;
                        $arr['_review_count'] = $item->_review_count ?? 0;
                        return $arr;
                    }),
                ]);
            } catch (\Throwable $fallbackError) {
                \Log::error('getAiData fallback also failed: ' . $fallbackError->getMessage(), [
                    'query' => $queryText,
                ]);

                return response()->json([
                    'success' => true,
                    'ai_filters' => null,
                    'message' => 'Search could not be completed. Please try a clearer role or location.',
                    'fallback' => true,
                    'data' => [],
                ]);
            }
        }
    }

    /**
     * Apply basic keyword filters without AI (fallback when no subscription)
     */
    private function applyBasicFilters($query, $queryText, array $parsedFilters = [])
    {
        $resolved = $this->resolveBasicSearchFilters(
            (string) $queryText,
            $parsedFilters,
            'role',
        );

        if ($resolved['role']) {
            $this->applyStaffRoleFilter($query, $resolved['role']);
        }

        if ($resolved['location']) {
            $this->applyStaffLocationFilter($query, $resolved['location']);
        }

        return $query;
    }


    public function getJobs() {
        $id = Auth::user()->id;
        $staff = User::find($id);
        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff member not found'
            ], 404);
        }

        $jobs = JobApplication::where('user_id', $staff->id)->where('application_status', 'accepted')->get();

        return response()->json([
            'success' => true,
            'message' => 'Jobs retrieved successfully',
            'data' => $jobs
        ]);
    }  
    
    
    public function getStaffList(Request $request)
    {
        try {
            // user_role_id = 2 is staff — use direct ID instead of Role lookup to avoid null
            $query = User::where('user_role_id', 2);
            
            if (Auth::guard('api')->check() && Auth::guard('api')->user()->user_role_id != 1) {
                $query->where('added_by', Auth::guard('api')->id());
            }

            // 🔍 Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhere('phone_number', 'like', "%{$search}%")
                      ->orWhere('aadhar_number', 'like', "%{$search}%")
                      ->orWhereHas('userWorkInfo', function ($sub) use ($search) {
                          $sub->where('primary_role', 'like', "%{$search}%")
                              ->orWhere('skills', 'like', "%{$search}%");
                      });
                });
            }

            // 🧩 Status filter
            if ($request->filled('user_type')) {
                $query->where('status', $request->user_type);
            }

            $staff = $query->with(['userWorkInfo', 'addresses', 'kycInformation', 'lastExp', 'addedByUser'])->latest()->paginate(50);

            return response()->json([
                'success' => true,
                'message' => 'Staff retrieved successfully',
                'data'    => $staff,
            ]);
        } catch (\Exception $e) {
            \Log::error('getStaffList failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staff list',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getJobByStaffAiData(Request $request)
    {
        $request->validate([
            'query' => 'nullable|string',
            'query_text' => 'nullable|string',
            'user_city' => 'nullable|string|max:150',
            'user_state' => 'nullable|string|max:150',
        ]);

        $queryText = trim((string) ($request->input('query_text') ?: $request->input('query', '')));
        $nearbyLocation = $this->resolveNearbyLocation($request);
        $isNearbySearch = $this->isNearbySearch($queryText);
        $basicQueryText = $queryText;

        if ($isNearbySearch && $nearbyLocation !== '') {
            $withoutNearbyPhrase = preg_replace(
                '/\b(?:near\s+me|nearby|around\s+me|close\s+to\s+me|mere\s+paas)\b|पास|దగ్గర|సమీప/iu',
                ' ',
                $queryText
            );
            $basicQueryText = trim((string) $withoutNearbyPhrase) . ' in ' . $nearbyLocation;
        }

        $baseQuery = Job::where('status', 'open');

        if ($queryText === '') {
            return response()->json([
                'success' => true,
                'ai_filters' => null,
                'data' => $baseQuery->get(),
            ]);
        }

        $preparsedFilters = $this->generateMultilingualSearchFilters($queryText, 'job');

        $subscription = SubscriptionUser::where('user_id', Auth::guard('api')->id())->first();

        if (!$subscription) {
            return response()->json([
                'success' => true,
                'ai_filters' => null,
                'message' => 'No active subscription found. Showing keyword-matched jobs.',
                'fallback' => true,
                'data' => $this->applyBasicJobFilters(
                    clone $baseQuery,
                    $basicQueryText,
                    $preparsedFilters,
                )->get(),
            ]);
        }

        $plan = Subscription::find($subscription->subscription_id);

        if (!$plan) {
            return response()->json([
                'success' => true,
                'ai_filters' => null,
                'message' => 'Subscription plan not found. Showing keyword-matched jobs.',
                'fallback' => true,
                'data' => $this->applyBasicJobFilters(
                    clone $baseQuery,
                    $basicQueryText,
                    $preparsedFilters,
                )->get(),
            ]);
        }

        // ✅ Check AI limit
        if ($plan->subscription_limit > 0 && $subscription->user_limit >= $plan->subscription_limit) {
            return response()->json([
                'success' => true,
                'ai_filters' => null,
                'message' => 'Monthly AI limit exceeded. Showing keyword-matched jobs.',
                'fallback' => true,
                'data' => $this->applyBasicJobFilters(
                    clone $baseQuery,
                    $basicQueryText,
                    $preparsedFilters,
                )->get(),
            ]);
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | Generate AI Filters
            |--------------------------------------------------------------------------
            */

            $filters = $preparsedFilters;
            if ($filters === []) {
                $filters = (new AiFilterService())->generateFilters(['query' => $queryText], 'job');
            }

            if (!is_array($filters)) {
                return response()->json([
                    'success' => true,
                    'ai_filters' => null,
                    'message' => 'Showing keyword-matched jobs.',
                    'fallback' => true,
                    'data' => $this->applyBasicJobFilters(
                        clone $baseQuery,
                        $basicQueryText,
                        $preparsedFilters,
                    )->get(),
                ]);
            }

            if ($isNearbySearch) {
                unset($filters['location'], $filters['city'], $filters['state']);
                if ($nearbyLocation !== '') {
                    $filters['location'] = $nearbyLocation;
                }
            }
            unset($filters['nearby']);

            if (empty($filters['title'])) {
                $detectedRole = $this->detectCanonicalRole($queryText);
                if ($detectedRole) {
                    $filters['title'] = $detectedRole;
                }
            }

            if (
                empty($filters['location']) &&
                empty($filters['city']) &&
                empty($filters['state']) &&
                !$isNearbySearch
            ) {
                $detectedLocation = $this->extractSearchLocation($queryText);
                if ($detectedLocation) {
                    $filters['location'] = $detectedLocation;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Map AI Filters
            |--------------------------------------------------------------------------
            */

            if (isset($filters['salary']['greater_than'])) {
                $filters['compensation']['gt'] = $filters['salary']['greater_than'];
            }

            if (isset($filters['salary']['less_than'])) {
                $filters['compensation']['lt'] = $filters['salary']['less_than'];
            }

            /*
            |--------------------------------------------------------------------------
            | Start Query
            |--------------------------------------------------------------------------
            */

            // Only show open/active jobs
            $query = clone $baseQuery;

            // Keep title for fallback if all filters return 0 results
            $titleFilter = $filters['title'] ?? null;
            $locationFilter = $filters['location']
                ?? $filters['city']
                ?? $filters['state']
                ?? null;

            /*
            |--------------------------------------------------------------------------
            | Text Filters
            |--------------------------------------------------------------------------
            */

            if (!empty($filters['title'])) {
                $this->applyJobRoleFilter($query, $filters['title']);
            }

            if ($locationFilter) {
                $this->applyJobLocationFilter($query, $locationFilter);
            }

            if (!empty($filters['required_skills'])) {
                $skill = $filters['required_skills'];
                $query->where(function ($skillQuery) use ($skill) {
                    $skillQuery->where('required_skills', 'like', '%' . $skill . '%')
                        ->orWhere('description', 'like', '%' . $skill . '%')
                        ->orWhere('additional_requirements', 'like', '%' . $skill . '%');
                });
            }

            if (!empty($filters['compensation_type'])) {
                $query->where('compensation_type', $filters['compensation_type']);
            }

            if (!empty($filters['salary']) && is_array($filters['salary'])) {

                $operator = $filters['salary']['operator'] ?? '=';
                $value = $filters['salary']['value'] ?? null;

                if ($value !== null) {

                    $allowedOperators = ['>', '<', '>=', '<=', '=', '!='];

                    if (in_array($operator, $allowedOperators)) {
                        $query->where('compensation', $operator, $value);
                    }
                }
            }

            if (!empty($filters['salary']) && is_array($filters['salary'])) {

                $salary = $filters['salary'];

                if (isset($salary['$gt'])) {
                    $query->where('compensation', '>', $salary['$gt']);
                }

                if (isset($salary['$gte'])) {
                    $query->where('compensation', '>=', $salary['$gte']);
                }

                if (isset($salary['$lt'])) {
                    $query->where('compensation', '<', $salary['$lt']);
                }

                if (isset($salary['$lte'])) {
                    $query->where('compensation', '<=', $salary['$lte']);
                }

                if (isset($salary['$eq'])) {
                    $query->where('compensation', '=', $salary['$eq']);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Compensation Filter
            |--------------------------------------------------------------------------
            */

            if (!empty($filters['compensation']) && is_array($filters['compensation'])) {

                $comp = $filters['compensation'];

                if (!empty($comp['gt'])) {
                    $query->where('compensation', '>', $comp['gt']);
                }

                if (!empty($comp['gte'])) {
                    $query->where('compensation', '>=', $comp['gte']);
                }

                if (!empty($comp['lt'])) {
                    $query->where('compensation', '<', $comp['lt']);
                }

                if (!empty($comp['lte'])) {
                    $query->where('compensation', '<=', $comp['lte']);
                }

                if (!empty($comp['eq'])) {
                    $query->where('compensation', '=', $comp['eq']);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Job Details
            |--------------------------------------------------------------------------
            */

            if (!empty($filters['commitment_type'])) {
                $query->where('commitment_type', $filters['commitment_type']);
            }

            if (!empty($filters['preferred_hours'])) {
                $query->where('preferred_hours', $filters['preferred_hours']);
            }

            if (!empty($filters['preferred_days'])) {
                $query->where('preferred_days', $filters['preferred_days']);
            }

            /*
            |--------------------------------------------------------------------------
            | Boolean Filters
            |--------------------------------------------------------------------------
            */

            // Boolean fields only applied if explicitly set to true (not inferred)
            $booleanFields = [
                'childcare_experience',
                'cooking_required',
                'driving_license_required',
                'first_aid_certified',
                'pet_care_required'
            ];

            foreach ($booleanFields as $field) {
                if (isset($filters[$field]) && $filters[$field] === true) {
                    $query->where($field, 1);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Expected Compensation
            |--------------------------------------------------------------------------
            */

            if (!empty($filters['expected_compensation'])) {
                $query->where('expected_compensation', '<=', $filters['expected_compensation']);
            }

            if (
                empty($filters['title']) &&
                empty($filters['location']) &&
                empty($filters['city']) &&
                empty($filters['state']) &&
                empty($filters['salary']) &&
                empty($filters['compensation']) &&
                empty($filters['compensation_type']) &&
                empty($filters['required_skills']) &&
                empty($filters['commitment_type']) &&
                empty($filters['preferred_hours']) &&
                empty($filters['preferred_days']) &&
                empty($filters['expected_compensation']) &&
                !isset($filters['childcare_experience']) &&
                !isset($filters['cooking_required']) &&
                !isset($filters['driving_license_required']) &&
                !isset($filters['first_aid_certified']) &&
                !isset($filters['pet_care_required'])
            ) {
                return response()->json([
                    'success' => true,
                    'ai_filters' => $filters,
                    'message' => 'Showing keyword-matched jobs.',
                    'fallback' => true,
                    'data' => $this->applyBasicJobFilters(
                        clone $baseQuery,
                        $basicQueryText,
                        $filters,
                    )->get(),
                ]);
            }

            $data = $query->get();

            // If strict filters returned nothing, fall back to title-only on open jobs
            if ($data->isEmpty() && $titleFilter) {
                $data = $this->applyBasicJobFilters(
                    clone $baseQuery,
                    $basicQueryText,
                    $filters,
                )->get();
            }

            if ($data->isEmpty() && $titleFilter && !$locationFilter) {
                $titleOnlyQuery = Job::where('status', 'open');
                $this->applyJobRoleFilter($titleOnlyQuery, $titleFilter);
                $data = $titleOnlyQuery->get();
            }

            // ✅ Increase usage
            $subscription->increment('user_limit');

            return response()->json([
                'success' => true,
                'ai_filters' => $filters,
                'remaining_limit' => $plan->subscription_limit > 0 ? max($plan->subscription_limit - $subscription->user_limit, 0) : 'Unlimited',
                'data' => $data
            ]);

        } catch (\Throwable $e) {
            \Log::error('getJobByStaffAiData failed, falling back to basic filters: ' . $e->getMessage(), [
                'query' => $queryText,
            ]);

            try {
                return response()->json([
                    'success' => true,
                    'ai_filters' => null,
                    'message' => 'Showing keyword-matched jobs.',
                    'fallback' => true,
                    'data' => $this->applyBasicJobFilters(
                        clone $baseQuery,
                        $basicQueryText,
                        $preparsedFilters,
                    )->get(),
                ]);
            } catch (\Throwable $fallbackError) {
                \Log::error('getJobByStaffAiData fallback also failed: ' . $fallbackError->getMessage(), [
                    'query' => $queryText,
                ]);

                return response()->json([
                    'success' => true,
                    'ai_filters' => null,
                    'message' => 'No matching jobs found. Try a clearer search.',
                    'fallback' => true,
                    'data' => [],
                ]);
            }
        }
    }

    private function applyBasicJobFilters($query, $queryText, array $parsedFilters = [])
    {
        $resolved = $this->resolveBasicSearchFilters(
            (string) $queryText,
            $parsedFilters,
            'title',
        );

        if ($resolved['role']) {
            $this->applyJobRoleFilter($query, $resolved['role']);
        }

        if ($resolved['location']) {
            $this->applyJobLocationFilter($query, $resolved['location']);
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Staff Availability & Hire Me Methods
    |--------------------------------------------------------------------------
    */

    public function updateAvailability(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);

            $isAvailable = filter_var($request->input('is_available', false), FILTER_VALIDATE_BOOLEAN);
            $isJobSeeking = filter_var($request->input('is_job_seeking', false), FILTER_VALIDATE_BOOLEAN);

            $updateData = [];

            // Only update if columns exist (safety check)
            if (Schema::hasColumn('users', 'is_available')) {
                $updateData['is_available'] = $isAvailable;
            }
            
            if (Schema::hasColumn('users', 'is_job_seeking')) {
                $updateData['is_job_seeking'] = $isJobSeeking;
            }

            // If both columns missing, fallback to is_active (old logic)
            if (empty($updateData)) {
                $updateData['is_active'] = $isAvailable ? 1 : 0;
            }

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Availability updated successfully',
                'data' => [
                    'is_available' => (bool)$isAvailable,
                    'is_job_seeking' => (bool)$isJobSeeking,
                    'is_active' => (bool)$user->is_active,
                    'status' => $isAvailable ? 'active' : 'paused'
                ]
            ], 200);
        } catch (\Exception $e) {
            \Log::error('updateAvailability error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error during update',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAvailabilityStatus()
    {
        $user = Auth::user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);

        $isAvailable = Schema::hasColumn('users', 'is_available') ? (bool)$user->is_available : (bool)$user->is_active;
        $isJobSeeking = Schema::hasColumn('users', 'is_job_seeking') ? (bool)$user->is_job_seeking : (bool)$user->is_active;

        return response()->json([
            'success' => true,
            'data' => [
                'is_available' => $isAvailable,
                'is_job_seeking' => $isJobSeeking,
                'status' => $isAvailable ? 'active' : 'paused'
            ]
        ]);
    }

    public function optInHireMe(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);

        // Update profile with hire me details if provided
        $updateData = [
            'is_active' => 1,
            'status' => 'active'
        ];

        if ($request->filled('city')) $updateData['current_city'] = $request->city;
        if ($request->filled('experience')) $updateData['years_of_experience'] = $request->experience;

        if (Schema::hasColumn('users', 'is_available')) $updateData['is_available'] = true;
        if (Schema::hasColumn('users', 'is_job_seeking')) $updateData['is_job_seeking'] = true;

        $user->update($updateData);

        // Also update UserWorkInfo if role or city is provided
        if ($request->filled('role') || $request->filled('city')) {
            $workInfoData = [];
            if ($request->filled('role')) $workInfoData['primary_role'] = $request->role;
            if ($request->filled('city')) $workInfoData['preferred_work_location'] = $request->city;

            UserWorkInfo::updateOrCreate(
                ['user_id' => $user->id],
                $workInfoData
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'You are now listed for hire',
            'data' => [
                'status' => 'active',
                'is_available' => true,
                'is_job_seeking' => true
            ]
        ]);
    }

    public function pauseHireMe()
    {
        $user = Auth::user();
        $user->update(['is_active' => 0, 'status' => 'paused']);
        
        if (Schema::hasColumn('users', 'is_available')) $user->update(['is_available' => false]);
        if (Schema::hasColumn('users', 'is_job_seeking')) $user->update(['is_job_seeking' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Profile paused',
            'data' => ['status' => 'paused']
        ]);
    }

    public function deactivateHireMe()
    {
        $user = Auth::user();
        $user->update(['is_active' => 0, 'status' => 'inactive']);

        if (Schema::hasColumn('users', 'is_available')) $user->update(['is_available' => false]);
        if (Schema::hasColumn('users', 'is_job_seeking')) $user->update(['is_job_seeking' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Profile deactivated',
            'data' => ['status' => 'inactive']
        ]);
    }


    public function getActiveTodayUser()
    {
        try {
            $user = Auth::guard('api')->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            // Get all staff members hired by this user (accepted, approved, active, or hired applications)
            $hiredStatuses = ['accepted', 'approved', 'active', 'hired'];
            
            $hiredStaffIds = JobApplication::whereIn('application_status', $hiredStatuses)
                ->whereHas('job', function($query) use ($user) {
                    $query->where('created_by', $user->id);
                })
                ->pluck('user_id')
                ->toArray();

            // Also include staff added directly by this user (if any) or where is_staff_added is 1
            $directlyAddedStaffIds = User::where('user_role_id', 2)
                ->where(function($query) use ($user) {
                    $query->where('added_by', $user->id)
                          ->orWhere('is_staff_added', 1); // Broaden to find any staff flagged as added
                })
                ->where('added_by', $user->id) // Re-narrow to ensure it's THIS user's staff
                ->pluck('id')
                ->toArray();

            $terminatedUserIds = \App\Models\Termination::where('reported_by', $user->id)
                ->pluck('user_id')
                ->toArray();

            $allStaffIds = array_unique(array_merge($hiredStaffIds, $directlyAddedStaffIds));
            $allStaffIds = array_diff($allStaffIds, $terminatedUserIds);

            if (empty($allStaffIds)) {
                return response()->json([
                    'success' => true,
                    'active_staff' => [],
                    'status' => ['date' => now()->toDateString()]
                ]);
            }

            $today = now()->toDateString();

            $staffMembers = User::with(['attendance_details' => function($query) use ($today) {
                    $query->where('date', $today);
                }, 'userWorkInfo'])
                ->whereIn('id', $allStaffIds)
                ->where('is_active', 1)
                ->where('is_deleted', 0)
                ->get()
                ->map(function($staff) use ($user, $today) {
                    $attendance = $staff->attendance_details->first();

                    // Lazy auto-attendance fallback: If the employer has auto-attendance enabled, 
                    // and no record exists, dynamically create it to cover for missed crons or late toggles.
                    $autoEnabled = $user->auto_attendence == "1" || $user->auto_attendence == 1 || $user->auto_attendence === true;
                    if (!$attendance && $autoEnabled) {
                        $rawDays = $staff->userWorkInfo?->working_days ?? ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                        $workingDays3 = array_map(fn($d) => substr(strtolower($d), 0, 3), $rawDays);
                        $today3 = substr(strtolower(now()->format('l')), 0, 3);
                        
                        if (in_array($today3, $workingDays3)) {
                            try {
                                $attendance = \App\Models\Attendance::create([
                                    'staff_id'      => $staff->id,
                                    'date'          => $today,
                                    'check_in_time' => '07:00:00',
                                    'status'        => 'present',
                                    'description'   => 'Auto-marked by system (Dynamic)',
                                    'processed_by'  => 1,
                                ]);
                            } catch (\Exception $e) {
                                // Silent fail if duplicate insertion happens concurrently
                            }
                        }
                    }

                    return [
                        'id' => $staff->id,
                        'name' => $staff->first_name . ' ' . $staff->last_name,
                        'first_name' => $staff->first_name,
                        'last_name' => $staff->last_name,
                        'image' => $staff->image ? ((strpos($staff->image, 'http') !== false) ? $staff->image : url($staff->image)) : null,
                        'staff' => $staff, // Include full staff object for frontend compatibility
                        'attendance_details' => $attendance ?: [
                            'status' => 'absent', // Default to absent if no record for today
                            'date' => $today
                        ]
                    ];
                });

            return response()->json([
                'success' => true,
                'active_staff' => $staffMembers,
                'status' => [
                    'date' => $today
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('getActiveTodayUser failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
