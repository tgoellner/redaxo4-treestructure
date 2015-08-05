<?php
/**
 * REDAXO Goellner Treeview-Theme - (be_style Plugin)
 *
 * @author post[at]thomasgoellner[dot]de Thomas Göllner
 * @author <a href="http://www.thomasgoellner.de">www.thomasgoellner.de</a>
 *
 *
 * @package redaxo4
 * @version 1.3
 */

$error = '';

if ($error != '')
  $REX['ADDON']['installmsg']['treestructure'] = $error;
else
  $REX['ADDON']['install']['treestructure'] = true;

?>