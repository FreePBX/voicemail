<div class="col-md-10">
	<div class="row">
		<div class="col-md-6">
			<div id="unavail" class="greeting-control">
				<h4><?php echo _('Unavailable Greeting')?></h4>
				<div id="freepbx_player_unavail" class="jp-jplayer"></div>
				<div id="freepbx_player_unavail_1" class="jp-audio <?php echo !isset($greetings['unavail']) ? 'greet-hidden' : ''?>">
				    <div class="jp-type-single">
				        <div class="jp-gui jp-interface">
				            <ul class="jp-controls">                
				                <li class="jp-play-wrapper"><a href="javascript:;" class="jp-play" tabindex="1">play</a></li>
				                <li class="jp-pause-wrapper"><a href="javascript:;" class="jp-pause" tabindex="1">pause</a></li>
				                <li class="jp-stop-wrapper"><a href="javascript:;" class="jp-stop" tabindex="1">stop</a></li>
				                <li class="jp-mute-wrapper"><a href="javascript:;" class="jp-mute" tabindex="1" title="mute">mute</a></li>
				                <li class="jp-unmute-wrapper"><a href="javascript:;" class="jp-unmute" tabindex="1" title="unmute">unmute</a></li>
				                <li class="jp-volume-max-wrapper"><a href="javascript:;" class="jp-volume-max" tabindex="1" title="max volume">max volume</a></li>
				            </ul>
				            <div class="jp-progress">
				                <div class="jp-seek-bar">
				                    <div class="jp-play-bar"></div>
				                </div>
				            </div>
				            <div class="jp-volume-bar">
				                <div class="jp-volume-bar-value"></div>
				            </div>
				            <div class="jp-current-time"></div>
				            <div class="jp-duration"></div>
					        <div class="jp-title">
					            <ul>
					                <li id="title-text"><?php echo _('Unavailable Greeting')?></li>
					            </ul>
					        </div>                  
				        </div>
				        <div class="jp-no-solution">
				            <span><?php echo _('Update Required')?></span>
				            <?php echo sprintf(_('To play the media you will need to either update your browser to a recent version or update your %s'),'<a href="http://get.adobe.com/flashplayer/" target="_blank">Flash plugin</a>')?>.
				        </div>
				    </div>
				</div>
				<span class="btn btn-file"><?php echo _('Upload New Greeting')?><input type="file" type="file" name="files[]" multiple /></span>
				<button class="<?php echo !isset($greetings['unavail']) ? 'greet-hidden' : ''?>" onclick="greetingdelete('unavail')"><?php echo _('Delete')?></button>
				<div class="filedrop hidden-xs hidden-sm">
					<div class="pbar">
						<span><?php echo _('Drag a New Greeting Here')?></span>
					</div>
				</div>
			</div>
		</div>
		<div class="col-md-6">
			<div id="busy" class="greeting-control">
				<h4><?php echo _('Busy Greeting')?></h4>
				<div id="freepbx_player_busy" class="jp-jplayer"></div>
				<div id="freepbx_player_busy_1" class="jp-audio <?php echo !isset($greetings['busy']) ? 'greet-hidden' : ''?>">
				    <div class="jp-type-single">
				        <div class="jp-gui jp-interface">
				            <ul class="jp-controls">                
				                <li class="jp-play-wrapper"><a href="javascript:;" class="jp-play" tabindex="1">play</a></li>
				                <li class="jp-pause-wrapper"><a href="javascript:;" class="jp-pause" tabindex="1">pause</a></li>
				                <li class="jp-stop-wrapper"><a href="javascript:;" class="jp-stop" tabindex="1">stop</a></li>
				                <li class="jp-mute-wrapper"><a href="javascript:;" class="jp-mute" tabindex="1" title="mute">mute</a></li>
				                <li class="jp-unmute-wrapper"><a href="javascript:;" class="jp-unmute" tabindex="1" title="unmute">unmute</a></li>
				                <li class="jp-volume-max-wrapper"><a href="javascript:;" class="jp-volume-max" tabindex="1" title="max volume">max volume</a></li>
				            </ul>
				            <div class="jp-progress">
				                <div class="jp-seek-bar">
				                    <div class="jp-play-bar"></div>
				                </div>
				            </div>
				            <div class="jp-volume-bar">
				                <div class="jp-volume-bar-value"></div>
				            </div>
				            <div class="jp-current-time"></div>
				            <div class="jp-duration"></div>
					        <div class="jp-title">
					            <ul>
					                <li id="title-text"><?php echo _('Busy Greeting')?></li>
					            </ul>
					        </div>                  
				        </div>
				        <div class="jp-no-solution">
				            <span><?php echo _('Update Required')?></span>
				            <?php echo sprintf(_('To play the media you will need to either update your browser to a recent version or update your %s'),'<a href="http://get.adobe.com/flashplayer/" target="_blank">Flash plugin</a>')?>.
				        </div>
				    </div>
				</div>
				<span class="btn btn-file"><?php echo _('Upload New Greeting')?><input type="file" id="fileupload" type="file" name="files[]" multiple /></span>
				<button class="<?php echo !isset($greetings['busy']) ? 'greet-hidden' : ''?>" onclick="greetingdelete('busy')"><?php echo _('Delete')?></button>
				<div class="filedrop hidden-xs hidden-sm">
					<div class="pbar">
						<span><?php echo _('Drag a New Greeting Here')?></span>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-6">
			<div id="greet" class="greeting-control">
				<h4><?php echo _('Name Greeting')?></h4>
				<div id="freepbx_player_greet" class="jp-jplayer"></div>
				<div id="freepbx_player_greet_1" class="jp-audio <?php echo !isset($greetings['greet']) ? 'greet-hidden' : ''?>">
				    <div class="jp-type-single">
				        <div class="jp-gui jp-interface">
				            <ul class="jp-controls">                
				                <li class="jp-play-wrapper"><a href="javascript:;" class="jp-play" tabindex="1">play</a></li>
				                <li class="jp-pause-wrapper"><a href="javascript:;" class="jp-pause" tabindex="1">pause</a></li>
				                <li class="jp-stop-wrapper"><a href="javascript:;" class="jp-stop" tabindex="1">stop</a></li>
				                <li class="jp-mute-wrapper"><a href="javascript:;" class="jp-mute" tabindex="1" title="mute">mute</a></li>
				                <li class="jp-unmute-wrapper"><a href="javascript:;" class="jp-unmute" tabindex="1" title="unmute">unmute</a></li>
				                <li class="jp-volume-max-wrapper"><a href="javascript:;" class="jp-volume-max" tabindex="1" title="max volume">max volume</a></li>
				            </ul>
				            <div class="jp-progress">
				                <div class="jp-seek-bar">
				                    <div class="jp-play-bar"></div>
				                </div>
				            </div>
				            <div class="jp-volume-bar">
				                <div class="jp-volume-bar-value"></div>
				            </div>
				            <div class="jp-current-time"></div>
				            <div class="jp-duration"></div>
					        <div class="jp-title">
					            <ul>
					                <li id="title-text"><?php echo _('Name Greeting')?></li>
					            </ul>
					        </div>                  
				        </div>
				        <div class="jp-no-solution">
				            <span><?php echo _('Update Required')?></span>
				            <?php echo sprintf(_('To play the media you will need to either update your browser to a recent version or update your %s'),'<a href="http://get.adobe.com/flashplayer/" target="_blank">Flash plugin</a>')?>.
				        </div>
				    </div>
				</div>
				<span class="btn btn-file"><?php echo _('Upload New Greeting')?><input type="file" id="fileupload" type="file" name="files[]" multiple /></span>
				<button class="<?php echo !isset($greetings['greet']) ? 'greet-hidden' : ''?>" onclick="greetingdelete('greet')"><?php echo _('Delete')?></button>
				<div class="filedrop hidden-xs hidden-sm">
					<div class="pbar">
						<span><?php echo _('Drag a New Greeting Here')?></span>
					</div>
				</div>
			</div>
		</div>
		<div class="col-md-6">
			<div id="temp" class="greeting-control">
				<h4><?php echo _('Temporary Greeting')?></h4>
				<div id="freepbx_player_temp" class="jp-jplayer"></div>
				<div id="freepbx_player_temp_1" class="jp-audio <?php echo !isset($greetings['temp']) ? 'greet-hidden' : ''?>">
				    <div class="jp-type-single">
				        <div class="jp-gui jp-interface">
				            <ul class="jp-controls">                
				                <li class="jp-play-wrapper"><a href="javascript:;" class="jp-play" tabindex="1">play</a></li>
				                <li class="jp-pause-wrapper"><a href="javascript:;" class="jp-pause" tabindex="1">pause</a></li>
				                <li class="jp-stop-wrapper"><a href="javascript:;" class="jp-stop" tabindex="1">stop</a></li>
				                <li class="jp-mute-wrapper"><a href="javascript:;" class="jp-mute" tabindex="1" title="mute">mute</a></li>
				                <li class="jp-unmute-wrapper"><a href="javascript:;" class="jp-unmute" tabindex="1" title="unmute">unmute</a></li>
				                <li class="jp-volume-max-wrapper"><a href="javascript:;" class="jp-volume-max" tabindex="1" title="max volume">max volume</a></li>
				            </ul>
				            <div class="jp-progress">
				                <div class="jp-seek-bar">
				                    <div class="jp-play-bar"></div>
				                </div>
				            </div>
				            <div class="jp-volume-bar">
				                <div class="jp-volume-bar-value"></div>
				            </div>
				            <div class="jp-current-time"></div>
				            <div class="jp-duration"></div>
					        <div class="jp-title">
					            <ul>
					                <li id="title-text"><?php echo _('Temporary Greeting')?></li>
					            </ul>
					        </div>                  
				        </div>
				        <div class="jp-no-solution">
				            <span><?php echo _('Update Required')?></span>
				            <?php echo sprintf(_('To play the media you will need to either update your browser to a recent version or update your %s'),'<a href="http://get.adobe.com/flashplayer/" target="_blank">Flash plugin</a>')?>.
				        </div>
				    </div>
				</div>
				<span class="btn btn-file"><?php echo _('Upload New Greeting')?><input type="file" id="fileupload" type="file" name="files[]" multiple /></span>
				<button class="<?php echo !isset($greetings['temp']) ? 'greet-hidden' : ''?>" onclick="greetingdelete('temp')"><?php echo _('Delete')?></button>
				<div class="filedrop hidden-xs hidden-sm">
					<div class="pbar">
						<span><?php echo _('Drag a New Greeting Here')?></span>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>