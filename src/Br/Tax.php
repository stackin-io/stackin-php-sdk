<?php

declare(strict_types=1);

namespace Stackin\Br;

/**
 * One item's taxes, already computed by the caller.
 *
 * The static factories build each group and nest it under the variant
 * name the API expects; the instance carries the groups an item has.
 * Every group in the official leiaute is here.
 */
final class Tax
{
    /**
     * @param array<string, mixed>|null $icms
     * @param array<string, mixed>|null $icmsUfDest
     * @param array<string, mixed>|null $ipi
     * @param array<string, mixed>|null $pis
     * @param array<string, mixed>|null $pisSt
     * @param array<string, mixed>|null $cofins
     * @param array<string, mixed>|null $cofinsSt
     */
    public function __construct(
        public readonly ?string $vTotTrib = null,
        public readonly ?array $icms = null,
        public readonly ?array $icmsUfDest = null,
        public readonly ?array $ipi = null,
        public readonly ?array $pis = null,
        public readonly ?array $pisSt = null,
        public readonly ?array $cofins = null,
        public readonly ?array $cofinsSt = null,
    ) {
    }

    /**
     * Returns the taxes as a plain array, ready for the request body.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return self::compact([
            'v_tot_trib' => $this->vTotTrib,
            'icms' => $this->icms,
            'icms_uf_dest' => $this->icmsUfDest,
            'ipi' => $this->ipi,
            'pis' => $this->pis,
            'pis_st' => $this->pisSt,
            'cofins' => $this->cofins,
            'cofins_st' => $this->cofinsSt,
        ]);
    }

    /**
     * ICMS fully taxed.
     *
     * @return array<string, mixed>
     */
    public static function icms00(
        string $orig,
        string $modBC,
        string $vBC,
        string $pICMS,
        string $vICMS,
        string $CST = '00',
        ?string $pFCP = null,
        ?string $vFCP = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'cst' => $CST,
            'mod_bc' => $modBC,
            'v_bc' => $vBC,
            'p_icms' => $pICMS,
            'v_icms' => $vICMS,
            'p_fcp' => $pFCP,
            'v_fcp' => $vFCP,
        ]);
    }

    /**
     * ICMS monofasico, taxed by unit.
     *
     * @return array<string, mixed>
     */
    public static function icms02(
        string $orig,
        string $adRemICMS,
        string $vICMSMono,
        string $CST = '02',
        ?string $qBCMono = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'cst' => $CST,
            'q_bc_mono' => $qBCMono,
            'ad_rem_icms' => $adRemICMS,
            'v_icms_mono' => $vICMSMono,
        ]);
    }

    /**
     * ICMS taxed with substitution.
     *
     * @return array<string, mixed>
     */
    public static function icms10(
        string $orig,
        string $modBC,
        string $vBC,
        string $pICMS,
        string $vICMS,
        string $modBCST,
        string $vBCST,
        string $pICMSST,
        string $vICMSST,
        string $CST = '10',
        ?string $vBCFCP = null,
        ?string $pFCP = null,
        ?string $vFCP = null,
        ?string $pMVAST = null,
        ?string $pRedBCST = null,
        ?string $vBCFCPST = null,
        ?string $pFCPST = null,
        ?string $vFCPST = null,
        ?string $vICMSSTDeson = null,
        ?string $motDesICMSST = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'cst' => $CST,
            'mod_bc' => $modBC,
            'v_bc' => $vBC,
            'p_icms' => $pICMS,
            'v_icms' => $vICMS,
            'v_bc_fcp' => $vBCFCP,
            'p_fcp' => $pFCP,
            'v_fcp' => $vFCP,
            'mod_bc_st' => $modBCST,
            'p_mva_st' => $pMVAST,
            'p_red_bc_st' => $pRedBCST,
            'v_bc_st' => $vBCST,
            'p_icms_st' => $pICMSST,
            'v_icms_st' => $vICMSST,
            'v_bc_fcp_st' => $vBCFCPST,
            'p_fcp_st' => $pFCPST,
            'v_fcp_st' => $vFCPST,
            'v_icms_st_deson' => $vICMSSTDeson,
            'mot_des_icms_st' => $motDesICMSST,
        ]);
    }

    /**
     * ICMS monofasico with retention.
     *
     * @return array<string, mixed>
     */
    public static function icms15(
        string $orig,
        string $adRemICMS,
        string $vICMSMono,
        string $adRemICMSReten,
        string $vICMSMonoReten,
        string $CST = '15',
        ?string $qBCMono = null,
        ?string $qBCMonoReten = null,
        ?string $pRedAdRem = null,
        ?string $motRedAdRem = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'cst' => $CST,
            'q_bc_mono' => $qBCMono,
            'ad_rem_icms' => $adRemICMS,
            'v_icms_mono' => $vICMSMono,
            'q_bc_mono_reten' => $qBCMonoReten,
            'ad_rem_icms_reten' => $adRemICMSReten,
            'v_icms_mono_reten' => $vICMSMonoReten,
            'p_red_ad_rem' => $pRedAdRem,
            'mot_red_ad_rem' => $motRedAdRem,
        ]);
    }

    /**
     * ICMS with a reduced base.
     *
     * @return array<string, mixed>
     */
    public static function icms20(
        string $orig,
        string $modBC,
        string $pRedBC,
        string $vBC,
        string $pICMS,
        string $vICMS,
        string $CST = '20',
        ?string $vBCFCP = null,
        ?string $pFCP = null,
        ?string $vFCP = null,
        ?string $vICMSDeson = null,
        ?string $motDesICMS = null,
        ?string $indDeduzDeson = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'cst' => $CST,
            'mod_bc' => $modBC,
            'p_red_bc' => $pRedBC,
            'v_bc' => $vBC,
            'p_icms' => $pICMS,
            'v_icms' => $vICMS,
            'v_bc_fcp' => $vBCFCP,
            'p_fcp' => $pFCP,
            'v_fcp' => $vFCP,
            'v_icms_deson' => $vICMSDeson,
            'mot_des_icms' => $motDesICMS,
            'ind_deduz_deson' => $indDeduzDeson,
        ]);
    }

    /**
     * ICMS exempt with substitution.
     *
     * @return array<string, mixed>
     */
    public static function icms30(
        string $orig,
        string $modBCST,
        string $vBCST,
        string $pICMSST,
        string $vICMSST,
        string $CST = '30',
        ?string $pMVAST = null,
        ?string $pRedBCST = null,
        ?string $vBCFCPST = null,
        ?string $pFCPST = null,
        ?string $vFCPST = null,
        ?string $vICMSDeson = null,
        ?string $motDesICMS = null,
        ?string $indDeduzDeson = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'cst' => $CST,
            'mod_bc_st' => $modBCST,
            'p_mva_st' => $pMVAST,
            'p_red_bc_st' => $pRedBCST,
            'v_bc_st' => $vBCST,
            'p_icms_st' => $pICMSST,
            'v_icms_st' => $vICMSST,
            'v_bc_fcp_st' => $vBCFCPST,
            'p_fcp_st' => $pFCPST,
            'v_fcp_st' => $vFCPST,
            'v_icms_deson' => $vICMSDeson,
            'mot_des_icms' => $motDesICMS,
            'ind_deduz_deson' => $indDeduzDeson,
        ]);
    }

    /**
     * ICMS exempt or not taxed.
     *
     * @return array<string, mixed>
     */
    public static function icms40(
        string $orig,
        string $CST,
        ?string $vICMSDeson = null,
        ?string $motDesICMS = null,
        ?string $indDeduzDeson = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'cst' => $CST,
            'v_icms_deson' => $vICMSDeson,
            'mot_des_icms' => $motDesICMS,
            'ind_deduz_deson' => $indDeduzDeson,
        ]);
    }

    /**
     * ICMS deferred.
     *
     * @return array<string, mixed>
     */
    public static function icms51(
        string $orig,
        string $CST = '51',
        ?string $modBC = null,
        ?string $pRedBC = null,
        ?string $cBenefRBC = null,
        ?string $vBC = null,
        ?string $pICMS = null,
        ?string $vICMSOp = null,
        ?string $pDif = null,
        ?string $vICMSDif = null,
        ?string $vICMS = null,
        ?string $vBCFCP = null,
        ?string $pFCP = null,
        ?string $vFCP = null,
        ?string $pFCPDif = null,
        ?string $vFCPDif = null,
        ?string $vFCPEfet = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'cst' => $CST,
            'mod_bc' => $modBC,
            'p_red_bc' => $pRedBC,
            'c_benef_rbc' => $cBenefRBC,
            'v_bc' => $vBC,
            'p_icms' => $pICMS,
            'v_icms_op' => $vICMSOp,
            'p_dif' => $pDif,
            'v_icms_dif' => $vICMSDif,
            'v_icms' => $vICMS,
            'v_bc_fcp' => $vBCFCP,
            'p_fcp' => $pFCP,
            'v_fcp' => $vFCP,
            'p_fcp_dif' => $pFCPDif,
            'v_fcp_dif' => $vFCPDif,
            'v_fcp_efet' => $vFCPEfet,
        ]);
    }

    /**
     * ICMS monofasico deferred.
     *
     * @return array<string, mixed>
     */
    public static function icms53(
        string $orig,
        string $CST = '53',
        ?string $qBCMono = null,
        ?string $adRemICMS = null,
        ?string $vICMSMonoOp = null,
        ?string $pDif = null,
        ?string $vICMSMonoDif = null,
        ?string $vICMSMono = null,
        ?string $qBCMonoDif = null,
        ?string $adRemICMSDif = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'cst' => $CST,
            'q_bc_mono' => $qBCMono,
            'ad_rem_icms' => $adRemICMS,
            'v_icms_mono_op' => $vICMSMonoOp,
            'p_dif' => $pDif,
            'v_icms_mono_dif' => $vICMSMonoDif,
            'v_icms_mono' => $vICMSMono,
            'q_bc_mono_dif' => $qBCMonoDif,
            'ad_rem_icms_dif' => $adRemICMSDif,
        ]);
    }

    /**
     * ICMS already charged by an earlier substitution.
     *
     * @return array<string, mixed>
     */
    public static function icms60(
        string $orig,
        string $CST = '60',
        ?string $vBCSTRet = null,
        ?string $pST = null,
        ?string $vICMSSubstituto = null,
        ?string $vICMSSTRet = null,
        ?string $vBCFCPSTRet = null,
        ?string $pFCPSTRet = null,
        ?string $vFCPSTRet = null,
        ?string $pRedBCEfet = null,
        ?string $vBCEfet = null,
        ?string $pICMSEfet = null,
        ?string $vICMSEfet = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'cst' => $CST,
            'v_bc_st_ret' => $vBCSTRet,
            'p_st' => $pST,
            'v_icms_substituto' => $vICMSSubstituto,
            'v_icms_st_ret' => $vICMSSTRet,
            'v_bc_fcp_st_ret' => $vBCFCPSTRet,
            'p_fcp_st_ret' => $pFCPSTRet,
            'v_fcp_st_ret' => $vFCPSTRet,
            'p_red_bc_efet' => $pRedBCEfet,
            'v_bc_efet' => $vBCEfet,
            'p_icms_efet' => $pICMSEfet,
            'v_icms_efet' => $vICMSEfet,
        ]);
    }

    /**
     * ICMS monofasico already charged earlier.
     *
     * @return array<string, mixed>
     */
    public static function icms61(
        string $orig,
        string $adRemICMSRet,
        string $vICMSMonoRet,
        string $CST = '61',
        ?string $qBCMonoRet = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'cst' => $CST,
            'q_bc_mono_ret' => $qBCMonoRet,
            'ad_rem_icms_ret' => $adRemICMSRet,
            'v_icms_mono_ret' => $vICMSMonoRet,
        ]);
    }

    /**
     * ICMS with a reduced base and substitution.
     *
     * @return array<string, mixed>
     */
    public static function icms70(
        string $orig,
        string $modBC,
        string $pRedBC,
        string $vBC,
        string $pICMS,
        string $vICMS,
        string $modBCST,
        string $vBCST,
        string $pICMSST,
        string $vICMSST,
        string $CST = '70',
        ?string $vBCFCP = null,
        ?string $pFCP = null,
        ?string $vFCP = null,
        ?string $pMVAST = null,
        ?string $pRedBCST = null,
        ?string $vBCFCPST = null,
        ?string $pFCPST = null,
        ?string $vFCPST = null,
        ?string $vICMSDeson = null,
        ?string $motDesICMS = null,
        ?string $indDeduzDeson = null,
        ?string $vICMSSTDeson = null,
        ?string $motDesICMSST = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'cst' => $CST,
            'mod_bc' => $modBC,
            'p_red_bc' => $pRedBC,
            'v_bc' => $vBC,
            'p_icms' => $pICMS,
            'v_icms' => $vICMS,
            'v_bc_fcp' => $vBCFCP,
            'p_fcp' => $pFCP,
            'v_fcp' => $vFCP,
            'mod_bc_st' => $modBCST,
            'p_mva_st' => $pMVAST,
            'p_red_bc_st' => $pRedBCST,
            'v_bc_st' => $vBCST,
            'p_icms_st' => $pICMSST,
            'v_icms_st' => $vICMSST,
            'v_bc_fcp_st' => $vBCFCPST,
            'p_fcp_st' => $pFCPST,
            'v_fcp_st' => $vFCPST,
            'v_icms_deson' => $vICMSDeson,
            'mot_des_icms' => $motDesICMS,
            'ind_deduz_deson' => $indDeduzDeson,
            'v_icms_st_deson' => $vICMSSTDeson,
            'mot_des_icms_st' => $motDesICMSST,
        ]);
    }

    /**
     * ICMS, other cases.
     *
     * @return array<string, mixed>
     */
    public static function icms90(
        string $orig,
        string $CST = '90',
        ?string $modBC = null,
        ?string $vBC = null,
        ?string $pRedBC = null,
        ?string $cBenefRBC = null,
        ?string $pICMS = null,
        ?string $vICMSOp = null,
        ?string $pDif = null,
        ?string $vICMSDif = null,
        ?string $vICMS = null,
        ?string $vBCFCP = null,
        ?string $pFCP = null,
        ?string $vFCP = null,
        ?string $pFCPDif = null,
        ?string $vFCPDif = null,
        ?string $vFCPEfet = null,
        ?string $modBCST = null,
        ?string $pMVAST = null,
        ?string $pRedBCST = null,
        ?string $vBCST = null,
        ?string $pICMSST = null,
        ?string $vICMSST = null,
        ?string $vBCFCPST = null,
        ?string $pFCPST = null,
        ?string $vFCPST = null,
        ?string $vICMSDeson = null,
        ?string $motDesICMS = null,
        ?string $indDeduzDeson = null,
        ?string $vICMSSTDeson = null,
        ?string $motDesICMSST = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'cst' => $CST,
            'mod_bc' => $modBC,
            'v_bc' => $vBC,
            'p_red_bc' => $pRedBC,
            'c_benef_rbc' => $cBenefRBC,
            'p_icms' => $pICMS,
            'v_icms_op' => $vICMSOp,
            'p_dif' => $pDif,
            'v_icms_dif' => $vICMSDif,
            'v_icms' => $vICMS,
            'v_bc_fcp' => $vBCFCP,
            'p_fcp' => $pFCP,
            'v_fcp' => $vFCP,
            'p_fcp_dif' => $pFCPDif,
            'v_fcp_dif' => $vFCPDif,
            'v_fcp_efet' => $vFCPEfet,
            'mod_bc_st' => $modBCST,
            'p_mva_st' => $pMVAST,
            'p_red_bc_st' => $pRedBCST,
            'v_bc_st' => $vBCST,
            'p_icms_st' => $pICMSST,
            'v_icms_st' => $vICMSST,
            'v_bc_fcp_st' => $vBCFCPST,
            'p_fcp_st' => $pFCPST,
            'v_fcp_st' => $vFCPST,
            'v_icms_deson' => $vICMSDeson,
            'mot_des_icms' => $motDesICMS,
            'ind_deduz_deson' => $indDeduzDeson,
            'v_icms_st_deson' => $vICMSSTDeson,
            'mot_des_icms_st' => $motDesICMSST,
        ]);
    }

    /**
     * ICMS split between the origin and destination states.
     *
     * @return array<string, mixed>
     */
    public static function icmsPart(
        string $orig,
        string $CST,
        string $modBC,
        string $vBC,
        string $pICMS,
        string $vICMS,
        string $modBCST,
        string $vBCST,
        string $pICMSST,
        string $vICMSST,
        string $pBCOp,
        string $UFST,
        ?string $pRedBC = null,
        ?string $pMVAST = null,
        ?string $pRedBCST = null,
        ?string $vBCFCPST = null,
        ?string $pFCPST = null,
        ?string $vFCPST = null,
        ?string $vICMSDeson = null,
        ?string $motDesICMS = null,
        ?string $indDeduzDeson = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'cst' => $CST,
            'mod_bc' => $modBC,
            'v_bc' => $vBC,
            'p_red_bc' => $pRedBC,
            'p_icms' => $pICMS,
            'v_icms' => $vICMS,
            'mod_bc_st' => $modBCST,
            'p_mva_st' => $pMVAST,
            'p_red_bc_st' => $pRedBCST,
            'v_bc_st' => $vBCST,
            'p_icms_st' => $pICMSST,
            'v_icms_st' => $vICMSST,
            'v_bc_fcp_st' => $vBCFCPST,
            'p_fcp_st' => $pFCPST,
            'v_fcp_st' => $vFCPST,
            'p_bc_op' => $pBCOp,
            'uf_st' => $UFST,
            'v_icms_deson' => $vICMSDeson,
            'mot_des_icms' => $motDesICMS,
            'ind_deduz_deson' => $indDeduzDeson,
        ]);
    }

    /**
     * ICMS charged earlier, for the substituted taxpayer.
     *
     * @return array<string, mixed>
     */
    public static function icmsSt(
        string $orig,
        string $CST,
        string $vBCSTRet,
        string $vICMSSTRet,
        string $vBCSTDest,
        string $vICMSSTDest,
        ?string $pST = null,
        ?string $vICMSSubstituto = null,
        ?string $vBCFCPSTRet = null,
        ?string $pFCPSTRet = null,
        ?string $vFCPSTRet = null,
        ?string $pRedBCEfet = null,
        ?string $vBCEfet = null,
        ?string $pICMSEfet = null,
        ?string $vICMSEfet = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'cst' => $CST,
            'v_bc_st_ret' => $vBCSTRet,
            'p_st' => $pST,
            'v_icms_substituto' => $vICMSSubstituto,
            'v_icms_st_ret' => $vICMSSTRet,
            'v_bc_fcp_st_ret' => $vBCFCPSTRet,
            'p_fcp_st_ret' => $pFCPSTRet,
            'v_fcp_st_ret' => $vFCPSTRet,
            'v_bc_st_dest' => $vBCSTDest,
            'v_icms_st_dest' => $vICMSSTDest,
            'p_red_bc_efet' => $pRedBCEfet,
            'v_bc_efet' => $vBCEfet,
            'p_icms_efet' => $pICMSEfet,
            'v_icms_efet' => $vICMSEfet,
        ]);
    }

    /**
     * Simples Nacional ICMS with a credit.
     *
     * @return array<string, mixed>
     */
    public static function icmsSn101(
        string $orig,
        string $pCredSN,
        string $vCredICMSSN,
        string $CSOSN = '101',
    ): array {
        return self::compact([
            'orig' => $orig,
            'csosn' => $CSOSN,
            'p_cred_sn' => $pCredSN,
            'v_cred_icms_sn' => $vCredICMSSN,
        ]);
    }

    /**
     * Simples Nacional ICMS without a credit.
     *
     * @return array<string, mixed>
     */
    public static function icmsSn102(
        string $CSOSN,
        ?string $orig = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'csosn' => $CSOSN,
        ]);
    }

    /**
     * Simples Nacional ICMS with a credit and substitution.
     *
     * @return array<string, mixed>
     */
    public static function icmsSn201(
        string $orig,
        string $modBCST,
        string $vBCST,
        string $pICMSST,
        string $vICMSST,
        string $pCredSN,
        string $vCredICMSSN,
        string $CSOSN = '201',
        ?string $pMVAST = null,
        ?string $pRedBCST = null,
        ?string $vBCFCPST = null,
        ?string $pFCPST = null,
        ?string $vFCPST = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'csosn' => $CSOSN,
            'mod_bc_st' => $modBCST,
            'p_mva_st' => $pMVAST,
            'p_red_bc_st' => $pRedBCST,
            'v_bc_st' => $vBCST,
            'p_icms_st' => $pICMSST,
            'v_icms_st' => $vICMSST,
            'v_bc_fcp_st' => $vBCFCPST,
            'p_fcp_st' => $pFCPST,
            'v_fcp_st' => $vFCPST,
            'p_cred_sn' => $pCredSN,
            'v_cred_icms_sn' => $vCredICMSSN,
        ]);
    }

    /**
     * Simples Nacional ICMS without a credit, with substitution.
     *
     * @return array<string, mixed>
     */
    public static function icmsSn202(
        string $orig,
        string $CSOSN,
        string $modBCST,
        string $vBCST,
        string $pICMSST,
        string $vICMSST,
        ?string $pMVAST = null,
        ?string $pRedBCST = null,
        ?string $vBCFCPST = null,
        ?string $pFCPST = null,
        ?string $vFCPST = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'csosn' => $CSOSN,
            'mod_bc_st' => $modBCST,
            'p_mva_st' => $pMVAST,
            'p_red_bc_st' => $pRedBCST,
            'v_bc_st' => $vBCST,
            'p_icms_st' => $pICMSST,
            'v_icms_st' => $vICMSST,
            'v_bc_fcp_st' => $vBCFCPST,
            'p_fcp_st' => $pFCPST,
            'v_fcp_st' => $vFCPST,
        ]);
    }

    /**
     * Simples Nacional ICMS already charged by substitution.
     *
     * @return array<string, mixed>
     */
    public static function icmsSn500(
        string $orig,
        string $CSOSN = '500',
        ?string $vBCSTRet = null,
        ?string $pST = null,
        ?string $vICMSSubstituto = null,
        ?string $vICMSSTRet = null,
        ?string $vBCFCPSTRet = null,
        ?string $pFCPSTRet = null,
        ?string $vFCPSTRet = null,
        ?string $pRedBCEfet = null,
        ?string $vBCEfet = null,
        ?string $pICMSEfet = null,
        ?string $vICMSEfet = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'csosn' => $CSOSN,
            'v_bc_st_ret' => $vBCSTRet,
            'p_st' => $pST,
            'v_icms_substituto' => $vICMSSubstituto,
            'v_icms_st_ret' => $vICMSSTRet,
            'v_bc_fcp_st_ret' => $vBCFCPSTRet,
            'p_fcp_st_ret' => $pFCPSTRet,
            'v_fcp_st_ret' => $vFCPSTRet,
            'p_red_bc_efet' => $pRedBCEfet,
            'v_bc_efet' => $vBCEfet,
            'p_icms_efet' => $pICMSEfet,
            'v_icms_efet' => $vICMSEfet,
        ]);
    }

    /**
     * Simples Nacional ICMS, other cases.
     *
     * @return array<string, mixed>
     */
    public static function icmsSn900(
        string $CSOSN = '900',
        ?string $orig = null,
        ?string $modBC = null,
        ?string $vBC = null,
        ?string $pRedBC = null,
        ?string $pICMS = null,
        ?string $vICMS = null,
        ?string $modBCST = null,
        ?string $pMVAST = null,
        ?string $pRedBCST = null,
        ?string $vBCST = null,
        ?string $pICMSST = null,
        ?string $vICMSST = null,
        ?string $vBCFCPST = null,
        ?string $pFCPST = null,
        ?string $vFCPST = null,
        ?string $pCredSN = null,
        ?string $vCredICMSSN = null,
    ): array {
        return self::compact([
            'orig' => $orig,
            'csosn' => $CSOSN,
            'mod_bc' => $modBC,
            'v_bc' => $vBC,
            'p_red_bc' => $pRedBC,
            'p_icms' => $pICMS,
            'v_icms' => $vICMS,
            'mod_bc_st' => $modBCST,
            'p_mva_st' => $pMVAST,
            'p_red_bc_st' => $pRedBCST,
            'v_bc_st' => $vBCST,
            'p_icms_st' => $pICMSST,
            'v_icms_st' => $vICMSST,
            'v_bc_fcp_st' => $vBCFCPST,
            'p_fcp_st' => $pFCPST,
            'v_fcp_st' => $vFCPST,
            'p_cred_sn' => $pCredSN,
            'v_cred_icms_sn' => $vCredICMSSN,
        ]);
    }

    /**
     * Interstate ICMS share owed to the destination state.
     *
     * @return array<string, mixed>
     */
    public static function icmsUfDest(
        string $vBCUFDest,
        string $pICMSUFDest,
        string $pICMSInter,
        string $pICMSInterPart,
        string $vICMSUFDest,
        string $vICMSUFRemet,
        ?string $vBCFCPUFDest = null,
        ?string $pFCPUFDest = null,
        ?string $vFCPUFDest = null,
    ): array {
        return self::compact([
            'v_bc_uf_dest' => $vBCUFDest,
            'v_bc_fcp_uf_dest' => $vBCFCPUFDest,
            'p_fcp_uf_dest' => $pFCPUFDest,
            'p_icms_uf_dest' => $pICMSUFDest,
            'p_icms_inter' => $pICMSInter,
            'p_icms_inter_part' => $pICMSInterPart,
            'v_fcp_uf_dest' => $vFCPUFDest,
            'v_icms_uf_dest' => $vICMSUFDest,
            'v_icms_uf_remet' => $vICMSUFRemet,
        ]);
    }

    /**
     * This item's IPI — the wrapper fields sit beside the variant.
     *
     * @param array<string, mixed> $trib
     *
     * @return array<string, mixed>
     */
    public static function ipi(
        string $cEnq,
        array $trib,
        ?string $CNPJProd = null,
        ?string $cSelo = null,
        ?string $qSelo = null,
    ): array {
        return self::compact([
            'cnpj_prod' => $CNPJProd,
            'c_selo' => $cSelo,
            'q_selo' => $qSelo,
            'c_enq' => $cEnq,
            'trib' => $trib,
        ]);
    }

    /**
     * IPI taxed by rate or by quantity.
     *
     * @return array<string, mixed>
     */
    public static function ipiTrib(
        string $CST,
        string $vIPI,
        ?string $vBC = null,
        ?string $pIPI = null,
        ?string $qUnid = null,
        ?string $vUnid = null,
    ): array {
        return self::compact([
            'cst' => $CST,
            'v_bc' => $vBC,
            'p_ipi' => $pIPI,
            'q_unid' => $qUnid,
            'v_unid' => $vUnid,
            'v_ipi' => $vIPI,
        ]);
    }

    /**
     * IPI not taxed.
     *
     * @return array<string, mixed>
     */
    public static function ipiNt(string $CST): array
    {
        return self::compact([
            'cst' => $CST,
        ]);
    }

    /**
     * PIS taxed by rate.
     *
     * @return array<string, mixed>
     */
    public static function pisAliq(
        string $CST,
        string $vBC,
        string $pPIS,
        string $vPIS,
    ): array {
        return self::compact([
            'cst' => $CST,
            'v_bc' => $vBC,
            'p_pis' => $pPIS,
            'v_pis' => $vPIS,
        ]);
    }

    /**
     * PIS taxed by quantity.
     *
     * @return array<string, mixed>
     */
    public static function pisQtde(
        string $qBCProd,
        string $vAliqProd,
        string $vPIS,
        string $CST = '03',
    ): array {
        return self::compact([
            'cst' => $CST,
            'q_bc_prod' => $qBCProd,
            'v_aliq_prod' => $vAliqProd,
            'v_pis' => $vPIS,
        ]);
    }

    /**
     * PIS not taxed.
     *
     * @return array<string, mixed>
     */
    public static function pisNt(string $CST): array
    {
        return self::compact([
            'cst' => $CST,
        ]);
    }

    /**
     * PIS taxed some other way.
     *
     * @return array<string, mixed>
     */
    public static function pisOutr(
        string $CST,
        string $vPIS,
        ?string $vBC = null,
        ?string $pPIS = null,
        ?string $qBCProd = null,
        ?string $vAliqProd = null,
    ): array {
        return self::compact([
            'cst' => $CST,
            'v_bc' => $vBC,
            'p_pis' => $pPIS,
            'q_bc_prod' => $qBCProd,
            'v_aliq_prod' => $vAliqProd,
            'v_pis' => $vPIS,
        ]);
    }

    /**
     * PIS withheld by substitution.
     *
     * @return array<string, mixed>
     */
    public static function pisSt(
        string $vPIS,
        ?string $vBC = null,
        ?string $pPIS = null,
        ?string $qBCProd = null,
        ?string $vAliqProd = null,
        ?string $indSomaPISST = null,
    ): array {
        return self::compact([
            'v_bc' => $vBC,
            'p_pis' => $pPIS,
            'q_bc_prod' => $qBCProd,
            'v_aliq_prod' => $vAliqProd,
            'v_pis' => $vPIS,
            'ind_soma_pis_st' => $indSomaPISST,
        ]);
    }

    /**
     * COFINS taxed by rate.
     *
     * @return array<string, mixed>
     */
    public static function cofinsAliq(
        string $CST,
        string $vBC,
        string $pCOFINS,
        string $vCOFINS,
    ): array {
        return self::compact([
            'cst' => $CST,
            'v_bc' => $vBC,
            'p_cofins' => $pCOFINS,
            'v_cofins' => $vCOFINS,
        ]);
    }

    /**
     * COFINS taxed by quantity.
     *
     * @return array<string, mixed>
     */
    public static function cofinsQtde(
        string $qBCProd,
        string $vAliqProd,
        string $vCOFINS,
        string $CST = '03',
    ): array {
        return self::compact([
            'cst' => $CST,
            'q_bc_prod' => $qBCProd,
            'v_aliq_prod' => $vAliqProd,
            'v_cofins' => $vCOFINS,
        ]);
    }

    /**
     * COFINS not taxed.
     *
     * @return array<string, mixed>
     */
    public static function cofinsNt(string $CST): array
    {
        return self::compact([
            'cst' => $CST,
        ]);
    }

    /**
     * COFINS taxed some other way.
     *
     * @return array<string, mixed>
     */
    public static function cofinsOutr(
        string $CST,
        string $vCOFINS,
        ?string $vBC = null,
        ?string $pCOFINS = null,
        ?string $qBCProd = null,
        ?string $vAliqProd = null,
    ): array {
        return self::compact([
            'cst' => $CST,
            'v_bc' => $vBC,
            'p_cofins' => $pCOFINS,
            'q_bc_prod' => $qBCProd,
            'v_aliq_prod' => $vAliqProd,
            'v_cofins' => $vCOFINS,
        ]);
    }

    /**
     * COFINS withheld by substitution.
     *
     * @return array<string, mixed>
     */
    public static function cofinsSt(
        string $vCOFINS,
        ?string $vBC = null,
        ?string $pCOFINS = null,
        ?string $qBCProd = null,
        ?string $vAliqProd = null,
        ?string $indSomaCOFINSST = null,
    ): array {
        return self::compact([
            'v_bc' => $vBC,
            'p_cofins' => $pCOFINS,
            'q_bc_prod' => $qBCProd,
            'v_aliq_prod' => $vAliqProd,
            'v_cofins' => $vCOFINS,
            'ind_soma_cofins_st' => $indSomaCOFINSST,
        ]);
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private static function compact(array $fields): array
    {
        return array_filter($fields, static fn ($value) => $value !== null);
    }
}
