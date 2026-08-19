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
                    `id`         int unsigned NOT NULL AUTO_INCREMENT,
                    `name`       VARCHAR(255) NOT NULL DEFAULT '',
                    `url`        VARCHAR(1024) NOT NULL DEFAULT '',
                    `height`     int NOT NULL DEFAULT '300',
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
        // Upgrading from a version that predates the natural size fields.
        if (!$DB->fieldExists($table, 'natural_width')) {
            $migration->addField($table, 'natural_width', 'int', ['value' => 1200, 'after' => 'height']);
        }
        if (!$DB->fieldExists($table, 'natural_height')) {
            $migration->addField($table, 'natural_height', 'int', ['value' => 800, 'after' => 'natural_width']);
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
