<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReconciliationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReconciliationService::class);
    }

    public function test_system_totals_calculation()
    {
        $totals = $this->service->getSystemTotals();

        $this->assertIsArray($totals);
        $this->assertArrayHasKey('master_bank', $totals);
        $this->assertArrayHasKey('master_fund', $totals);
        $this->assertArrayHasKey('user_banks_total', $totals);
        $this->assertArrayHasKey('outstanding_loans_total', $totals);
    }
}
