<?php

use GlpiPlugin\Urlwidget\Config as UrlwidgetConfig;

function plugin_urlwidget_install()
{
    global $DB;

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();

    $migration = new Migration(PLUGIN_URLWIDGET_VERSION);

    $table = UrlwidgetConfig::getTable();
    if (!$DB->tableExists($table)) {
        $query = "CREATE TABLE `$table` (
                    `id`            int unsigned NOT NULL AUTO_INCREMENT,
                    `name`          VARCHAR(255) NOT NULL DEFAULT '',
                    `url`           VARCHAR(1024) NOT NULL DEFAULT '',
                    `value_path`    VARCHAR(255) NOT NULL DEFAULT '',
                    `value_prefix`  VARCHAR(50) NOT NULL DEFAULT '',
                    `value_suffix`  VARCHAR(50) NOT NULL DEFAULT '',
                    `cache_ttl`     int unsigned NOT NULL DEFAULT '300',
                    `cached_value`  TEXT NULL,
                    `cached_at`     timestamp NULL DEFAULT NULL,
                    `verify_ssl`    TINYINT NOT NULL DEFAULT '1',
                    `height`        int NOT NULL DEFAULT '300',
                    `natural_width`  int NOT NULL DEFAULT '1200',
                    `natural_height` int NOT NULL DEFAULT '800',
                    `date_mod`   timestamp NULL DEFAULT NULL,
                    `date_creation` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`)
                  ) ENGINE=InnoDB
                  DEFAULT CHARSET={$default_charset}
                  COLLATE={$default_collation}";
        $DB->doQuery($query);
    } else {
        // Upgrading from a version that predates the natural size fields
        // (legacy iframe rendering, no longer used but kept for
        // backward compatibility with existing installs).
        if (!$DB->fieldExists($table, 'natural_width')) {
            $migration->addField($table, 'natural_width', 'int', ['value' => 1200, 'after' => 'height']);
        }
        if (!$DB->fieldExists($table, 'natural_height')) {
            $migration->addField($table, 'natural_height', 'int', ['value' => 800, 'after' => 'natural_width']);
        }

        // Upgrading from a version that predates the text-value fetching
        // (the widget used to only render an iframe of the URL).
        if (!$DB->fieldExists($table, 'value_path')) {
            $migration->addField($table, 'value_path', 'string', ['value' => '', 'after' => 'url']);
        }
        if (!$DB->fieldExists($table, 'value_prefix')) {
            $migration->addField($table, 'value_prefix', 'string', ['value' => '', 'after' => 'value_path']);
        }
        if (!$DB->fieldExists($table, 'value_suffix')) {
            $migration->addField($table, 'value_suffix', 'string', ['value' => '', 'after' => 'value_prefix']);
        }
        if (!$DB->fieldExists($table, 'cache_ttl')) {
            $migration->addField($table, 'cache_ttl', 'integer', ['value' => 300, 'after' => 'value_suffix']);
        }
        if (!$DB->fieldExists($table, 'cached_value')) {
            $migration->addField($table, 'cached_value', 'text', ['after' => 'cache_ttl']);
        }
        if (!$DB->fieldExists($table, 'cached_at')) {
            $migration->addField($table, 'cached_at', 'timestamp', ['null' => true, 'after' => 'cached_value']);
        }
        if (!$DB->fieldExists($table, 'verify_ssl')) {
            $migration->addField($table, 'verify_ssl', 'bool', ['value' => 1, 'after' => 'cached_at']);
        }
    }

    $migration->executeMigration();

    return true;
}

function plugin_urlwidget_uninstall()
{
    global $DB;

    $tables = [
        UrlwidgetConfig::getTable(),
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`");
        }
    }

    return true;
}
