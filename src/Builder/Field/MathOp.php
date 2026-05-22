<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder\Field;

/**
 * Options for the `operator_mathop` block's `OPERATOR` field.
 */
enum MathOp: string
{
    case Abs = 'abs';
    case Floor = 'floor';
    case Ceiling = 'ceiling';
    case Sqrt = 'sqrt';
    case Sin = 'sin';
    case Cos = 'cos';
    case Tan = 'tan';
    case Asin = 'asin';
    case Acos = 'acos';
    case Atan = 'atan';
    case Ln = 'ln';
    case Log = 'log';
    case EExp = 'e ^';
    case TenExp = '10 ^';
}
