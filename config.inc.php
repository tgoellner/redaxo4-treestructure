<?php
/**
 * REDAXO TreeStructure Plugin
 *
 * @author post[at]thomasgoellner[dot]de Thomas Göllner
 * @author <a href="http://www.thomasgoellner.de">www.thomasgoellner.de</a>
 *
 *
 * @package redaxo4
 * @version 1.3
 */


$REX['ADDON']['rxid']['treestructure'] = 'xxx';
$REX['ADDON']['perm']['treestructure'] = 'treestructure[]';
$REX['ADDON']['version']['treestructure'] = "1.3";
$REX['ADDON']['author']['treestructure'] = "Thomas Göllner";
$REX['ADDON']['supportpage']['treestructure'] = 'forum.redaxo.de';
$REX['PERM'][] = 'treestructure[]';

// set this variable to keep a good performance on your site -
// if the structure contains more items than this setting
// the addon will show the default structure page
$REX['ADDON']['treestructure']['maxitems'] = 400;
$REX['ADDON']['treestructure']['allow_besearch_in_sructure'] = false;


if($REX['REDAXO'] && !empty($_SESSION[$REX['INSTNAME']]['UID']))
{
  $I18N->appendFile($REX['INCLUDE_PATH']."/addons/treestructure/lang/");
  require_once(dirname(__FILE__). '/extensions/extensions_goellner.inc.php');

  global $REX_USER;

  if(is_object($REX_USER) && ($REX_USER->hasPerm('treestructure[]') || $REX_USER->hasPerm('admin[]')))
  {
    if(rex_treestructure_treeviewAvailable(0))
    {
      $REX['PAGES']['structure']->page->setPath($REX['INCLUDE_PATH'].'/addons/treestructure/pages/index.inc.php');
    }
  }

  rex_register_extension('PAGE_HEADER', 'rex_treestructure_cssjs_add');

  rex_register_extension('CAT_UPDATED', 'rex_treestructure_updateCategory');
  rex_register_extension('ART_UPDATED', 'rex_treestructure_updateArticle');
  rex_register_extension('ART_META_UPDATED', 'rex_treestructure_updateArticle');

  rex_register_extension('ART_ADDED', 'rex_treestructure_itemAdded');
  rex_register_extension('CAT_ADDED', 'rex_treestructure_itemAdded');
  rex_register_extension('ART_DELETED', 'rex_treestructure_itemDeleted');
  rex_register_extension('CAT_DELETED', 'rex_treestructure_itemDeleted');
}
?>
