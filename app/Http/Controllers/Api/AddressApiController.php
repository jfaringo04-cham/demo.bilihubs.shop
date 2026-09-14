<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AddressApiController extends Controller
{
    private string $baseUrl = 'http://psgc.cloud/api';

    private function mapItem(array $item, array $keys): array
    {
        $name = $item[$keys['name']] ?? null;
        if ($name) {
            $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
            $corrections = [
                'Los BaÃ±os' => 'Los Baños',
                'BaÃ±os' => 'Baños',
            ];
            foreach ($corrections as $wrong => $correct) {
                $name = str_replace($wrong, $correct, $name);
            }
        }
        return [
            'code' => $item[$keys['code']] ?? null,
            'name' => $name,
        ];
    }

    public function regions(): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/regions");

        if ($response->successful()) {
            $regions = collect($response->json())->map(function ($item) {
                return $this->mapItem($item, ['code' => 'code', 'name' => 'name']);
            })->filter()->sortBy('name')->values();

            return response()->json($regions);
        }

        return response()->json([]);
    }

    public function region(string $regionCode): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/regions/{$regionCode}");

        if ($response->successful()) {
            return response()->json($this->mapItem($response->json(), ['code' => 'code', 'name' => 'name']));
        }

        return response()->json(null);
    }

    public function provinces(): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/provinces");

        if ($response->successful()) {
            $provinces = collect($response->json())->map(function ($item) {
                return $this->mapItem($item, ['code' => 'code', 'name' => 'name']);
            })->filter()->sortBy('name')->values();

            return response()->json($provinces);
        }

        return response()->json([]);
    }

    public function regionProvinces(string $regionCode): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/regions/{$regionCode}/provinces");

        if ($response->successful()) {
            $items = collect($response->json())->map(function ($item) {
                return $this->mapItem($item, ['code' => 'code', 'name' => 'name']);
            })->filter()->sortBy('name')->values();

            return response()->json($items);
        }

        return response()->json([]);
    }

    public function regionCities(string $regionCode): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/regions/{$regionCode}/cities");

        if ($response->successful()) {
            $items = collect($response->json())->map(function ($item) {
                return $this->mapItem($item, ['code' => 'code', 'name' => 'name']);
            })->filter()->sortBy('name')->values();

            return response()->json($items);
        }

        return response()->json([]);
    }

    public function province(string $provinceCode): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/provinces/{$provinceCode}");

        if ($response->successful()) {
            return response()->json($this->mapItem($response->json(), ['code' => 'code', 'name' => 'name']));
        }

        return response()->json(null);
    }

    public function provinceMunicipalities(string $provinceCode): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/provinces/{$provinceCode}/municipalities");

        if ($response->successful()) {
            $items = collect($response->json())->map(function ($item) {
                return $this->mapItem($item, ['code' => 'code', 'name' => 'name']);
            })->filter()->sortBy('name')->values();

            return response()->json($items);
        }

        return response()->json([]);
    }

    public function provinceCities(string $provinceCode): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/provinces/{$provinceCode}/cities");

        if ($response->successful()) {
            $items = collect($response->json())->map(function ($item) {
                return $this->mapItem($item, ['code' => 'code', 'name' => 'name']);
            })->filter()->sortBy('name')->values();

            return response()->json($items);
        }

        return response()->json([]);
    }

    public function cities(Request $request): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/cities");
        
        if ($response->successful()) {
            $provinceCode = $request->query('province_code');
            $regionCode = $request->query('region_code');
            
            $cities = collect($response->json())->map(function ($item) {
                return $this->mapItem($item, ['code' => 'code', 'name' => 'name']);
            })->filter();

            if ($provinceCode) {
                $cities = $cities->filter(function ($item) use ($provinceCode) {
                    return str_starts_with($item['code'], $provinceCode);
                });
            } elseif ($regionCode) {
                $cities = $cities->filter(function ($item) use ($regionCode) {
                    return str_starts_with($item['code'], $regionCode);
                });
            }

            return response()->json($cities->sortBy('name')->values());
        }

        return response()->json([]);
    }

    public function city(string $cityCode): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/cities/{$cityCode}");

        if ($response->successful()) {
            return response()->json($this->mapItem($response->json(), ['code' => 'code', 'name' => 'name']));
        }

        return response()->json(null);
    }

    public function cityBarangays(string $cityCode): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/cities/{$cityCode}/barangays");

        if ($response->successful()) {
            $items = collect($response->json())->map(function ($item) {
                return $this->mapItem($item, ['code' => 'code', 'name' => 'name']);
            })->filter()->sortBy('name')->values();

            return response()->json($items);
        }

        return response()->json([]);
    }

    public function municipalities(Request $request): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/municipalities");
        
        if ($response->successful()) {
            $provinceCode = $request->query('province_code');
            $regionCode = $request->query('region_code');
            
            $municipalities = collect($response->json())->map(function ($item) {
                return $this->mapItem($item, ['code' => 'code', 'name' => 'name']);
            })->filter();

            if ($provinceCode) {
                $municipalities = $municipalities->filter(function ($item) use ($provinceCode) {
                    return str_starts_with($item['code'], $provinceCode);
                });
            } elseif ($regionCode) {
                $municipalities = $municipalities->filter(function ($item) use ($regionCode) {
                    return str_starts_with($item['code'], $regionCode);
                });
            }

            return response()->json($municipalities->sortBy('name')->values());
        }

        return response()->json([]);
    }

    public function municipality(string $municipalityCode): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/municipalities/{$municipalityCode}");

        if ($response->successful()) {
            return response()->json($this->mapItem($response->json(), ['code' => 'code', 'name' => 'name']));
        }

        return response()->json(null);
    }

    public function municipalityBarangays(string $municipalityCode): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/municipalities/{$municipalityCode}/barangays");

        if ($response->successful()) {
            $items = collect($response->json())->map(function ($item) {
                return $this->mapItem($item, ['code' => 'code', 'name' => 'name']);
            })->filter()->sortBy('name')->values();

            return response()->json($items);
        }

        return response()->json([]);
    }

    public function barangays(Request $request): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/barangays");
        
        if ($response->successful()) {
            $municipalityCode = $request->query('municipality_code');
            $cityCode = $request->query('city_code');
            $provinceCode = $request->query('province_code');
            
            $barangays = collect($response->json())->map(function ($item) {
                return $this->mapItem($item, ['code' => 'code', 'name' => 'name']);
            })->filter();

            if ($municipalityCode) {
                $barangays = $barangays->filter(function ($item) use ($municipalityCode) {
                    return str_starts_with($item['code'], $municipalityCode);
                });
            } elseif ($cityCode) {
                $barangays = $barangays->filter(function ($item) use ($cityCode) {
                    return str_starts_with($item['code'], $cityCode);
                });
            } elseif ($provinceCode) {
                $barangays = $barangays->filter(function ($item) use ($provinceCode) {
                    return str_starts_with($item['code'], $provinceCode);
                });
            }

            return response()->json($barangays->sortBy('name')->values());
        }

        return response()->json([]);
    }

    public function barangay(string $barangayCode): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/barangays/{$barangayCode}");

        if ($response->successful()) {
            return response()->json($this->mapItem($response->json(), ['code' => 'code', 'name' => 'name']));
        }

        return response()->json(null);
    }

    public function districts(): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/districts");

        if ($response->successful()) {
            $districts = collect($response->json())->map(function ($item) {
                return $this->mapItem($item, ['code' => 'code', 'name' => 'name']);
            })->filter()->sortBy('name')->values();

            return response()->json($districts);
        }

        return response()->json([]);
    }

    public function district(string $districtCode): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/districts/{$districtCode}");

        if ($response->successful()) {
            return response()->json($this->mapItem($response->json(), ['code' => 'code', 'name' => 'name']));
        }

        return response()->json(null);
    }

    public function subMunicipalities(): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/sub-municipalities");

        if ($response->successful()) {
            $items = collect($response->json())->map(function ($item) {
                return $this->mapItem($item, ['code' => 'code', 'name' => 'name']);
            })->filter()->sortBy('name')->values();

            return response()->json($items);
        }

        return response()->json([]);
    }

    public function subMunicipality(string $subMunicipalityCode): JsonResponse
    {
        $response = Http::timeout(10)->withoutVerifying()->get("{$this->baseUrl}/sub-municipalities/{$subMunicipalityCode}");

        if ($response->successful()) {
            return response()->json($this->mapItem($response->json(), ['code' => 'code', 'name' => 'name']));
        }

        return response()->json(null);
    }
}
