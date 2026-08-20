<?php

namespace GlpiPlugin\Urlwidget;

class Dashboard
{
    /**
     * Declares one dashboard card per configured Metabase question (dynamic,
     * from DB).
     *
     * Unlike the previous iframe-based approach, this uses GLPI's own core
     * "bigNumber" widget type - the same one used for native counters
     * (number of computers, tickets, etc.). Because it's a built-in type,
     * GLPI already knows how to render it everywhere, including the public
     * "embed" dashboard view, without us having to declare or maintain a
     * custom widget type/renderer at all.
     *
     * The row id is embedded directly in the provider's *method name*
     * (provideBigNumber_<id>) rather than passed as a runtime argument,
     * since the normal dashboard grid and the embed view do not call
     * providers the same way. __callStatic() below extracts the id from
     * the method name itself, regardless of what arguments GLPI passes.
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
                    'label'      => $row['name'] !== '' ? $row['name'] : __('Metabase question', 'urlwidget'),
                    'provider'   => __CLASS__ . '::provideBigNumber_' . (int) $row['id'],
                ];
            }

            return $cards;
        } catch (\Throwable $e) {
            \Toolbox::logInFile('urlwidget', "EXCEPTION in getCards(): " . $e->getMessage() . "\n");
            return $cards;
        }
    }

    /**
     * Catches calls to provideBigNumber_<id>(...) regardless of what
     * arguments GLPI passed (or didn't), and dispatches to the real logic
     * with the id parsed straight out of the method name.
     */
    public static function __callStatic($name, $arguments)
    {
        if (preg_match('/^provideBigNumber_(\d+)$/', $name, $m)) {
            return self::buildBigNumberData((int) $m[1]);
        }

        \Toolbox::logInFile('urlwidget', "__callStatic() unknown method: {$name}\n");
        return [
            'number' => 0,
            'label'  => __('Metabase question', 'urlwidget'),
        ];
    }

    /**
     * Builds the ['number' => ..., 'label' => ..., 'url' => ..., 'icon' => ...]
     * payload expected by GLPI's core "bigNumber" widget, using the live
     * value fetched from Metabase's public JSON endpoint.
     */
    private static function buildBigNumberData(int $config_id)
    {
        $config = new Config();
        $number = 0;
        $label  = __('Metabase question', 'urlwidget');
        $url    = '';

        $found = $config_id ? $config->getFromDB($config_id) : false;
        \Toolbox::logInFile('urlwidget', "buildBigNumberData(config_id={$config_id}) found=" . var_export($found, true) . "\n");

        if ($found) {
            $url    = $config->fields['url'];
            $label  = $config->fields['name'] !== '' ? $config->fields['name'] : $label;

            $fetched = self::fetchMetabaseValue($url);

            if ($fetched['error'] !== null) {
                \Toolbox::logInFile(
                    'urlwidget',
                    "buildBigNumberData(config_id={$config_id}) fetch error: {$fetched['error']}\n"
                );
            } else {
                $number = $fetched['number'];
                // Prefer Metabase's own column label when we don't have a
                // more friendly admin-provided name.
                if ($config->fields['name'] === '' && $fetched['label'] !== '') {
                    $label = $fetched['label'];
                }
                \Toolbox::logInFile(
                    'urlwidget',
                    "buildBigNumberData(config_id={$config_id}) number={$number} label={$label}\n"
                );
            }
        }

        return [
            'number' => $number,
            'label'  => $label,
            'url'    => $url,
            'icon'   => 'ti ti-phone',
        ];
    }

    /**
     * Fetches a Metabase public question as raw JSON (public link + ".json")
     * and extracts the first column of the first row - the expected shape
     * for a single-value question like `SELECT COUNT(*) AS ... FROM ...`.
     *
     * Returns ['number' => mixed, 'label' => string, 'error' => ?string].
     */
    private static function fetchMetabaseValue(string $public_url): array
    {
        $result = ['number' => 0, 'label' => '', 'error' => null];

        if (empty($public_url)) {
            $result['error'] = 'no URL configured';
            return $result;
        }

        $json_url = self::toJsonUrl($public_url);

        if (!function_exists('curl_init')) {
            $result['error'] = 'curl extension not available';
            return $result;
        }

        try {
            $ch = curl_init($json_url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
            ]);
            $body       = curl_exec($ch);
            $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $effective  = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $curl_err   = curl_error($ch);
            curl_close($ch);

            \Toolbox::logInFile(
                'urlwidget',
                "fetchMetabaseValue GET {$json_url} => HTTP {$http_code} (effective_url={$effective})" . ($curl_err !== '' ? " curl_error={$curl_err}" : '') . "\n"
            );

            if ($body === false || $http_code >= 400) {
                $result['error'] = "HTTP {$http_code}" . ($curl_err !== '' ? " ({$curl_err})" : '');
                return $result;
            }

            $data = json_decode($body, true);

            if (!is_array($data) || count($data) === 0) {
                $result['error'] = 'empty or non-JSON response - is this a public Metabase question link?';
                return $result;
            }

            $first_row = $data[0];
            if (!is_array($first_row) || count($first_row) === 0) {
                $result['error'] = 'no columns in first result row';
                return $result;
            }

            $key   = array_key_first($first_row);
            $value = $first_row[$key];

            $result['number'] = is_numeric($value) ? ($value + 0) : $value;
            $result['label']  = ucwords(str_replace(['_', '-'], ' ', (string) $key));
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Turns a Metabase public question URL (e.g.
     * https://host/public/question/<uuid>?titled=false) into its raw JSON
     * export URL (https://host/public/question/<uuid>.json).
     */
    private static function toJsonUrl(string $url): string
    {
        $parts = parse_url($url);

        $base = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $base .= ':' . $parts['port'];
        }
        $base .= $parts['path'] ?? '';

        if (!str_ends_with($base, '.json')) {
            $base .= '.json';
        }

        return $base;
    }
}
