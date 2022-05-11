<?php if (!empty($extension)) { ?>
<h3><?php echo _("Account View Links:") ?></h3>
<ul class="nav nav-tabs">
	<li role="presentation"><a  class="nav-link <?php echo $action == 'bsettings' ? 'active' : ''?>" href="config.php?display=voicemail&amp;action=bsettings&amp;ext=<?php echo $extension ?>"><?php echo _("Account Settings") ?></a></li>
	<li role="presentation"><a  class="nav-link <?php echo $action == 'usage' ? 'active' : ''?>" href="config.php?display=voicemail&amp;action=usage&amp;ext=<?php echo $extension ?>"><?php echo _("Account Usage") ?></a></li>
	<li role="presentation"><a  class="nav-link <?php echo $action == 'settings' ? 'active' : ''?>" href="config.php?display=voicemail&amp;action=settings&amp;ext=<?php echo $extension ?>"><?php echo _("Account Advanced Settings") ?></a></li>
</ul>

<?php }

