<?php

namespace GlpiPlugin\Urlwidget;

use CommonDBTM;
use CommonGLPI;
use Session;
use Glpi\Application\View\TemplateRenderer;

class Config extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return __('Widgets URL', 'urlwidget');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof \Config) {
            return self::createTabEntry(self::getTypeName());
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof \Config) {
            self::showConfigForm();
        }
        return true;
    }

    /**
     * Displays the list of configured URLs plus a form to add a new one.
     * Kept intentionally simple: editing an entry is done by deleting and
     * re-adding it.
     */
    public static function showConfigForm()
    {
        global $DB;

        $canedit = Session::haveRight(self::$rightname, UPDATE);

        $rows = [];
        if ($DB->tableExists(self::getTable())) {
            $iterator = $DB->request(['FROM' => self::getTable(), 'ORDER' => 'name']);
            foreach ($iterator as $row) {
                $rows[] = $row;
            }
        }

        TemplateRenderer::getInstance()->display('@urlwidget/config.html.twig', [
            'rows'     => $rows,
            'can_edit' => $canedit,
            'form_url' => \Plugin::getWebDir('urlwidget') . '/front/config.form.php',
        ]);
    }
}
