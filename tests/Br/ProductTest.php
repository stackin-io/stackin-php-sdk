<?php

declare(strict_types=1);

namespace Stackin\Tests\Br;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stackin\Br\Fuel;
use Stackin\Br\Product;
use Stackin\Errors\InvoiceError;

final class ProductTest extends TestCase
{
    public function testMinimalProduct(): void
    {
        $product = new Product(description: 'Servico basico', amount: 100.0);
        $data = $product->toArray();

        $this->assertSame('Servico basico', $data['description']);
        $this->assertSame('100', $data['amount']);
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
            tax: ['icms' => ['orig' => '0', 'cst' => '00']],
        );
        $data = $product->toArray();

        $this->assertSame(
            ['icms' => ['orig' => '0', 'cst' => '00']],
            $data['product']['br']['tax'],
        );
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
            recopiNumber: '20131106114609734393',
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
        $this->assertSame('20131106114609734393', $data['recopi_number']);
    }

    public function testUnitPriceIsSentWhenGiven(): void
    {
        $product = new Product(
            description: 'Teclado',
            quantity: 2.0,
            unitPrice: 120.00,
            unit: 'UN',
        );

        $data = $product->toArray();

        $this->assertSame('120', $data['unit_price']);
        $this->assertArrayNotHasKey('amount', $data);
    }

    public function testTheLegacyLineStillSendsATotal(): void
    {
        $product = new Product(description: 'Servico', amount: 100.00, quantity: 3.0);

        $data = $product->toArray();

        $this->assertSame('100', $data['amount']);
        $this->assertArrayNotHasKey('unit_price', $data);
    }

    public function testBothMayBeSentWhenTheyAgree(): void
    {
        $product = new Product(
            description: 'Teclado',
            amount: 240.00,
            quantity: 2.0,
            unitPrice: 120.00,
        );

        $data = $product->toArray();

        $this->assertSame('120', $data['unit_price']);
        $this->assertSame('240', $data['amount']);
    }

    public function testTheTenthPlaceSurvivesTheWire(): void
    {
        $product = new Product(
            description: 'Granel',
            quantity: 1.0,
            unitPrice: 0.0000000001,
        );

        $this->assertSame('0.0000000001', $product->toArray()['unit_price']);
    }

    public function testALineMayCarryNeither(): void
    {
        $data = (new Product(description: 'Servico'))->toArray();

        $this->assertArrayNotHasKey('amount', $data);
        $this->assertArrayNotHasKey('unit_price', $data);
    }

    public function testIbsCbsNestsUnderBr(): void
    {
        $product = new Product(
            description: 'Teclado',
            quantity: 2.0,
            unitPrice: 120.00,
            ncm: '84716052',
            cfop: '5102',
            ibsCbs: [
                'cst' => '000',
                'classification' => '000001',
                'rate_state' => 0.1,
                'rate_city' => 0.0,
                'rate_federal' => 0.9,
            ],
        );

        $br = $product->toArray()['product']['br'];

        $this->assertSame('000', $br['ibs_cbs']['cst']);
        $this->assertSame('000001', $br['ibs_cbs']['classification']);
    }

    public function testAnItemWithoutIbsCbsSendsNothing(): void
    {
        $product = new Product(description: 'Teclado', unitPrice: 10.00);

        $this->assertArrayNotHasKey('br', $product->toArray()['product']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validNcms(): array
    {
        return [
            'eight digits' => ['84713012'],
            'service line' => ['00'],
        ];
    }

    #[DataProvider('validNcms')]
    public function testAcceptsAnEightDigitNcmOr00(string $ncm): void
    {
        $product = new Product(description: 'Teclado', amount: 10.0, ncm: $ncm);

        $this->assertSame($ncm, $product->toArray()['product']['br']['ncm']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNcms(): array
    {
        return [
            'two-digit chapter 84' => ['84'],
            'two-digit chapter 12' => ['12'],
            'seven digits' => ['8471301'],
            'nine digits' => ['847130120'],
            'with dots' => ['8471.30.12'],
            'empty' => [''],
        ];
    }

    #[DataProvider('invalidNcms')]
    public function testRejectsAnNcmThatIsNotEightDigitsOr00(string $ncm): void
    {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage(
            'ncm must be the full 8 digits, or 00 for a service line',
        );

        new Product(description: 'Teclado', amount: 10.0, ncm: $ncm);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validBarcodes(): array
    {
        return [
            'sem gtin' => ['SEM GTIN'],
            'gtin-8' => ['96385074'],
            'gtin-12' => ['036000291452'],
            'gtin-13' => ['4006381333931'],
            'gtin-14' => ['17891234567892'],
        ];
    }

    #[DataProvider('validBarcodes')]
    public function testAcceptsAValidBarcode(string $barcode): void
    {
        $product = new Product(description: 'Teclado', amount: 10.0, barcode: $barcode);

        $this->assertSame($barcode, $product->toArray()['product']['barcode']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedBarcodes(): array
    {
        return [
            'seven digits' => ['1234567'],
            'nine digits' => ['123456789'],
            'eleven digits' => ['12345678901'],
            'fifteen digits' => ['123456789012345'],
            'lowercase sem gtin' => ['sem gtin'],
            'letters' => ['ABCDEFGH'],
            'empty' => [''],
        ];
    }

    #[DataProvider('malformedBarcodes')]
    public function testRejectsAMalformedBarcode(string $barcode): void
    {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage("barcode must be 'SEM GTIN' or a GTIN of 8, 12, 13 or 14");

        new Product(description: 'Teclado', amount: 10.0, barcode: $barcode);
    }

    /**
     * GS1 modulo-10, computed independently of the SDK so the vectors are
     * not just echoing the implementation.
     */
    private static function checkDigit(string $body): int
    {
        $digits = array_reverse(str_split($body));
        $sum = 0;
        foreach ($digits as $position => $digit) {
            $sum += (int) $digit * ($position % 2 === 0 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10;
    }

    public function testTheVectorsCarryTheirComputedCheckDigit(): void
    {
        $this->assertSame(4, self::checkDigit('9638507'));
        $this->assertSame(2, self::checkDigit('03600029145'));
        $this->assertSame(1, self::checkDigit('400638133393'));
        $this->assertSame(5, self::checkDigit('789123456789'));
        $this->assertSame(2, self::checkDigit('1789123456789'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function wrongCheckDigits(): array
    {
        return [
            'gtin-8' => ['96385075'],
            'gtin-12' => ['036000291453'],
            'gtin-13' => ['4006381333932'],
            'gtin-14 from the issue' => ['17891234567894'],
            'gtin-14 off by one' => ['17891234567891'],
        ];
    }

    #[DataProvider('wrongCheckDigits')]
    public function testRejectsAWrongGtinCheckDigit(string $barcode): void
    {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage('barcode has an invalid GTIN check digit');

        new Product(description: 'Teclado', amount: 10.0, barcode: $barcode);
    }

    public function testAcceptsAValidGtin13(): void
    {
        $product = new Product(description: 'Teclado', amount: 10.0, barcode: '7891234567895');

        $this->assertSame('7891234567895', $product->toArray()['product']['barcode']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validRecopiNumbers(): array
    {
        return [
            'moc 8.5.3 example' => ['20131106114609734393'],
            'remainders 0 and 1 give 0' => ['00000000000012345600'],
        ];
    }

    #[DataProvider('validRecopiNumbers')]
    public function testAcceptsAValidRecopiNumber(string $recopi): void
    {
        $product = new Product(description: 'Papel', amount: 10.0, recopiNumber: $recopi);

        $this->assertSame($recopi, $product->toArray()['product']['br']['recopi_number']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function wrongRecopiCheckDigits(): array
    {
        return [
            'bad dv2' => ['20131106114609734394'],
            'bad dv1' => ['20131106114609734383'],
        ];
    }

    #[DataProvider('wrongRecopiCheckDigits')]
    public function testRejectsWrongRecopiCheckDigits(string $recopi): void
    {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage('recopiNumber has invalid check digits');

        new Product(description: 'Papel', amount: 10.0, recopiNumber: $recopi);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedRecopiNumbers(): array
    {
        return [
            '19 digits' => ['2013110611460973439'],
            '21 digits' => ['201311061146097343930'],
            'letters' => ['2013110611460973439A'],
            'trailing newline' => ["20131106114609734393\n"],
        ];
    }

    #[DataProvider('malformedRecopiNumbers')]
    public function testRejectsARecopiNumberThatIsNot20Digits(string $recopi): void
    {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage('recopiNumber must have exactly 20 digits');

        new Product(description: 'Papel', amount: 10.0, recopiNumber: $recopi);
    }

    private function gasoline(): Fuel
    {
        return new Fuel(
            anpCode: '320102001',
            anpDescription: 'GASOLINA C COMUM',
            consumerState: 'SC',
        );
    }

    public function testFuelGoesUnderBrInSnakeCase(): void
    {
        $product = new Product(
            description: 'GLP',
            amount: 120.0,
            ncm: '27111910',
            cfop: '5656',
            fuel: new Fuel(
                anpCode: '210203001',
                anpDescription: 'GLP',
                consumerState: 'SC',
                glpPercent: 60.0,
                naturalGasNationalPercent: 30.0,
                naturalGasImportedPercent: 10.0,
                startingValue: 95.5,
                codif: '123456',
                temperatureQuantity: 13.0,
                biodieselPercent: 0.0,
            ),
        );

        $this->assertSame(
            [
                'anp_code' => '210203001',
                'anp_description' => 'GLP',
                'consumer_state' => 'SC',
                'glp_percent' => 60.0,
                'natural_gas_national_percent' => 30.0,
                'natural_gas_imported_percent' => 10.0,
                'starting_value' => 95.5,
                'codif' => '123456',
                'temperature_quantity' => 13.0,
                'biodiesel_percent' => 0.0,
            ],
            $product->toArray()['product']['br']['fuel'],
        );
    }

    public function testFuelSendsOnlyTheRequiredFieldsWhenTheRestAreUnset(): void
    {
        $this->assertSame(
            [
                'anp_code' => '320102001',
                'anp_description' => 'GASOLINA C COMUM',
                'consumer_state' => 'SC',
            ],
            $this->gasoline()->toArray(),
        );
    }

    public function testOmitsFuelWhenUnset(): void
    {
        $product = new Product(
            description: 'Produto',
            amount: 50.0,
            ncm: '84713012',
            cfop: '5102',
        );

        $this->assertArrayNotHasKey('fuel', $product->toArray()['product']['br']);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function fuelGroupInExtraGroups(): array
    {
        return [
            'comb merged into br' => [['comb' => ['cProdANP' => '320102001']]],
            'comb under extra_groups' => [
                ['extra_groups' => ['comb' => ['cProdANP' => '320102001']]],
            ],
            'fuel merged into br' => [['fuel' => ['anp_code' => '320102001']]],
        ];
    }

    /**
     * @param array<string, mixed> $extraGroups
     */
    #[DataProvider('fuelGroupInExtraGroups')]
    public function testRefusesFuelTogetherWithACombGroup(array $extraGroups): void
    {
        $this->expectException(InvoiceError::class);
        $this->expectExceptionMessage('send the fuel group once');

        new Product(
            description: 'Gasolina',
            amount: 10.0,
            ncm: '27101259',
            cfop: '5656',
            extraGroups: $extraGroups,
            fuel: $this->gasoline(),
        );
    }

    public function testKeepsOtherExtraGroupsBesideFuel(): void
    {
        $product = new Product(
            description: 'Gasolina',
            amount: 10.0,
            ncm: '27101259',
            cfop: '5656',
            extraGroups: ['custom_field' => 'value'],
            fuel: $this->gasoline(),
        );

        $br = $product->toArray()['product']['br'];
        $this->assertSame('value', $br['custom_field']);
        $this->assertSame('320102001', $br['fuel']['anp_code']);
    }
}
