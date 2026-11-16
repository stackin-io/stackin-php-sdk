<?php

declare(strict_types=1);

namespace Stackin;

/**
 * Which way the goods on an NF-e move (tpNF): out of the company, or in —
 * a note the company issues itself for goods it receives, such as a sale
 * the buyer returned.
 */
enum Direction: string
{
    case OUT = 'out';
    case IN = 'in';
}
