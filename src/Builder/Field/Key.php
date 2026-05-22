<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder\Field;

/**
 * Options for the `event_whenkeypressed` and `sensing_keyoptions` `KEY_OPTION`
 * field. Values match Scratch's runtime exactly.
 */
enum Key: string
{
    case Any = 'any';
    case Space = 'space';
    case Enter = 'enter';
    case LeftArrow = 'left arrow';
    case RightArrow = 'right arrow';
    case UpArrow = 'up arrow';
    case DownArrow = 'down arrow';

    case A = 'a';
    case B = 'b';
    case C = 'c';
    case D = 'd';
    case E = 'e';
    case F = 'f';
    case G = 'g';
    case H = 'h';
    case I = 'i';
    case J = 'j';
    case K = 'k';
    case L = 'l';
    case M = 'm';
    case N = 'n';
    case O = 'o';
    case P = 'p';
    case Q = 'q';
    case R = 'r';
    case S = 's';
    case T = 't';
    case U = 'u';
    case V = 'v';
    case W = 'w';
    case X = 'x';
    case Y = 'y';
    case Z = 'z';

    case Digit0 = '0';
    case Digit1 = '1';
    case Digit2 = '2';
    case Digit3 = '3';
    case Digit4 = '4';
    case Digit5 = '5';
    case Digit6 = '6';
    case Digit7 = '7';
    case Digit8 = '8';
    case Digit9 = '9';
}
