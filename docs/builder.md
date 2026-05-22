# Scratch Builder API

The builder API lets you create and manipulate Scratch projects
programmatically: create sprites, build block scripts, and query or modify
existing blocks. It is object-oriented — every entry point is a class with
static factories and fluent methods, so there are no global helper functions to
import.

There are two ways to build blocks:

- **Typed category factories** (recommended) — `Motion::moveSteps(10)`,
  `Looks::say('Hi!')`, … one class per Scratch palette category, with precise,
  autocompletable arguments.
- **The generic `Block::make()`** — `Block::make('motion_movesteps')->input('STEPS', 10)`
  — the low-level escape hatch for custom procedures and extension blocks.

The classes live in the `CodeClubLuxembourg\Sb3Toolkit\Builder` namespace (factories under
`CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks`); project creation lives on
`CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject`.

## Create a project from scratch

```php
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Control;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Events;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Looks;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks\Motion;
use CodeClubLuxembourg\Sb3Toolkit\Scratch\ScratchProject;

$project = ScratchProject::create();

$cat = $project->addSprite('Cat', x: 0, y: 0, size: 100);

$cat->addScript(
    Events::whenFlagClicked()->then(
        Control::forever([
            Motion::moveSteps(10),
            Looks::say('Hi!'),
        ]),
    ),
);

$project->saveToSb3File('output.sb3');
```

> **Chaining:** `$block->then($next)` appends `$next` to the end of the chain
> and returns the **root** block, so `$root->then($a)->then($b)` keeps anchoring
> on the root. Pass the root to `addScript()`.
>
> If you want the JS-Promise-style "walk forward" variant where each call
> returns the newly appended block, use `andThen($next)` instead:
> `$root->andThen($a)->andThen($b)` ends up holding `$b`. Either way, pass the
> *original root* to `addScript()`.

## `ScratchProject::create(array $meta = [])`

Creates an empty project with a blank stage and no sprites.

```php
$project = ScratchProject::create(['semver' => '3.0.0', 'vm' => '0.2.0']);
```

## Building on a project

`ScratchProject` is the single entry point for building:

```php
// Add a new sprite with default options
$sprite = $project->addSprite('Cat');

// Add with explicit position/appearance (named arguments)
$sprite = $project->addSprite(
    'Dog',
    x: 100, y: -50,
    size: 80,
    direction: 180,
    visible: true,
    rotationStyle: 'left-right',
    draggable: false,
);

// Access an existing sprite (throws if not found)
$existing = $project->sprite('Cat');
```

### Backdrops, variables and lists

```php
$forestIdx = $project->addBackdrop('Forest', file_get_contents('background.svg'));
$oceanIdx  = $project->addBackdrop('Ocean', file_get_contents('background.png'), 'png');

// Variables and lists live either on the stage (global) or on a sprite (local).
// Both addVariable() / addList() return a VariableRef / ListRef carrying name+id
// so callers never need to thread a separate id string through.
$timer      = $project->addVariable('timer', 0);          // VariableRef (stage)
$highScores = $project->addList('highscores');            // ListRef    (stage)
$health     = $cat->addVariable('health', 100);           // VariableRef (sprite-local)
```

## Typed block factories

Each Scratch palette category has a class under `CodeClubLuxembourg\Sb3Toolkit\Builder\Blocks`
with one static factory per block. Every factory returns a `Block`, so the
results compose with `then()` and with each other:

| Class | Examples |
|-------|----------|
| `Motion` | `moveSteps`, `turnRight`, `goTo`, `goToXY`, `pointInDirection` |
| `Looks` | `say`, `think`, `switchCostumeTo`, `setEffectTo`, `goToFrontBack` |
| `Sound` | `play`, `playUntilDone`, `setVolumeTo`, `changeEffectBy` |
| `Events` | `whenFlagClicked`, `whenKeyPressed`, `whenBroadcastReceived`, `broadcast` |
| `Control` | `wait`, `repeat`, `forever`, `if`, `ifElse`, `repeatUntil`, `stop` |
| `Sensing` | `touchingColor`, `colorIsTouchingColor`, `keyPressed`, `of`, `timer` |
| `Operators` | `add`, `subtract`, `random`, `equals`, `and`, `join`, `mathOp` |
| `Data` | `setVariableTo`, `changeVariableBy`, `addToList`, `itemOfList` |
| `Pen` | `clear`, `stamp`, `penDown`, `setPenColorToColor`, `setPenSizeTo` |

Argument types are precise:

```php
Motion::moveSteps(10);                       // int|float|Block|Input
Looks::say('Hi!');                           // string|Block|Input
Operators::not(Sensing::mouseDown());        // boolean inputs take a reporter Block
Sensing::touchingColor('#ff0000');           // color: a hex string (or Input)
Data::setVariableTo(0, 'score', $varId);     // value, then variable name + id
```

**Dropdown fields are PHP enums** (`CodeClubLuxembourg\Sb3Toolkit\Builder\Field`):

```php
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\StopOption;
use CodeClubLuxembourg\Sb3Toolkit\Builder\Field\RotationStyle;

Control::stop(StopOption::All);
Motion::setRotationStyle(RotationStyle::LeftRight);
```

**Bodies (substacks) take a `list<Block>`** and are chained for you. The same
shape is used everywhere — `forever`, `repeat`, `if`, `ifElse`, etc. — so call
sites are uniform:

```php
Control::forever([
    Motion::moveSteps(2),
    Control::if(
        Sensing::touchingColor('#000000'),
        [Motion::turnRight(15)],
    ),
]);

Control::ifElse(
    Operators::equals($score, 10),
    [Looks::say('You win!')],   // SUBSTACK
    [Looks::say('Keep going')], // SUBSTACK2
);
```

**Dropdown menu inputs** accept the selection directly — the shadow menu block
is created for you:

```php
Motion::goTo('Sprite1');        // builds the motion_goto_menu shadow
Events::broadcast('start');     // builds the broadcast menu shadow
```

The factory classes are generated from the opcode metadata in
`CodeClubLuxembourg\Sb3Toolkit\Validator\OpcodeRegistry` by `bin/generate-blocks.php`
(`composer generate-blocks`). They are committed; you only regenerate when the
metadata changes.

### Adding a new block type

To extend the typed factories with a new opcode (most often: an extension
block, or a new core opcode that ships with a Scratch release):

1. **Add the metadata** to `OpcodeRegistry::OPCODE_SPECS` in
   `src/Validator/OpcodeRegistry.php`. Each entry describes the opcode's inputs
   (with their role: `number`, `text`, `boolean`, `color`, `substack`, or
   `menu:<menu_opcode>:<menu_field>`) and fields (plain strings or
   `enum:<EnumName>` for typed dropdowns, plus `variable`/`list`/`broadcast`
   for id-bearing fields). Set `name` to the desired camelCase PHP method
   name; the category prefix on the opcode (e.g. `motion_…`) routes the
   factory into the right `Blocks/` class.
2. **Add a `Field/` enum** if the new block uses a typed dropdown. Each case's
   `->value` must be the exact wire string Scratch's runtime expects; pin it in
   `tests/Builder/Field/FieldEnumTest.php` so silent drift fails the build.
3. **Run `composer generate-blocks`**. The generator emits a typed PHP method
   in the matching `Blocks/<Category>.php`, threading parameters into the
   right `input()`/`field()` calls. Re-run after every metadata change; the
   output is byte-stable thanks to a final php-cs-fixer pass.
4. **The validator picks it up automatically** because rules consult the same
   `OpcodeRegistry`, so `MISSING_INPUT` / `MISSING_FIELD_VALUE` checks light
   up without further wiring.

## `Block`

`Block` is the low-level builder used by the factories above, and the escape
hatch for custom procedures / extension blocks not covered by a factory.

```php
Block::make('event_whenflagclicked');                          // no inputs/fields
Block::make('motion_movesteps')->input('STEPS', 10);           // number → [4, value]
Block::make('looks_say')->input('MESSAGE', 'Hi!');             // string → [10, value]
Block::make('data_setvariableto')                              // field with id
    ->input('VALUE', 0)
    ->field('VARIABLE', $score->name, $score->id);
Block::make('control_stop')->field('STOP_OPTION', 'all');      // field without id

// Nested block as input (substack or reporter)
$condition = Block::make('operator_equals')
    ->input('OPERAND1', Input::variable($score))
    ->input('OPERAND2', 10);
Block::make('control_if')
    ->input('CONDITION', $condition)
    ->input('SUBSTACK', $moveBlock);
```

### Fluent methods

- `input(string $name, Input|Block|int|float|string $value): self` — set an
  input. Plain int/float/string values and nested `Block` reporters are wrapped
  automatically; pass an `Input` for a specific encoding.
- `field(string $name, string $value, ?string $id = null): self` — set a field.
- `mutation(array $mutation): self` — attach a procedure/control mutation.
- `then(Block $next): self` — append a block at the end of the chain; returns the
  root.

## `Input`

`Input` is a typed value object for a block input slot. Use the factories when
you need a specific encoding (a plain int/float/string or `Block` is wrapped for
you):

| Factory | Encodes as | Use for |
|---------|-----------|---------|
| `Input::number($value)` | `[4, value]` | Number literals |
| `Input::text($value)` | `[10, value]` | String literals |
| `Input::variable($ref)` | `[12, name, id]` | Variable reporter (takes a `VariableRef`) |
| `Input::list($ref)` | `[13, name, id]` | List reporter (takes a `ListRef`) |
| `Input::broadcast($ref)` | `[11, name, id]` | Broadcast reference (takes a `BroadcastRef`) |
| `Input::color($value)` | `[9, value]` | Color value |
| `Input::block($block)` | block reference | Nested reporter/substack |

```php
$score = $project->addVariable('score', 0);             // VariableRef

Block::make('data_setvariableto')
    ->input('VALUE', Input::variable($score));          // explicit variable input

Block::make('motion_movesteps')->input('STEPS', 10);    // 10 → Input::number(10)
```

## `Sprite`

Returned by `ScratchProject::addSprite()` and `ScratchProject::sprite()`.

```php
// Adding scripts (returns $this for chaining)
$sprite->addScript($hat);           // places at (0, 0)
$sprite->addScript($hat, 50, 100);  // explicit canvas position

// Querying blocks (each returns BlockRef[])
$moveBlocks  = $sprite->findBlocks('motion_movesteps');
$allScripts  = $sprite->findScripts();
$flagScripts = $sprite->findScripts('event_whenflagclicked');

// Getting a block by id (throws if not found)
$ref = $sprite->block($someBlock->id);

// Costumes, variables, lists
$idx    = $sprite->addCostume('cat-walk', file_get_contents('cat.svg'));
$idx    = $sprite->addCostume('cat-jump', file_get_contents('cat.png'), 'png');
$varId  = $sprite->addVariable('score', 0);
$listId = $sprite->addList('items');

// The current sprite data
$data = $sprite->data();
```

## `BlockRef`

A reference to a single block within a live sprite, for in-place edits.

```php
$ref = $sprite->block($moveBlock->id);

$ref->input('STEPS', 20);                       // plain number
$ref->input('STEPS', Input::number(20));        // explicit encoding
$ref->field('STOP_OPTION', 'this script');
$ref->field('VARIABLE', 'score', $varId);

// Insert a block between this one and its current successor
$pause = Block::make('control_wait')->input('DURATION', 1);
$sprite->block($hat->id)->insertAfter($pause);

// Remove a block from the next-chain and re-link predecessor to successor
$sprite->block($moveA->id)->delete();

// Read the referenced block via typed accessors (no array-shape juggling)
$opcode      = $ref->opcode();                     // e.g. "motion_movesteps"
$steps       = $ref->getInputLiteralValue('STEPS'); // 20
$stopOption  = $ref->getFieldValue('STOP_OPTION'); // "this script"
$rawSlot     = $ref->getInputSlot('STEPS');        // [1, [4, 20]] — full encoding
$rawField    = $ref->getField('VARIABLE');         // ["score", "var-id"]
$rawBlock    = $ref->data();                       // escape hatch: full block array
```

`input()` and `field()` return `$this`, so edits can be chained.

## Manipulating an existing project

```php
$project = ScratchProject::fromSb3File('existing.sb3');
$cat = $project->sprite('Cat');

// Double every "move N steps" block
foreach ($cat->findBlocks('motion_movesteps') as $ref) {
    $current = $ref->getInputLiteralValue('STEPS');
    if (is_numeric($current)) {
        $ref->input('STEPS', $current * 2);
    }
}

$project->saveToSb3File('modified.sb3');
```
