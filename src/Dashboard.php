<?php

namespace GlpiPlugin\Urlwidget;

class Dashboard
{
    /**
     * Declares the widget type available for cards (the renderer).
     */
    public static function getTypes($types = [])
    {
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
            if (!$DB->tableExists($table)) {
                return $cards;
            }

            $iterator = $DB->request(['FROM' => $table, 'ORDER' => 'name']);
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
            }

            return $cards;
        } catch (\Throwable $e) {
            \Toolbox::logInFile('urlwidget', "EXCEPTION in getCards(): " . $e->getMessage() . "\n");
            return $cards;
        }
    }

    /**
     * Provider: reads the configured URL/height for a given card instance
     * and hands it to the renderer as 'data'.
     *
     * The normal dashboard grid calls this passing each value declared in
     * the card's 'args' array as a separate positional parameter, followed
     * by the standard dashboard $params array. The public "embed" dashboard
     * view, however, calls providers with the $params array alone (config_id
     * nested under $params['args']['config_id']). We accept both shapes so
     * the widget works identically in both contexts.
     */
    public static function provideIframeData(...$raw_args)
    {
        $config_id = 0;
        $params    = [];

        foreach ($raw_args as $arg) {
            if (is_array($arg)) {
                $params = $arg;
            } elseif (is_scalar($arg) && !$config_id) {
                $config_id = (int) $arg;
            }
        }

        if (!$config_id && isset($params['args']['config_id'])) {
            $config_id = (int) $params['args']['config_id'];
        }

        $config = new Config();
        $url            = '';
        $height         = 300;
        $natural_width  = 1200;
        $natural_height = 800;
        $label          = __('Iframe / URL', 'urlwidget');

        if ($config_id && $config->getFromDB($config_id)) {
            $url            = $config->fields['url'];
            $height         = (int) $config->fields['height'];
            $natural_width  = (int) ($config->fields['natural_width'] ?? 1200);
            $natural_height = (int) ($config->fields['natural_height'] ?? 800);
            $label          = $config->fields['name'] !== '' ? $config->fields['name'] : $label;
        }

        return [
            'data'  => [
                'url'            => $url,
                'height'         => $height,
                'natural_width'  => $natural_width,
                'natural_height' => $natural_height,
            ],
            'label' => $label,
        ];
    }

    /**
     * Renderer: outputs the actual <iframe> HTML shown on the dashboard grid.
     *
     * Metabase (and most embedded dashboards) render at a fixed layout size
     * and don't reflow to fit a small card. Instead of shrinking the iframe
     * (which just crops the content), we render it at its natural size and
     * scale the whole thing down with CSS so everything stays visible.
     */
    public static function renderIframe(array $params = [])
    {
        $default = [
            'data'  => [],
            'title' => '',
            'color' => '',
        ];
        $params = array_merge($default, $params);

        $url            = $params['data']['url'] ?? '';
        $height         = $params['data']['height'] ?? 300;
        $natural_width  = (int) ($params['data']['natural_width'] ?? 1200);
        $natural_height = (int) ($params['data']['natural_height'] ?? 800);
        $title          = $params['title'];
        $color          = $params['color'];

        if (empty($url)) {
            return "<div class='card' style='background-color: {$color};'>"
                . "<span class='card-title'>" . htmlspecialchars($title) . "</span>"
                . "<p>" . __('No URL configured for this widget.', 'urlwidget') . "</p>"
                . "</div>";
        }

        $safe_url = htmlspecialchars($url, ENT_QUOTES);

        $html  = "<div class='card' style='background-color: {$color}; padding:0; overflow:hidden; min-height:{$height}px;'>";
        $html .= "<div class='urlwidget-scale-wrap' style='width:100%; height:100%; min-height:{$height}px; overflow:hidden; position:relative;'>";
        $html .= "<iframe src='{$safe_url}' loading='lazy' scrolling='no' "
               . "style='border:0; width:{$natural_width}px; height:{$natural_height}px; "
               . "transform-origin: top left; position:absolute; top:0; left:0;'></iframe>";
        $html .= "</div>";
        $html .= "<script>(function(){";
        $html .= "var wrap = document.currentScript.previousElementSibling;";
        $html .= "var iframe = wrap.querySelector('iframe');";
        $html .= "var nw = {$natural_width}, nh = {$natural_height};";
        $html .= "function resize(){";
        $html .= "  var w = wrap.clientWidth || 1;";
        $html .= "  var scale = w / nw;";
        $html .= "  iframe.style.transform = 'scale(' + scale + ')';";
        $html .= "  wrap.style.height = Math.round(nh * scale) + 'px';";
        $html .= "}";
        $html .= "if (window.ResizeObserver) { new ResizeObserver(resize).observe(wrap); }";
        $html .= "else { window.addEventListener('resize', resize); }";
        $html .= "resize();";
        $html .= "})();</script>";
        $html .= "</div>";

        return $html;
    }
}
