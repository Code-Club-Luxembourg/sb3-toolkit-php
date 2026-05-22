<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli\Packager;

/**
 * Builds an inline script injected into a TurboWarp-packaged HTML file.
 *
 * The script patches `vm.runtime.startHats` so that every Scratch broadcast is
 * forwarded to the parent frame as a `postMessage` event:
 *
 *     { type: 'scratch-broadcast', broadcast: '<broadcast name>' }
 *
 * `vm` is a global exposed by the TurboWarp runtime after initialisation. The
 * interval fallback handles the rare case where custom JS runs before the
 * runtime finishes setting up.
 */
final class BridgeScript
{
    public static function build(): string
    {
        return <<<'JS'
            (() => {
              function installBridge() {
                const orig = vm.runtime.startHats.bind(vm.runtime);
                vm.runtime.startHats = function (hat, matchFields, target) {
                  if (hat === 'event_whenbroadcastreceived' && matchFields && matchFields.BROADCAST_OPTION) {
                    window.parent.postMessage(
                      { type: 'scratch-broadcast', broadcast: matchFields.BROADCAST_OPTION },
                      '*'
                    );
                  }
                  return orig(hat, matchFields, target);
                };
              }

              if (typeof vm !== 'undefined' && vm.runtime) {
                installBridge();
              } else {
                const check = setInterval(function () {
                  if (typeof vm !== 'undefined' && vm.runtime) {
                    clearInterval(check);
                    installBridge();
                  }
                }, 50);
              }
            })();
            JS;
    }
}
