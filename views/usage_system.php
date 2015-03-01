<br/>
<div class="row">
	<div class="col-sm-6">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title"><?php echo _("Number of Accounts")?></h3>
			</div>
			<div class="panel-body">
				<table class="table table-striped">
					<tr><td><?php echo _("Activated")?></td><td><?php echo $acts_act ?></td></tr>
					<tr><td><?php echo _("Unactivated")?></td><td><?php echo $acts_unact ?></td></tr>
					<tr><td><?php echo _("Disabled")?></td><td><?php echo $disabled_count ?></td></tr>
					<tr><td><?php echo _("Total")?></td><td><?php echo $acts_total?></td></tr>
				</table>
			</div>
		</div>
	</div>
	<div class="col-sm-6">
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title"><?php echo _("General Usage")?></h3>
			</div>
			<div class="panel-body">
				<table class="table table-striped">
					<tr>
						<td>
							<a href='#' class='info'><?php echo _("Number of Messages:") ?><span><?php echo _("Total ( Messages in inboxes / Messages in other folders )") ?></span></a>
						</td>
						<td>&nbsp;&nbsp;&nbsp;&nbsp;<?php echo $msg_total ?>&nbsp;&nbsp;(&nbsp;<?php echo $msg_in ?>&nbsp;/&nbsp;<?php echo $msg_other ?>&nbsp;)</td>
						<td>
							<input type='checkbox' name='del_msgs' id='del_msgs' value='true' />&nbsp;<a href='#' class='info'><?php echo _("Delete") ?><span><?php echo _("Remove all messages") ?></span></a>
						</td>
					</tr>

					<tr>
						<td>
							<a href='#' class='info'><?php echo _("Recorded Names:") ?><span><?php echo _("Number of recorded name greetings") ?></span></a>
						</td>
						<td>&nbsp;&nbsp;&nbsp;&nbsp;<?php echo $name ?></td>
						<td>
							<input type='checkbox' name='del_names' id='del_names' value='true' />&nbsp;<a href='#' class='info'><?php echo _("Delete") ?><span><?php echo _("Remove all recorded names") ?></span></a>
						</td>
					</tr>


					<tr>
						<td>
							<a href='#' class='info'><?php echo _("Unavailable Greetings:") ?><span><?php echo _("Number of recorded unavailable greetings") ?></span></a>
						</td>
						<td>&nbsp;&nbsp;&nbsp;&nbsp;<?php echo $unavail ?></td>
						<td>
							<input type='checkbox' name='del_unavail' id='del_unavail' value='true' />&nbsp;<a href='#' class='info'><?php echo _("Delete") ?><span><?php echo _("Remove all unavailable greetings") ?></span></a>
						</td>
					</tr>

					<tr>
						<td>
							<a href='#' class='info'><?php echo _("Busy Greetings:") ?><span><?php echo _("Number of recorded busy greetings") ?></span></a>
						</td>
						<td>&nbsp;&nbsp;&nbsp;&nbsp;<?php echo $busy ?></td>
						<td>
							<input type='checkbox' name='del_busy' id='del_busy' value='true' />&nbsp;<a href='#' class='info'><?php echo _("Delete") ?><span><?php echo _("Remove all busy greetings") ?></span></a>
						</td>
					</tr>

					<tr>
						<td>
							<a href='#' class='info'><?php echo _("Temporary Greetings:") ?><span><?php echo _("Number of recorded temporary greetings") ?></span></a>
						</td>
						<td>&nbsp;&nbsp;&nbsp;&nbsp;<?php echo $temp ?></td>
						<td>
							<input type='checkbox' name='del_temp' id='del_temp' value='true' />&nbsp;<a href='#' class='info'><?php echo _("Delete") ?><span><?php echo _("Remove all temporary greetings") ?></span></a>
						</td>
					</tr>

					<tr>
						<td>
							<a href='#' class='info'><?php echo _("Abandoned Greetings:") ?><span><?php echo _("Number of abandoned greetings. Such greetings were recorded by the user but were NOT accepted, so the sound file remains on disk but is not used as a greeting.") ?></span></a>
						</td>
						<td>&nbsp;&nbsp;&nbsp;&nbsp;<?php echo $abandoned ?></td>
						<td>
							<input type='checkbox' name='del_abandoned' id='del_abandoned' value='true' />&nbsp;<a href='#' class='info'><?php echo _("Delete") ?><span><?php echo _("Remove all abandoned greetings (> 1 day old)") ?></span></a>
						</td>
					</tr>

					<tr>
						<td>
							<a href='#' class='info'><?php echo _("Storage Used:") ?><span><?php echo _("Disk space currently in use by Voicemail data") ?></span></a>
						</td>
						<td>&nbsp;&nbsp;&nbsp;&nbsp;<?php echo $storage ?></td>
					</tr>
				</table>
			</div>
		</div>
	</div>
</div>	