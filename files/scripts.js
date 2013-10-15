/**
 * REDAXO TreeStructure PlugIn
 *
 * @author post[at]thomasgoellner[dot]de Thomas Göllner
 * @author <a href="http://www.thomasgoellner.de">www.thomasgoellner.de</a>
 *
 *
 * @package redaxo4
 * @version 1.1
 */

(function($){
    $(window).ready(function(){
		treeStructure.init();
	});

	var treeStructure = {
		isLoading : false,

		init : function(){
			if($('#rex-treestructure').length)
			{
				// detect browser version and add css class to the html tag
				if($.browser.msie)
				{
					// check if browser is IE (compatibility mode)
					ver = typeof(document.documentMode) != 'undefined' ? document.documentMode : Math.floor($.browser.version);
					$('html').addClass('ie'+ver);
				}
				else if($.browser.mozilla )
				{
					// check if browser is Firefox
					ver = !isNaN(Math.floor($.browser.version)) ? Math.floor($.browser.version) : Math.floor($.browser.version.substr(0,3));
					if(ver<=2) ver+=2; // FF<5 calls himself 2 versions lower
					$('html').addClass('ff'+ver);
				}

 				$('#rex-treestructure .rex-collapse').each(function(i,el){
					$(el).bind('click touchend', function(e){
						$(this).parents('li').first().toggleClass('collapsed');
						if($(this).parents('li').first().hasClass('collapsed'))
							$(this).parents('li').first().find('li.rex-category').each(function(i,li){ $(li).addClass('collapsed'); });
						treeStructure.updateCookie();
					});
				});

				var activeElements = ['#rex-treestructure .rex-edit',
									  '#rex-treestructure .rex-i-article-add',
									  '#rex-treestructure .rex-i-category-add',
									  '#rex-treestructure .rex-delete',
									  '#rex-treestructure .rex-status'
									  ];

				$(activeElements.join(',')).each(function(i,el){
					$(el).bind('click touchend', function(e){
						if($(this).hasClass('rex-loading'))
							return false;

						if($(this).attr('class').indexOf('delete')<=-1 || ($(this).attr('class').indexOf('delete')>-1 && confirm($(this).attr('title'))))
							treeStructure.generateForm(this);
						return false;
					});
				});

				this.dragndrop.init();
			}

			treeStructure.updateCookie();
		},

		dragndrop : {
			currentDropper : null, // stores the current item that is dragged

			init : function() {
				// no moving allowed? cancel initialization
				if($('#rex-treestructure.move-article').length<=0 && $('#rex-treestructure.move-category').length<=0)
					return false;

				// add LIs in between the elements so we can drop items to change the prior
				$('#rex-treestructure ul').each(function(i,ul){
					$(ul).children('li').each(function(j,li){
						if($(li).find('.sensitive-area').css('padding-left'))
						{
							ml = $(li).find('.sensitive-area').css('padding-left');
						}
						if(j==0)
							$('<li class="ui-dropper-inbetween" style="margin-left:'+ml+'"></li>').insertBefore($(li));

						$('<li class="ui-dropper-inbetween" style="margin-left:'+ml+'"></li>').insertAfter($(li));
					});
				});

				// atach drag&drop behaviour...

				// first attach the dragging behaviour...
				var selector = [];
				if($('#rex-treestructure.move-article').length)
					selector.push("#rex-treestructure li.rex-article>.sensitive-area");

				if($('#rex-treestructure.move-category').length)
					selector.push("#rex-treestructure li.rex-category>.sensitive-area");

				$(selector.join(',')).draggable({
					axis : 			'y',
					opacity: 		0.6,
					containment: 	'#rex-treestructure',
					revert: 		'invalid',
					handle:			'.article-icon',
					helper:			'clone',
					start: function(event, ui) {
						treeStructure.currentDropper = this;

						// collapse any category tree so the user cannot drag the category into one of its child categories
						if($(this).parents('li:first').hasClass('rex-category'))
						{
							this.iscollapsed = $(this).parents('li.rex-category:first').hasClass('collapsed');
							$(this).parents('li.rex-category:first').addClass('collapsed');
						}
					},
					stop : function(event, ui) {
						// expand any category that has been collapsed by the dragging script...
						treeStructure.currentDropper = null;
						if($(this).parents('li:first').hasClass('rex-category') && !this.iscollapsed)
							$(this).parents('li.rex-category:first').removeClass('collapsed');
					}
				});

				// attach dropping behaviour for the categories
				$( "#rex-treestructure li.rex-category>.sensitive-area" ).droppable({
					// hoverClass: 'ui-state-active',
					over: function( event, ui ) {
						type = 'active';

						// do not allow dropping of a category into one of its child categories
						if(ui.draggable.parents('li').html().indexOf('id="'+$(this).parents('li').attr('id')+'"')>-1)
							type = 'invalid';

						// attach css classes...
						$(this).addClass('ui-state-'+type);

						this.isvalid = type==='active';
					},
					out: function( event, ui ) {
						// remove css classes...
						$(this).removeClass('ui-state-invalid');
						$(this).removeClass('ui-state-active');
					},
					drop: function( event, ui ) {
						// remove any state class
						$(this).removeClass('ui-state-invalid');
						$(this).removeClass('ui-state-active');

						ui.helper.removeClass('ui-state-invalid');
						ui.helper.removeClass('ui-state-active');

						// cancel if the item cannot be dropped here
						if(!this.isvalid)
							return false;
						else
						{
							// otherwise prevent expanding of a collapsed category
							if(treeStructure.currentDropper!==null)
							{
								treeStructure.currentDropper.iscollapsed = true;
								treeStructure.currentDropper = null;
							}

							// and do the ajax magic...
							treeStructure.dragndrop.updateStructure(this,event,ui);
						}
					}
				});
				$( "#rex-treestructure li.ui-dropper-inbetween" ).droppable({
					over: function( event, ui ) {
						var type = 'active';

						// do not allow to drag articles between categories
						if(ui.draggable.parents('li:first').hasClass('rex-article') && $(this).parents('ul:first').hasClass('rex-subcategories'))
							type = 'invalid';

						// do not allow to drag categories between articles
						if(ui.draggable.parents('li:first').hasClass('rex-category') && $(this).parents('ul:first').hasClass('rex-subarticles'))
							type = 'invalid';

						// do not allow to drag a category into one of its child categories
						if(ui.draggable.parents('li:first').hasClass('rex-category') && ui.draggable.parents('li').html().indexOf('id="'+$(this).parents('li.rex-category').attr('id')+'"')>-1)
							type = 'invalid';

						// do not allow to drag a category into itself
						if(ui.draggable.parents('li:first').hasClass('rex-category') && ui.draggable.parents('li.rex-category').attr('id') == $(this).parents('li.rex-category').attr('id'))
							type = 'invalid';

						// do not allow to drag an article that is the only child of its parent category to another position inside its parent category
						if(ui.draggable.parents('li:first').hasClass('rex-article') &&
						   $(this).parents('li.rex-category:first').attr('id') == ui.draggable.parents('li.rex-category:first').attr('id') &&
						   ui.draggable.parents('ul:first').children('li.rex-article').length<=1)
							type = 'invalid';

						// do not allow to drag a category that is the only child of its parent category to another position inside its parent category
						if(ui.draggable.parents('li:first').hasClass('rex-category') &&
						   $(this).parents('ul:first').parents('li.rex-category:first').attr('id') == ui.draggable.parents('ul:first').parents('li.rex-category:first').attr('id') &&
						   ui.draggable.parents('ul:first').children('li.rex-category').length<=1)
							type = 'invalid';

						// add the appropriate state class
						$(this).addClass('ui-state-'+type);
						ui.helper.addClass('ui-state-'+type);

						// save if this item can be dropped
						this.isvalid = type==='active';
					},
					out: function( event, ui ) {
						// remove any state class
						$(this).removeClass('ui-state-invalid');
						$(this).removeClass('ui-state-active');

						ui.helper.removeClass('ui-state-invalid');
						ui.helper.removeClass('ui-state-active');
					},
					drop: function( event, ui ) {
						// remove any state class
						$(this).removeClass('ui-state-invalid');
						$(this).removeClass('ui-state-active');

						ui.helper.removeClass('ui-state-invalid');
						ui.helper.removeClass('ui-state-active');

						// cancel if the item cannot be dropped here
						if(!this.isvalid)
							return false;
						else
						{
							// otherwise prevent expanding of a collapsed category
							if(treeStructure.currentDropper!==null)
							{
								treeStructure.currentDropper.iscollapsed = true;
								treeStructure.currentDropper = null;
							}

							// and do the ajax magic...
							treeStructure.dragndrop.updateStructure(this,event,ui);
						}
					}
				});
			},

			updateStructure : function(obj,event,ui) {
				if(!treeStructure.isLoading);

				// the data that will be submitted...
				data = {
					article_id : 0,
					clang : 0,
					dest_cat : -1,
					dest_after : -1
				}

				if((href = ui.draggable.find('a[href*="article_id"]')).length)
				{
					// get the article parameters from the first recognized href...
					href = $(href).attr('href');
					data.article_id 	= treeStructure.getUrlVars('article_id',href);
					data.clang 			= treeStructure.getUrlVars('clang',href);
				}

				// get the destination category
				if($(obj).parents('.rex-category').length)
					data.dest_cat = parseInt($(obj).parents('.rex-category').attr('id').substr(12));

				if($(obj).hasClass('ui-dropper-inbetween'))
				{
					// if the item has been dropped onto a li.dropper-inbetween, the prior has to be changed
					// so first get the id of the parent category (to move the item there) and then get the
					// id of the article/category that sits right next to this li.dropper-inbetween
					if($(obj).parents('.rex-category').length)
						data.dest_cat = parseInt($(obj).parents('.rex-category').attr('id').substr(12));
					if($(obj).prev('li').attr('id'))
						data.dest_after = parseInt($(obj).prev('li').attr('id').substr(12));
					else
						data.dest_after = 0;
				}

				if(data.article_id > 0 && data.dest_cat > -1)
				{
					// show the loader...
					treeStructure.startLoading();

					// do the ajax magic...
					url = 'index.php';
					data.page = 'structure';
					data.json = '1';
					data.func = 'move_art';
					data.action = '1';

					$.post(
						'index.php',
						data,
						function(data) {
							if(data.status!='ok')
							{
								alert(data.message);
							}
							else
							{
								if(typeof(data.article)!='undefined')
									treeStructure.updateTable(data);
							}
							treeStructure.stopLoading();
						},
						'json'
					);
				}
			}
		},

		updateCookie : function() {
			var open = [];
			$('li.rex-category').each(function(i,li){
				if(!$(li).hasClass('collapsed'))
				{
					if((id = parseInt($(li).attr('id').substr(12)))>0)
						open.push(id);
				}
			});
			open = open.join(',');
			document.cookie = "treestructure_open_items="+escape(open);
		},

		updateStatus : function(article_id,status) {
			if($('li#rex-article-'+article_id).length)
			{
				if($('li#rex-article-'+article_id+':first').children('.sensitive-area:first').find('.rex-status').length)
				{
					el = $('li#rex-article-'+article_id+':first').children('.sensitive-area:first').find('.rex-status:first');
					$(el).removeClass('rex-online').removeClass('rex-offline').removeClass('rex-loading').addClass('rex-'+status);
				}
			}
		},

		startLoading : function() {
			this.isLoading = true;
			$('<div id="loading"></div>')
			.css({opacity:0})
			.appendTo($('body'))
			.animate({opacity:1},300);
		},

		stopLoading : function() {
			this.isLoading = false;
			$('#loading').stop().animate({opacity:0},200,function(){ $(this).remove(); });
		},

		generateForm : function(link,action) {
			if($(link).attr('href') && (article = $(link).parents('li').first()))
			{
				if(this.isLoading)
					return false;

				if(!$(link).hasClass('rex-status'))
					this.startLoading();
				else
					$(link).addClass('rex-loading');

				obj = {
					article_id : 0,
					clang : 0,
					open : []
				};

				for(var i=0; i<(classes = article.attr('class').split(' ')).length; i++)
				{
					if(classes[i].substr(0,15)=='rex-article-id-')
						obj.article_id = parseInt(classes[i].substr(15));
					else if(classes[i].substr(0,13)=='rex-clang-id-')
						obj.clang = parseInt(classes[i].substr(13));
				}

				lis = $('#rex-treestructure li.rex-category:not(.collapsed)');
				for(i=0; i<lis.length; i++)
				{
					if($(lis[i]).attr('id').substr(0,12)=='rex-article-')
					{
						id = parseInt($(lis[i]).attr('id').substr(12));
						if(!isNaN(id) && id>0)
							obj.open.push(id);
					}
				}

				obj.open = obj.open.join(',');
				$.post(
					$(link).attr('href')+'&json=1',
					obj,
					function(data) {
						if(data.status!='ok')
						{
							alert(data.message);
						}
						else
						{
							if(typeof(data['function'])=='string')
								eval(data['function']);
							else
							{
								if(typeof(data.article)!='undefined')
									treeStructure.updateTable(data);

								if(typeof(data.html)!='undefined')
								{
									treeStructure.dialogues.show(data.html);
									if(typeof(data.autohide)!='undefined')
										window.setTimeout(function() { treeStructure.dialogues.hide(); }, 1000);
								}

								treeStructure.showHtml(data.html,data.func);
							}
						}

						treeStructure.stopLoading();
					},
					'json'
				);
			}

			return false;
		},

		showHtml : function(html,func) {
			if(typeof(func)=='string')
			{
				this.dialogues.show(html);
			}
		},

		updateTable : function(data) {
			if(data.func == 'edit_art' || data.func == 'edit_cat')
			{
				$('#rex-article-'+data.article.article_id).find('.article-name:first').text(data.article.name);
				$('#rex-article-'+data.article.article_id).find('.article-template:first').text(data.article.template);
			}
			else if(typeof(data.structurehtml)=='string')
			{
				$('#rex-treestructure').html(data.structurehtml);
				if(data.func.indexOf('add')>-1)
					$('#rex-article-'+data.article.article_id).addClass('new-added-item');

				to = $('#rex-article-'+data.article.article_id).length ? $('#rex-article-'+data.article.article_id).offset().top-100 : 0;

				$('html, body').animate({scrollTop: to}, 'fast');
				this.init();
			}
		},

		dialogues : {
			show : function(html,onCancel,onConfirm) {
				if(typeof(html)==undefined)
					return false;

				onCancel  = typeof(onCancel)=='function' ? onCancel : void(0);
				onConfirm = typeof(onConfirm)=='function' ? onConfirm : void(0);
				// show Overlay
				if($('#dialogue-overlay').length<=0)
					$('<div id="dialogue-overlay" />').css({opacity:0}).prependTo($('body'));;

				$('#dialogue-overlay').animate({opacity:1},200);

				if($('#dialogue-div').length<=0)
					$('<div id="dialogue-div" />').css({display:'block',opacity:0}).prependTo($('#dialogue-overlay'));

				// clear the dialogue...
				if($('#dialogue-div').find('#dialogue-content').length)
					$('#dialogue-div').find('#dialogue-content').empty();
				else
					$('<div id="dialogue-content" />').prependTo($('#dialogue-div'));

				$('#dialogue-div').find('#dialogue-content').html(html);

				// focus the first item
				if($('#dialogue-div').find('#dialogue-content input[type=text]').length)
					$('#dialogue-div').find('#dialogue-content input[type=text]:first').focus();

				// rearrange the #rex-cat-form-add form fields
				if((fields = $('#rex-cat-form-add input[type=text], #rex-cat-form-add select, #rex-cat-form-add textarea')).length)
				{
					$(fields).each(function(i,el){
						if($(el).parents('p.rex-form-select-date').length)
						{
							if($(el).parents('p.rex-form-select-date:first').find('.form-field').length<=0)
							{
								var box = $(el).parents('p.rex-form-select-date:first');
								var tmp = $('<div class="form-field" />').appendTo($(box));

								$(box).find('select,input,span').each(function(j,slct){
									$(slct).appendTo($(tmp));
								});

								if($(box).find('input[type=checkbox]').length)
								{
									$(box).find('input[type=checkbox]:first').bind('click touchend', function(e) {
										if($(this).attr('checked'))
										{
											$(this).parents('.form-field').find('select').each(function(j,slct){
												$(slct).attr('disabled',null);
											});
										}
										else
										{
											$(this).parents('.form-field').find('select').each(function(j,slct){
												$(slct).attr('disabled','disabled');
											});
										}
									});
									if(!$(box).find('input[type=checkbox]:first').attr('checked'))
									{
										$(this).parents('.form-field').find('select').each(function(j,slct){
											$(slct).attr('disabled','disabled');
										});
									}
								}
							}
						}
						else
						{
							if($(el).parents('.rex-form-widget:first').length<=0)
								$(el).wrap('<div class="form-field" />');
						}
					});
				}
				if($('#dialogue-div .collapse-headline').length)
				{
					$('#dialogue-div .collapse-headline').each(function(i,el){
						box = $(el).parents('div:first').find('.collapse-content:first');
						el.boxheight = $(box).height();
						$(box).addClass('collapsed');

						$(el).bind('click touchend',function(e){
							box = $(this).parents('div:first').find('.collapse-content:first');
							$(box).toggleClass('collapsed');
							if($(box).hasClass('collapsed'))
							{
								to = {
									height 			: (parseInt($('#dialogue-div').css('height')) - this.boxheight) + 'px',
									'margin-top' 	: (parseInt($('#dialogue-div').css('height'))/-2 + this.boxheight/2) + 'px'
								};
								$('#dialogue-div').stop().css(to);
							}
							else
							{
								to = {
									height 			: (parseInt($('#dialogue-div').css('height')) + this.boxheight) + 'px',
									'margin-top' 	: (parseInt($('#dialogue-div').css('height'))/-2 - this.boxheight/2) + 'px'
								};
								$('#dialogue-div').stop().animate(to,500);
							}
						});
					})
				}


				$('#dialogue-div').css({height:'auto'});
				$('#dialogue-div').css({
					height:$('#dialogue-div').height(),
					'margin-top' : Math.round($('#dialogue-div').height()/-2)
				});

				$('#dialogue-div').animate({opacity:1},200);

				// does the html contain a form? then prevent the form from being submitted via HTTP
				// and attach an AJAX submission function:

				if((form = $('#dialogue-div').find('form')).length>0)
				{
					form.submit(function(event) {

						// disable keys
						try { $(window).unbind('keyup', this.addKeys); } catch(e) { };

						/* stop form from being submitted normally */
						if(event.preventDefault) event.preventDefault();
						else event.returnValue = false;

						// show loader div...
						$('<div id="dialogue-loading" />').css({display:'block',opacity:0}).prependTo($('#dialogue-div')).animate({opacity:1},200);

						/* get some values from elements on the page: */
						var $form = $(this),
							formdata = $(this).serialize()+'&json=1',
							url = $form.attr('action');

						$form.find('[type=submit]').each(function(i,el){
							$(el).attr('disabled','disabled');
							$(el).css({opacity:.5});
						})

						/* Send the data using post and put the results in a div */
						$.post( url, formdata, function( data ) {
							$('#dialogue-loading').animate({opacity:0},200,function(){ $(this).remove(); });

							// insert the returned data to the dialogue
							treeStructure.updateTable(data);

							if(data.html!='') {
								treeStructure.dialogues.show(data.html);

								if(typeof(data.autohide)!='undefined')
									window.setTimeout(function() { treeStructure.dialogues.hide(); }, 1000);
							}
							else if(typeof(data.autohide)!='undefined')
								treeStructure.dialogues.hide();

							// hide the loader div
						},
						'json');
					});
				}

				// attach cancel-Button (with onCancel-Function)
				if((cancel_btn = $('#dialogue-div').find('.cancel')).length<=0)
				{
					cancel_btn = $('<a href="javascript:void(0)" class="btn cancel">Cancel</a>').appendTo($('#dialogue-div'));
				}

				$(cancel_btn).bind('click touchend',{onCancel: onCancel}, function(e){
					try { $(window).unbind('keyup', this.addKeys); } catch(e) { };
					if(e.preventDefault) e.preventDefault();
					else e.returnValue = false;
					treeStructure.dialogues.hide(onCancel);
				});

				$(window).bind('keyup',this.addKeys);

				// attach confirm-Button (with onConfirm-Function)
				if(typeof(onConfirm)=='function')
				{
					if((confirm_btn = $('#dialogue-div').find('.confirm')).length<=0)
						confirm_btn = $('<a href="javascript:void(0)" class="btn confirm">Confirm</a>').appendTo($('#dialogue-div'));

					$(confirm_btn).bind('click touchend',{onConfirm: onConfirm}, function(e){
						if(e.preventDefault) e.preventDefault();
						else e.returnValue = false;
						treeStructure.dialogues.hide(onConfirm);
					});
				}
			},

			hide : function(execfunc)
			{
				if(typeof(execfunc)!='function')
					execfunc = function() { return true; };

				$('#dialogue-div').animate({opacity:0},200,function(){ $('#dialogue-div').remove(); });

				$('#dialogue-overlay').animate({opacity:0},500,execfunc);
				window.setTimeout(function() { $('#dialogue-overlay').remove(); }, 600);
			},

			addKeys : function(e) {
				if(e.keyCode==27 && $('#dialogue-div').find('.cancel').length)
					$('#dialogue-div').find('.cancel').trigger('click');
			}
		},


		doAction : function(data) {
			// here goes the AJAX magic
		},

		getUrlVars : function(name,uristring)
		{
			var vars = [], hash;
			uristring = typeof(uristring)!='string' ? window.location.href : uristring;
			var hashes = uristring.slice(uristring.indexOf('?') + 1).split('&');

			for(var i = 0; i < hashes.length; i++)
			{
				hash = hashes[i].split('=');
				vars.push(hash[0]);
				vars[hash[0]] = hash[1];
			}

			if(typeof(name)=='string')
			{
				if(typeof(vars[name])!='undefined')
					return vars[name];
				else
					return null;
			}
			return vars;
		}
	};
})(jQuery);
