<?php
/**
 * REDAXO TreeStructure Plugin
 *
 * @author post[at]thomasgoellner[dot]de Thomas Göllner
 * @author <a href="http://www.thomasgoellner.de">www.thomasgoellner.de</a>
 *
 *
 * @package redaxo4
 * @version 1.2
 */
  if(file_exists($file = $REX['INCLUDE_PATH'].'/addons/treestructure/classes/class.treestructure.inc.php'))
  {
    require_once($file);

    if(rex_treestructure_treeviewAvailable())
    {
      $rexSS = new treestructure();
      $rexSS->init();

      if(rex_request('page','string',false)=='structure' && ($func = rex_request('func','string',false)) && ($json = rex_request('json','bool',false)))
      {
        $return = $rexSS->processFunctionRequest($func);
        echo json_encode($return);
        exit();
      }
      else
      {
        require $REX['INCLUDE_PATH'].'/layout/top.php';

        rex_title($I18N->msg('title_structure'));
        require_once($REX['INCLUDE_PATH'].'/functions/function_rex_languages.inc.php');

        echo $rexSS->outputPage();

        require $REX['INCLUDE_PATH'].'/layout/bottom.php';
      }
    }
    else
    {
      require $REX['INCLUDE_PATH'].'/layout/top.php';
      require $REX['INCLUDE_PATH'].'/pages/structure.inc.php';
      require $REX['INCLUDE_PATH'].'/layout/bottom.php';
    }
  }
  else
    exit($file.' not found.');
?>