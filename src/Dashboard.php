<?php

namespace GlpiPlugin\Urlwidget;

class Dashboard
{
    /**
     * Declares the widget type available for cards (the renderer).
     */
    public static function getTypes($types = [])
    {
        \Toolbox::logInFile('urlwidget', "getTypes() called\n");

        if (!is_array($types)) {
            $types = [];
        }

        // Small inline SVG icon (a monitor/frame glyph) so the widget-type
        // picker always has something to display, even offline.
        $icon = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 86" width="100" height="86">'
            . '<rect x="4" y="4" width="92" height="60" rx="4" fill="none" stroke="#6c7a89" stroke-width="4"/>'
            . '<line x1="4" y1="20" x2="96" y2="20" stroke="#6c7a89" stroke-width="4"/>'
            . '<circle cx="12" cy="12" r="2.5" fill="#6c7a89"/>'
            . '<circle cx="20" cy="12" r="2.5" fill="#6c7a89"/>'
            . '<circle cx="28" cy="12" r="2.5" fill="#6c7a89"/>'
            . '<line x1="50" y1="64" x2="50" y2="76" stroke="#6c7a89" stroke-width="4"/>'
            . '<line x1="30" y1="82" x2="70" y2="82" stroke="#6c7a89" stroke-width="4"/>'
            . '</svg>'
        );

        $types['urlwidget_iframe'] = [
            'label'    => __('Iframe / URL', 'urlwidget'),
            'function' => __CLASS__ . '::renderIframe',
            'image'    => $icon,
        ];

        return $types;
    }

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
            \Toolbox::logInFile('urlwidget', "getCards() called, table={$table}\n");

            if (!$DB->tableExists($table)) {
                \Toolbox::logInFile('urlwidget', "table {$table} does not exist!\n");
                return $cards;
            }

            $iterator = $DB->request(['FROM' => $table, 'ORDER' => 'name']);
            \Toolbox::logInFile('urlwidget', "found " . count($iterator) . " row(s)\n");

            foreach ($iterator as $row) {
                $key = 'urlwidget_' . $row['id'];
                $cards[$key] = [
                    'widgettype' => ['urlwidget_iframe'],
                    'label'      => $row['name'] !== '' ? $row['name'] : __('Iframe / URL', 'urlwidget'),
                    'provider'   => __CLASS__ . '::provideIframeData',
                    'args'       => [
                        'config_id' => $row['id'],
                    ],
                ];
                \Toolbox::logInFile('urlwidget', "added card key={$key} label={$row['name']}\n");
            }

            return $cards;
        } catch (\Throwable $e) {
            \Toolbox::logInFile('urlwidget', "EXCEPTION in getCards(): " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
            return $cards;
        }
    }

    /**
     * Provider: reads the configured URL/height for a given card instance
     * and hands it to the renderer as 'data'.
     */
    public static function provideIframeData(array $params = [])
    {
        $default = [
            'args' => [],
        ];
        $params = array_merge($default, $params);

        $config_id = $params['args']['config_id'] ?? 0;

        $config = new Config();
        $url    = '';
        $height = 300;
        $label  = __('Iframe / URL', 'urlwidget');

        if ($config_id && $config->getFromDB($config_id)) {
            $url    = $config->fields['url'];
            $height = (int) $config->fields['height'];
            $label  = $config->fields['name'] !== '' ? $config->fields['name'] : $label;
        }

        return [
            'data'  => [
                'url'    => $url,
                'height' => $height,
            ],
            'label' => $label,
        ];
    }

    /**
     * Renderer: outputs the actual <iframe> HTML shown on the dashboard grid.
     */
    public static function renderIframe(array $params = [])
    {
        $default = [
            'data'  => [],
            'title' => '',
            'color' => '',
        ];
        $params = array_merge($default, $params);

        $url    = $params['data']['url'] ?? '';
        $height = $params['data']['height'] ?? 300;
        $title  = $params['title'];
        $color  = $params['color'];

        if (empty($url)) {
            return "<div class='card' style='background-color: {$color};'>"
                . "<span class='card-title'>" . htmlspecialchars($title) . "</span>"
                . "<p>" . __('No URL configured for this widget.', 'urlwidget') . "</p>"
                . "</div>";
        }

        $safe_url = htmlspecialchars($url, ENT_QUOTES);

        $html  = "<div class='card' style='background-color: {$color}; padding:0; overflow:hidden;'>";
        $html .= "<iframe src='{$safe_url}' loading='lazy' "
               . "style='border:0; width:100%; height:100%; min-height:{$height}px;'></iframe>";
        $html .= "</div>";

        return $html;
    }
}
