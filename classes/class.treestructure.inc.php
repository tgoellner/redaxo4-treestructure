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

class treestructure {
  private $initialized=false;
  private $category_id;
  private $article_id;
  private $clang;
  private $ctype;
  private $edit_id;
  private $reqfunction;
  private $mountpoints;
  private $catperms;
  private $catStatusTypes;
  private $artStatusTypes;
  private $template_select;
  private $availableFunctions;
  private $functionsThatUseTheForm;
  private $numOfItems;

  private $openItems;

  private $OUT='';

  private $info = '';
  private $warning = '';

  public function init() {
    global $REX, $KATPERM, $I18N;

    $this->category_id  = rex_request('category_id', 'rex-category-id');
    $this->article_id  = rex_request('article_id',  'rex-article-id');
    $this->clang    = rex_request('clang',       'rex-clang-id', $REX['START_CLANG_ID']);
    $this->ctype    = rex_request('ctype',       'rex-ctype-id');
    $this->edit_id    = rex_request('edit_id',     'rex-category-id');
    $this->reqfunction  = rex_request('function',    'string');

    $this->getOpenItems();

    $this->functionsThatUseTheForm = array(
      'edit_art',    // edit an article
      'edit_cat',    // edit a category
      'add_art',    // add an article
      'add_cat'    // add a category
    );

    $this->availableFunctions = array_merge(
      $this->functionsThatUseTheForm,
      array(
        'move_art',    // move an article to another position
        'del_art',    // delete an article
        'del_cat',    // delete a category
        'status'    // change the status of an article/category
      )
    );

    // --------------------------------------------- Mountpoints
    $this->catperms = $REX["USER"]->getMountpoints();
    
    if(count($this->mountpoints = $this->catperms)>1) {
      // kategorien innerhalb von elternkategorien ausschließen...
      $tmp = array();
      foreach($this->mountpoints as $catid) {
        $add = true;
        
        if(!empty($tmp) && (is_object($obj = OOCategory::getCategoryById($catid)))) {
          $path = $obj->getValue('path').$obj->getValue('id');
          $path = explode('|',$path);
          
          foreach($tmp as $tmpid) {
            if(in_array($tmpid,$path)) {
              $add = false;
              break;
            }
          }
        }
        
        if($add)
          $tmp[] = $catid;
          
        unset($obj,$path,$add,$tmpid);
      }
      
      $this->mountpoints = $tmp;
      unset($tmp,$catid);
    }
    
    if(count($this->mountpoints) && $this->category_id == 0) {
      // Nur ein Mointpoint -> Sprung in die Kategory
      $this->category_id = reset($this->mountpoints);
    }

    // --------------------------------------------- Rechte pruefen
    global $category_id; $category_id = $this->category_id;
    global $article_id; $article_id = $this->article_id;
    global $clang; $clang = $this->clang;
    global $ctype; $ctype = $this->ctype;
    global $edit_id; $edit_id = $this->edit_id;
    global $function; $function = $this->reqfunction;
    global $sprachen_add; $sprachen_add = '&amp;category_id='. $this->category_id;

    #  require_once($REX['INCLUDE_PATH'].'/functions/function_rex_category.inc.php');
    require_once($REX['INCLUDE_PATH'].'/functions/function_rex_content.inc.php');

    // -------------- STATUS_TYPE Map
    $this->catStatusTypes = rex_categoryStatusTypes();
    $this->artStatusTypes = rex_articleStatusTypes();

    $this->TEMPLATE_NAME = array();

    $this->numOfItems = 0;

    $this->initialized = true;
  }

  public function getItemCount() {
    global $REX;
    $qry = "SELECT COUNT(*) as NUM FROM `".$REX['TABLE_PREFIX']."article`";
    if(count($this->mountpoints) && !$REX['USER']->isAdmin())
    {
      $qry.=" WHERE `path` LIKE '%|".join("|%' OR `path` LIKE '%|",$this->mountpoints)."|%'";
      $qry.=" OR `id` = ".join(" OR `id` = ",$this->mountpoints);
    }

    $sql = new rex_sql();
    $sql->setQuery($qry);
    $row = $sql->getRow();
    if(!empty($row['NUM']))
      return intval($row['NUM']);
    else
      return 0;
  }

  private function getOpenItems($add=null) {
    $this->openItems   = array();

    if($open = rex_request('category_id','int',false))
    {
      $open = array($open);
    }
    else if($open = rex_request('open','string',false))
    {
      // rex_set_session('treestructure_open_items', $open);
      if($open==='collapse_all')
        $open = array();
      elseif($open==='expand_all')
        $open = array($open);
      else
        $open = strpos($open,',') ? explode(',',$open) : array($open);

    }
    else
    {
      // try to get the open items from session
      // if($open = rex_session('treestructure_open_items','string',null))
      if($open = rex_cookie('treestructure_open_items','string', null))
      {
        $open = strpos($open,',') ? explode(',',$open) : array($open);
      }
      else
        $open = array();
    }

    if(!in_array('expand_all',$open))
    {
      if(!empty($add) && intval($add)>0)
        $open[] = $add;

      foreach($open as $id)
      {
        if(is_object($obj = OOCategory::getCategoryById($id)))
        {
          $cat = '';
          if(!$obj->isStartArticle() && $obj->hasValue('re_id') && intval($obj->getValue('re_id'))>0)
            $cat = strval(intval($obj->getValue('re_id'))).'|';

          $this->openItems[] = intval($obj->getId());
          $path = $obj->getValue('path').$cat;
          $path = explode('|',$path);

          foreach($path as $i)
          {
            if(intval($i)>0)
              $this->openItems[] = intval($i);
          }
        }
      }
    }
    else
      $this->openItems = $open;

    $this->openItems = array_unique($this->openItems);

    return $this->openItems;
  }

  private function initCategoryTemplates($catid=0)
  {
    $this->TEMPLATE_NAME = array();

    $slct = new rex_select;
    $slct->setName('template_id');
    $slct->setId('rex-form-template');
    $slct->setSize(1);

    $templates = OOCategory::getTemplates($catid);

    if(count($templates)>0)
    {
      foreach($templates as $t_id => $t_name)
      {
        $slct->addOption(rex_translate($t_name, null, false), $t_id);
        $this->TEMPLATE_NAME[$t_id] = rex_translate($t_name);
      }
    }
    else
    {
      global $I18N;
      $slct->addOption($I18N->msg('option_no_template'), '0');
      $this->TEMPLATE_NAME[0] = $I18N->msg('template_default_name');
    }

    return $slct;
  }

  private function getTemplateName($catid=0,$template_id=0)
  {
    $templates = OOCategory::getTemplates($catid);

    if(count($templates)>0)
    {
      foreach($templates as $t_id => $t_name)
      {
        if($t_id==$template_id)
          return $t_name;
      }
    }

    return false;
  }

  private function hasCatPerm($category_id=null, $clang=null)
  {
    global $REX;

    // admins and all-cat-users definately have access to this category
    if($REX['USER']->hasPerm('csw[0]') || $REX['USER']->isAdmin())
      return true;

    // no $category_id given? return false...
    if($category_id===null)
      return false;

    // no clang given? use the current one...
    if($clang===null)
      $clang = $this->clang;

    // has the user permission for the fiven category_id?
    if($REX['USER']->hasPerm('csw['. $category_id .']'))
      return true;

    if(empty($this->catPaths))
    {
      $this->catPaths = array();

      global $REX;
      $sql = rex_sql::factory();
      $sql->setQuery("SELECT `id`,`clang`,`path` FROM ".$REX['TABLE_PREFIX']."article WHERE startpage=1");
      if(count($rows = $sql->getArray()))
      {
        foreach($rows as $row)
          $this->catPaths[$row['clang'].'_'.$row['id']] = $row['path'];
      }
    }

    if(empty($this->catPaths[$clang.'_'.$category_id]))
      return false;

    $path = explode('|',$this->catPaths[$clang.'_'.$category_id]);
    for($i=0; $i<count($path); $i++)
    {
      $catid = intval($path[$i]);

      if($catid>0 && $REX['USER']->hasPerm('csw['.$catid.']'))
        return true;
    }

    return false;
  }


  private function actionsAllowed($id=null)
  {
    $actions_allowed = false;
    global $REX;
    if(is_object($article = OOArticle::getArticleById($id,$this->clang)))
    {
      if(($REX['USER']->isAdmin() || $REX['USER']->hasPerm('article2startpage[]')) ||
         (!$article->isStartArticle() && ($REX['USER']->isAdmin() || $REX['USER']->hasPerm('article2category[]'))) ||
         ($article->isStartArticle() && ($REX['USER']->isAdmin() || ($REX['USER']->hasPerm('article2category[]') && $REX['USER']->hasCategoryPerm($article->getValue('re_id'))))) ||
         (($REX['USER']->isAdmin() || $REX['USER']->hasPerm('copyContent[]')) && count($REX['USER']->getClangPerm()) > 1) ||
         (!$article->isStartArticle() && ($REX['USER']->isAdmin() || $REX['USER']->hasPerm('moveArticle[]'))) ||
         ($REX['USER']->isAdmin() || $REX['USER']->hasPerm('copyArticle[]')) ||
         ($article->isStartArticle() && ($REX['USER']->isAdmin() || ($REX['USER']->hasPerm('moveCategory[]') && $REX['USER']->hasCategoryPerm($article->getValue('re_id')))))
        )
      {
        $actions_allowed = true;
      }
    }
    unset($article);

    return $actions_allowed;
  }

  public function getQuickJumpPages() {
    global $REX;

    if(file_exists($file = $REX['INCLUDE_PATH'].'/addons/treestructure/quickjump.txt')) {
      $data = file_get_contents($file);
      $data = explode("\n",$data);
      $pages = array();
      foreach($data as $line) {
        $line = trim($line);
        if(!empty($line)) {
          $line = explode(',',$line);
          $options = array();
          foreach($line as $option) {
            if(($p = strpos($option,':'))!==false) {
              $key = trim(substr($option,0,$p));
              $value = trim(substr($option,$p+1));
              if(!empty($key) && !empty($value)) {
                $options[$key] = rex_translate($value);
              }
            }
          }
          if(!empty($options['page'])) {
            $allowed = false;
            if($REX['USER']->isAdmin())
              $allowed = true;
            else if($options['page']=='content' && !empty($options['article_id'])) {
              $clang = !empty($options['clang']) ? $options['clang'] : $REX['CUR_CLANG'];
              if(is_object($art = OOArticle::getArticleById($options['article_id'],$clang))) {
                $catid = $art->isStartArticle() ? $art->getId() : $art->getCategoryId();
                if($this->hasCatPerm(catid, $clang)) {
                  if(!empty($options['mode']) && $REX['USER']->hasPerm('editContentOnly[]')) {
                  }
                  else {
                    $allowed = true;
                    if(empty($options['title']))
                      $options['title'] = $art->getName();
                  }
                }                  
              }
              unset($clang,$art,$catid);
            }
            else if($REX['USER']->hasPerm($options['page'].'['.(!empty($options['plugin']) ? $options['plugin'] : '').']')) {
              $allowed = true;
            }

            if($allowed) {
              $title = !empty($options['title']) ? $options['title'] : $options['page'];
              unset($options['title']);
              $url = 'index.php?'.http_build_query($options);
              $pages[$url] = $title;
            }
          }
        }
      }
    }
    unset($data,$line,$options,$p,$option,$key,$value,$allowed,$title,$url);
      
    if(!empty($pages)) {
      $tmp = '<div id="rex-treestructure-quickjump"'.(count($pages)>6 ? ' class="two-rows"' : '').'><p class="title">Schnell-Start-Menü:</p><ul class="cf">';
      foreach($pages as $url => $name)
        $tmp.='<li><a href="'.$url.'">'.$name.'</a></li>';
      $tmp.='</ul></div>';
      unset($url,$name);

      return $tmp;
    }
    return null;
  }

  public function outputPage() {
    global $REX;
    if(!$this->initialized)
      $this->init();

    if($tmp = $this->getQuickJumpPages()) {
      $this->OUT.= $tmp;
    }

    if(!empty($this->warning))
      $this->OUT.= rex_warning($this->warning);

    if(!empty($this->info))
      $this->OUT.= rex_info($this->info);

    if(!empty($REX['ADDON']['treestructure']['allow_besearch_in_sructure'])) {
      $this->OUT.= rex_register_extension_point('PAGE_STRUCTURE_HEADER', '',
        array(
          'category_id' => $this->category_id,
          'clang' => $this->clang
        )
      );
    }

    $cls = array();
    if($REX['USER']->isAdmin() || ($REX['USER']->hasPerm('moveArticle[]') && !$REX['USER']->hasPerm('editContentOnly[]')))
      $cls[] = 'move-article';

    if($REX['USER']->isAdmin() || ($REX['USER']->hasPerm('moveCategory[]') && !$REX['USER']->hasPerm('editContentOnly[]')))
      $cls[] = 'move-category';

    $this->OUT.='<ul id="rex-treestructure"'.(!empty($cls) ? ' class="'.join(' ',$cls).'"' : '').'>';

    $this->OUT.= $this->outputCategory();

    $this->OUT.= '</ul>';

    echo $this->OUT;
  }

  private function getActionUrl($obj=null,$func=null,$action=false)
  {
    if(is_object($obj))
    {
      $obj = array(
        'category_id' => $obj->getCategory() ? $obj->getCategory()->getId() : 0,
        'clang' => $obj->getClang(),
        'article_id' => $obj->getId()
      );
    }

    if(is_array($obj) && !empty($obj) && in_array($func,$this->availableFunctions))
    {
      $obj = array(
        'category_id='.(substr($func,0,4)=='add_' ? $obj['id'] : $obj['category_id']),
        'clang='.$obj['clang'],
        'article_id='.$obj['id'],
        'func='.$func,
        'page=structure',
        'action='.($action===true ? '1' : '0'),
        'open='.join(',',$this->openItems)
      );


    }
    else
      $obj = array('page=structure');

    return 'index.php?'.join('&amp;',$obj);
  }

  private function outputCategory($cat=null,$level=0)
  {
    $return = '';

    if(empty($cat))
    {
      if(count($this->mountpoints)>0)
      {
        foreach($this->mountpoints as $catid)
          $return.= $this->outputCategory($catid);
      }
      else
      {
        $cat = $this->walkCategories(0);
        if($this->category_id!=0)
          $level=1;
      }
    }
    elseif(is_string($cat))
    {
      $cat = $this->walkCategories(intval($cat));
      if($cat!=0)
        $level=1;
    }

    if(!empty($cat))
    {
      global $REX, $I18N;

      $buttons = array();
      $actions = array();
      $classes = array();

      if(!empty($cat['id']))
        $buttons[] = '<a title="'.$I18N->msg('show').'" class="rex-view" href="../../..' . rex_getUrl($cat['id'],$cat['clang']) . '" target="_blank" '. rex_tabindex() .'>' . $I18N->msg('show') . '</a>';

      $kat_status = $this->catStatusTypes[$cat['status']][0];
      $status_class = $this->catStatusTypes[$cat['status']][1];
      $this->initCategoryTemplates($cat['id']);

      if ($this->hasCatPerm($cat['id'])) {
        if(!$REX['USER']->hasPerm('editContentOnly[]'))
          $actions[] = '<a class="rex-i-element rex-i-category-add" href="'.$this->getActionUrl($cat,'add_cat').'"'. rex_accesskey($I18N->msg('add_category'), $REX['ACKEY']['ADD']) .'><span class="rex-i-element-text">'.$I18N->msg("add_category").'</span></a>';

        if(!$REX['USER']->hasPerm('editContentOnly[]') || ($REX['USER']->hasPerm('editContentOnly[]') && in_array($cat['id'],$this->catperms) && !in_array($cat['id'],$this->mountpoints))) {
          $actions[] = '<a class="rex-i-element rex-i-article-add" href="'.$this->getActionUrl($cat,'add_art').'"'. rex_accesskey($I18N->msg('article_add'), $REX['ACKEY']['ADD_2']) .'><span class="rex-i-element-text">'. $I18N->msg('article_add') .'</span></a>';
        }
      }

      if(!empty($cat['id']))
      {
        if($this->hasCatPerm($cat['id']))
        {
          // online/offline button
          if ($REX['USER']->isAdmin() || $this->hasCatPerm($cat['id']) && $REX['USER']->hasPerm('publishCategory[]'))
            $buttons[] = '<a title="'. $kat_status .'" class="rex-status '. $status_class .'" href="'.$this->getActionUrl($cat,'status',true).'">'. $kat_status .'</a>';
          else
            $buttons[] = '<span title="'. $kat_status .'" class="rex-status '. $status_class .'">'. $kat_status .'</span>';

          // delete button
          if(!$REX['USER']->hasPerm('editContentOnly[]'))
          {
            if(empty($cat['categories']) && empty($cat['articles']) && !$cat['isSiteStartArticle'] && !$cat['isNotFoundArticle'])
              $actions[] = '<a title="'. $I18N->msg('delete') .'" class="rex-delete" href="'.$this->getActionUrl($cat,'del_cat',true).'" title="'.$I18N->msg('delete').': '.$cat['catname'].' ?">'.$I18N->msg('delete').'</a>';
            else
            {
              if(empty($cat['categories']) || empty($cat['articles']))
                $actions[] = '<span title="'. $I18N->msg('pool_kat_not_deleted') .'" class="rex-delete">'.$I18N->msg('delete').'</span>';
              elseif($cat['isSiteStartArticle'])
                $actions[] = '<span title="'. $I18N->msg('cant_delete_sitestartarticle') .'" class="rex-delete">'.$I18N->msg('delete').'</span>';
              elseif($cat['isNotFoundArticle'])
                $actions[] = '<span title="'. $I18N->msg('cant_delete_notfoundarticle') .'" class="rex-delete">'.$I18N->msg('delete').'</span>';
            }

            $actions[] = '<a title="' . $I18N->msg('metadata') . '" class="rex-metadata" href="index.php?page=content&amp;article_id=' . $cat['id'] . '&amp;mode=meta&amp;clang=' . $cat['clang'] . '"'. rex_tabindex() .'>' . $I18N->msg('metadata') . '</a>';

            if($this->actionsAllowed($cat['id']))
              $actions[] = '<a title="' . $I18N->msg('actions') . '" class="rex-actions" href="index.php?page=content&amp;article_id=' . $cat['id'] . '&amp;mode=meta&amp;subpage=actions&amp;clang=' . $cat['clang'] . '"'. rex_tabindex() .'>' . $I18N->msg('actions') . '</a>';
          }

          // edit button
          $actions[] = '<a title="'. $I18N->msg('edit') .'" class="rex-edit" href="'.$this->getActionUrl($cat,'edit_cat').'">'. $I18N->msg('edit') .'</a>';
        }
        elseif ($REX['USER']->hasPerm('csw['. $cat['id'] .']'))
        {
          $actions[] = '<span class="rex-delete">'. $I18N->msg('change') .'</span>';
          $actions[] = '<span class="rex-delete">'. $I18N->msg('delete') .'</span>';
          $buttons[] = '<span class="rex-status '. $status_class .'">'. $kat_status .'</span>';
        }
      }

      if(!empty($buttons) || !empty($actions))
      {
        // sub categories...
        $subcats = '';
        if(!empty($cat['categories']) && is_array($cat['categories']))
        {
          $children = array();
          foreach($cat['categories'] as $child) {
            if(($child = trim($this->outputCategory($child,$level+1)))!='') {
              $children[] = $child;
            }
          }

          if(!empty($children)){
            $subcats =  '<ul class="rex-subcategories">'.join('',$children).'</ul>';
          }
          unset($children,$child);
        }

        // sub articles
        $subarts = '';
        if(!empty($cat['articles']) && is_array($cat['articles'])) {
          $children = array();

          foreach($cat['articles'] as $child) {
            if(($child = trim($this->outputArticle($child,$level+1)))!='') {
              $children[] = $child;
            }
          }

          if(!empty($children)){
            $subarts =  '<ul class="rex-subarticles">'.join('',$children).'</ul>';
          }
          unset($children,$child);
        }

        $classes[] = 'rex-category';
        $classes[] = 'rex-prior-'.$cat['prior'];
        if(empty($cat['id']))
          $classes[] = 'rex-category-root';
        else {
          $classes[] = in_array(intval($cat['id']),$this->openItems) || in_array('expand_all',$this->openItems) ? '' : 'collapsed';
          $classes[] = 'rex-article-id-'.$cat['id'];
          $classes[] = 'rex-clang-id-'.$cat['clang'];
        }
        if(!empty($cat['isSiteStartArticle'])) $classes[] = 'rex-sitestart-article';
        if(!empty($cat['isNotFoundArticle'])) $classes[] = 'rex-notfound-article';
        if(!empty($buttons)) $classes[] = 'has-buttons';
        if(!empty($actions)) $classes[] = 'has-actions';
        if(!empty($cat['template_id'])) $classes[] = 'rex-template-id-'.$cat['template_id'];

        // open...
        $return =  '<li id="rex-article-'.strval($cat['id']).'" class="'.join(' ',$classes).'">';

        // the mouse senstive area
        $return.=  '<span class="sensitive-area"'.(!empty($level) ? ' style="padding-left:'.strval($level*24).'px"' : '').'>';

        // the collapse / open button
        if(!empty($cat['id']) && (!empty($subcats) || !empty($subarts)))
        {
          $m = $level>1 ? ($level-1)*24 : 0;
          $return.=  '<span class="rex-collapse" style="left:'.strval($m).'px"></span>';
          unset($m);
        }

        // the icon..
        $icon = '<span class="article-icon"';
        if(!empty($cat['isSiteStartArticle']))
          $icon.=' title="'.$I18N->msg('treestructure_sitestartarticle').'"';
        elseif(!empty($cat['isNotFoundArticle']))
          $icon.=' title="'.$I18N->msg('treestructure_notfoundarticle').'"';
        $icon.=  '></span>';
        $return.= $icon;
        unset($icon);

        // the link..
        if(!empty($cat['id']))
          $return.=  '<a title="' . $I18N->msg('edit_mode') . '" class="article-link" href="index.php?page=content&amp;article_id='. $cat['id'] .'&amp;category_id='. $cat['id'] .'&amp;clang='. $cat['clang'] .'&amp;mode=edit">';

        // the name
        $return.=  '<span class="article-name">'.$cat['catname'].'</span>';

        // the id
        if(!empty($cat['id']))
          $return.=  '<span class="article-id">'.rex_translate($I18N->msg('treestructure_article_id',strval($cat['id']))).'</span>';

        // the template
        if(!empty($cat['id']))
        {
          $tmpl = isset($this->TEMPLATE_NAME[$cat['template_id']]) ? $this->TEMPLATE_NAME[$cat['template_id']] : '';
          $return.=  '<span class="article-template">'.$tmpl.'</span>';
        }

        // close the link
        if(!empty($cat['id']))
          $return.=  '</a>';

        // the buttons
        $return.=  '<span class="article-buttons">'.join('',$buttons).'</span>';

        // the actions
        $return.=  '<span class="article-actions">'.join('',$actions).'</span>';

        // the date
        if(!empty($cat['time']))
          $return.=  '<span class="article-time">'.rex_translate($I18N->msg('treestructure_article_time',$cat['time'])).'</span>';

        // close the mouse senstive area
        $return.=  '</span>';

        // sub categories...
        $return.=  $subcats;

        // sub articles
        $return.=  $subarts;

        // close...
        $return.=  '</li>';
      }
    }

    return $return;
  }

  private function outputArticle($article=null,$level=0)
  {
    $return = '';

    if(empty($article))
      return '';

    global $REX, $I18N;

    $buttons = array();
    $actions = array();
    $classes = array();

    $buttons[] = '<a title="'.$I18N->msg('show').'" class="rex-view" href="../../..' . rex_getUrl($article['id'],$article['clang']) . '" target="_blank" '. rex_tabindex() .'>' . $I18N->msg('show') . '</a>';

    if ($this->hasCatPerm($article['category_id'])) {

      $article_status = $this->artStatusTypes[$article['status']][0];
      $status_class = $this->artStatusTypes[$article['status']][1];

      // online/offline button
      if ($REX['USER']->isAdmin() || $this->hasCatPerm($article['category_id']) && $REX['USER']->hasPerm('publishCategory[]'))
        $buttons[] = '<a title="'. $article_status .'" class="rex-status '. $status_class .'" href="'.$this->getActionUrl($article,'status',true).'">'. $article_status .'</a>';
      else
        $buttons[] = '<span title="'. $article_status .'"  class="rex-status '. $status_class .'">'. $article_status .'</span>';

      // delete button
      if(!$REX['USER']->hasPerm('editContentOnly[]'))
      {
        if(!$article['isSiteStartArticle'] && !$article['isNotFoundArticle'])
          $actions[] = '<a title="'.$I18N->msg('delete').'" class="rex-delete" href="'.$this->getActionUrl($article,'del_art',true).'" title="'.$I18N->msg('delete').': '.$article['artname'].' ?">'.$I18N->msg('delete').'</a>';
        else
        {
          if($article['isSiteStartArticle'])
            $actions[] = '<span title="'. $I18N->msg('cant_delete_sitestartarticle') .'" class="rex-delete">'.$I18N->msg('delete').'</span>';
          elseif($article['isNotFoundArticle'])
            $actions[] = '<span title="'. $I18N->msg('cant_delete_notfoundarticle') .'" class="rex-delete">'.$I18N->msg('delete').'</span>';
        }

        $actions[] = '<a title="' . $I18N->msg('metadata') . '" class="rex-metadata" href="index.php?page=content&amp;article_id=' . $article['id'] . '&amp;mode=meta&amp;clang=' . $article['clang'] . '"'. rex_tabindex() .'>' . $I18N->msg('metadata') . '</a>';

        $actions[] = '<a title="' . $I18N->msg('actions') . '" class="rex-actions" href="index.php?page=content&amp;article_id=' . $article['id'] . '&amp;mode=meta&amp;subpage=actions&amp;clang=' . $article['clang'] . '"'. rex_tabindex() .'>' . $I18N->msg('actions') . '</a>';

      }

      // edit button
      $actions[] = '<a title="'. $I18N->msg('edit') .'" class="rex-edit" href="'.$this->getActionUrl($article,'edit_art').'">'. $I18N->msg('edit') .'</a>';
    }
    elseif ($REX['USER']->hasPerm('csw['. $i_category_id .']'))
    {
      $actions[] = '<span class="rex-delete">'. $I18N->msg('change') .'</span>';
      $actions[] = '<span class="rex-delete">'. $I18N->msg('delete') .'</span>';
      $buttons[] = '<span class="rex-status '. $status_class .'">'. $article_status .'</span>';
    }

    if(!empty($buttons) || !empty($actions))
    {
      $classes[] = 'rex-article';
      $classes[] = 'rex-prior-'.$article['prior'];
      if(!empty($article['isSiteStartArticle'])) $classes[] = 'rex-sitestart-article';
      if(!empty($article['isNotFoundArticle'])) $classes[] = 'rex-notfound-article';
      if(!empty($buttons)) $classes[] = 'has-buttons';
      if(!empty($actions)) $classes[] = 'has-actions';
      if(!empty($article['template_id'])) $classes[] = 'rex-template-id-'.$article['template_id'];
      $classes[] = 'rex-article-id-'.$article['id'];
      $classes[] = 'rex-clang-id-'.$article['clang'];

      // open...
      $return =  '<li id="rex-article-'.strval($article['id']).'" class="'.join(' ',$classes).'">';

      // the mouse senstive area
      $return.=  '<span class="sensitive-area"'.(!empty($level) ? ' style="padding-left:'.strval($level*24).'px"' : '').'>';

      // the icon..
      $icon = '<span class="article-icon"';
      if(!empty($article['isSiteStartArticle']))
        $icon.=' title="'.$I18N->msg('treestructure_sitestartarticle').'"';
      elseif(!empty($article['isNotFoundArticle']))
        $icon.=' title="'.$I18N->msg('treestructure_notfoundarticle').'"';
      $icon.=  '></span>';
      $return.= $icon;
      unset($icon);

      // the link..
      $return.=  '<a title="' . $I18N->msg('edit_mode') . '" class="article-link" href="index.php?page=content&amp;article_id='. $article['id'] .'&amp;category_id='. $article['category_id'] .'&amp;clang='. $article['clang'] .'&amp;mode=edit">';

      // the name
      $return.=  '<span class="article-name">'.strval($article['artname']).'</span>';

      // the id
      $return.=  '<span class="article-id">'.rex_translate($I18N->msg('treestructure_article_id',strval($article['id']))).'</span>';

      // the template
      $tmpl = isset($this->TEMPLATE_NAME[$article['template_id']]) ? $this->TEMPLATE_NAME[$article['template_id']] : '';
      $return.=  '<span class="article-template">'.$tmpl.'</span>';

      // close the link
      $return.=  '</a>';

      // the buttons
      $return.=  '<span class="article-buttons">'.join('',$buttons).'</span>';

      // the actions
      $return.=  '<span class="article-actions">'.join('',$actions).'</span>';

      // the date
      if(!empty($article['time']))
        $return.=  '<span class="article-time">'.rex_translate($I18N->msg('treestructure_article_time',$article['time'])).'</span>';

      // close the mouse senstive area
      $return.=  '</span>';

      // close...
      $return.=  '</li>';
    }

    return $return;
  }

  public function walkCategories($cat=null)
  {
    $return = array(
      'catname'    => '',
      'artname'    => '',
      'clang'      => $this->clang,
      'id'         => 0,
      'prior'      => 0,
      'time'       => 0,
      'status'     => 0,
      'template_id'=> -1,
      'category_id' => 0,
      'categories' => array(),
      'articles'   => array(),
      'isSiteStartArticle' => false,
      'isNotFoundArticle' => false
    );

    if(empty($cat))
    {
      global $I18N;
      $cats = OOCategory::getRootCategories();
      $arts = OOArticle::getRootArticles();
      $return['catname'] = $I18N->msg('treestructure_root');
    }
    elseif(is_string($cat) && !is_nan(intval($cat)) && intval($cat)>0)
      $cat = OOCategory::getCategoryById(intval($cat),$this->clang);
    elseif(is_int($cat))
      $cat = OOCategory::getCategoryById($cat,$this->clang);

    if(is_object($cat))
    {
      $cats = $cat->getChildren();
      $arts = $cat->getArticles();

      $return['catname']     = $cat->getName();
      $return['artname']     = $cat->getStartArticle()->getName();
      $return['id']          = $cat->getId();
      $return['prior']      = $cat->getPriority();
      $return['time']        = date('d.m.Y H:i:s',($cat->hasValue('art_newsdate') && $cat->getValue('art_newsdate')!='' ? $cat->getValue('art_newsdate') : $cat->getUpdateDate()));
      $return['status']      = $cat->isOnline() ? 1 : 0;
      $return['template_id']  = $cat->getValue('template_id');
      $return['category_id']  = $cat->getParentId() ? $cat->getParentId() : 0;
      $return['isSiteStartArticle']   = $cat->isSiteStartArticle();
      $return['isNotFoundArticle']   = $cat->isNotFoundArticle();
    }

    if(!empty($cats))
    {
      foreach($cats as $child)
      {
        $return['categories'][] = $this->walkCategories($child);
      }
    }

    if(!empty($arts))
    {
      foreach($arts as $art)
      {
        if(is_object($art) && !$art->isStartArticle())
        {
          $return['articles'][] = array(
            'catname'    => $return['catname'],
            'artname'    => $art->getName(),
            'clang'      => $this->clang,
            'id'         => $art->getId(),
            'prior'      => $art->getPriority(),
            'time'       => date('d.m.Y H:i:s',($art->hasValue('art_newsdate') && $art->getValue('art_newsdate')!='' ? $art->getValue('art_newsdate') : $art->getUpdateDate())),
            'status'     => $art->isOnline() ? 1 : 0,
            'template_id'=> $art->getValue('template_id'),
            'category_id'=> $art->getCategoryId() ? $art->getCategoryId() : 0,
            'isSiteStartArticle' => $art->isSiteStartArticle(),
            'isNotFoundArticle' => $art->isNotFoundArticle()
          );
        }
      }
    }

    return $return;
  }

  private function categoryActions() {

    global $REX, $KATPERN;

    if (rex_post('catedit_function', 'boolean') && $this->edit_id != '' && $KATPERM) {
      // --------------------- KATEGORIE EDIT
      $data = array();
      $data['catprior'] = rex_post('Position_Category', 'int');
      $data['catname']  = rex_post('kat_name', 'string');
      $data['path']     = $KATPATH;

      list($success, $message) = rex_editCategory($this->edit_id, $this->clang, $data);

      if($success)
        $this->info = $message;
      else
        $this->warning = $message;
    }
    elseif ($this->reqfunction == 'catdelete_function' && $this->edit_id != '' && $KATPERM && !$REX['USER']->hasPerm('editContentOnly[]')) {
      // --------------------- KATEGORIE DELETE
      list($success, $message) = rex_deleteCategoryReorganized($this->edit_id);

      if($success) {
        $this->info = $message;
      }
      else {
        $this->warning = $message;
        $this->reqfunction = 'edit';
      }
    }
    elseif ($this->reqfunction == 'status' && $this->edit_id != '' && ($REX['USER']->isAdmin() || $KATPERM && $REX['USER']->hasPerm('publishArticle[]'))) {
      // --------------------- KATEGORIE STATUS
      list($success, $message) = rex_categoryStatus($this->edit_id, $this->clang);

      if($success)
        $this->info = $message;
      else
        $this->warning = $message;
    }
    elseif (rex_post('catadd_function', 'boolean') && $KATPERM && !$REX['USER']->hasPerm('editContentOnly[]'))
    {
      // --------------------- KATEGORIE ADD
      $data = array();
      $data['catprior'] = rex_post('Position_New_Category', 'int');
      $data['catname']  = rex_post('category_name', 'string');
      $data['path']     = $KATPATH;

      list($success, $message) = rex_addCategory($this->category_id, $data);

      if($success)
        $this->info = $message;
      else
        $this->warning = $message;
    }
  }

  private function articleActions($function=null) {

    global $REX, $KATPERM;
    $article_id = rex_post('article_id','rex-article-id');
    $clang = rex_post('clang', 'int', $REX['CUR_CLANG']);

    $return = array(
      'status'   => 'error',
      'message'   => '',
      'html'    => '',
      'func'    => $function,
      'article'  => array()
    );
    if ($this->reqfunction == 'status_article' && $this->article_id != '' && ($REX['USER']->isAdmin() || $KATPERM && $REX['USER']->hasPerm('publishArticle[]')))
    {
      // --------------------- ARTICLE STATUS
      list($success, $message) = rex_articleStatus($this->article_id, $this->clang);

      if($success)
        $this->info = $message;
      else
        $this->warning = $message;
    }
    // Hier mit !== vergleichen, da 0 auch einen gültige category_id ist (RootArtikel)
    elseif (rex_post('artadd_function', 'boolean') && $this->category_id !== '' && $KATPERM &&  !$REX['USER']->hasPerm('editContentOnly[]'))
    {
      // --------------------- ARTIKEL ADD
      $data = array();
      $data['prior']       = rex_post('Position_New_Article', 'int');
      $data['name']        = rex_post('article_name', 'string');
      $data['template_id'] = rex_post('template_id', 'rex-template-id');
      $data['category_id'] = $this->category_id;
      $data['path']        = $KATPATH;

      list($success, $message) = rex_addArticle($data);

      if($success)
        $this->info = $message;
      else
        $this->warning = $message;
    }
    elseif ($this->reqfunction == 'artdelete_function' && $this->article_id != '' && $KATPERM && !$REX['USER']->hasPerm('editContentOnly[]'))
    {
      // --------------------- ARTIKEL DELETE
      list($success, $message) = rex_deleteArticleReorganized($this->article_id);

      if($success)
        $this->info = $message;
      else
        $this->warning = $message;
    }

    if(!empty($this->info) || !empty($this->warning))
    {
      $return['status']  = !empty($this->warning) ? 'error' : 'ok';
      $return['html']   = !empty($this->warning) ? rex_warning($this->warning) : rex_info($this->info);
    }
    return $return;
  }

  private function doActions($func=null,$return=null)
  {
    if(empty($func) || empty($return))
      return false;

    $data = rex_request('data','array',false);
    if(empty($data))
    {
      $data = array(
        'article_id' => rex_request('article_id','rex-article-id', null),
        'category_id' => rex_request('category_id','rex-category-id', null),
        'clang' => rex_request('clang','int', null)
      );
    }

    if(!isset($data['article_id']))
      return $return;
    else
    {
      if(!isset($data['prior']))
        $data['prior'] = 99999;

      $data['article_id']   = _rex_cast_var($data['article_id'], 'rex-article-id', false, '');
      $data['prior']         = _rex_cast_var($data['prior'], 'int', 99999, '');

      if(isset($data['name']))
      {
        $data['name']          = _rex_cast_var($data['name'], 'string', '', '');
        $data['catname']       = $data['name'];
      }

      if(isset($data['template_id']))
        $data['template_id']   = _rex_cast_var($data['template_id'], 'rex-template-id', false, '');

      if(isset($data['category_id']))
        $data['category_id']   = _rex_cast_var($data['category_id'], 'rex-category-id', false, '');
      
    if(substr($func,0,5)!='move_' && !empty($data['article_id']) && !empty($data['clang']) && is_object($art = OOArticle::getArticleById($data['article_id'],$data['clang']))) {
      $data['path'] = $art->getValue('path');
    }
    elseif(isset($data['path']))
      $data['path'] = _rex_cast_var($data['path'], 'string', '', '');
    }

    global $REX;

    switch($func)
    {
      case 'edit_art' : // edit an article
        list($success, $message) = rex_editArticle($data['article_id'], $data['clang'], $data);
        if($success)
        {
          $this->info = $message;
          rex_generateArticle($data['article_id']);
          // rex_generateAll();

          $data['template'] = $this->getTemplateName($data['category_id'],$data['template_id']);
          $data['type'] = 'article';
          $data['name'] = stripslashes($data['name']);
          $data['template'] = stripslashes($data['template']);
          $return['article'] = $data;
          $return['autohide'] = '1';
        }
        else
          $this->warning = $message;

        break;
      case 'edit_cat' : // edit a category
        $data['category_id'] = $data['article_id'];
        $data['catprior'] = $data['prior'];
        // unset($data['prior']);
        $data['catname'] = $data['name'];

        // to store the cat metadata we have to move all the $_GET vars to $_POST
        // and reset the Request Method...
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = array_merge($_POST,$_GET);

        list($success, $message) = rex_editCategory($data['article_id'], $data['clang'], $data);
        if($success)
        {
          // category edited - update the template
          rex_editArticle($data['article_id'], $data['clang'], $data);

          $this->info = $message;
          rex_generateArticle($data['article_id']);
          // rex_generateAll();

          $data['template'] = $this->getTemplateName($data['article_id'],$data['template_id']);
          $data['type'] = 'article';
          $data['name'] = stripslashes($data['name']);
          $data['template'] = stripslashes($data['template']);
          $return['article'] = $data;
          $return['autohide'] = '1';
        }
        else
          $this->warning = $message;

        break;

      case 'add_art' : // add an article
      case 'add_cat' : // add a category
        if($REX['USER']->isAdmin() || $this->hasCatPerm($data['category_id']) && $REX['USER']->hasPerm('publishArticle[]'))
        {
          unset($data['article_id']);

          if($func=='add_cat')
          {
            $data['catprior'] = $data['prior'];
            unset($data['prior']);

            // to store the cat metadata we have to move all the $_GET vars to $_POST
            // and reset the Request Method...
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST = array_merge($_POST,$_GET);

            list($success, $message) = rex_addCategory($data['category_id'], $data);
          }
          else{
            list($success, $message) = rex_addArticle($data);
          }

          if(!empty($_REQUEST['treestructure_added_item']))
          {
            global $I18N;
            $obj = OOArticle::getArticleById($_REQUEST['treestructure_added_item'],$data['clang']);
            $message = $obj->isStartArticle() ? $I18N->msg('category_added_and_startarticle_created') : $I18N->msg('article_added');
          }

          if($success)
          {
            $this->info = $message;

            if($func=='add_cat') {
              // category added - update the template
              $data['template'] = $this->getTemplateName($data['category_id'],$data['template_id']);
              $data['template'] = stripslashes($data['template']);

              rex_editArticle($obj->getId(), $obj->getClang(), $data);
            }

            # $this->info = $message;
            rex_generateArticle($obj->getId());
            // rex_generateAll();

            $data['template'] = $this->getTemplateName($obj->getCategoryId(),$obj->getTemplateId());
            $data['prior'] = $obj->getPriority();
            $data['type'] = 'article';

            $data['article_id'] = $obj->getId();
            if($func=='add_cat')
              $data['category_id'] = $data['article_id'];

            $return['article'] = $data;

            $this->getOpenItems($data['article_id']);

            $return['structurehtml'] = $this->outputCategory();
            $return['autohide'] = '1';
          }
          else
            $this->warning = $message;
        }
        break;
      case 'move_art' : // move an article to another position
        if(is_object($source = OOArticle::getArticleById($data['article_id'])))
        {
          $data['clang'] = rex_request('clang','rex-clang-id',0);
          $data['dest_cat'] = rex_request('dest_cat','int',-1);
          $data['dest_after'] = rex_request('dest_after','int',-1);

          $messages = array();
          $valid  = false;
          $move   = false;
          $type  = $source->isStartArticle() ? 'category' : 'article';
          $reorderdata = null;
          
          if(intval($data['dest_cat'])===0)
          { 
            $valid = true;
            if($source->getCategoryId()!=0)
              $move = true;
          }
          elseif(is_object($destcat = OOCategory::getCategoryById(intval($data['dest_cat']))))
          {
            if($this->hasCatPerm($destcat->getId()))
            {
              if($destcat->getId() == $source->getCategoryId())
              {
                $valid = true;
              }
              else
              {
                $destpath =  $destcat->getValue('path').$destcat->getId().'|';
                if(!strpos(' '.$destpath,'|'.$source->getId().'|'))
                {
                  $valid  = true;
                  $move  = true;
                  $data['dest_cat'] = intval($destcat->getId());
                }
              }
            }
          }
          unset($destcat);
          if($valid && $move) {
            global $I18N;

            // move article...
            if($type=='article')
            {
              if($REX['USER']->isAdmin() || ($REX['USER']->hasPerm('moveArticle[]') && $REX['USER']->hasCategoryPerm($data['dest_cat'])))
              {
                // just move?
/*
                if($source->getCategoryId() == $data['dest_cat']) {
                  $reorderdata = array(
                    'prior' => $data['dest_after']==0 ? 1 : ((int) OOArticle::getArticleById($data['dest_after'],$data['clang'])->getValue('prior'))+1,
                    'name' => $source->getValue('name'),
                    'template_id' => $source->getValue('template_id'),
                    'category_id' => $source->getValue('category_id'),
                    'path' => $source->getValue('path')
                  );
                  $msg = rex_editArticle($source->getId(), $data['clang'], $reorderdata);
                  unset($reorderdata);
                }
                else {
                  $msg = rex_moveArticle($source->getId(), $source->getCategoryId(), $data['dest_cat']);
                }
*/
                $moved = true;
                if($source->getCategoryId() != $data['dest_cat']) {
                  if($moved = rex_moveArticle($source->getId(), $source->getCategoryId(), $data['dest_cat'])) {
                    # rex_generateArticle($source->getId());
                    $source = OOArticle::getArticleById($source->getId());
                  }
                }
                
                if($moved) {
                  if($data['dest_after']<=1 && $data['dest_cat']>0)
                    $p = 2;
                  elseif($data['dest_after']<=1 && $data['dest_cat']<=0)
                    $p = 1;
                  else
                    $p = ((int) OOArticle::getArticleById($data['dest_after'],$data['clang'])->getValue('prior'))+1;
                  
                  $reorderdata = array(
                    'prior' =>        $p,
                    'name' =>         $source->getValue('name'),
                    'template_id' =>  $source->getValue('template_id'),
                    'category_id' =>  $source->getValue('category_id'),
                    'path' =>         $source->getValue('path')
                  );
                  $msg = rex_editArticle($source->getId(), $data['clang'], $reorderdata);
                  unset($reorderdata,$p);
                }
              
                if ($msg)
                {
                  $articles = empty($data['dest_cat']) ? OOArticle::getRootArticles() : OOArticle::getArticlesOfCategory($data['dest_cat']);
                  
                  foreach($articles as $article) {
                    rex_generateArticle($article->getId());
                  }
                  unset($articles,$article);
                  $messages[] = $I18N->msg('content_articlemoved');
                  $source = OOArticle::getArticleById($source->getId());
                }
                else
                {
                  $this->warning = $I18N->msg('content_errormovearticle');
                  $valid = false;
                }
              }
              else
              {
                $this->warning = $I18N->msg('no_rights_to_this_function');
                $valid = false;
              }
            }
            else if($type=='category')
            {
              if($REX['USER']->isAdmin() || ($REX['USER']->hasPerm('moveCategory[]') && $REX['USER']->hasCategoryPerm($source->getValue('re_id')) && $REX['USER']->hasCategoryPerm($data['dest_cat'])))
              {
                if($source->getParentId() == $data['dest_cat']) {
                  $reorderdata = array(
                    'catprior' => $data['dest_after']==0 ? 1 : ((int) OOCategory::getCategoryById($data['dest_after'],$data['clang'])->getValue('catprior'))+1,
                    'catname' => $source->getValue('catname'),
                    'path' => $source->getValue('path')
                  );
                  $msg = rex_editCategory($source->getId(), $data['clang'], $reorderdata);
                  unset($reorderdata);
                }
                else {
                  $msg = rex_moveCategory($source->getId(), $data['dest_cat']);
                }
                
                if($msg)
                {
                  $articles = empty($data['dest_cat']) ? OOCategory::getRootCategories() : OOCategory::getChildrenById($data['dest_cat']);
                  
                  foreach($articles as $article) {
                    rex_generateArticle($article->getId());
                  }
                  unset($articles,$article);
                  $messages[] = $I18N->msg('category_moved');
                  $source = OOCategory::getCategoryById($source->getId());
                }
                else
                {
                  $this->warning = $I18N->msg('content_error_movecategory');
                  $valid = false;
                }
              }
              else
              {
                $this->warning = $I18N->msg('no_rights_to_this_function');
                $valid = false;
              }
            }
          }

          if($valid)
          {
            // change prior...
            $prior = 9999999999;
            if(intval($data['dest_after'])==0)
            {
              // set element to the top of the list
              $prior = $type=='article' && $data['dest_cat']>0 ? 1 : 0;
            }
            else
            {
              if($type=='article')
                $after = OOArticle::getArticleById(intval($data['dest_after']));
              else
                $after = OOCategory::getCategoryById(intval($data['dest_after']));

              if(is_object($after))
              {
                if($after->getValue('startpage')=='0' && $type=='category')
                {}
                else if($after->getValue('startpage')=='1' && $type=='article') {
                  $prior = 0;
                }
                else {
                  $qry = "SELECT `prior` FROM `".$REX['TABLE_PREFIX']."article` WHERE `id`=".$after->getId();
                  $sql = rex_sql::factory();
                  $sql->setQuery($qry);
                  if(count($prior = $sql->getArray())) {
                    $prior = reset($prior);
                    $prior = intval($prior['prior']);

                    if($prior > intval($source->getValue('prior'))) {
                      $prior--;
                    }
                  }
                  if(empty($prior)) {
                    $prior = intval($after->getPriority());
                  }
                }
              }
            }

            if($prior>-1 && (empty($after) || ($after->getId() != $source->getId())))
            {
              $obj = array();
              if($type=='article')
                $obj['prior']  = $prior+1;
              else
                $obj['catprior']= $prior+1;

              $obj['name']    = addslashes($source->getName());
              $obj['catname']    = $obj['name'];
              $obj['path']    = $source->getValue('path');
              $obj['template_id']  = $source->getTemplateId();
              $obj['category_id']  = $source->getValue('re_id');

              if($type=='article')
                list($success, $message) = rex_editArticle($source->getId(), $data['clang'], $obj);
              else
                list($success, $message) = rex_editCategory($source->getId(), $data['clang'], $obj);

              if($success)
              {
                $messages[] = $message;
                rex_generateArticle($source->getId());
                // rex_generateAll();

                if($type=='article')
                  $obj = OOArticle::getArticleById($source->getId(),$data['clang']);
                else
                  $obj = OOCategory::getCategoryById($source->getId(),$data['clang']);

                $data = array(
                  'article_id' => $obj->getId(),
                  'category_id' => $obj->getValue('re_id'),
                  'clang' => $data['clang'],
                  'template' => $this->getTemplateName($obj->getValue('re_id'),$obj->getTemplateId()),
                  'prior' => $obj->getPriority(),
                  'type' => $type
                );

                $return['article'] = $data;

                $this->getOpenItems($data['category_id']);

                $return['structurehtml'] = $this->outputCategory();
                $return['autohide'] = '1';
              }
              else
              {
                $this->warning = $message;
                $valid = false;
              }
            }
            else
              $valid = false;
          }

          if($valid)
            $this->info = join("\n",$messages);
        }
        break;

      case 'del_art' : // delete an article
      case 'del_cat' : // delete a category
        if ($this->hasCatPerm($data['category_id']) && !$REX['USER']->hasPerm('editContentOnly[]'))
        {
          if($func=='del_cat')
            list($success, $message) = rex_deleteCategoryReorganized($data['article_id']);
          else
            list($success, $message) = rex_deleteArticleReorganized($data['article_id']);

          if($success)
          {
            if(!empty($_REQUEST['treestructure_deleted_item']))
            {
              global $I18N;
              $this->info = $I18N->msg('treestructure_'.$func.'_success',$_REQUEST['treestructure_deleted_item']);
            }
            else
              $this->info = $message;

            // rex_generateAll();

            #$data['template'] = $this->getTemplateName($data['category_id'],$data['template_id']);
            #$data['type'] = 'article';
            $return['article'] = $data;

            // $this->getOpenItems($data['article_id']);
            $return['structurehtml'] = $this->outputCategory(); // $this->outputCategory($this->walkCategories());
            $return['autohide'] = '1';
          }
          else
            $this->warning = $message;
        }
        break;

      case 'status' :  // change the status of an article
        $valid = false;
        if(is_object($obj = OOArticle::getArticleById($data['article_id'])))
        {
          $type = $obj->isStartPage() ? 'category' : 'article';
          if($type=='article') {
            $testcat = $obj->getCategoryId();
          }
          else {
            $testcat = $obj->getId();
          }
          if($REX['USER']->isAdmin())
            $valid = true;
          elseif(($type=='article' && $this->hasCatPerm($obj->getCategoryId())) || ($type=='category' && $this->hasCatPerm($obj->getId())))
          {
            if($type=='article' && $REX['USER']->hasPerm('publishArticle[]'))
              $valid = true;
            elseif($type=='category' && $REX['USER']->hasPerm('publishCategory[]'))
              $valid = true;
          }
        }

        if($valid) {
          if($type == 'article')
            list($success, $message) = rex_articleStatus($obj->getId(), $data['clang']);
          else
            list($success, $message) = rex_categoryStatus($obj->getId(), $data['clang']);

          if($success)
          {
            $this->info = $message;
            rex_generateArticle($obj->getId());
            // rex_generateAll();
            $obj = OOArticle::getArticleById($obj->getId(),$data['clang']);

            $return['status'] = 'ok';
            $return['message'] = '';
            $return['html'] = '';
            $return['function'] = "treeStructure.updateStatus(".$obj->getId().",'".($obj->isOnline() ? "online" : "offline")."')";

            return $return;

            // $this->getOpenItems($data['article_id']);
            // $return['structurehtml'] = $this->outputCategory($this->walkCategories());
            // $return['autohide'] = '1';
          }
          else
            $this->warning = $message;
        }

        break;
    }

    if(!empty($this->info) || !empty($this->warning))
    {
      $return['status']  = !empty($this->warning) ? 'error' : 'ok';
      $return['message']   = !empty($this->warning) ? $this->warning : 'ok';
      if(!empty($this->warning))
        $return['html'] = rex_warning($this->warning);
      elseif(!empty($this->info))
        $return['html'] = rex_info($this->info);
    }

    return $return;
  }


  public function processFunctionRequest($func=null) {

    global $REX,$I18N;

    $return = array(
      'status'  => 'error',
      'message'  => rex_translate($I18N->msg('treestructure_error')),
      'html'    => '',
      'func'    => $func
    );
    if(!in_array($func,$this->availableFunctions))
    {
      $return['message'] = rex_translate($I18N->msg('treestructure_error_no_such_function'));
      return $return;
    }
    if(rex_request('action','boolean',false))
    {
      // do not show a form, execute the functions
      $return = $this->doActions($func,$return);
      return $return;
    }
    elseif(!in_array($func,$this->functionsThatUseTheForm))
      return $return;

    // a form is requested - return it...
    $return['status'] = 'ok';
    $return['message'] = '';

    $legend = substr($func,4)=='add_' ? $I18N->msg('article_add') : $I18N->msg('article_edit');

    $article = array(
      'id'       => rex_request('article_id','int',0),
      'clang'     => rex_request('clang','int',$REX['CUR_CLANG']),
      'category_id'   => rex_request('category_id','int',$this->category_id),
      'name'       => '',
      'prior'     => 99999,
      'template_id'   => 0,
      'path'      => '|'
    );

    if(is_object($art = OOArticle::getArticleById($article['id'],$article['clang']))) {
    $article['template_id']  = $art->getValue('template_id');
    if($art->isStartPage()) {
      $article['path']     = $art->getValue('path'); //.$article['id'].'|';
      $article['category_id'] = $art->getId();
      $article['prior']     = $art->getValue('catprior');
      $article['name']     = $art->getValue('catname');
    }
    else {
      $article['name']     = $art->getName();
      $article['prior']     = $art->getPriority();
    }
    }

    $children = null;
    if(substr($func,0,4)=='add_')
    {
      $article['name'] = '';
    if(!empty($article['id']))
    $article['path'].=$article['id'].'|';

      $article['id'] = 0;

    if(empty($article['template_id']))
    $article['template_id'] = $REX['DEFAULT_TEMPLATE_ID'];

      if(strpos($func,'_cat'))
        $children = OOCategory::getChildrenById($article['category_id']);
      elseif(strpos($func,'_art'))
        $children = OOArticle::getArticlesOfCategory($article['category_id']);
    }


    $return['html'] = '<form action="index.php" method="post" id="rex-treestructure-form">'.
              '<fieldset class="cf">'.
                '<legend><span>'.$legend .'</span></legend>'.

                '<input type="hidden" name="page" value="structure" />'.
                '<input type="hidden" name="func" value="'. $func .'" />'.
                '<input type="hidden" name="action" value="1" />'.
                '<input type="hidden" name="open" value="'.join(',',$this->openItems).'" />'.
                '<input type="hidden" name="data[category_id]" value="'. $article['category_id'] .'" />'.
                '<input type="hidden" name="data[article_id]" value="'. strval($article['id']) .'" />'.
                '<input type="hidden" name="data[clang]" value="'. strval($article['clang']) .'" />'.
                '<input type="hidden" name="data[path]" value="'. $article['path'] .'" />'.

    $return['html'].=     '<div class="rex-form-field-wrapper rex-article-name a-col60"><div class="cnt">'.
                  '<label for="rex-treestructure-article_name">'.rex_translate($I18N->msg('name_description')).'</label>'.
                  '<div class="form-field"><input id="rex-treestructure-article_name" name="data[name]" type="text" class="text" value="'.rex_translate($article['name']).'"/></div>'.
                '</div></div>'.
                '<div class="rex-form-field-wrapper rex-article-template a-col'.(!empty($children) ? '20' : '40').'"><div class="cnt">'.
                  '<label for="rex-treestructure-article_template">'.rex_translate($I18N->msg('header_template')).'</label>';

    $template_selector = $this->initCategoryTemplates($article['category_id']);
    $template_selector->setSelected($article['template_id']);
    $template_selector->setAttribute('name','data[template_id]');
    $template_selector->setAttribute('id','rex-treestructure-article_template');
    $return['html'].= '<div class="form-field">'.$template_selector->get().'</div>';

    $return['html'].=     '</div></div>';


    if(!empty($children))
    {
      $slct = new rex_select();
      $slct->setAttribute('size',1);
      $slct->setAttribute('name','data[prior]');
      $slct->setAttribute('id','rex-treestructure-article_prior');
      foreach($children as $child)
      {
        if(strpos($func,'_art') && $child->isStartArticle())
          continue;
        $slct->addOption(rex_translate($I18N->msg('treestructure_set_item_before',$child->getName()), null, false), $child->getPriority());
      }

      $slct->addOption(rex_translate($I18N->msg('treestructure_set_item_to_bottom'), null, false), 999999);

      $slct->setSelected(999999);

      $return['html'].=   '<div class="rex-form-field-wrapper rex-article-prior a-col20"><div class="cnt">'.
                  '<label for="rex-treestructure-article_prior">'.rex_translate($I18N->msg('treestructure_article_position')).'</label>'.
                  '<div class="form-field">'.$slct->get().'</div>'.
                '</div></div>';
    }
    else
      $return['html'].=   '<input type="hidden" name="data[prior]" value="'. strval($article['prior']) .'" />';


    if($func=='add_cat') {
      $form = rex_register_extension_point('CAT_FORM_ADD', '', array (
        'id' => $article['category_id'],
        'clang' => $article['clang'],
        'data_colspan' => 1
      ));
    }
    elseif($func=='edit_cat' && !empty($cat)) {

      $KAT = rex_sql::factory();
      $KAT->setQuery('SELECT * FROM '.$REX['TABLE_PREFIX'].'article WHERE `id`='.$article['id'].' AND `startpage`=1');

      $_SERVER['REQUEST_METHOD'] = 'GET';

      $form = rex_register_extension_point('CAT_FORM_EDIT', '', array (
        'id' => $article['id'],
        'clang' => $article['clang'],
        'category' => $KAT,
        'catname' => $article['name'],
        'catprior' => $article['prior'],
        'data_colspan' => 1
      ));
      unset($KAT);
    }

    if(!empty($form))
    {
      $items = explode('<div class="rex-form-row">',$form);
      if(count($items)>1)
      {
        $tmp = array();
        for($i=1; $i<count($items); $i++)
        {
          $item = $items[$i];
          $tmp[] = '<div class="rex-form-field-wrapper rex-metafield a-col100"><div class="cnt">'.$item.'</div>';
        }

        $form = '<div id="rex-cat-form-add"><p class="collapse-headline">'.$I18N->msg('treestructure_metafields').'</p><div class="collapse-content">'.join('',$tmp).'</div></div>';
      }
      unset($items,$i,$tmp);

      $return['html'].=   $form;
    }

    $return['html'].=     '<div class="rex-submit-wrapper a-col100"><div class="cnt">'.
                  '<button class="confirm" type="submit" name="rex-treestructure-submit" id="rex-treestructure-submit">'.rex_translate($I18N->msg('form_save')).'</button>'.
                '</div></div>'.
              '</fieldset>'.
              '</form>';

    $return['status'] = 'ok';

    return $return;
  }
}