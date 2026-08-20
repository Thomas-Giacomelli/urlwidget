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
     *
     * The row id is embedded directly in the provider's *method name*
     * (provideIframeData_<id>) instead of being passed through a card
     * 'args' array. GLPI's dashboard grid and its public "embed" view do
     * not call providers the same way (positional args vs a single $params
     * array), which made a param-based approach fragile. Encoding the id in
     * the callable string sidesteps that entirely: whatever arguments GLPI
     * does or doesn't pass, __callStatic() below extracts the id from the
     * method name itself.
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
                    'provider'   => __CLASS__ . '::provideIframeData_' . (int) $row['id'],
                ];
            }

            return $cards;
        } catch (\Throwable $e) {
            \Toolbox::logInFile('urlwidget', "EXCEPTION in getCards(): " . $e->getMessage() . "\n");
            return $cards;
        }
    }

    /**
     * Catches calls to provideIframeData_<id>(...) regardless of what
     * arguments GLPI passed (or didn't), and dispatches to the real logic
     * with the id parsed straight out of the method name.
     */
    public static function __callStatic($name, $arguments)
    {
        if (preg_match('/^provideIframeData_(\d+)$/', $name, $m)) {
            return self::buildIframeData((int) $m[1]);
        }

        \Toolbox::logInFile('urlwidget', "__callStatic() unknown method: {$name}\n");
        return [
            'data'  => [],
            'label' => __('Iframe / URL', 'urlwidget'),
        ];
    }

    private static function buildIframeData(int $config_id)
    {
        $config         = new Config();
        $url            = '';
        $height         = 300;
        $natural_width  = 1200;
        $natural_height = 800;
        $label          = __('Iframe / URL', 'urlwidget');

        $found = $config_id ? $config->getFromDB($config_id) : false;

        \Toolbox::logInFile(
            'urlwidget',
            "buildIframeData(config_id={$config_id}) found=" . var_export($found, true) . "\n"
        );

        if ($found) {
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
     * GLPI already wraps every card in its own container (title bar, drag
     * handle, borders, background) - core widgets only ever return their
     * *inner* content. Our first version additionally wrapped its output in
     * a second '<div class="card">', which fought with GLPI's own chrome
     * and made the card look broken/oversized. This version returns only
     * the inner content, filling whatever space GLPI's own card provides.
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
        ];
        $params = array_merge($default, $params);

        $url            = $params['data']['url'] ?? '';
        $natural_width  = (int) ($params['data']['natural_width'] ?? 1200);
        $natural_height = (int) ($params['data']['natural_height'] ?? 800);
        $title          = $params['title'];

        if (empty($url)) {
            return "<div class='empty-card'>"
                . "<p>" . __('No URL configured for this widget.', 'urlwidget') . "</p>"
                . "</div>";
        }

        $safe_url = htmlspecialchars($url, ENT_QUOTES);

        $html  = "<div class='urlwidget-scale-wrap' style='width:100%; height:100%; overflow:hidden; position:relative;'>";
        $html .= "<iframe src='{$safe_url}' title='" . htmlspecialchars($title, ENT_QUOTES) . "' loading='lazy' scrolling='no' "
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

        return $html;
    }
}
