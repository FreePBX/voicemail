<div id="settings">
	<ul class="nav nav-tabs">
		<li class="active"><a href="#vmsettings" data-toggle="tab"><?php echo _("Voicemail Settings")?></a></li>
		<li><a href="#greetings" data-toggle="tab"><?php echo _("Greetings")?></a></li>
	</ul>
	<div class="tab-content">
		<?php foreach ($tabcontent as $content) {
			echo $content;
		}
		?>
	</div>
</div>
