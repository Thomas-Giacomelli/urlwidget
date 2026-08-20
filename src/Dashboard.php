<?php

namespace GlpiPlugin\Urlwidget;

/**
 * Bridges our plugin data (Config itemtype) with GLPI's native dashboard
 * system (Glpi\Dashboard\Grid).
 *
 * Cards use GLPI's own "bigNumber" widget type (the same one used by
 * built-in cards like "Number of tickets") instead of a custom renderer.
 * This guarantees the card looks and behaves exactly like a native GLPI
 * card, and removes any need to maintain our own markup/CSS.
 */
class Dashboard
{
    /**
     * Declares one dashboard card per configured URL (dynamic, from DB).
     */
    public static function getCards($cards = [])
    {
        try {
            if (!is_array($cards)) {
                $cards = [];
            }

            global $DB;

            $table = Config::getTable();
            if (!$DB->tableExists($table)) {
                return $cards;
            }

            $iterator = $DB->request(['FROM' => $table, 'ORDER' => 'name']);
            foreach ($iterator as $row) {
                $key = 'urlwidget_' . $row['id'];

                $cards[$key] = [
                    'widgettype' => ['bigNumber'],
                    'label'      => $row['name'] !== '' ? $row['name'] : __('URL Widget', 'urlwidget'),
                    // The configuration row is captured directly in the
                    // closure (instead of being looked up from arguments
                    // passed by the dashboard grid at render time). This
                    // is deliberate: GLPI does not call providers the same
                    // way in every context (normal dashboard, ajax
                    // refresh, and public "embed" links all forward
                    // arguments slightly differently), which previously
                    // caused the embedded/public dashboard to lose track
                    // of which URL a card belonged to and fall back to
                    // "No URL configured". Since the row is already known
                    // here, the provider below never needs to resolve it
                    // from anything the grid passes in, so it behaves
                    // identically everywhere.
                    'provider'   => function (...$ignored_grid_args) use ($row) {
                        return self::provideData($row);
                    },
                ];
            }

            return $cards;
        } catch (\Throwable $e) {
            \Toolbox::logInFile('urlwidget', "EXCEPTION in getCards(): " . $e->getMessage() . "\n");
            return $cards;
        }
    }

    /**
     * Data provider for GLPI's native "bigNumber" widget: a big value plus
     * a label, exactly like the built-in dashboard cards.
     */
    public static function provideData(array $row)
    {
        $label = $row['name'] !== '' ? $row['name'] : __('URL Widget', 'urlwidget');

        return [
            'number' => self::getValue($row),
            'label'  => $label,
        ];
    }

    /**
     * Returns the value to display, refreshing it from the configured URL
     * only when the cache has expired (avoids calling the remote URL on
     * every single dashboard render).
     */
    private static function getValue(array $row)
    {
        $now       = time();
        $ttl       = max(0, (int) ($row['cache_ttl'] ?? 300));
        $cached_at = !empty($row['cached_at']) ? strtotime($row['cached_at']) : 0;
        $raw       = $row['cached_value'] ?? '';

        $needs_refresh = !empty($row['url']) && (($now - $cached_at) >= $ttl);

        if ($needs_refresh) {
            $fetched = self::fetchValue($row);

            if ($fetched !== null) {
                $raw = $fetched;

                // Persist the freshly fetched value so the next render
                // (within the cache TTL, including on other users'
                // dashboards or the public embed link) reuses it instead
                // of calling the remote URL again.
                $config = new Config();
                $config->update([
                    'id'           => $row['id'],
                    'cached_value' => $raw,
                    'cached_at'    => date('Y-m-d H:i:s', $now),
                ]);
            }
        }

        $prefix = $row['value_prefix'] ?? '';
        $suffix = $row['value_suffix'] ?? '';

        if ($prefix === '' && $suffix === '' && $raw !== '' && is_numeric($raw)) {
            // Pure number: let GLPI format it natively (thousands
            // separators, etc.) exactly like other big-number cards.
            return $raw + 0;
        }

        $text = trim($prefix . $raw . $suffix);

        return $text !== '' ? $text : __('No data', 'urlwidget');
    }

    /**
     * Performs the HTTP GET call to the configured URL and extracts the
     * value to display (see extractValue()).
     */
    private static function fetchValue(array $row)
    {
        if (empty($row['url'])) {
            return null;
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $row['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => !empty($row['verify_ssl']),
            CURLOPT_SSL_VERIFYHOST => !empty($row['verify_ssl']) ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);

        $body      = curl_exec($ch);
        $errno     = curl_errno($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $body === false || $http_code >= 400) {
            \Toolbox::logInFile(
                'urlwidget',
                sprintf(
                    "Erreur lors de l'appel de %s (HTTP %s, curl errno %s)\n",
                    $row['url'],
                    $http_code,
                    $errno
                )
            );
            return null;
        }

        return self::extractValue((string) $body, (string) ($row['value_path'] ?? ''));
    }

    /**
     * Extracts a scalar TEXT value from a raw HTTP response body:
     * - If the body is valid JSON, walks the given dot-notation path
     *   (e.g. "data.rows.0.0" for a Metabase public question result).
     * - Otherwise, strips any HTML tags and returns the trimmed text, so
     *   the card never ends up displaying raw markup.
     */
    private static function extractValue(string $body, string $path)
    {
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return trim(strip_tags($body));
        }

        if ($path === '') {
            return is_scalar($decoded) ? (string) $decoded : json_encode($decoded);
        }

        $value = $decoded;
        foreach (explode('.', $path) as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return '';
            }
        }

        return is_scalar($value) ? (string) $value : json_encode($value);
    }
}
