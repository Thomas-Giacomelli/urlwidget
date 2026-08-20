<?php

use GlpiPlugin\Urlwidget\Config;

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

$config = new Config();

if (isset($_POST['add'])) {
    $config->add([
        'name'          => $_POST['name'] ?? '',
        'url'           => $_POST['url'] ?? '',
        'value_path'    => $_POST['value_path'] ?? '',
        'value_prefix'  => $_POST['value_prefix'] ?? '',
        'value_suffix'  => $_POST['value_suffix'] ?? '',
        'cache_ttl'     => (int) ($_POST['cache_ttl'] ?? 300),
        'verify_ssl'    => isset($_POST['verify_ssl']) ? 1 : 0,
    ]);
    Html::back();
} elseif (isset($_POST['delete'])) {
    $config->delete(['id' => $_POST['id']], true);
    Html::back();
} else {
    Html::back();
}
