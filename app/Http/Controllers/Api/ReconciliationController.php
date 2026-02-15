<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReconciliationService;
use App\Models\Reconciliation;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    public function __construct(
        protected ReconciliationService $service
    ) {}

    public function latest()
    {
        $latest = Reconciliation::latest('reconciliation_date')->first();
        return response()->json($latest);
    }

    public function run()
    {
        try {
            $reconciliation = $this->service->runDailyReconciliation();
            return response()->json([
                'success' => true,
                'reconciliation' => $reconciliation,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function summary()
    {
        $summary = $this->service->getReconciliationSummary();
        return response()->json($summary);
    }
}
