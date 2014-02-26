<table>
	<tr>
    	<td><a href="#" class="info"><?php echo _("Allowed Voicemail")?>:<span><?php echo _("These are the assigned and active extensions which will show up for this user to control and edit in UCP")?></span></a></td>
		<td>
			<div class="extensions-list">
			<?php foreach($fpbxusers as $fpbxuser) {?>
				<label><input type="checkbox" name="ucp|voicemail[]" value="<?php echo $fpbxuser['ext']?>" <?php echo $fpbxuser['selected'] ? 'checked' : '' ?>> <?php echo $fpbxuser['data']['name']?> &lt;<?php echo $fpbxuser['ext']?>&gt;</label><br />
			<?php } ?>
			</div>
		</td>
	</tr>
</table>