<?php
/**
 * REDAXO Goellner TreeView-Theme - (by_style Plugin)
 *
 * @author post[at]thomasgoellner[dot]de Thomas Göllner
 * @author <a href="http://www.thomasgoellner.de">www.thomasgoellner.de</a>
 *
 *
 * @package redaxo4
 * @version 1.2
 */

$error = '';

if ($error != '')
  $REX['ADDON']['installmsg']['treestructure'] = $error;
else
  $REX['ADDON']['install']['treestructure'] = 0;

?>