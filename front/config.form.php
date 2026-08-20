<?php

use GlpiPlugin\Urlwidget\Config;

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

$config = new Config();

if (isset($_POST['add'])) {
    $natural_height = (int) ($_POST['natural_height'] ?? 800);

    $config->add([
        'name'           => $_POST['name'] ?? '',
        'url'            => $_POST['url'] ?? '',
        'natural_width'  => (int) ($_POST['natural_width'] ?? 1200),
        'natural_height' => $natural_height,
        // Reasonable default so the card isn't collapsed before the
        // scaling script runs (it will resize itself immediately after).
        'height'         => min(300, $natural_height),
    ]);
    Html::back();
} elseif (isset($_POST['delete'])) {
    $config->delete(['id' => $_POST['id']], true);
    Html::back();
} else {
    Html::back();
}
