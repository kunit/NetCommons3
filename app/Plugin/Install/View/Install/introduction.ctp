<?php
$this->assign('title', __d('install', 'Introduction'));
?>
<div class="install">
	<form method="post" action="<?php echo $this->Html->url(array('plugin' => 'install','controller' => 'install','action' => 'check')); ?>">
		<?php echo($this->element('hidden')); ?>
		<h1><?php echo $this->fetch('title'); ?></h1>
		<div class="top-description">
			<?php echo(__d('install', 'introduction description')); ?>
		</div>
		<div class="btn-bottom align-right">
			<input type="button" value="<?php echo(h(__('<<Back'))); ?>" name="next" class="btn" onclick="history.back();" />
			<input type="submit" value="<?php echo(h(__('Next>>'))); ?>" name="next" class="btn" />
		</div>
	</form>
</div>