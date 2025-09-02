<?php

namespace Modules\Admin\Http\Controllers\Api;


use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Repositories\Role\RoleRepository;
use Modules\Shop\Entities\Setting;

class SettingController extends ApiAdminController
{

    /**
     * ArticleRepositoryInterface constructor.
     */
    public function __construct()
    {

    }

    public function show($id)
    {
        try {
            $setting = Setting::find($id);

            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting not found'
                ], 404);
            }

            $elasticBaseUrl = 'http://134.209.88.205:9200';
            $auth = ['elastic', 'MIkroelectron@123mikro'];

            // Get multiple Elasticsearch endpoints for comprehensive monitoring
            $endpoints = [
                'cluster_health' => '/_cluster/health',
                'last_products_stats' => '/last_products/_stats',
                'last_products_health' => '/last_products/_health',
                'last_products_settings' => '/last_products/_settings',
                'last_products_mapping' => '/last_products/_mapping',
                'nodes_stats' => '/_nodes/stats',
                'indices_stats' => '/_stats'
            ];

            $elasticData = [];

            foreach ($endpoints as $key => $endpoint) {
                try {
                    $response = Http::withBasicAuth($auth[0], $auth[1])
                        ->timeout(15)
                        ->get($elasticBaseUrl . $endpoint);

                    if ($response->successful()) {
                        $elasticData[$key] = $response->json();
                    } else {
                        $elasticData[$key] = [
                            'error' => 'Failed to fetch ' . $key,
                            'status' => $response->status()
                        ];
                    }
                } catch (\Exception $e) {
                    $elasticData[$key] = [
                        'error' => 'Exception fetching ' . $key,
                        'message' => $e->getMessage()
                    ];
                }

                // Small delay between requests
                usleep(100000); // 0.1 second
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'setting' => $setting,
                    'elastic_monitoring' => $elasticData
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching monitoring data: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching monitoring data',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function update($id): JsonResponse
    {
        $data = $this->validate();
        $setting = Setting::find($id);
        $setting->value = $data['searchType'];
        $setting->save();
        return $this->success($setting);
    }

    // Add this method to your SettingController.php
    public function fixClusterHealth(Request $request)
    {
        try {
            $elasticBaseUrl = 'http://134.209.88.205:9200';
            $auth = ['elastic', 'MIkroelectron@123mikro'];

            // 1. Set replicas to 0 for all indices
            $replicaResponse = Http::withBasicAuth($auth[0], $auth[1])
                ->timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->put($elasticBaseUrl . '/last_products,test_products,products/_settings', [
                    'index' => [
                        'number_of_replicas' => 0
                    ]
                ]);

            // 2. Clear cache
            $cacheResponse = Http::withBasicAuth($auth[0], $auth[1])
                ->timeout(30)
                ->post($elasticBaseUrl . '/last_products,test_products,products/_cache/clear');

            // 3. Refresh indices
            $refreshResponse = Http::withBasicAuth($auth[0], $auth[1])
                ->timeout(30)
                ->post($elasticBaseUrl . '/last_products,test_products,products/_refresh');

            return response()->json([
                'success' => true,
                'data' => [ // Wrap in 'data' to match your expected structure
                    'message' => 'Cluster health optimization initiated',
                    'results' => [
                        'replicas' => $replicaResponse->successful() ? 'Success' : 'Failed',
                        'cache_clear' => $cacheResponse->successful() ? 'Success' : 'Failed',
                        'refresh' => $refreshResponse->successful() ? 'Success' : 'Failed'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fixing cluster health: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error optimizing cluster health',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'searchType' => 'required|max:255',
        ]);
    }

}
