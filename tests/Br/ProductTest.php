<?php

declare(strict_types=1);

namespace Stackin\Tests\Br;

use PHPUnit\Framework\TestCase;
use Stackin\Br\Product;

final class ProductTest extends TestCase
{
    public function testMinimalProduct(): void
    {
        $product = new Product(description: 'Servico basico', amount: 100.0);
        $data = $product->toArray();

        $this->assertSame('Servico basico', $data['description']);
        $this->assertSame(100.0, $data['amount']);
        $this->assertSame(
            ['unit' => 'UN', 'quantity' => 1.0, 'used_movable_asset' => false],
            $data['product'],
        );
        $this->assertArrayNotHasKey('service_code', $data);
        $this->assertArrayNotHasKey('observations', $data);
        $this->assertFalse($data['tax_retained']);
    }

    public function testBrFieldsNestUnderBr(): void
    {
        $product = new Product(
            description: 'Produto',
            amount: 50.0,
            ncm: '84713012',
            cfop: '5102',
            cest: '0300700',
        );
        $data = $product->toArray();

        $this->assertSame('84713012', $data['product']['br']['ncm']);
        $this->assertSame('5102', $data['product']['br']['cfop']);
        $this->assertSame('0300700', $data['product']['br']['cest']);
    }

    public function testOmitsBrWhenEmpty(): void
    {
        $product = new Product(description: 'Servico', amount: 10.0);
        $data = $product->toArray();

        $this->assertArrayNotHasKey('br', $data['product']);
    }

    public function testPresumedCredits(): void
    {
        $product = new Product(
            description: 'Produto',
            amount: 50.0,
            ncm: '84713012',
            cfop: '5102',
            presumedCredits: [['code' => 'PR820001', 'percentage' => 3.0, 'amount' => 2.40]],
        );
        $data = $product->toArray();

        $this->assertSame(
            [['code' => 'PR820001', 'percentage' => 3.0, 'amount' => 2.40]],
            $data['product']['br']['presumed_credits'],
        );
    }

    public function testExtraGroupsMergedIntoBr(): void
    {
        $product = new Product(
            description: 'Produto',
            amount: 50.0,
            ncm: '84713012',
            cfop: '5102',
            extraGroups: ['custom_field' => 'value'],
        );
        $data = $product->toArray();

        $this->assertSame('value', $data['product']['br']['custom_field']);
    }

    public function testRawTaxArrayPassedThrough(): void
    {
        $product = new Product(
            description: 'Produto',
            amount: 50.0,
            ncm: '84713012',
            cfop: '5102',
            tax: ['ICMS' => ['ICMS00' => ['orig' => '0']]],
        );
        $data = $product->toArray();

        $this->assertSame(['ICMS' => ['ICMS00' => ['orig' => '0']]], $data['product']['br']['tax']);
    }

    public function testNfseFields(): void
    {
        $product = new Product(
            description: 'Consultoria',
            amount: 1500.0,
            serviceCode: '1.06',
            serviceDiscount: 50.0,
            taxRetained: true,
            observations: 'Nota de teste',
        );
        $data = $product->toArray();

        $this->assertSame('1.06', $data['service_code']);
        $this->assertSame(50.0, $data['discount']);
        $this->assertTrue($data['tax_retained']);
        $this->assertSame('Nota de teste', $data['observations']);
        $this->assertArrayNotHasKey('br', $data['product']);
    }

    public function testQuantityAndExtraExpenses(): void
    {
        $product = new Product(
            description: 'Produto',
            amount: 10.0,
            unit: 'CX',
            quantity: 20.0,
            barcode: '7891000100103',
            freight: 15.0,
            insurance: 5.0,
            discount: 10.0,
            otherExpenses: 3.5,
            usedMovableAsset: true,
            purchaseOrder: 'PC-1',
            purchaseOrderItem: '1',
        );
        $data = $product->toArray()['product'];

        $this->assertSame('CX', $data['unit']);
        $this->assertSame(20.0, $data['quantity']);
        $this->assertSame('7891000100103', $data['barcode']);
        $this->assertSame(15.0, $data['freight']);
        $this->assertSame(5.0, $data['insurance']);
        $this->assertSame(10.0, $data['discount']);
        $this->assertSame(3.5, $data['other_expenses']);
        $this->assertTrue($data['used_movable_asset']);
        $this->assertSame('PC-1', $data['purchase_order']);
        $this->assertSame('1', $data['purchase_order_item']);
    }

    public function testRemainingBrFields(): void
    {
        $product = new Product(
            description: 'Produto',
            amount: 1.0,
            ncm: '84713012',
            cfop: '5102',
            nveCodes: ['NV0001', 'NV0002'],
            indEscala: 'N',
            manufacturerCnpj: '12345678000195',
            taxBenefitCode: 'PR820001',
            exTipi: '01',
            importContentControlNumber: '550E8400-E29B-41D4-A716-446655440000',
            recopiNumber: '00000000000012345678',
        );
        $data = $product->toArray()['product']['br'];

        $this->assertSame(['NV0001', 'NV0002'], $data['nve_codes']);
        $this->assertSame('N', $data['ind_escala']);
        $this->assertSame('12345678000195', $data['manufacturer_cnpj']);
        $this->assertSame('PR820001', $data['tax_benefit_code']);
        $this->assertSame('01', $data['ex_tipi']);
        $this->assertSame(
            '550E8400-E29B-41D4-A716-446655440000',
            $data['import_content_control_number'],
        );
        $this->assertSame('00000000000012345678', $data['recopi_number']);
    }
}
