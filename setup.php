<?php

use Glpi\Plugin\Hooks;
use GlpiPlugin\Urlwidget\Dashboard;
use GlpiPlugin\Urlwidget\Config as UrlwidgetConfig;

define('PLUGIN_URLWIDGET_VERSION', '1.1.3');

// Minimal GLPI version, inclusive
define('PLUGIN_URLWIDGET_MIN_GLPI_VERSION', '10.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_URLWIDGET_MAX_GLPI_VERSION', '11.99.99');

function plugin_init_urlwidget()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['urlwidget'] = true;

    // Declare new dashboard cards (one per configured Metabase question).
    // These reuse GLPI's own core "bigNumber" widget type, so no custom
    // widget type/renderer needs to be declared (DASHBOARD_TYPES hook) -
    // this also means the widget renders correctly in the public "embed"
    // dashboard view, which does not reliably support custom widget types.
    $PLUGIN_HOOKS[Hooks::DASHBOARD_CARDS]['urlwidget'] = 'plugin_urlwidget_dashboard_cards';

    if (Plugin::isPluginActive('urlwidget')) {
        // Adds a tab on Setup > General to configure the URL(s)
        Plugin::registerClass(UrlwidgetConfig::class, [
            'addtabon' => \Config::class,
        ]);
    }
}

/**
 * Global function wrapper, called directly by GLPI's hook dispatcher.
 * Kept as a plain function (not a class/method array) since this is the
 * pattern used by official GLPI plugins and is guaranteed to be invoked
 * correctly by the dashboard hook system.
 */
function plugin_urlwidget_dashboard_cards($cards = [])
{
    return Dashboard::getCards($cards);
}

function plugin_version_urlwidget()
{
    return [
        'name'           => 'URL Widget',
        'version'        => PLUGIN_URLWIDGET_VERSION,
        'author'         => 'Thomas Giacomelli',
        'license'        => 'GPLv2+',
        'homepage'       => 'https://github.com/Thomas-Giacomelli/urlwidget',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_URLWIDGET_MIN_GLPI_VERSION,
                'max' => PLUGIN_URLWIDGET_MAX_GLPI_VERSION,
            ],
        ],
    ];
}

function plugin_urlwidget_check_prerequisites()
{
    return true;
}

function plugin_urlwidget_check_config()
{
    return true;
}
