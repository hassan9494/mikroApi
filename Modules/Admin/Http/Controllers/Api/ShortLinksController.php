<?php


namespace Modules\Admin\Http\Controllers\Api;

use App\Traits\Datatable;
use Illuminate\Http\JsonResponse;
use Modules\Admin\Http\Resources\MediaResource;
use Modules\Admin\Http\Resources\ShortLinkClicksResource;
use Modules\Admin\Http\Resources\ShortLinkResource;
use Modules\Common\Entities\ShortLink;
use Modules\Common\Entities\ShortLinkClickSummary;
use Modules\Common\Repositories\ShortLinks\ShortLinksRepositoryInterface;
use Modules\Shop\Http\Resources\CategoryResource;
use Psy\Util\Str;
use Illuminate\Http\Request;

class ShortLinksController extends ApiAdminController
{

    /**
     * PromotionRepositoryInterface constructor.
     * @param ShortLinksRepositoryInterface $repository
     */
    public function __construct(ShortLinksRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function show($id)
    {
        $model = $this->repository->findOrFail($id);
        return $this->success([
            'id' => $model->id,
            'name' => $model->name,
            'link' => $model->link,
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function datatable(): JsonResponse
    {
        return Datatable::make($this->repository->model())
            ->search('id', 'name', 'link')

            ->resource(ShortLinkResource::class)
            ->json();
    }

    public function store(): JsonResponse
    {
        $data = $this->validate();
        do {
            $data['short_id'] = substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 6)), 0, 6);
        } while (\Modules\Common\Entities\ShortLink::where('short_id', $data['short_id'])->exists());
        $model = $this->repository->create($data);
        return $this->success(
            $model
        );
    }


    public function update($id): JsonResponse
    {
        $data = $this->validate();
        $model = $this->repository->update($id, $data);
        return $this->success(
            $model
        );
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        return request()->validate([
            'name' => 'required',
            'link' => 'required',
        ]);
    }

    public function goToLink($short_id, Request $request)
    {
     $shortLink = ShortLink::where('short_id',$short_id)->first();
        $ip = $request->ip();

        // Get country using built-in PHP or simple API
        $country = $this->getCountrySimple($ip);

        // Track the click
        $shortLink->trackClick($ip, $country);

        // Redirect to original link
        return redirect($shortLink->link);
    }
    /**
     * Simple country detection without packages
     * This uses ip-api.com (free, no API key needed for basic info)
     * Alternative: Use a local database or skip country detection
     */
    private function getCountrySimple($ip)
    {
        // Skip local IPs
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return 'Localhost';
        }

        // Use ip-api.com (free, 45 requests per minute)
        $url = "http://ip-api.com/json/{$ip}?fields=country";

        try {
            $response = @file_get_contents($url);
            if ($response !== false) {
                $data = json_decode($response, true);
                return $data['country'] ?? null;
            }
        } catch (\Exception $e) {
            // Silent fail - country is optional
        }

        return null;
    }

    /**
     * Alternative: Even simpler - just store IP, no country
     */
    private function getCountryEvenSimpler($ip)
    {
        return null; // Just don't track country
    }

    /**
     * View click statistics
     */
    public function stats($shortId)
    {
        $shortLink = ShortLink::where('short_id', $shortId)
            ->with(['clickSummaries' => function($query) {
                $query->with('individualClicks')
                    ->orderBy('click_count', 'desc');
            }])
            ->firstOrFail();

        // Get click times for each IP
        $clickDetails = [];
        foreach ($shortLink->clickSummaries as $summary) {
            $clickDetails[] = [
                'ip' => $summary->ip_address,
                'country' => $summary->country,
                'total_clicks' => $summary->click_count,
                'click_times' => $summary->getClickTimes(),
                'first_click' => $summary->created_at,
                'last_click' => $summary->updated_at
            ];
        }

        return view('shortlink.stats', [
            'shortLink' => $shortLink,
            'clickDetails' => $clickDetails,
            'stats' => $shortLink->getClickStats()
        ]);
    }

}
