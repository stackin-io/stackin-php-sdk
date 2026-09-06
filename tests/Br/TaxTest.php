<?php

declare(strict_types=1);

namespace Stackin\Tests\Br;

use PHPUnit\Framework\TestCase;
use Stackin\Br\Product;
use Stackin\Br\Tax;

final class TaxTest extends TestCase
{
    public function testIcmsGroupNestsUnderItsTag(): void
    {
        $icms = Tax::icms00(
            orig: '0',
            modBC: '3',
            vBC: '100.00',
            pICMS: '18.00',
            vICMS: '18.00',
        );

        $this->assertSame([
                'orig' => '0',
                'cst' => '00',
                'mod_bc' => '3',
                'v_bc' => '100.00',
                'p_icms' => '18.00',
                'v_icms' => '18.00',
            ], $icms);
    }

    public function testEachGroupFillsTheCstItIsFixedTo(): void
    {
        $this->assertSame(
            ['orig' => '0', 'cst' => '60'],
            Tax::icms60(orig: '0'),
        );
        $this->assertSame(
            ['csosn' => '900'],
            Tax::icmsSn900(),
        );
    }

    public function testTheCallerCanOverrideTheDefaultCst(): void
    {
        $this->assertSame(
            ['orig' => '0', 'cst' => '61'],
            Tax::icms60(orig: '0', CST: '61'),
        );
    }

    public function testOmittedFieldsAreDropped(): void
    {
        $this->assertSame(
            ['csosn' => '102'],
            Tax::icmsSn102(CSOSN: '102'),
        );
    }

    public function testIpiKeepsTheVariantUnderTrib(): void
    {
        $ipi = Tax::ipi('999', Tax::ipiTrib(CST: '50', vIPI: '5.00', vBC: '100.00'));

        $this->assertSame([
            'c_enq' => '999',
            'trib' => ['cst' => '50', 'v_bc' => '100.00', 'v_ipi' => '5.00'],
        ], $ipi);
    }

    public function testIpiAcceptsTheUntaxedVariant(): void
    {
        $this->assertSame(
            ['c_enq' => '999', 'trib' => ['cst' => '53']],
            Tax::ipi('999', Tax::ipiNt('53')),
        );
    }

    public function testOnlyTheGroupsGivenAreEmitted(): void
    {
        $tax = new Tax(
            vTotTrib: '30.00',
            icms: Tax::icms40(orig: '0', CST: '40'),
            pis: Tax::pisNt('07'),
            cofins: Tax::cofinsNt('07'),
        );

        $this->assertSame(
            ['v_tot_trib', 'icms', 'pis', 'cofins'],
            array_keys($tax->toArray()),
        );
    }

    public function testAnEmptyTaxIsAnEmptyArray(): void
    {
        $this->assertSame([], (new Tax())->toArray());
    }

    public function testTheDestinationStateShareKeepsItsOwnKey(): void
    {
        $tax = new Tax(icmsUfDest: Tax::icmsUfDest(
            vBCUFDest: '100.00',
            pICMSUFDest: '18.00',
            pICMSInter: '12.00',
            pICMSInterPart: '100.00',
            vICMSUFDest: '18.00',
            vICMSUFRemet: '0.00',
        ));

        $this->assertSame([
            'icms_uf_dest' => [
                'v_bc_uf_dest' => '100.00',
                'p_icms_uf_dest' => '18.00',
                'p_icms_inter' => '12.00',
                'p_icms_inter_part' => '100.00',
                'v_icms_uf_dest' => '18.00',
                'v_icms_uf_remet' => '0.00',
            ],
        ], $tax->toArray());
    }

    public function testItRidesOnAProductAsThatItemsTax(): void
    {
        $tax = new Tax(pis: Tax::pisAliq(
            CST: '01',
            vBC: '100.00',
            pPIS: '1.65',
            vPIS: '1.65',
        ));
        $product = new Product(
            description: 'Produto',
            amount: 100.0,
            ncm: '84713012',
            cfop: '5102',
            tax: $tax->toArray(),
        );

        $this->assertSame(
            ['pis' => [
                'cst' => '01',
                'v_bc' => '100.00',
                'p_pis' => '1.65',
                'v_pis' => '1.65',
            ]],
            $product->toArray()['product']['br']['tax'],
        );
    }

    public function testAMonofasicoGroupCarriesItsOwnFields(): void
    {
        $this->assertSame([
                'orig' => '0',
                'cst' => '02',
                'ad_rem_icms' => '0.1234',
                'v_icms_mono' => '1.23',
            ], Tax::icms02(orig: '0', adRemICMS: '0.1234', vICMSMono: '1.23'));
    }

    public function testPartilhaNamesTheDestinationState(): void
    {
        $group = Tax::icmsPart(
            orig: '0',
            CST: '10',
            modBC: '3',
            vBC: '100.00',
            pICMS: '18.00',
            vICMS: '18.00',
            modBCST: '4',
            vBCST: '120.00',
            pICMSST: '18.00',
            vICMSST: '21.60',
            pBCOp: '100.0000',
            UFST: 'RJ',
        );

        $this->assertSame('RJ', $group['uf_st']);
        $this->assertSame('100.0000', $group['p_bc_op']);
    }

    public function testTheSubstitutedTaxpayerGroupIsItsOwnVariant(): void
    {
        $group = Tax::icmsSt(
            orig: '0',
            CST: '60',
            vBCSTRet: '100.00',
            vICMSSTRet: '18.00',
            vBCSTDest: '120.00',
            vICMSSTDest: '21.60',
        );

        $this->assertSame('60', $group['cst']);
    }

    public function testTheRemainingSimplesVariantsAreAvailable(): void
    {
        $this->assertSame('201', Tax::icmsSn201(
            orig: '0',
            modBCST: '4',
            vBCST: '120.00',
            pICMSST: '18.00',
            vICMSST: '21.60',
            pCredSN: '2.50',
            vCredICMSSN: '2.50',
        )['csosn']);
        $this->assertSame('202', Tax::icmsSn202(
            CSOSN: '202',
            orig: '0',
            modBCST: '4',
            vBCST: '1.00',
            pICMSST: '1.00',
            vICMSST: '1.00',
        )['csosn']);
        $this->assertSame('500', Tax::icmsSn500(orig: '0')['csosn']);
    }

    public function testPisAndCofinsCanBeTaxedByQuantity(): void
    {
        $this->assertSame([
                'cst' => '03',
                'q_bc_prod' => '10.0000',
                'v_aliq_prod' => '0.1000',
                'v_pis' => '1.00',
            ], Tax::pisQtde(qBCProd: '10.0000', vAliqProd: '0.1000', vPIS: '1.00'));
        $this->assertSame('03', Tax::cofinsQtde(
            qBCProd: '10.0000',
            vAliqProd: '0.1000',
            vCOFINS: '1.00',
        )['cst']);
    }

    public function testTheWithheldGroupsSitUnderTheirOwnKeys(): void
    {
        $tax = new Tax(
            pisSt: Tax::pisSt(vPIS: '1.65', vBC: '100.00', pPIS: '1.65'),
            cofinsSt: Tax::cofinsSt(vCOFINS: '7.60', vBC: '100.00', pCOFINS: '7.60'),
        );

        $this->assertSame(['pis_st', 'cofins_st'], array_keys($tax->toArray()));
    }

    public function testIpiCarriesTheStampFieldsTheSchemaAllows(): void
    {
        $this->assertSame([
            'cnpj_prod' => '11222333000181',
            'c_selo' => '001',
            'q_selo' => '10',
            'c_enq' => '999',
            'trib' => ['cst' => '53'],
        ], Tax::ipi(
            cEnq: '999',
            trib: Tax::ipiNt('53'),
            CNPJProd: '11222333000181',
            cSelo: '001',
            qSelo: '10',
        ));
    }
}
