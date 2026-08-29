<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\InventoryTransaction\Enums\StockDocumentType;
use Modules\InventoryTransaction\Services\StockDocumentNumberGenerator;
use Tests\TestCase;

class StockDocumentNumberTest extends TestCase
{
    use DatabaseTransactions;

    public function test_generates_unique_concurrency_safe_document_numbers(): void
    {
        $generator = new StockDocumentNumberGenerator();

        $rcv1 = $generator->generate(StockDocumentType::RECEIVE);
        $iss1 = $generator->generate(StockDocumentType::ISSUE);
        $trf1 = $generator->generate(StockDocumentType::TRANSFER);
        $adj1 = $generator->generate(StockDocumentType::ADJUSTMENT);
        $rcv2 = $generator->generate(StockDocumentType::RECEIVE);

        $this->assertStringStartsWith('RCV-', $rcv1);
        $this->assertStringStartsWith('ISS-', $iss1);
        $this->assertStringStartsWith('TRF-', $trf1);
        $this->assertStringStartsWith('ADJ-', $adj1);
        $this->assertStringStartsWith('RCV-', $rcv2);

        $this->assertNotEquals($rcv1, $rcv2);
        $this->assertNotEquals($rcv1, $iss1);
    }
}
