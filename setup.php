<?php

use Glpi\Plugin\Hooks;
use GlpiPlugin\Urlwidget\Dashboard;
use GlpiPlugin\Urlwidget\Config as UrlwidgetConfig;

define('PLUGIN_URLWIDGET_VERSION', '1.1.2');

// Minimal GLPI version, inclusive
define('PLUGIN_URLWIDGET_MIN_GLPI_VERSION', '10.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_URLWIDGET_MAX_GLPI_VERSION', '11.99.99');

function plugin_init_urlwidget()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['urlwidget'] = true;

    // Declare new dashboard widget type (the iframe renderer)
    $PLUGIN_HOOKS[Hooks::DASHBOARD_TYPES]['urlwidget'] = 'plugin_urlwidget_dashboard_types';

    // Declare new dashboard cards (one per configured URL)
    $PLUGIN_HOOKS[Hooks::DASHBOARD_CARDS]['urlwidget'] = 'plugin_urlwidget_dashboard_cards';

    if (Plugin::isPluginActive('urlwidget')) {
        // Adds a tab on Setup > General to configure the URL(s)
        Plugin::registerClass(UrlwidgetConfig::class, [
            'addtabon' => \Config::class,
        ]);
    }
}

/**
 * Global function wrappers, called directly by GLPI's hook dispatcher.
 * Kept as plain functions (not a class/method array) since this is the
 * pattern used by official GLPI plugins and is guaranteed to be invoked
 * correctly by the dashboard hook system.
 */
function plugin_urlwidget_dashboard_types($types = [])
{
    return Dashboard::getTypes($types);
}

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
