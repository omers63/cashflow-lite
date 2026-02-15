<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_id_generation()
    {
        $id1 = Transaction::generateTransactionId();
        $id2 = Transaction::generateTransactionId();

        $this->assertNotEquals($id1, $id2);
        $this->assertStringStartsWith('GEN-', $id1);
    }

    public function test_transaction_id_generation_with_prefix()
    {
        $id = Transaction::generateTransactionId('TEST');

        $this->assertStringStartsWith('TEST-', $id);
    }
}
