<?php

use GlpiPlugin\Urlwidget\Config;

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

$config = new Config();

if (isset($_POST['add'])) {
    $config->add([
        'name'   => $_POST['name'] ?? '',
        'url'    => $_POST['url'] ?? '',
        'height' => (int) ($_POST['height'] ?? 300),
    ]);
    Html::back();
} elseif (isset($_POST['delete'])) {
    $config->delete(['id' => $_POST['id']], true);
    Html::back();
} else {
    Html::back();
}
