<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Builder\Field;

/**
 * Options for the `sensing_setdragmode` block's `DRAG_MODE` field.
 */
enum DragMode: string
{
    case Draggable = 'draggable';
    case NotDraggable = 'not draggable';
}
