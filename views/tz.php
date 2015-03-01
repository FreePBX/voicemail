<div class="well well-info">
	<?php echo _("A timezone definition specifies how the Voicemail system announces the time.") ?>
	<?php echo  _("For example, the time a message was left will be announced according to the user's timezone on message playback.") ?><br />
	<b><?php echo _("Entries below will be written to Voicemail configuration as-is.") ?></b><br />
	<b><?php echo _("Please be sure to follow the format for timezone definitions described below.") ?></b>
</div>
<?php foreach ($settings as $key => $val) { ?>
	<!--<?php echo $key?>-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="tz__<?php echo $key ?>"><?php echo $key ?></label>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="tz__<?php echo $key ?>" name="tz__<?php echo $key ?>" value="<?php echo htmlentities($val) ?>">
							<span class="radioset">
								<input type='checkbox' name='tzdel__<?php echo $key ?>' id='tzdel__<?php echo $key ?>' value='true' />
								<label for="tzdel__<?php echo $key ?>"><?php echo _("Delete") ?></label>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<!--END <?php echo $key?>-->
	<?php } ?>
	<!--New Name-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="tznew_name"><?php echo _("New Name") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="tznew_name"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="tznew_name" name="tznew_name" value="">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="tznew_name-help" class="help-block fpbx-help-block"><?php echo $tooltips["tz"]["name"] ?></span>
			</div>
		</div>
	</div>
	<!--END New Name-->
	<!--New Timezone Definition-->
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="tznew_def"><?php echo _("New Timezone Definition") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="tznew_def"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="tznew_def" name="tznew_def" value="">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="tznew_def-help" class="help-block fpbx-help-block"><?php echo $tooltips["tz"]["def"]?></span>
			</div>
		</div>
	</div>
	<!--END New Timezone Definition-->
	<input type='hidden' name='action' id='action' value='Submit' />

<div class="well well-info">
	<?php echo ("Timezone definition format is: ") ?>&nbsp;&nbsp;<b style='font-family:courier;'><?php echo ("timezone|values")?></b>
	<br /><br/><b><?php echo ("<i>Timezones</i> are listed in /usr/share/zoneinfo")?>
</div>

	<table class="table table-striped">

	<tr>
		<td style='max-width: 60px' colspan='2'>
			<b><?php echo ("The <i>values</i> supported in the timezone definition string include:")?></b>
		</td>
	</tr>
	<tr>
		<td>
			<?php echo ("'filename'")?>
		</td>
		<td style='max-width: 60px' colspan='2'>
			<?php echo ("The name of a sound file (the file name must be single-quoted)")?>
		</td>
	</tr>
	<tr>
		<td>
			<?php echo ("variable")?>
		</td>
		<td style='max-width: 60px' colspan='2'>
			<?php echo ("A variable to be substituted (see below for supported variable values)")?>
		</td>
	</tr>
	<tr>
		<td style='max-width: 60px' colspan='2'>
			<b><?php echo ("Supported <i>variables</i>:")?></b>
		</td>
	</tr>
	<tr>
		<td style='max-width: 60px'>
			<?php echo ("A or a") 	?>
		</td>
		<td style='max-width: 60px' colspan='2'>
			<?php echo ("Day of week (Saturday, Sunday, ...)")?>
		</td>
	</tr>
	<tr>
		<td style='max-width: 60px'>
			<?php echo ("B or b or h")?>
		</td>
		<td style='max-width: 60px' colspan='2'>
			<?php echo ("Month name (January, February, ...)")?>
		</td>
	</tr>
	<tr>
		<td style='max-width: 60px'>
			<?php echo ("d or e") 	?>
		</td>
		<td style='max-width: 60px' colspan='2'>
			<?php echo ("numeric day of month (first, second, ..., thirty-first)")?>
		</td>
	</tr>
	<tr>
		<td style='max-width: 60px'>
			<?php echo ("Y")?>
		</td>
		<td style='max-width: 60px' colspan='2'>
			<?php echo ("Year")?>
		</td>
	</tr>
	<tr>
		<td style='max-width: 60px'>
			<?php echo ("I or l") 	?>
		</td><td style='max-width: 60px' colspan='2'>
			<?php echo ("Hour, 12 hour clock")?>
		</td>
	</tr>
	<tr>
		<td style='max-width: 60px'>
			<?php echo ("H")?>
		</td>
		<td style='max-width: 60px' colspan='2'>
			<?php echo ("Hour, 24 hour clock (single digit hours preceded by \"oh\")")?>
		</td>
	</tr>
	<tr>
		<td style='max-width: 60px'>
			<?php echo ("k")?>
		</td>
		<td style='max-width: 60px' colspan='2'>
			<?php echo ("Hour, 24 hour clock (single digit hours NOT preceded by \"oh\")")?>
		</td>
	</tr>
	<tr>
		<td style='max-width: 60px'>
			<?php echo ("M")?>
		</td>
		<td style='max-width: 60px' colspan='2'>
			<?php echo ("Minute, with 00 pronounced as \"o'clock\"")?>
		</td></tr>
		<tr>
			<td style='max-width: 60px'>
				<?php echo ("N")?>
			</td>
			<td style='max-width: 60px' colspan='2'>
				<?php echo ("Minute, with 00 pronounced as \"hundred\" (US military time)")?>
			</td>
		</tr>
		<tr>
			<td style='max-width: 60px'>
				<?php echo ("P or p") 	?>
			</td>
			<td style='max-width: 60px' colspan='2'>
				<?php echo ("AM or PM")?>
			</td>
		</tr>
		<tr>
			<td style='max-width: 60px'>
				<?php echo ("Q")?>
			</td>
			<td style='max-width: 60px' colspan='2'>
				<?php echo ("\"today\", \"yesterday\" or ABdY")?>
			</td>
		</tr>
		<tr>
			<td style='max-width: 60px'>
				<?php echo ("q")?>
			</td>
			<td style='max-width: 60px' colspan='2'>
				<?php echo ("\"\" (for today), \"yesterday\", weekday, or ABdY")?>
			</td>
		</tr>
		<tr>
			<td style='max-width: 60px'>
				<?php echo ("R")?>
			</td>
			<td style='max-width: 60px' colspan='2'>
				<?php echo ("24 hour time, including minute")?>
			</td>
		</tr>
	</tr>
</table>