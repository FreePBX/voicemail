<div class="col-md-2">
	<div class="folder-list">
	<?php foreach($folders as $folder) {?>
		<div class="folder <?php echo ($folder['folder'] == $activeList) ? 'active' : ''?>" data-name="<?php echo $folder['name']?>" data-folder="<?php echo $folder['folder']?>"><a vm-pjax href="?display=dashboard&amp;mod=voicemail&amp;sub=<?php echo $ext?>&amp;folder=<?php echo $folder['folder']?>&amp;view=folder" class="folder-inner"><?php echo $folder['name']?> <span class="badge"><?php echo isset($folder['count']) ? $folder['count'] : 0?></span></a></div>
	<?php }?>
	</div>
	<div class="separator-list"></div>
	<div class="settings-list">
		<?php if($showSettings) {?>
			<div class="settings <?php echo ('settings' == $activeList) ? 'active' : ''?>"><a vm-pjax href="?display=dashboard&amp;mod=voicemail&amp;sub=<?php echo $ext?>&amp;view=settings" class="settings-inner"><?php echo _('Settings')?> <i class="fa fa-cog"></i></a></div>
		<?php } ?>
		<?php if($showGreetings) {?>
			<div class="settings <?php echo ('greetings' == $activeList) ? 'active' : ''?>"><a vm-pjax href="?display=dashboard&amp;mod=voicemail&amp;sub=<?php echo $ext?>&amp;view=greetings" class="settings-inner"><?php echo _('Greetings')?> <i class="fa fa-cog"></i></a></div>
		<?php } ?>
	</div>
</div>
