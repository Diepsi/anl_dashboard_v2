<?php

namespace App\Http\Controllers;

use App\Services\VendorClusteringService;
use Illuminate\Contracts\View\View;

class VendorAnalyticsController extends Controller
{
    public function __construct(private VendorClusteringService $clustering) {}

    public function index(): View
    {
        $payload = $this->clustering->build();

        return view('analytics.vendor-clustering', [
            'clusters' => $payload['clusters'],
            'undecided' => $payload['undecided'],
            'method' => $payload['method'],
        ]);
    }
}
