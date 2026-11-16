<?php

declare(strict_types=1);

namespace Stackin;

/**
 * Why an NF-e is issued (finNFe): a normal sale, a complement or an
 * adjustment to an earlier note, or the return of goods.
 */
enum Purpose: string
{
    case NORMAL = 'normal';
    case COMPLEMENTARY = 'complementary';
    case ADJUSTMENT = 'adjustment';
    case RETURN = 'return';
}
