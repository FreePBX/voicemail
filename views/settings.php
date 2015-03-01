<?php if (!empty($extension)) { ?>
<h3><?php echo _("Account View Links:") ?></h3>
<ul class="nav nav-tabs">
	<li role="presentation" <?php echo $action == 'bsettings' ? ' class="active"' : ''?>><a href="config.php?display=voicemail&amp;action=bsettings&amp;ext=<?php echo $extension ?>"><?php echo _("Settings") ?></a></li>
	<li role="presentation" <?php echo $action == 'usage' ? ' class="active"' : ''?>><a href="config.php?display=voicemail&amp;action=usage&amp;ext=<?php echo $extension ?>"><?php echo _("Usage") ?></a></li>
	<li role="presentation" <?php echo $action == 'settings' ? ' class="active"' : ''?>><a href="config.php?display=voicemail&amp;action=settings&amp;ext=<?php echo $extension ?>"><?php echo _("Advanced Settings") ?></a></li>
</ul>

<?php }