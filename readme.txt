REDAXO TreeStructure PlugIn for Redaxo 4.3 / 4.4

post@thomasgoellner.de / Thomas Göllner
www.thomasgoellner.de

Version: 1.0.3 (2012/12/19)


If you experience performance problems with large sitemap you can either disable
the whole add on or you can change the variable

$REX['ADDON'][$mypage]['maxitems']

in config.inc.php - this defines the maximum number of items that this add on
can handle. If the sitemap contains more items, the default structure page is
rendered.




In the file styles.css you can alter the styles to match your backend theme.
Colors that are used:

hilite (light):		#e7f5d3
hilite (normal):	#77b82a
error:				#aa0000
bgcolor:			#eee




You can also use your own icons for elements with specific template ids.

For example: You want any article with the template ID 3 to show the icon
'my_icon.gif' - copy the icon file into the folder

./files/addons/treestructure/

and add these lines to ./files/addons/treestructure/styles.css :

#rex-treestructure li.rex-article.rex-template-id-3 .sensitive-area>.article-icon,
#rex-treestructure li.rex-article.rex-template-id-3 .sensitive-area:hover>.article-icon { background: url(my_icon.gif) no-repeat center !important; }


To attach the icon to any category with the template 3 use:

#rex-treestructure li.rex-category.rex-template-id-3 .sensitive-area>.article-icon,
#rex-treestructure li.rex-category.rex-template-id-3 .sensitive-area:hover>.article-icon { background: url(my_icon.gif) no-repeat center !important; }


CHANGELOG
-------------------------------------------------
1.0.3 (2012/12/19):

_ Path is now saved correctly when editing articles and categories

_ minor changes


1.0.2 (2012/09/13):

_ Template changes to categories did not save - this is fixed

_ When changing a category name the new names was displayed for all child categories

_ BE Search addon is now disabled in treestructure view - can be enabled by setting the variable
$REX['ADDON']['treestructure']['allow_besearch_in_sructure'] in config.inc.php

_ merged with git commit by jdlx

_ fixed no-view-problem for users with perm editContentOnly[]


1.0.1 (2012/08/16):

_ The AddOn page is hidden from the menu

_ Instead of overwriting the POST/REQUEST page variable the be_page->path is
used to hide the default structure page

_ Category meta fields are also included in the edit form and wont be hidden
in the default structure view

_ some changes to css / js files
