<form class="fpbx-submit" name="frm_voicemail" id="frm_voicemail" action="" method="post" data-fpbx-delete="" role="form">
	<input type="hidden" name="type" id="type" value="setup">
	<input type="hidden" name="display" id="display" value="voicemail">
	<input type="hidden" name="ext" id="ext" value="">
	<input type="hidden" name="page_type" id="page_type" value="settings">
	<input type="hidden" name="action" id="action" value="Submit">

  <?php foreach($d as $data) { ?>
    <?php foreach($data['settings'] as $key => $items) { ?>
      <div class="element-container">
    		<div class="row">
    			<div class="col-md-12">
    				<div class="row">
    					<div class="form-group">
    						<div class="col-md-3">
    							<label class="control-label" for="<?php echo $id_prefix?>__<?php echo $key?>"><?php echo $items['description']?></label>
    							<i class="fa fa-question-circle fpbx-help-icon" data-for="<?php echo $id_prefix?>__<?php echo $key?>"></i>
    						</div>
    						<div class="col-md-9">
                  <?php switch($items['type']) {
                        case "number": ?>
                        <input type="number" class="form-control" id="<?php echo $id_prefix?>__<?php echo $key?>" name="<?php echo $id_prefix?>__<?php echo $key?>" value="<?php echo !empty($settings[$key]) ? $settings[$key] : $default ?>" <?php if(!empty($items['options'])) {?>min="<?php echo $items['options'][0]?>" max="<?php echo $items['options'][1]?>"<?php } ?>>
                    <?php break;
                        case "text": ?>
                        <input type="text" class="form-control" id="<?php echo $id_prefix?>__<?php echo $key?>" name="<?php echo $id_prefix?>__<?php echo $key?>" value="<?php echo !empty($settings[$key]) ? $settings[$key] : $default ?>">
                    <?php break;
                        case "radio": ?>
                        <div class="radioset">
                          <?php foreach($items['options'] as $k => $v) { ?>
                            <input type="radio" class="form-control" id="<?php echo $id_prefix?>__<?php echo $key?>_<?php echo $k?>" name="<?php echo $id_prefix?>__<?php echo $key?>" value="<?php echo $k?>" <?php echo ((!empty($settings[$key]) && $settings[$key] == $k) || ($items['default'] == $k)) ? 'checked' : '' ?>>
                            <label for="<?php echo $id_prefix?>__<?php echo $key?>_<?php echo $k?>"><?php echo $v?></label>
                          <?php } ?>
                        </div>
                    <?php break;
                  } ?>
                </div>
    					</div>
    				</div>
    			</div>
    		</div>
    		<div class="row">
    			<div class="col-md-12">
    				<span id="<?php echo $id_prefix?>__<?php echo $key?>-help" class="help-block fpbx-help-block"><?php echo $items['helptext']?></span>
    			</div>
    		</div>
    	</div>
    <?php } ?>
  <?php } ?>
</form>
