<div id="nc-pages-setting-dialog" style="visibility:hidden;">
	<div class="nc-pages-setting-icon table-cell nc-panel-color">
		<?php
		$postfix = ($this->action == 'index') ? '-on' : '';
		echo $this->Html->link('', array('plugin' => 'page', 'controller' => 'page', 'action' => 'index'),
		array('title' => __d('page', 'Pages menu'), 'class' => 'nc-pages-menu-icon' . $postfix . ' nc-tooltip',
			'data-tooltip-desc' => __d('page', 'Displays the menu for displaying and editing the page list, the community.'), 'data-ajax' => '#nc-pages-setting-dialog'));
		$postfix = ($this->action == 'favorite') ? '-on' : '';
		echo $this->Html->link('', array('plugin' => 'page', 'controller' => 'page', 'action' => 'favorite'),
		array('title' => __d('page', 'Pages favorite'), 'class' => 'nc-pages-favorite-icon' . $postfix . ' nc-tooltip',
			'data-tooltip-desc' => __d('page', 'I display the list of pages that oneself looks at well.'), 'data-ajax' => '#nc-pages-setting-dialog'));
		$postfix = ($this->action == 'meta') ? '-on' : '';
		echo $this->Html->link('', array('plugin' => 'page', 'controller' => 'page', 'action' => 'meta'),
		array('title' => __d('page', 'Page info'), 'class' => 'nc-page-metas-icon' . $postfix . ' nc-tooltip',
			'data-tooltip-desc' => __d('page', 'I can edit the page title, the description of the page, the keyword.'), 'data-ajax' => '#nc-pages-setting-dialog'));
		$postfix = ($this->action == 'theme') ? '-on' : '';
		echo $this->Html->link('', array('plugin' => 'page', 'controller' => 'page', 'action' => 'theme'),
		array('title' => __d('page', 'Page theme'), 'class' => 'nc-page-themes-icon' . $postfix . ' nc-tooltip',
			'data-tooltip-desc' => __d('page', 'I can change the design of the page.'), 'data-ajax' => '#nc-pages-setting-dialog'));
		$postfix = ($this->action == 'style') ? '-on' : '';
		echo $this->Html->link('', array('plugin' => 'page', 'controller' => 'page', 'action' => 'style'),
		array('title' => __d('page', 'Page style'), 'class' => 'nc-page-styles-icon' . $postfix . ' nc-tooltip',
			'data-tooltip-desc' => __d('page', 'I can change the page style color, and font.'), 'data-ajax' => '#nc-pages-setting-dialog'));
		$postfix = ($this->action == 'layout') ? '-on' : '';
		echo $this->Html->link('', array('plugin' => 'page', 'controller' => 'page', 'action' => 'layout'),
		array('title' => __d('page', 'Page layout'), 'class' => 'nc-page-layouts-icon' . $postfix . ' nc-tooltip',
			'data-tooltip-desc' => __d('page', 'I can switch the display and non-display of up, down, left, or right column.'), 'data-ajax' => '#nc-pages-setting-dialog'));
		?>
	</div>
	<div class="table-cell nc-panel-color">
		<div id="nc-pages-setting-main">
			<?php echo($this->element($this->action, isset($element_params) ? $element_params : array())); ?>
		</div>
	</div>
	<div id="nc-pages-setting-arrow-outer" class="table-cell nc-panel-color" data-page-setting-url="<?php echo $this->Html->url(array('plugin' => 'page', 'controller' => 'display'));?>">
		<div class="nc-pages-setting-arrow nc-arrow-left"></div>
	</div>
	<?php
		echo $this->Html->css('Page.index');
		echo $this->Html->script('Page.index');
		$pos = $this->Session->read(NC_SYSTEM_KEY.'.page_menu.pos');
		$pos = isset($pos) ? intval($pos) : _ON;
	?>
	<script>
	$(function(){
		$('#nc-pages-setting-dialog').Page(<?php echo($pos);?>);
	});
	</script>
</div>