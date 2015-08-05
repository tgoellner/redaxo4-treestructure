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

function rex_treestructure_cssjs_add($params)
{
  if(rex_request('page','string',false)==='structure')
  {
    global $REX;
    $params['subject'].=  "\n".'<script type="text/javascript" src="../files/addons/treestructure/jquery-ui.min.js"></script>'."\n";
    $params['subject'].=  "\n".'<script type="text/javascript" src="../files/addons/treestructure/scripts.js"></script>'."\n";
    if(OOPlugin::isAvailable('be_style','simplerex')) {
      $params['subject'].=  '<link rel="stylesheet" href="../files/addons/treestructure/simplerex/simplerex.css" type="text/css" media="screen, projection, print" />'."\n";
    } elseif(OOPlugin::isAvailable('be_style','agk_skin')) {
      $params['subject'].=  '<link rel="stylesheet" href="../files/addons/treestructure/agk_skin/agk_skin.css" type="text/css" media="screen, projection, print" />'."\n";
    }
  }

  return $params['subject'];
}

function rex_treestructure_updateCategory($param)
{
  /* If any category is saved, make sure that the article will get the same name as the category */
  if(
    is_object($art = OOArticle::getArticleById($param['id'],$param['clang'])) &&
    is_object($cat = OOCategory::getCategoryById($param['id'],$param['clang'])) &&
    $art->getName() != $cat->getName()
  )
  {
    global $REX;
    // set catname = artname
    $qry = "UPDATE `".$REX['TABLE_PREFIX']."article` SET `name`=`catname` WHERE `id`=".$param['id']." AND `clang`=".$param['clang']." LIMIT 1";
    $sql = new rex_sql();
    $sql->setQuery($qry);
    unset($qry,$sql);
  }
}

function rex_treestructure_updateArticle($param)
{
  /* If any start article is saved, make sure that the category will get the same name as the article */
  if(
    is_object($art = OOArticle::getArticleById($param['id'],$param['clang'])) &&
    is_object($cat = OOCategory::getCategoryById($param['id'],$param['clang'])) &&
    $art->getName() != $cat->getName()
  )
  {
    global $REX;
    // set catname = artname
    $qry = "UPDATE `".$REX['TABLE_PREFIX']."article` SET `catname`=`name` WHERE `id`=".$param['id']." AND `clang`=".$param['clang']." LIMIT 1";
    $sql = new rex_sql();
    $sql->setQuery($qry);
    unset($qry,$sql);
  }
}

function rex_treestructure_itemAdded($params)
{
  // store the id of any saved article so we can create appropriate success / error messages in treestructre->doActions();
  if(rex_request('page','string',false)==='structure' && ($func = rex_request('func','string',false)) && ($json = rex_request('json','bool',false)))
  {
    $_REQUEST['treestructure_added_item'] = $params['id'];
  }
  return $params['subject'];
}

function rex_treestructure_itemDeleted($params)
{
  // store the id of any deleted article so we can create appropriate success / error messages in treestructre->doActions();
  if(rex_request('page','string',false)==='structure' && ($func = rex_request('func','string',false)) && ($json = rex_request('json','bool',false)))
  {
    $_REQUEST['treestructure_deleted_item'] = $params['name'];
  }
  return $params['subject'];
}

function rex_treestructure_treeviewAvailable()
{
  if(rex_request('page','string',false)=='structure')
  {
    global $REX;
    $qry = "SELECT COUNT(*) as NUM FROM `".$REX['TABLE_PREFIX']."article`";
    if(count($mp = $REX["USER"]->getMountpoints()) && !$REX['USER']->isAdmin())
    {
      $qry.=" WHERE `path` LIKE '%|".join("|%' OR `path` LIKE '%|",$mp)."|%'";
      $qry.=" OR `id` = ".join(" OR `id` = ",$mp);
    }

    $sql = new rex_sql();
    $sql->setQuery($qry);
    $row = $sql->getRow();
    if(!empty($row['NUM']))
      $num = intval($row['NUM']);
    else
      $num = 0;

    unset($qry,$sql,$row);

    if($num <= $REX['ADDON']['treestructure']['maxitems'])
      return true;
  }
  return false;
}
?>