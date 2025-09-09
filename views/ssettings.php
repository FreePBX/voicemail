<?php if (!empty($extension)) { ?>
<h3><?php echo _("Account View Links:") ?></h3>
<ul class="nav nav-tabs">
        <li role="presentation" <?php echo $action == 'bsettings' ? ' class="active"' : ''?>><a href="config.php?display=voicemail&amp;action=bsettings&amp;ext=<?php echo $extension ?>"><?php echo _("Account Settings") ?></a></li>
        <li role="presentation" <?php echo $action == 'usage' ? ' class="active"' : ''?>><a href="config.php?display=voicemail&amp;action=usage&amp;ext=<?php echo $extension ?>"><?php echo _("Account Usage") ?></a></li>
        <li role="presentation" <?php echo $action == 'settings' ? ' class="active"' : ''?>><a href="config.php?display=voicemail&amp;action=settings&amp;ext=<?php echo $extension ?>"><?php echo _("Account Advanced Settings") ?></a></li>
</ul>

<?php }
?>
<div class="container-fluid">
	<div class="nav-container">
		<div class="scroller scroller-left"><i class="fa fa-chevron-left"></i></div>
		<div class="scroller scroller-right"><i class="fa fa-chevron-right"></i></div>
		<div class="wrapper">
			<ul class="nav nav-tabs list" role="tablist">
			<?php foreach($d as $section => $data) { ?>
				<li data-name="<?php echo $section?>" class="change-tab <?php echo ($section == "general") ? "active" : ""?>"><a href="#<?php echo $section?>" aria-controls="<?php echo $section?>" role="tab" data-toggle="tab"><?php echo $data['name']?></a></li>
			<?php } ?>
			</ul>
		</div>
	</div>
	<div class="tab-content display">
		<?php foreach($d as $section => $data) { ?>
			<div id="<?php echo $section?>" class="tab-pane <?php echo ($section == "general") ? "active" : ""?>">
				<?php if(!empty($data['helptext'])) { ?>
					<div class="alert alert-info"><?php echo $data['helptext'] ?></div>
				<?php } ?>
				<?php foreach($data['settings'] as $key => $items) { ?>
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<?php // Only add 'for' attribute if it's not a radio group label ?>
											<label class="control-label" <?php if ($items['type'] !== 'radio'): ?>for="<?php echo $id_prefix?>__<?php echo $key?>"<?php endif; ?>><?php echo $items['description']?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="<?php echo $id_prefix?>__<?php echo $key?>"></i>
										</div>
										<div class="col-md-9">
											<?php switch($items['type']) {
														case "number": ?>
														<input type="number" class="form-control" id="<?php echo $id_prefix?>__<?php echo $key?>" name="<?php echo $id_prefix?>__<?php echo $key?>" value="<?php echo !empty($settings[$key]) ? htmlentities((string) $settings[$key], ENT_COMPAT, 'UTF-8') : $items['default'] ?>" <?php if(!empty($items['options'])) {?>min="<?php echo $items['options'][0]?>" max="<?php echo $items['options'][1]?>"<?php } ?>>
												<?php break;
														case "text": ?>
														<input type="text" class="form-control" id="<?php echo $id_prefix?>__<?php echo $key?>" name="<?php echo $id_prefix?>__<?php echo $key?>" value="<?php echo !empty($settings[$key]) ? htmlentities((string) $settings[$key], ENT_COMPAT, 'UTF-8') : $items['default'] ?>">
														<?php // Add Impersonation Method Dropdown and Button after authpassword field
															if ($key === 'authpassword'): ?>
																<div style="margin-top: 15px;">
																	<label for="<?php echo $id_prefix ?>__imapimpersonationmethod"><?php echo _('IMAP Impersonation Method:'); ?></label>
																	<select class="form-control" id="<?php echo $id_prefix ?>__imapimpersonationmethod" name="<?php echo $id_prefix ?>__imapimpersonationmethod">
																		<option value="" <?php echo empty($settings['imapimpersonationmethod']) ? 'selected' : '' ?>><?php echo _('-- Not Set (Requires Test) --'); ?></option>
																		<option value="none" <?php echo ($settings['imapimpersonationmethod'] ?? '') === 'none' ? 'selected' : '' ?>><?php echo _('None (Per-Extension Credentials)'); ?></option>
																		<option value="separator_backslash" <?php echo ($settings['imapimpersonationmethod'] ?? '') === 'separator_backslash' ? 'selected' : '' ?>><?php echo _('Backslash Separator ([authuser]\\[imapuser])'); ?></option>
																		<option value="separator_slash" <?php echo ($settings['imapimpersonationmethod'] ?? '') === 'separator_slash' ? 'selected' : '' ?>><?php echo _('Separator: / ([authuser]/[imapuser])'); ?></option>
																		<option value="separator_at" <?php echo ($settings['imapimpersonationmethod'] ?? '') === 'separator_at' ? 'selected' : '' ?>><?php echo _('Separator: @ ([authuser]@[imapuser])'); ?></option>
																		<option value="separator_plus" <?php echo ($settings['imapimpersonationmethod'] ?? '') === 'separator_plus' ? 'selected' : '' ?>><?php echo _('Separator: + ([authuser]+[imapuser])'); ?></option>
																		<option value="separator_colon" <?php echo ($settings['imapimpersonationmethod'] ?? '') === 'separator_colon' ? 'selected' : '' ?>><?php echo _('Separator: : ([authuser]:[imapuser])'); ?></option>
																		<option value="authuser" <?php echo ($settings['imapimpersonationmethod'] ?? '') === 'authuser' ? 'selected' : '' ?>><?php echo _('AuthUser Parameter (/authuser=[authuser])'); ?></option>
																	</select>
																	<span class="help-block"><?php echo _('Select the method for the admin user to impersonate the target user. Use the Test button to determine the correct setting, then save changes.'); ?></span>
																</div>
																<div style="margin-top: 15px;">
																	<label for="<?php echo $id_prefix ?>__imapauthtype"><?php echo _('IMAP Auth Type:'); ?></label>
																	<select class="form-control" id="<?php echo $id_prefix ?>__imapauthtype" name="<?php echo $id_prefix ?>__imapauthtype">
																		<option value="None" <?php echo ($settings['imapauthtype'] ?? 'None') === 'None' ? 'selected' : '' ?>><?php echo _('Default (None)'); ?></option>
																		<option value="DISABLE_GSSAPI" <?php echo ($settings['imapauthtype'] ?? 'None') === 'DISABLE_GSSAPI' ? 'selected' : '' ?>><?php echo _('Disable GSSAPI'); ?></option>
																		<option value="DISABLE_PLAIN" <?php echo ($settings['imapauthtype'] ?? 'None') === 'DISABLE_PLAIN' ? 'selected' : '' ?>><?php echo _('Disable PLAIN'); ?></option>
																		<option value="DISABLE_NTLM" <?php echo ($settings['imapauthtype'] ?? 'None') === 'DISABLE_NTLM' ? 'selected' : '' ?>><?php echo _('Disable NTLM'); ?></option>
																		<option value="DISABLE_CRAM_MD5" <?php echo ($settings['imapauthtype'] ?? 'None') === 'DISABLE_CRAM_MD5' ? 'selected' : '' ?>><?php echo _('Disable CRAM-MD5'); ?></option>
																		<option value="DISABLE_LOGIN" <?php echo ($settings['imapauthtype'] ?? 'None') === 'DISABLE_LOGIN' ? 'selected' : '' ?>><?php echo _('Disable LOGIN'); ?></option>
																		<?php /* Add other options here if needed in the future */ ?>
																	</select>
																	<span class="help-block"><?php echo _('Advanced: Select the authentication type. "None" allows standard negotiation. Disabling specific types (like GSSAPI) can sometimes resolve issues with certain servers. Use the Test button to verify.'); ?></span>
																</div>
																<button type="button" id="test-imap-impersonation" class="btn btn-default" style="margin-top: 10px;"><?php echo _('Test IMAP Impersonation'); ?></button>
																<div id="imap-test-results" style="margin-top: 15px; padding: 10px; border: 1px solid #ccc; display: none;"></div>
															<?php endif; ?>
												<?php break;
														case "textbox":
															$value = !empty($settings[$key]) ?  htmlentities((string) $settings[$key], ENT_COMPAT, 'UTF-8') : $items['default'];
															$value = str_replace(["\\n", "\\t", "\\r"],["\n", "\t", "\r"],(string) $value);
												?>
														<textarea class="form-control autosize" id="<?php echo $id_prefix?>__<?php echo $key?>" name="<?php echo $id_prefix?>__<?php echo $key?>"><?php echo $value ?></textarea>
												<?php break;
														case "radio": ?>
														<div class="radioset">
															<?php foreach($items['options'] as $k => $v) { ?>
																<input type="radio" class="form-control" id="<?php echo $id_prefix?>__<?php echo $key?>_<?php echo $k?>" name="<?php echo $id_prefix?>__<?php echo $key?>" value="<?php echo $k?>" <?php echo ((!empty($settings[$key]) && $settings[$key] == $k) || ( empty($settings[$key]) && $items['default'] == $k)) ? 'checked' : '' ?>>
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
			</div>
		<?php } ?>
	</div>
</div>

<?php // Add JavaScript for the button handler ?>
<script type="text/javascript">
// Wrap in a self-executing function for jQuery safety
(function($) {
    // Log whether jQuery is available
    console.log("jQuery available:", typeof $ === 'function');
    
    // Button handler with core functionality
$(document).ready(function() {
        console.log("Document ready called");
        
        // Main button handler
        $("#test-imap-impersonation").on("click", function() {
            console.log("IMAP test button clicked");
        var button = $(this);
            var resultsDiv = $("#imap-test-results");
            
            // Find form field prefix
            var prefix = "gen";
            if ($("#acct__imapserver").length > 0) {
                prefix = "acct";
            }
            console.log("Using form prefix:", prefix);
            
            // Get form values
            var server = $("#" + prefix + "__imapserver").val();
            var port = $("#" + prefix + "__imapport").val();
            var authUser = $("#" + prefix + "__authuser").val();
            var authPassword = $("#" + prefix + "__authpassword").val();
            var flags = $("#" + prefix + "__imapflags").val() || "";
            var authType = $("#" + prefix + "__imapauthtype").val();
            
            console.log("Form values:", {
                server: server,
                port: port,
                authUser: authUser,
                authType: authType,
                hasPassword: authPassword ? "yes" : "no"
            });
            
            // Validate required fields
            if (!server || !authUser || !authPassword) {
                resultsDiv.html("<p class='text-danger'><strong>Error:</strong> Please fill in server, auth username, and auth password.</p>").show();
            return;
        }

            // Ask for test username
            var testUser = prompt("Enter a mailbox username to test impersonation with:");
            if (!testUser) {
                resultsDiv.html("<p class='text-warning'>Test cancelled.</p>").show();
                return;
            }
            
            // Show loading state
            button.prop("disabled", true).text("Testing...");
            resultsDiv.html("<p>Running tests...</p>").show();
            
            // Make AJAX call to test impersonation
        $.ajax({
                url: window.location.href,
                type: "POST",
                dataType: "json",
            data: {
                    display: "voicemail",
                    action: "testImapConnection",
                server: server,
                port: port,
                flags: flags,
                authUser: authUser,
                authPassword: authPassword,
                    testUser: testUser,
                    authType: authType
            },
            success: function(response) {
                    console.log("AJAX Success:", response);
                    
                    // Build simple HTML output
                    var html = "";
                    
                    if (response && response.status === true) {
                        html += "<h4>IMAP Impersonation Test Results:</h4>";
                        html += "<p class='text-success'><strong>Success!</strong> A working impersonation method was found.</p>";
                        html += "<ul>";
                        
                        // Method
                        if (response.data && response.data.successful_method) {
                            html += "<li><strong>Method:</strong> " + response.data.successful_method + "</li>";
                            
                            // Update method dropdown
                            var methodValue = "";
                            if (response.data.successful_method === 'backslash') {
                                methodValue = 'separator_backslash';
                            } else if (response.data.successful_method === 'authuser') {
                                methodValue = 'authuser';
                            } else if (response.data.successful_method === 'none') {
                                methodValue = 'none';
                            } else if (response.data.successful_method.startsWith('separator_')) {
                                methodValue = response.data.successful_method;
                            }
                            
                            if (methodValue) {
                                $("#" + prefix + "__imapimpersonationmethod").val(methodValue);
                                html += "<p class='text-info' style='margin-left:20px;'><em>Selected impersonation method in dropdown.</em></p>";
                            }
                        }
                        
                        // Use the auth type that actually worked
                        var authTypeToUse = null;
                        
                        if (response.data && response.data.successful_auth_type) {
                            authTypeToUse = response.data.successful_auth_type;
                            html += "<li><strong>Auth Type:</strong> " + authTypeToUse + "</li>";
                        }
                        
                        if (authTypeToUse) {
                            $("#" + prefix + "__imapauthtype").val(authTypeToUse);
                            html += "<p class='text-info' style='margin-left:20px;'><em>Selected auth type in dropdown.</em></p>";
                            
                            // If we're recommending DISABLE_GSSAPI when 'None' was used in the test, explain why
                            if (authTypeToUse === 'DISABLE_GSSAPI' && 
                                response.data && 
                                response.data.successful_auth_type === 'None') {
                                html += "<p class='text-warning' style='margin-left:20px;'><em>Note: Although 'None' worked, we're recommending 'DISABLE_GSSAPI' because Kerberos authentication errors were detected during the test. This will give better performance.</em></p>";
                            }
                        }
                        
                        // Port
                        if (response.data && response.data.successful_port) {
                            html += "<li><strong>Port:</strong> " + response.data.successful_port + "</li>";
                            $("#" + prefix + "__imapport").val(response.data.successful_port);
                            html += "<p class='text-info' style='margin-left:20px;'><em>Updated port in field.</em></p>";
                        }
                        
                        // Flags
                        if (response.data && response.data.successful_flags) {
                            html += "<li><strong>Flags:</strong> " + response.data.successful_flags + "</li>";
                            
                            var baseFlag = 'ssl';
                            if (response.data.successful_flags.includes('tls')) {
                                    baseFlag = 'tls'; 
                            } else if (response.data.successful_flags.includes('ssl')) {
                                    baseFlag = 'ssl'; 
                            } else if (response.data.successful_flags.includes('notls')) {
                                    baseFlag = 'notls'; 
                                }
                            
                            $("#" + prefix + "__imapflags").val(baseFlag);
                            html += "<p class='text-info' style='margin-left:20px;'><em>Updated flags to '" + baseFlag + "' in field.</em></p>";
                            
                                 if (baseFlag === 'notls') {
                                html += "<div class='alert alert-danger' style='margin-top:10px;'>";
                                html += "<strong>Security Warning:</strong> The connection succeeded using an insecure method (notls). ";
                                html += "Passwords will be sent in plaintext. Please enable SSL or TLS if possible.";
                                html += "</div>";
                            }
                        }
                        
                        html += "</ul>";
                        html += "<p class='text-info'><strong>Remember to Save Changes after reviewing settings.</strong></p>";
                        } else {
                        html += "<p class='text-danger'><strong>Failed.</strong> No working impersonation method found.</p>";
                        
                        if (response && response.message) {
                            html += "<p><strong>Error Message:</strong> " + response.message + "</p>";
                        }
                        
                        if (response && response.data && response.data.error_summary) {
                            html += "<p><strong>Detailed Errors:</strong></p><ul>";
                            response.data.error_summary.forEach(function(err) {
                                html += "<li>" + err + "</li>";
                            });
                            html += "</ul>";
                        }
                    }
                    
                    // Add debug data
                    if (response && response.data) {
                        html += "<h5>Debug Information:</h5>";
                        html += "<pre style='max-height:200px; overflow-y:auto;'>" + JSON.stringify(response.data, null, 2) + "</pre>";
                    }
                    
                    // Update results
                    resultsDiv.html(html).show();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown);
                    
                    var errorMessage = "<p class='text-danger'><strong>AJAX Error:</strong> " + textStatus + " - " + errorThrown + "</p>";
                    
                    try {
                        var errorResponse = JSON.parse(jqXHR.responseText);
                        errorMessage += "<pre>" + JSON.stringify(errorResponse, null, 2) + "</pre>";
                } catch(e) {
                    // Ignore parse error
                }
                 
                    resultsDiv.html(errorMessage).show();
            },
            complete: function() {
                    button.prop("disabled", false).text("Test IMAP Impersonation");
                }
            });
        });

        // Visual indicator
        $("#test-imap-impersonation").css("border", "2px solid blue").attr("title", "IMAP Test Enabled");
    });
})(jQuery);
</script>

