<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Validator;

/**
 * Registry mapping Scratch opcodes to their inputs and fields.
 *
 * Each entry is enriched with builder metadata used to generate the typed
 * per-block factories (see bin/generate-blocks.php):
 *   - 'name'     : the factory method name (omitted for internal opcodes)
 *   - 'i'        : input name => role (number|text|boolean|color|any|substack,
 *                  or 'menu:<menuOpcode>:<FIELD>')
 *   - 'f'        : field name => kind ('string'|'variable'|'list'|'broadcast',
 *                  or 'enum:<EnumName>')
 *   - 'internal' : true for menu/procedure opcodes that are validated but not
 *                  exposed as public factories
 *
 * Validation only needs the input/field names, which {@see default()} derives
 * from the keys of 'i' and 'f'.
 */
final class OpcodeRegistry
{
    /**
     * @var array<string, array{name?: string, i?: array<string, string>, f?: array<string, string>, internal?: bool}>
     */
    private const array OPCODE_SPECS = [
        // Motion
        'motion_movesteps' => ['name' => 'moveSteps', 'i' => ['STEPS' => 'number']],
        'motion_turnright' => ['name' => 'turnRight', 'i' => ['DEGREES' => 'number']],
        'motion_turnleft' => ['name' => 'turnLeft', 'i' => ['DEGREES' => 'number']],
        'motion_goto' => ['name' => 'goTo', 'i' => ['TO' => 'menu:motion_goto_menu:TO']],
        'motion_gotoxy' => ['name' => 'goToXY', 'i' => ['X' => 'number', 'Y' => 'number']],
        'motion_glideto' => ['name' => 'glideTo', 'i' => ['SECS' => 'number', 'TO' => 'menu:motion_glideto_menu:TO']],
        'motion_glidesecstoxy' => ['name' => 'glideSecsToXY', 'i' => ['SECS' => 'number', 'X' => 'number', 'Y' => 'number']],
        'motion_setx' => ['name' => 'setX', 'i' => ['X' => 'number']],
        'motion_sety' => ['name' => 'setY', 'i' => ['Y' => 'number']],
        'motion_changexby' => ['name' => 'changeXBy', 'i' => ['DX' => 'number']],
        'motion_changeyby' => ['name' => 'changeYBy', 'i' => ['DY' => 'number']],
        'motion_pointindirection' => ['name' => 'pointInDirection', 'i' => ['DIRECTION' => 'number']],
        'motion_pointtowards' => ['name' => 'pointTowards', 'i' => ['TOWARDS' => 'menu:motion_pointtowards_menu:TOWARDS']],
        'motion_ifonedgebounce' => ['name' => 'ifOnEdgeBounce'],
        'motion_setrotationstyle' => ['name' => 'setRotationStyle', 'f' => ['STYLE' => 'enum:RotationStyle']],
        'motion_xposition' => ['name' => 'xPosition'],
        'motion_yposition' => ['name' => 'yPosition'],
        'motion_direction' => ['name' => 'direction'],
        'motion_goto_menu' => ['internal' => true, 'f' => ['TO' => 'string']],
        'motion_glideto_menu' => ['internal' => true, 'f' => ['TO' => 'string']],
        'motion_pointtowards_menu' => ['internal' => true, 'f' => ['TOWARDS' => 'string']],

        // Looks
        'looks_sayforsecs' => ['name' => 'sayForSecs', 'i' => ['MESSAGE' => 'text', 'SECS' => 'number']],
        'looks_say' => ['name' => 'say', 'i' => ['MESSAGE' => 'text']],
        'looks_thinkforsecs' => ['name' => 'thinkForSecs', 'i' => ['MESSAGE' => 'text', 'SECS' => 'number']],
        'looks_think' => ['name' => 'think', 'i' => ['MESSAGE' => 'text']],
        'looks_switchcostumeto' => ['name' => 'switchCostumeTo', 'i' => ['COSTUME' => 'menu:looks_costume:COSTUME']],
        'looks_nextcostume' => ['name' => 'nextCostume'],
        'looks_switchbackdropto' => ['name' => 'switchBackdropTo', 'i' => ['BACKDROP' => 'menu:looks_backdrops:BACKDROP']],
        'looks_nextbackdrop' => ['name' => 'nextBackdrop'],
        'looks_changesizeby' => ['name' => 'changeSizeBy', 'i' => ['CHANGE' => 'number']],
        'looks_setsizeto' => ['name' => 'setSizeTo', 'i' => ['SIZE' => 'number']],
        'looks_changeeffectby' => ['name' => 'changeEffectBy', 'i' => ['CHANGE' => 'number'], 'f' => ['EFFECT' => 'enum:GraphicEffect']],
        'looks_seteffectto' => ['name' => 'setEffectTo', 'i' => ['VALUE' => 'number'], 'f' => ['EFFECT' => 'enum:GraphicEffect']],
        'looks_cleargraphiceffects' => ['name' => 'clearGraphicEffects'],
        'looks_show' => ['name' => 'show'],
        'looks_hide' => ['name' => 'hide'],
        'looks_gotofrontback' => ['name' => 'goToFrontBack', 'f' => ['FRONT_BACK' => 'enum:FrontBack']],
        'looks_goforwardbackwardlayers' => ['name' => 'goForwardBackwardLayers', 'i' => ['NUM' => 'number'], 'f' => ['FORWARD_BACKWARD' => 'enum:ForwardBackward']],
        'looks_costumenumbername' => ['name' => 'costumeNumberName', 'f' => ['NUMBER_NAME' => 'enum:NumberName']],
        'looks_backdropnumbername' => ['name' => 'backdropNumberName', 'f' => ['NUMBER_NAME' => 'enum:NumberName']],
        'looks_size' => ['name' => 'size'],
        'looks_costume' => ['internal' => true, 'f' => ['COSTUME' => 'string']],
        'looks_backdrops' => ['internal' => true, 'f' => ['BACKDROP' => 'string']],

        // Sound
        'sound_playuntildone' => ['name' => 'playUntilDone', 'i' => ['SOUND_MENU' => 'menu:sound_sounds_menu:SOUND_MENU']],
        'sound_play' => ['name' => 'play', 'i' => ['SOUND_MENU' => 'menu:sound_sounds_menu:SOUND_MENU']],
        'sound_stopallsounds' => ['name' => 'stopAllSounds'],
        'sound_changeeffectby' => ['name' => 'changeEffectBy', 'i' => ['VALUE' => 'number'], 'f' => ['EFFECT' => 'enum:SoundEffect']],
        'sound_seteffectto' => ['name' => 'setEffectTo', 'i' => ['VALUE' => 'number'], 'f' => ['EFFECT' => 'enum:SoundEffect']],
        'sound_cleareffects' => ['name' => 'clearEffects'],
        'sound_changevolumeby' => ['name' => 'changeVolumeBy', 'i' => ['VOLUME' => 'number']],
        'sound_setvolumeto' => ['name' => 'setVolumeTo', 'i' => ['VOLUME' => 'number']],
        'sound_volume' => ['name' => 'volume'],
        'sound_sounds_menu' => ['internal' => true, 'f' => ['SOUND_MENU' => 'string']],

        // Events
        'event_whenflagclicked' => ['name' => 'whenFlagClicked'],
        'event_whenkeypressed' => ['name' => 'whenKeyPressed', 'f' => ['KEY_OPTION' => 'enum:Key']],
        'event_whenstageclicked' => ['name' => 'whenStageClicked'],
        'event_whenspriteclicked' => ['name' => 'whenSpriteClicked'],
        'event_whenbroadcastreceived' => ['name' => 'whenBroadcastReceived', 'f' => ['BROADCAST_OPTION' => 'string']],
        'event_whenbackdropswitchesto' => ['name' => 'whenBackdropSwitchesTo', 'f' => ['BACKDROP' => 'string']],
        'event_whengreaterthan' => ['name' => 'whenGreaterThan', 'i' => ['VALUE' => 'number'], 'f' => ['WHENGREATERTHANMENU' => 'enum:GreaterThanMenu']],
        'event_broadcast' => ['name' => 'broadcast', 'i' => ['BROADCAST_INPUT' => 'menu:event_broadcast_menu:BROADCAST_OPTION']],
        'event_broadcastandwait' => ['name' => 'broadcastAndWait', 'i' => ['BROADCAST_INPUT' => 'menu:event_broadcast_menu:BROADCAST_OPTION']],
        'event_broadcast_menu' => ['internal' => true, 'f' => ['BROADCAST_OPTION' => 'string']],

        // Control
        'control_wait' => ['name' => 'wait', 'i' => ['DURATION' => 'number']],
        'control_repeat' => ['name' => 'repeat', 'i' => ['TIMES' => 'number', 'SUBSTACK' => 'substack']],
        'control_forever' => ['name' => 'forever', 'i' => ['SUBSTACK' => 'substack']],
        'control_if' => ['name' => 'if', 'i' => ['CONDITION' => 'boolean', 'SUBSTACK' => 'substack']],
        'control_if_else' => ['name' => 'ifElse', 'i' => ['CONDITION' => 'boolean', 'SUBSTACK' => 'substack', 'SUBSTACK2' => 'substack']],
        'control_wait_until' => ['name' => 'waitUntil', 'i' => ['CONDITION' => 'boolean']],
        'control_repeat_until' => ['name' => 'repeatUntil', 'i' => ['CONDITION' => 'boolean', 'SUBSTACK' => 'substack']],
        'control_stop' => ['name' => 'stop', 'f' => ['STOP_OPTION' => 'enum:StopOption']],
        'control_start_as_clone' => ['name' => 'startAsClone'],
        'control_create_clone_of' => ['name' => 'createCloneOf', 'i' => ['CLONE_OPTION' => 'menu:control_create_clone_of_menu:CLONE_OPTION']],
        'control_delete_this_clone' => ['name' => 'deleteThisClone'],
        'control_create_clone_of_menu' => ['internal' => true, 'f' => ['CLONE_OPTION' => 'string']],

        // Sensing
        'sensing_touchingobject' => ['name' => 'touchingObject', 'i' => ['TOUCHINGOBJECTMENU' => 'menu:sensing_touchingobjectmenu:TOUCHINGOBJECTMENU']],
        'sensing_touchingcolor' => ['name' => 'touchingColor', 'i' => ['COLOR' => 'color']],
        'sensing_coloristouchingcolor' => ['name' => 'colorIsTouchingColor', 'i' => ['COLOR' => 'color', 'COLOR2' => 'color']],
        'sensing_distanceto' => ['name' => 'distanceTo', 'i' => ['DISTANCETOMENU' => 'menu:sensing_distancetomenu:DISTANCETOMENU']],
        'sensing_askandwait' => ['name' => 'askAndWait', 'i' => ['QUESTION' => 'text']],
        'sensing_answer' => ['name' => 'answer'],
        'sensing_keypressed' => ['name' => 'keyPressed', 'i' => ['KEY_OPTION' => 'menu:sensing_keyoptions:KEY_OPTION:enum:Key']],
        'sensing_mousedown' => ['name' => 'mouseDown'],
        'sensing_mousex' => ['name' => 'mouseX'],
        'sensing_mousey' => ['name' => 'mouseY'],
        'sensing_setdragmode' => ['name' => 'setDragMode', 'f' => ['DRAG_MODE' => 'enum:DragMode']],
        'sensing_loudness' => ['name' => 'loudness'],
        'sensing_loud' => ['name' => 'loud'],
        'sensing_timer' => ['name' => 'timer'],
        'sensing_resettimer' => ['name' => 'resetTimer'],
        'sensing_of' => ['name' => 'of', 'i' => ['OBJECT' => 'menu:sensing_of_object_menu:OBJECT'], 'f' => ['PROPERTY' => 'string']],
        'sensing_current' => ['name' => 'current', 'f' => ['CURRENTMENU' => 'enum:CurrentMenu']],
        'sensing_dayssince2000' => ['name' => 'daysSince2000'],
        'sensing_username' => ['name' => 'username'],
        'sensing_userid' => ['name' => 'userId'],
        'sensing_touchingobjectmenu' => ['internal' => true, 'f' => ['TOUCHINGOBJECTMENU' => 'string']],
        'sensing_distancetomenu' => ['internal' => true, 'f' => ['DISTANCETOMENU' => 'string']],
        'sensing_of_object_menu' => ['internal' => true, 'f' => ['OBJECT' => 'string']],
        'sensing_keyoptions' => ['internal' => true, 'f' => ['KEY_OPTION' => 'string']],

        // Operators
        'operator_add' => ['name' => 'add', 'i' => ['NUM1' => 'number', 'NUM2' => 'number']],
        'operator_subtract' => ['name' => 'subtract', 'i' => ['NUM1' => 'number', 'NUM2' => 'number']],
        'operator_multiply' => ['name' => 'multiply', 'i' => ['NUM1' => 'number', 'NUM2' => 'number']],
        'operator_divide' => ['name' => 'divide', 'i' => ['NUM1' => 'number', 'NUM2' => 'number']],
        'operator_random' => ['name' => 'random', 'i' => ['FROM' => 'number', 'TO' => 'number']],
        'operator_lt' => ['name' => 'lt', 'i' => ['OPERAND1' => 'any', 'OPERAND2' => 'any']],
        'operator_equals' => ['name' => 'equals', 'i' => ['OPERAND1' => 'any', 'OPERAND2' => 'any']],
        'operator_gt' => ['name' => 'gt', 'i' => ['OPERAND1' => 'any', 'OPERAND2' => 'any']],
        'operator_and' => ['name' => 'and', 'i' => ['OPERAND1' => 'boolean', 'OPERAND2' => 'boolean']],
        'operator_or' => ['name' => 'or', 'i' => ['OPERAND1' => 'boolean', 'OPERAND2' => 'boolean']],
        'operator_not' => ['name' => 'not', 'i' => ['OPERAND' => 'boolean']],
        'operator_join' => ['name' => 'join', 'i' => ['STRING1' => 'text', 'STRING2' => 'text']],
        'operator_letter_of' => ['name' => 'letterOf', 'i' => ['LETTER' => 'number', 'STRING' => 'text']],
        'operator_length' => ['name' => 'length', 'i' => ['STRING' => 'text']],
        'operator_contains' => ['name' => 'contains', 'i' => ['STRING1' => 'text', 'STRING2' => 'text']],
        'operator_mod' => ['name' => 'mod', 'i' => ['NUM1' => 'number', 'NUM2' => 'number']],
        'operator_round' => ['name' => 'round', 'i' => ['NUM' => 'number']],
        'operator_mathop' => ['name' => 'mathOp', 'i' => ['NUM' => 'number'], 'f' => ['OPERATOR' => 'enum:MathOp']],

        // Data - variables
        'data_variable' => ['name' => 'variable', 'f' => ['VARIABLE' => 'variable']],
        'data_setvariableto' => ['name' => 'setVariableTo', 'i' => ['VALUE' => 'any'], 'f' => ['VARIABLE' => 'variable']],
        'data_changevariableby' => ['name' => 'changeVariableBy', 'i' => ['VALUE' => 'number'], 'f' => ['VARIABLE' => 'variable']],
        'data_showvariable' => ['name' => 'showVariable', 'f' => ['VARIABLE' => 'variable']],
        'data_hidevariable' => ['name' => 'hideVariable', 'f' => ['VARIABLE' => 'variable']],

        // Data - lists
        'data_listcontents' => ['name' => 'listContents', 'f' => ['LIST' => 'list']],
        'data_addtolist' => ['name' => 'addToList', 'i' => ['ITEM' => 'any'], 'f' => ['LIST' => 'list']],
        'data_deleteoflist' => ['name' => 'deleteOfList', 'i' => ['INDEX' => 'number'], 'f' => ['LIST' => 'list']],
        'data_deletealloflist' => ['name' => 'deleteAllOfList', 'f' => ['LIST' => 'list']],
        'data_insertatlist' => ['name' => 'insertAtList', 'i' => ['ITEM' => 'any', 'INDEX' => 'number'], 'f' => ['LIST' => 'list']],
        'data_replaceitemoflist' => ['name' => 'replaceItemOfList', 'i' => ['ITEM' => 'any', 'INDEX' => 'number'], 'f' => ['LIST' => 'list']],
        'data_itemoflist' => ['name' => 'itemOfList', 'i' => ['INDEX' => 'number'], 'f' => ['LIST' => 'list']],
        'data_itemnumoflist' => ['name' => 'itemNumOfList', 'i' => ['ITEM' => 'any'], 'f' => ['LIST' => 'list']],
        'data_lengthoflist' => ['name' => 'lengthOfList', 'f' => ['LIST' => 'list']],
        'data_listcontainsitem' => ['name' => 'listContainsItem', 'i' => ['ITEM' => 'any'], 'f' => ['LIST' => 'list']],
        'data_showlist' => ['name' => 'showList', 'f' => ['LIST' => 'list']],
        'data_hidelist' => ['name' => 'hideList', 'f' => ['LIST' => 'list']],

        // Procedures (custom blocks) — internal, built via Block::make() + mutation
        'procedures_definition' => ['internal' => true],
        'procedures_call' => ['internal' => true],
        'procedures_prototype' => ['internal' => true],
        'argument_reporter_string_number' => ['internal' => true, 'f' => ['VALUE' => 'string']],
        'argument_reporter_boolean' => ['internal' => true, 'f' => ['VALUE' => 'string']],

        // Pen extension
        'pen_clear' => ['name' => 'clear'],
        'pen_stamp' => ['name' => 'stamp'],
        'pen_penDown' => ['name' => 'penDown'],
        'pen_penUp' => ['name' => 'penUp'],
        'pen_setPenColorToColor' => ['name' => 'setPenColorToColor', 'i' => ['COLOR' => 'color']],
        'pen_changePenColorParamBy' => ['name' => 'changePenColorParamBy', 'i' => ['COLOR_PARAM' => 'text', 'VALUE' => 'number']],
        'pen_setPenColorParamTo' => ['name' => 'setPenColorParamTo', 'i' => ['COLOR_PARAM' => 'text', 'VALUE' => 'number']],
        'pen_changePenSizeBy' => ['name' => 'changePenSizeBy', 'i' => ['SIZE' => 'number']],
        'pen_setPenSizeTo' => ['name' => 'setPenSizeTo', 'i' => ['SIZE' => 'number']],
        'pen_setPenShadeToNumber' => ['name' => 'setPenShadeToNumber', 'i' => ['SHADE' => 'number']],
        'pen_changePenShadeBy' => ['name' => 'changePenShadeBy', 'i' => ['SHADE' => 'number']],
        'pen_setPenHueToNumber' => ['name' => 'setPenHueToNumber', 'i' => ['HUE' => 'number']],
        'pen_changePenHueBy' => ['name' => 'changePenHueBy', 'i' => ['HUE' => 'number']],
    ];

    /**
     * @param array<string, OpcodeSpec> $specs
     */
    public function __construct(private readonly array $specs) {}

    /**
     * Build the registry with the built-in opcode specs.
     */
    public static function default(): self
    {
        $specs = [];
        foreach (self::OPCODE_SPECS as $opcode => $spec) {
            $specs[$opcode] = new OpcodeSpec(
                array_keys($spec['i'] ?? []),
                array_keys($spec['f'] ?? []),
            );
        }

        return new self($specs);
    }

    /**
     * The raw enriched opcode metadata, used by the block-factory generator.
     *
     * @return array<string, array{name?: string, i?: array<string, string>, f?: array<string, string>, internal?: bool}>
     */
    public static function specs(): array
    {
        return self::OPCODE_SPECS;
    }

    public function get(string $opcode): ?OpcodeSpec
    {
        return $this->specs[$opcode] ?? null;
    }

    public function has(string $opcode): bool
    {
        return isset($this->specs[$opcode]);
    }

    /**
     * Return a new registry extended with additional opcode specs.
     *
     * @param array<string, OpcodeSpec> $specs
     */
    public function withSpecs(array $specs): self
    {
        return new self([...$this->specs, ...$specs]);
    }
}
