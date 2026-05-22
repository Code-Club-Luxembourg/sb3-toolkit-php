<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli\Packager;

use RuntimeException;

/**
 * Pure-PHP packager: assembles a standalone HTML player without Node.
 *
 * It inlines the vendored TurboWarp scaffolding runtime (the Scratch VM,
 * renderer, audio, storage and JSZip — see resources/scaffolding/), embeds the
 * `.sb3` project as base64, and drives the runtime through its stable public
 * API (`Scaffolding.Scaffolding`, `Scaffolding.JSZip`, `storage.addHelper`,
 * `loadProject`, `start`). The structure mirrors @turbowarp/packager's
 * html target; only the project encoding differs (base64 rather than base85).
 */
final class HtmlPackager extends AbstractPackager
{
    private const string FOREGROUND = '#ffffff';
    private const string BACKGROUND = '#000000';
    private const string ACCENT = '#ff4c4c';
    private const string VARIABLE_COLOR = '#ff8c1a';
    private const string LIST_COLOR = '#fc662c';
    private const string LIST_EDITING_COLOR = '#ee7d42';
    private const string RESIZE_MODE = 'preserve-ratio';

    private readonly string $scaffoldingPath;

    public function __construct(?string $scaffoldingPath = null)
    {
        $this->scaffoldingPath = $scaffoldingPath
            ?? \dirname(__DIR__, 3) . '/resources/scaffolding/scaffolding-min.js';
    }

    protected function emit(PackRequest $request, string $projectData, ?string $customJs): void
    {
        if (!is_file($this->scaffoldingPath)) {
            throw new RuntimeException(\sprintf(
                'Missing scaffolding runtime: %s. Run bin/refresh-scaffolding.sh to vendor it.',
                $this->scaffoldingPath,
            ));
        }
        $runtime = file_get_contents($this->scaffoldingPath);
        if ($runtime === false) {
            throw new RuntimeException(\sprintf('Failed to read scaffolding runtime: %s', $this->scaffoldingPath));
        }

        $html = $this->head($request)
            . "\n  <script>" . $this->escapeScriptClosers($runtime) . "</script>\n"
            . $this->bootstrap($request)
            . $this->customJsScript($customJs)
            . $this->projectScript($projectData, $request)
            . "\n</body>\n</html>\n";

        if (file_put_contents($request->outputPath, $html) === false) {
            throw new RuntimeException(\sprintf('Failed to write output: %s', $request->outputPath));
        }
    }

    private function title(PackRequest $request): string
    {
        $name = pathinfo($request->outputPath, \PATHINFO_FILENAME);

        return $name === '' ? 'Scratch Project' : $name;
    }

    private function head(PackRequest $request): string
    {
        $template = <<<'HTML'
            <!DOCTYPE html>
            <!-- Created with sb3-toolkit -->
            <html>
            <head>
              <meta charset="utf-8">
              <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
              <meta http-equiv="Content-Security-Policy" content="default-src * 'self' 'unsafe-inline' 'unsafe-eval' data: blob:">
              <title>__TITLE__</title>
              <style>
                body { color: __FOREGROUND__; font-family: sans-serif; overflow: hidden; margin: 0; padding: 0; }
                :root, body.is-fullscreen { background-color: __BACKGROUND__; }
                [hidden] { display: none !important; }
                h1 { font-weight: normal; }
                a { color: inherit; text-decoration: underline; cursor: pointer; }
                #app, #loading, #error, #launch { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
                .screen { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; cursor: default; user-select: none; -webkit-user-select: none; background-color: __BACKGROUND__; }
                #launch { background-color: rgba(0, 0, 0, 0.7); cursor: pointer; }
                .green-flag { width: 80px; height: 80px; padding: 16px; border-radius: 100%; background: rgba(255, 255, 255, 0.75); border: 3px solid hsla(0, 100%, 100%, 1); display: flex; justify-content: center; align-items: center; box-sizing: border-box; }
                .progress-bar-outer { border: 1px solid currentColor; height: 10px; width: 200px; max-width: 200px; }
                .progress-bar-inner { height: 100%; width: 0; background-color: currentColor; }
                .loading-text, noscript { font-weight: normal; font-size: 36px; margin: 0 0 16px; }
                #error-message, #error-stack { font-family: monospace; max-width: 600px; white-space: pre-wrap; user-select: text; -webkit-user-select: text; }
                #error-stack { text-align: left; max-height: 200px; overflow: auto; }
                .control-button { width: 2rem; height: 2rem; padding: 0.375rem; margin-top: 0.5rem; margin-bottom: 0.5rem; user-select: none; -webkit-user-select: none; cursor: pointer; border: 0; border-radius: 4px; }
                .control-button-highlight:hover { background: __ACCENT__26; }
                .control-button-highlight.active { background: __ACCENT__59; }
                .fullscreen-button { background: white; }
                .standalone-fullscreen-button { position: absolute; top: 0; right: 0; background-color: rgba(0, 0, 0, 0.5); border-radius: 0 0 0 4px; padding: 4px; cursor: pointer; }
                .sc-canvas { cursor: auto; }
                .sc-monitor-root[data-opcode^="data_"] .sc-monitor-value-color { background-color: __VARIABLE_COLOR__; }
                .sc-monitor-row-value-outer { background-color: __LIST_COLOR__; }
                .sc-monitor-row-value-editing .sc-monitor-row-value-outer { background-color: __LIST_EDITING_COLOR__; }
              </style>
              <meta name="theme-color" content="__BACKGROUND__">
            </head>
            <body>
              <div id="app"></div>

              <div id="launch" class="screen" hidden title="Click to start">
                <div class="green-flag">
                  <svg viewBox="0 0 16.63 17.5" width="42" height="44">
                    <defs><style>.cls-1,.cls-2{fill:#4cbf56;stroke:#45993d;stroke-linecap:round;stroke-linejoin:round;}.cls-2{stroke-width:1.5px;}</style></defs>
                    <path class="cls-1" d="M.75,2A6.44,6.44,0,0,1,8.44,2h0a6.44,6.44,0,0,0,7.69,0V12.4a6.44,6.44,0,0,1-7.69,0h0a6.44,6.44,0,0,0-7.69,0"/>
                    <line class="cls-2" x1="0.75" y1="16.75" x2="0.75" y2="0.75"/>
                  </svg>
                </div>
              </div>

              <div id="loading" class="screen">
                <noscript>Enable JavaScript</noscript>
                <div class="progress-bar-outer"><div class="progress-bar-inner" id="loading-inner"></div></div>
              </div>

              <div id="error" class="screen" hidden>
                <h1>Error</h1>
                <details>
                  <summary id="error-message"></summary>
                  <p id="error-stack"></p>
                </details>
              </div>
            HTML;

        return strtr($template, [
            '__TITLE__' => htmlspecialchars($this->title($request), \ENT_QUOTES),
            '__FOREGROUND__' => self::FOREGROUND,
            '__BACKGROUND__' => self::BACKGROUND,
            '__ACCENT__' => self::ACCENT,
            '__VARIABLE_COLOR__' => self::VARIABLE_COLOR,
            '__LIST_COLOR__' => self::LIST_COLOR,
            '__LIST_EDITING_COLOR__' => self::LIST_EDITING_COLOR,
        ]);
    }

    private function bootstrap(PackRequest $request): string
    {
        $template = <<<'JS_WRAP'
              <script>
                const appElement = document.getElementById('app');
                const launchScreen = document.getElementById('launch');
                const loadingScreen = document.getElementById('loading');
                const loadingInner = document.getElementById('loading-inner');
                const errorScreen = document.getElementById('error');
                const errorScreenMessage = document.getElementById('error-message');
                const errorScreenStack = document.getElementById('error-stack');

                const handleError = (error) => {
                  console.error(error);
                  if (!errorScreen.hidden) return;
                  errorScreen.hidden = false;
                  errorScreenMessage.textContent = '' + error;
                  let debug = error && error.stack || 'no stack';
                  debug += '\nUser agent: ' + navigator.userAgent;
                  errorScreenStack.textContent = debug;
                };
                const setProgress = (progress) => {
                  if (loadingInner) loadingInner.style.width = progress * 100 + '%';
                };
                const interpolate = (a, b, t) => a + t * (b - a);

                try {
                  setProgress(0.1);

                  const scaffolding = new Scaffolding.Scaffolding();
                  scaffolding.width = __STAGE_WIDTH__;
                  scaffolding.height = __STAGE_HEIGHT__;
                  scaffolding.resizeMode = __RESIZE_MODE__;
                  scaffolding.editableLists = false;
                  scaffolding.usePackagedRuntime = true;
                  scaffolding.setup();
                  scaffolding.appendTo(appElement);

                  const vm = scaffolding.vm;
                  window.scaffolding = scaffolding;
                  window.vm = vm;
                  window.Scratch = {
                    vm,
                    renderer: vm.renderer,
                    audioEngine: vm.runtime.audioEngine,
                    bitmapAdapter: vm.runtime.v2BitmapAdapter,
                    videoProvider: vm.runtime.ioDevices.video.provider
                  };

                  scaffolding.setUsername('player' + Math.floor(Math.random() * 10000));
                  scaffolding.setAccentColor(__ACCENT_JSON__);

                  const greenFlagButton = document.createElement('img');
                  greenFlagButton.src = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16.63 17.5"><path d="M.75 2a6.44 6.44 0 017.69 0h0a6.44 6.44 0 007.69 0v10.4a6.44 6.44 0 01-7.69 0h0a6.44 6.44 0 00-7.69 0" fill="#4cbf56" stroke="#45993d" stroke-linecap="round" stroke-linejoin="round"/><path stroke-width="1.5" fill="#4cbf56" stroke="#45993d" stroke-linecap="round" stroke-linejoin="round" d="M.75 16.75v-16"/></svg>');
                  greenFlagButton.className = 'control-button control-button-highlight green-flag-button';
                  greenFlagButton.draggable = false;
                  greenFlagButton.addEventListener('click', () => scaffolding.greenFlag());
                  scaffolding.addEventListener('PROJECT_RUN_START', () => greenFlagButton.classList.add('active'));
                  scaffolding.addEventListener('PROJECT_RUN_STOP', () => greenFlagButton.classList.remove('active'));
                  scaffolding.addControlButton({ element: greenFlagButton, where: 'top-left' });

                  const stopAllButton = document.createElement('img');
                  stopAllButton.src = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 14 14"><path fill="#ec5959" stroke="#b84848" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" d="M4.3.5h5.4l3.8 3.8v5.4l-3.8 3.8H4.3L.5 9.7V4.3z"/></svg>');
                  stopAllButton.className = 'control-button control-button-highlight stop-all-button';
                  stopAllButton.draggable = false;
                  stopAllButton.addEventListener('click', () => scaffolding.stopAll());
                  scaffolding.addControlButton({ element: stopAllButton, where: 'top-left' });

                  if (document.fullscreenEnabled || document.webkitFullscreenEnabled) {
                    let isFullScreen = false;
                    const fullscreenButton = document.createElement('img');
                    fullscreenButton.draggable = false;
                    fullscreenButton.className = 'control-button fullscreen-button';
                    fullscreenButton.addEventListener('click', () => {
                      if (isFullScreen) {
                        (document.exitFullscreen || document.webkitExitFullscreen).call(document);
                      } else {
                        (document.body.requestFullscreen || document.body.webkitRequestFullscreen).call(document.body);
                      }
                    });
                    const updateFullScreen = () => {
                      isFullScreen = !!(document.fullscreenElement || document.webkitFullscreenElement);
                      document.body.classList.toggle('is-fullscreen', isFullScreen);
                    };
                    updateFullScreen();
                    document.addEventListener('fullscreenchange', updateFullScreen);
                    document.addEventListener('webkitfullscreenchange', updateFullScreen);
                    scaffolding.addControlButton({ element: fullscreenButton, where: 'top-right' });
                  }

                  vm.setTurboMode(false);
                  if (vm.setInterpolation) vm.setInterpolation(false);
                  if (vm.setFramerate) vm.setFramerate(30);
                  if (vm.renderer.setUseHighQualityRender) vm.renderer.setUseHighQualityRender(false);
                  if (vm.setRuntimeOptions) vm.setRuntimeOptions({ fencing: true, miscLimits: true, maxClones: 300 });
                  if (vm.setCompilerOptions) vm.setCompilerOptions({ enabled: true, warpTimer: false });
                  if (vm.runtime.setEnforcePrivacy) vm.runtime.setEnforcePrivacy(false);

                  if (scaffolding.setExtensionSecurityManager) {
                    scaffolding.setExtensionSecurityManager({
                      getSandboxMode: () => 'unsandboxed',
                      canLoadExtensionFromProject: () => true
                    });
                  }
                } catch (e) {
                  handleError(e);
                }
              </script>
            JS_WRAP;

        return strtr($template, [
            '__STAGE_WIDTH__' => (string) $request->stageWidth,
            '__STAGE_HEIGHT__' => (string) $request->stageHeight,
            '__RESIZE_MODE__' => $this->jsString(self::RESIZE_MODE),
            '__ACCENT_JSON__' => $this->jsString(self::ACCENT),
        ]);
    }

    private function customJsScript(?string $customJs): string
    {
        if ($customJs === null || $customJs === '') {
            return '';
        }

        return "\n  <script>\n    try {\n" . $this->escapeScriptClosers($customJs) . "\n    } catch (e) { handleError(e); }\n  </script>\n";
    }

    /**
     * Defuse stray `</script` substrings in inlined JS so they cannot terminate
     * the enclosing `<script>` tag. The browser still parses the original
     * characters because `\/` in a JS string/regex is identical to `/`.
     */
    private function escapeScriptClosers(string $js): string
    {
        return str_ireplace('</script', '<\/script', $js);
    }

    private function projectScript(string $projectData, PackRequest $request): string
    {
        $template = <<<'JS_WRAP'
              <script>
                const decodeProject = (b64) => {
                  const binary = atob(b64);
                  const len = binary.length;
                  const bytes = new Uint8Array(len);
                  for (let i = 0; i < len; i++) bytes[i] = binary.charCodeAt(i);
                  return bytes.buffer;
                };

                const getProjectData = (function () {
                  const storage = scaffolding.storage;
                  storage.onprogress = (total, loaded) => setProgress(interpolate(0.75, 0.98, loaded / total));
                  let zip;
                  vm.runtime.on('PROJECT_LOADED', () => (zip = null));
                  const findFileInZip = (path) => zip.file(path) || zip.file(new RegExp("^([^/]*/)?" + path + "$"))[0];
                  storage.addHelper({
                    load: (assetType, assetId, dataFormat) => {
                      if (!zip) throw new Error('Zip is not loaded or has been closed');
                      const path = assetId + '.' + dataFormat;
                      const file = findFileInZip(path);
                      if (!file) {
                        console.error('Asset is not in zip: ' + path);
                        return Promise.resolve(null);
                      }
                      return file.async('uint8array').then((data) => storage.createAsset(assetType, dataFormat, data, assetId));
                    }
                  });
                  return () => Promise.resolve(decodeProject(PROJECT_DATA)).then(async (data) => {
                    zip = await Scaffolding.JSZip.loadAsync(data);
                    const file = findFileInZip('project.json');
                    if (!file) throw new Error('project.json is not in zip');
                    return file.async('arraybuffer');
                  });
                })();

                const run = async () => {
                  const projectData = await getProjectData();
                  await scaffolding.loadProject(projectData);
                  setProgress(1);
                  loadingScreen.hidden = true;
                  if (__AUTOPLAY__) {
                    scaffolding.start();
                  } else {
                    launchScreen.hidden = false;
                    launchScreen.addEventListener('click', () => {
                      launchScreen.hidden = true;
                      scaffolding.start();
                    });
                    launchScreen.focus();
                  }
                };
                run().catch(handleError);
              </script>
            JS_WRAP;

        // The base64 string is injected separately so the (multi-megabyte) project
        // is never scanned by strtr and cannot collide with a placeholder.
        $template = strtr($template, ['__AUTOPLAY__' => $request->autoplay ? 'true' : 'false']);
        $base64 = 'const PROJECT_DATA = "' . base64_encode($projectData) . '";';

        return "\n  <script>" . $base64 . "</script>\n" . $template;
    }

    private function jsString(string $value): string
    {
        return json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    }
}
