<?php

declare(strict_types=1);

namespace CodeClubLuxembourg\Sb3Toolkit\Cli\Packager;

/**
 * Builds an inline script that shows a countdown and reloads the page when the
 * project checksum changes.
 */
final class CountdownScript
{
    public static function build(int $countdownSeconds, string $checksumUrl, string $initialChecksum): string
    {
        $template = <<<'JS'
            (() => {
              const totalSeconds = __TOTAL_SECONDS__;
              const checksumUrl = __CHECKSUM_URL__;
              const initialChecksum = __INITIAL_CHECKSUM__;
              let remaining = totalSeconds;

              const container = document.createElement('div');
              container.style.position = 'fixed';
              container.style.top = '10px';
              container.style.right = '10px';
              container.style.zIndex = '9999';
              container.style.padding = '6px 10px';
              container.style.borderRadius = '6px';
              container.style.background = 'rgba(0, 0, 0, 0.6)';
              container.style.color = '#ffffff';
              container.style.font = '14px/1.4 system-ui, -apple-system, Segoe UI, sans-serif';
              container.style.pointerEvents = 'none';
              container.textContent = String(remaining);
              document.body.appendChild(container);

              const updateText = () => {
                container.textContent = String(remaining);
              };

              const fetchChecksum = async () => {
                const url = checksumUrl + (checksumUrl.includes('?') ? '&' : '?') + 't=' + Date.now();
                const response = await fetch(url, { cache: 'no-store' });
                if (!response.ok) {
                  return null;
                }
                return (await response.text()).trim();
              };

              const checkAndReload = async () => {
                try {
                  const latest = await fetchChecksum();
                  if (latest && latest !== initialChecksum) {
                    location.reload();
                  }
                } catch (error) {
                  // ignore and continue countdown
                }
              };

              const tick = async () => {
                remaining -= 1;
                if (remaining <= 0) {
                  remaining = totalSeconds;
                  updateText();
                  await checkAndReload();
                  return;
                }
                updateText();
              };

              updateText();
              setInterval(tick, 1000);
            })();
            JS;

        return strtr($template, [
            '__TOTAL_SECONDS__' => (string) $countdownSeconds,
            '__CHECKSUM_URL__' => self::jsString($checksumUrl),
            '__INITIAL_CHECKSUM__' => self::jsString($initialChecksum),
        ]);
    }

    private static function jsString(string $value): string
    {
        return json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    }
}
