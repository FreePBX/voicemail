var VoicemailC = UCPMC.extend({
	init: function() {
		this.loaded = null;
		this.recording = false;
		this.recorder = null;
		this.recordTimer = null;
		this.startTime = null;
		this.soundBlobs = {};
		this.placeholders = [];
		window.update_table = false; // Initialize flag to false
		window.previous_vm_data = null; // Initialize previous data state
		window.initialVoicemailLoadComplete = false; // ADDED: Flag for initial load
	},
	resize: function(widget_id) {
		$(".grid-stack-item[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('resetView',{height: $(".grid-stack-item[data-id='"+widget_id+"'] .widget-content").height()});
	},
	findmeFollowState: function() {
		if (!$("#vmx-p1_enable").is(":checked") && $("#ddial").is(":checked") && $("#vmx-state").is(":checked")) {
			$("#vmxerror").text(_("Find me Follow me is enabled when VmX locator option 1 is disabled. This means VmX Locator will be skipped, instead going directly to Find Me/Follow Me")).addClass("alert-danger").fadeIn("fast");
		} else {
			$("#vmxerror").fadeOut("fast");
		}
	},
	saveVmXSettings: function(ext, key, value) {
		var data = { ext: ext, settings: { key: key, value: value } };
		$.post( UCP.ajaxUrl + "?module=voicemail&command=vmxsettings", data, function( data ) {
			if (data.status) {
				$("#vmxmessage").text(data.message).addClass("alert-" + data.alert).fadeIn("fast", function() {

				});
			} else {
				return false;
			}
		});
	},
	poll: function(data) {
		if (typeof data.boxes === "undefined") {
			return;
		}

		var notify = false;
		var self = this;
        
        // Removed default setting: window.update_table = window.update_table || true;
        //console.log("Voicemail poll function executed, update_table =", window.update_table);

		/**
		 * Check all extensions and boxes at once.
		 */
		$.ajax({
			type: "POST",
			url: UCP.ajaxUrl + "?module=voicemail&command=checkextensions",
			async: true, // **** Changed from false to true ****
			data: data.boxes,
			success: function(vm_data){
				window.vm_data = vm_data; // Store the fetched data globally
				//console.log("Received voicemail data for extensions (Async)", Object.keys(vm_data));

				var dataChanged = false;
				// Compare with previous data to see if anything changed
				if (window.previous_vm_data) {
					Object.keys(vm_data).forEach(function(ext) {
						if (!window.previous_vm_data[ext] ||
							JSON.stringify(window.previous_vm_data[ext]) !== JSON.stringify(vm_data[ext])) {
							//console.log("Voicemail data changed for extension " + ext);
							dataChanged = true; // Set local flag, window.update_table handled later if needed
						}
					});
				}

				// Store current data for next comparison (deep copy)
				window.previous_vm_data = JSON.parse(JSON.stringify(vm_data));

				// --- Start of logic moved inside success callback ---
				notify = false; // Reset notify flag for this poll cycle

				// Set the global update flag ONLY if data actually changed
				// (The initialVoicemailLoadComplete flag is implicitly handled because this runs after the first load)
				if (dataChanged) {
					window.update_table = true;
					//console.log("Poll detected data change, setting window.update_table = true");
				}

				async.forEachOf(window.vm_data, function (value, extension, callback_inner) {
					var el = $(".grid-stack-item[data-rawname='voicemail'][data-widget_type_id='"+extension+"'] .mailbox");

					// Refresh grid ONLY if update_table flag is true
					if(window.update_table && el.length) {
						if(el.data("inbox") < value){ // Check for new messages specifically for notification
							notify = true;
							//console.log("New messages detected for extension " + extension + ", will notify.");
						}
						el.data("inbox", value); // Update inbox data cache on element

						// Always refresh if auto-refresh is enabled or not explicitly disabled
						if((typeof Cookies.get('vm-refresh-'+extension) === "undefined") || Cookies.get('vm-refresh-'+extension) == 1) {
							//console.log("Auto-refreshing voicemail grid for extension " + extension + " due to update_table=true");
							$(".grid-stack-item[data-rawname='voicemail'][data-widget_type_id='"+extension+"'] .voicemail-grid").bootstrapTable('refresh',{silent: true});
						}
					}
					callback_inner();
				}, function(err) {
					if(err) {
						//console.error("Error refreshing voicemail data within poll", err);
					} else if(notify) {
						var voicemailNotification = new Notify("Voicemail", {
							body: _("You have a new voicemail"),
							icon: "modules/Voicemail/assets/images/mail.png"
						});
						if (UCP.notify) {
							voicemailNotification.show();
						}
					}

					// Only reset update flag after potentially refreshing all extensions
					if (window.update_table) {
						setTimeout(function() {
							window.update_table = false;
							//console.log("Reset update_table flag to false after poll cycle processed updates.");
						}, 500); // Short delay to allow refreshes to initiate
					}
				});
				// --- End of logic moved inside success callback ---
			},
			error: function (xhr, ajaxOptions, thrownError) {
				//console.error('Unable to check extensions', thrownError, xhr);
			}
		});

	},
	displayWidgetSettings: function(widget_id, dashboard_id) {
		var self = this,
				extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");

		/* Settings changes binds */
		$("#widget_settings .widget-settings-content input[type!='checkbox'][id!=vm-refresh]").change(function() {
			$(this).blur(function() {
				self.saveVMSettings(extension);
				$(this).off("blur");
			});
		});
		$("#widget_settings .widget-settings-content input[type='checkbox'][id!=vm-refresh]").change(function() {
			self.saveVMSettings(extension);
		});

		$("#widget_settings .widget-settings-content input[id=vm-refresh]").change(function() {
			Cookies.remove('vm-refresh-'+extension, {path: ''});
			if($(this).is(":checked")) {
				Cookies.set('vm-refresh-'+extension, 1);
			} else {
				Cookies.set('vm-refresh-'+extension, 0);
			}
		});
		if((typeof Cookies.get('vm-refresh-'+extension) === "undefined" && (typeof Cookies.get('vm-refresh-'+extension) === "undefined" || Cookies.get('vm-refresh-'+extension) == 1)) || Cookies.get('vm-refresh-'+extension) == 1) {
			$("#widget_settings .widget-settings-content input[id=vm-refresh]").prop("checked",true);
		} else {
			$("#widget_settings .widget-settings-content input[id=vm-refresh]").prop("checked",false);
		}
		$("#widget_settings .widget-settings-content input[id=vm-refresh]").bootstrapToggle('destroy');
		$("#widget_settings .widget-settings-content input[id=vm-refresh]").bootstrapToggle({
			on: _("Enable"),
			off: _("Disable")
		});
		this.greetingsDisplay(extension);
		this.bindGreetingPlayers(extension);
		$("#widget_settings .vmx-setting").change(function() {
			var name = $(this).attr("name"),
					val = $(this).val();
			if($(this).attr("type") == "checkbox") {
				self.saveVmXSettings(extension, name, $(this).is(":checked"));
			} else {
				self.saveVmXSettings(extension, name, val);
			}

		});
		// <<< Add Download Greeting Listener Start >>>
		$("#widget_settings .widget-settings-content").off("click", ".download-greeting").on("click", ".download-greeting", function(e) {
			e.preventDefault();
			var $button = $(this);
			var greetingType = $button.data('id');
			// We need the extension, which is available in the parent scope of displayWidgetSettings
			var currentExtension = extension; 

			if (!greetingType || !currentExtension) {
				console.error("Missing greeting type or extension for download.");
				// Optionally show a user-friendly error message here
				return;
			}

			// Construct the download URL using the 'stream' command
			var downloadUrl = UCP.ajaxUrl + "?module=voicemail&command=stream"
			                        + "&type=greeting" // Specify type
			                        + "&force_download=1" // Force download
			                        + "&ext=" + encodeURIComponent(currentExtension)
			                        + "&msg=" + encodeURIComponent(greetingType) // Pass greeting name as msg
			                        + "&quietmode=1" // Keep quietmode for direct streaming
			                        + "&t=" + new Date().getTime(); // Cache buster
			
			// Initiate download by navigating the browser
			window.location.href = downloadUrl;
		});
		// <<< Add Download Greeting Listener End >>>
	},
	displayWidget: function(widget_id) {
		var self = this;
		var extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");
		var $widget = $("div[data-id='"+widget_id+"']"); // Cache widget selector
		var $grid = $widget.find(".voicemail-grid"); // Cache grid selector
		var $folderList = $widget.find(".folder-list"); // Cache folder list selector

		var settings = {
			"uniqueid": "voicemail-grid-" + extension,
			"detail": false,
			"striped": true,
			"icons": {
				"paginationSwitchDown": 'fa-long-arrow-down',
				"paginationSwitchUp": 'fa-long-arrow-up',
				"refresh": 'fa-refresh',
				"columns": 'fa-th'
			},
			"showRefresh": true,
			"toolbar": "#" + widget_id + "-toolbar",
			"stateField": "msg_id",
			"sortName": "date",
			"sortOrder": "desc",
			"pagination": true,
			"buttonsAlign": "right",
			"filterControl": true,
			"pageSize": 5,
			"sidePagination": "client",
			"fitColumns": true,
			"autoResize": true,
			"resizable": true,
			"url": UCP.ajaxUrl + "?module=voicemail&command=grid&ext="+extension+"&folder=INBOX",
			"columns": [
				{
					field: 'state',
					checkbox: true,
					visible: false 
				},
				{
					"field": "origtime",
					"sortable": true,
					"title": _("Date/Time"),
					"formatter": this.dateFormatter,
					"searchable": true
				},
				{
					"field": "callerid",
					"sortable": true,
					"title": _("From"),
					"formatter": this.calleridFormatter,
					"searchable": true
				},
				{
					"field": "priority",
					"sortable": true,
					"title": _("Priority"),
					"formatter": this.priorityFormatter,
					"searchable": true
				},
				{
					"field": "duration",
					"sortable": true,
					"title": _("Duration"),
					"formatter": this.durationFormatter,
					"searchable": true
				},
				{
					"field": "playback",
					"sortable": false,
					"title": _("Listen"),
					"formatter": this.playbackFormatter,
					"searchable": false,
					"width": 300
				},
				{
					"field": "controls",
					"sortable": false,
					"title": _("Controls"),
					"formatter": this.controlFormatter,
					"searchable": false
				}
			],
			onRefresh: function() {
			},
			onLoadSuccess: function(data) {
				window.initialVoicemailLoadComplete = true;

				try {
					$(".message-count").text(data.total);
				} catch (e) {
				}
				
				// Initial folder count refresh - only call once
				if (!window.vm_initial_refresh_done) {
					window.vm_initial_refresh_done = {};
				}
				if (!window.vm_initial_refresh_done[extension]) {
					window.vm_initial_refresh_done[extension] = true;
					setTimeout(function() {
						self.refreshFolderCount(extension);
						// Start periodic refresh for new messages and folder updates
						self.startPeriodicRefresh(extension, $widget);
					}, 500);
				}
			},
			onLoadError: function(status, xhr) {
				console.error("Grid load error", status, xhr);
			}
		};
		
		// Initialize the table
		$grid.bootstrapTable(settings);
		
		// Add tooltip to the toggle switch
		setTimeout(function() {
			$grid.find('.toggle').attr('data-toggle', 'tooltip').attr('title', _('Switch between table and card view')).tooltip();
		}, 200);
		
		// Force auto-sizing after table is initialized
		setTimeout(function() {
			$grid.find('table').css({
				'table-layout': 'auto',
				'width': 'auto'
			});
			$grid.find('th, td').css({
				'width': 'auto',
				'white-space': 'nowrap'
			});
		}, 100);
		
		$("div[data-id='"+widget_id+"'] .refresh").click(function() {
			$grid.bootstrapTable('refresh');
			return false;
		});
		
		
		$("div[data-id='"+widget_id+"'] .folder-list a").click(function() {
			$("div[data-id='"+widget_id+"'] .folder-list li").removeClass("active");
			$(this).parent("li").addClass("active");
			$grid.bootstrapTable('refresh',{url: UCP.ajaxUrl + "?module=voicemail&command=grid&ext="+extension+"&folder=" + $(this).data("folder")});
			return false;
		});
		
		// Initialize player after grid data load
		$grid.on('post-body.bs.table', function (e, data) {
			// Removed /* Now Re-enabled */ comment
			var $table = $(this);

			// Get the data currently loaded in the table
			var currentTableData = $table.bootstrapTable('getData');
			if (Array.isArray(currentTableData) && currentTableData.length === 0) {

			} else if (Array.isArray(currentTableData) && currentTableData.length > 0) {

				$table.find('tbody > tr').off('click').on('click', function () {
					// Existing row click logic...
					var row = $table.bootstrapTable('getRowByUniqueId', $(this).data('uniqueid'));

					if (row && row.filename) {
					} else {
					}
				});
				
				// Apply unread styling to rows
				$table.find('tbody > tr').each(function() {
					var rowData = $table.bootstrapTable('getRowByUniqueId', $(this).data('uniqueid'));
					if (rowData && rowData.unread === '1') {
						$(this).addClass('unread');
					}
				});
			} else {
			}
		});

		// Add handler for the refresh button on the voicemail grid
		$("div[data-id='"+widget_id+"'] .voicemail-grid").on("click", ".btn-refresh", function(event) {
			//console.log("Manual refresh button clicked for " + extension);
			event.preventDefault();
			
			// Removed excessive refresh call
			
			// Then refresh the grid
			$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('refresh');
		});
		
		// Set up click handlers
		$("div[data-id='"+widget_id+"'] .voicemail-grid").on("click", "a.listen", function() {
			var id = $(this).data("id");
			UCP.showDialog(_("Listen on Your Handset"),
				_("Enter Your Phone Number or Extension") + ':</label><input type="text" class="form-control" id="VMto">',
				'<button class="btn btn-default" id="listenVM">' + _("Listen") + '</button>',
				function() {
					$("#listenVM").click(function() {
						var recpt = $("#VMto").val();
						self.listenVoicemail(id, extension, recpt);
					});
					$("#VMto").keypress(function(event) {
						if (event.keyCode == 13) {
							var recpt = $("#VMto").val();
							self.listenVoicemail(id, extension, recpt);
						}
					});
				}
			);
		});

		$("div[data-id='"+widget_id+"'] .voicemail-grid").on("post-body.bs.table", function (e) {
			$("div[data-id='"+widget_id+"'] .voicemail-grid a.listen").click(function() {
				var id = $(this).data("id"),
						select = '';
				$.each(self.staticsettings.extensions, function(i,v) {
					select = select + "<option value='"+v+"'>"+v+"</option>";
				});
				UCP.showDialog(_("Listen to Voicemail"),
					_("On") + ':</label><select class="form-control" data-toggle="select" id="VMto">'+select+"</select>",
					'<button class="btn btn-default" id="listenVM">' + _("Listen") + '</button>',
					function() {
						$("#listenVM").click(function() {
							var recpt = $("#VMto").val();
							self.listenVoicemail(id,extension,recpt);
						});
						$("#VMto").keypress(function(event) {
							if (event.keyCode == 13) {
								var recpt = $("#VMto").val();
								self.listenVoicemail(id,extension,recpt);
							}
						});
					}
				);
			});
			$("div[data-id='"+widget_id+"'] .voicemail-grid .clickable").click(function(e) {
				var text = $(this).text();
				if (UCP.validMethod("Contactmanager", "showActionDialog")) {
					UCP.Modules.Contactmanager.showActionDialog("number", text, "phone");
				}
			});
			$("div[data-id='"+widget_id+"'] .voicemail-grid a.forward").click(function() {
				var id = $(this).data("id"),
						select = '';

				$.each(self.staticsettings.mailboxes, function(i,v) {
					select = select + "<option value='"+v+"'>"+v+"</option>";
				});
				UCP.showDialog(_("Forward Voicemail"),
					_("To")+':</label><select class="form-control" id="VMto">'+select+'</select>',
					'<button class="btn btn-default" id="forwardVM">' + _("Forward") + '</button>',
					function() {
						$("#forwardVM").click(function() {
							var recpt = $("#VMto").val();
							self.forwardVoicemail(id,extension,recpt, function(data) {
								if(data.status) {
									UCP.showAlert(sprintf(_("Successfully forwarded voicemail to %s"),recpt));
									$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('uncheckAll');
									UCP.closeDialog();
								}
							});
						});
						$("#VMto").keypress(function(event) {
							if (event.keyCode == 13) {
								var recpt = $("#VMto").val();
								self.forwardVoicemail(id,extension,recpt, function(data) {
									if(data.status) {
										UCP.showAlert(sprintf(_("Successfully forwarded voicemail to %s"),recpt));
										$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('uncheckAll');
										UCP.closeDialog();
									}
								});
							}
						});
					}
				);
			});
			$("div[data-id='"+widget_id+"'] .voicemail-grid a.delete").click(function() {
				var extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");
				var id = $(this).data("id");
				var $row = $(this).closest("tr");
				var rowData = $grid.bootstrapTable('getData').find(function(item) { return item.msg_id == id; });
				var folder = rowData ? rowData.folder : 'INBOX';
				var isImap = rowData ? rowData.imap : false;
				
				UCP.showConfirm(_("Are you sure you wish to delete this voicemail?"),'warning',function() {
					self.deleteVoicemail(id, extension, function(data) {
						if(data.status) {
							$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('remove', {field: "msg_id", values: [String(id)]});
							// Update folder counts - only if not already refreshing
							if (!window.vm_refresh_in_progress) {
								window.vm_refresh_in_progress = {};
							}
							if (!window.vm_refresh_in_progress[extension]) {
								window.vm_refresh_in_progress[extension] = true;
								setTimeout(function() {
									self.refreshFolderCount(extension);
									window.vm_refresh_in_progress[extension] = false;
								}, 1000);
							}
						}
					}, folder, isImap);
				});
			});
			self.bindPlayers(widget_id);
			
			// Initial folder count refresh - only call once
			if (!window.vm_initial_refresh_done) {
				window.vm_initial_refresh_done = {};
			}
			if (!window.vm_initial_refresh_done[extension]) {
				window.vm_initial_refresh_done[extension] = true;
				setTimeout(function() {
					self.refreshFolderCount(extension);
				}, 1000);
			}
			
			// Also refresh on widget load - only call once
			$widget.on('widget-loaded', function() {
				if (!window.vm_initial_refresh_done) {
					window.vm_initial_refresh_done = {};
				}
				if (!window.vm_initial_refresh_done[extension]) {
					window.vm_initial_refresh_done[extension] = true;
					setTimeout(function() {
						self.refreshFolderCount(extension);
					}, 500);
				}
			});
		});
		$("div[data-id='"+widget_id+"'] .voicemail-grid").on("check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table", function () {
			var sel = $("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('getSelections'),
					dis = true;
			if(sel.length) {
				dis = false;
			}
			$("div[data-id='"+widget_id+"'] .delete-selection").prop("disabled",dis);
			$("div[data-id='"+widget_id+"'] .forward-selection").prop("disabled",dis);
			$("div[data-id='"+widget_id+"'] .move-selection").prop("disabled",dis);
		});

		$("div[data-id='"+widget_id+"'] .folder").click(function() {
			$("div[data-id='"+widget_id+"'] .folder").removeClass("active");
			$(this).addClass("active");
			var folder = $(this).data("folder");
			$grid.bootstrapTable('refreshOptions',{
				url: UCP.ajaxUrl+'?module=voicemail&command=grid&folder='+folder+'&ext='+extension
			});
			// Update folder counts after switching
			setTimeout(function() {
				self.refreshFolderCount(extension);
			}, 500);
		});

		$("div[data-id='"+widget_id+"'] .move-selection").click(function() {
			var opts = '',
			    // Fix #3: Determine current folder from the active element in the list, default to INBOX
			    cur = $("div[data-id='"+widget_id+"'] .folder-list .folder.active").data("folder") || "INBOX",
			    sel = $("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('getSelections');

			//console.log("Move dialog opened. Current viewed folder (cur):", cur); // Debugging

			$.each($("div[data-id='"+widget_id+"'] .folder-list .folder"), function(i, v){
				var folder = $(v).data("folder");
				var folderDisplayName = $(v).data("name");

				// Add folder to dropdown if it's not the one currently being viewed (Case-insensitive)
				if(folder && cur && folder.toLowerCase() != cur.toLowerCase()) {
					opts += '<option value="'+folder+'">'+folderDisplayName+'</option>';
				}
			});
			UCP.showDialog(_("Move Voicemail"),
				_("To")+':</label><select class="form-control" data-toggle="select" id="VMmove">'+opts+"</select>",
				'<button class="btn btn-default" id="moveVM"><span id="spin"></span>&nbsp;&nbsp;' + _("Move") + '</button>',
				function() {
					//var total = sel.length; // Not used
					$("#moveVM").click(function() {
						$("#moveVM").prop("disabled",true);
						$("#spin").html('<i class="fa fa-spinner fa-spin"></i>');

						var data = [];
						var current_widget_id = widget_id;

						Object.keys(sel).forEach(function(key) {
							// Fix #2: Get the source folder from the message data itself
							var messageFromFolder = sel[key].folder || 'INBOX'; // Default to INBOX if missing

							if(!sel[key].folder) {
								//console.warn("Warning: Message data missing 'folder' property for msg_id:", sel[key].msg_id, "Defaulting fromFolder to INBOX.");
							}

							data.push({
								msg: sel[key].msg_id,
								folder: $("#VMmove").val(), // Target folder from dropdown
								ext: extension,
								fromFolder: messageFromFolder // Use actual source folder
							});
						});

						self.moveVoicemailBulk(data, extension, function (responseData) {
							$("#spin").html('');
							if (responseData && responseData.status === true) { 

								window.update_table = false; 

								// Grid refresh is now handled inside moveVoicemailBulk success

								if (responseData.moveStatus && responseData.moveStatus.includes(false)) {
									UCP.showAlert(_('Not able to move some of the voicemails.'));
								}
								UCP.closeDialog(); // Close dialog 
							} else {
								$("#moveVM").prop("disabled", false);
								var errorMsg = _("Failed to move messages");
								if (responseData && typeof responseData.message === 'string' && responseData.message.length > 0) {
									errorMsg = responseData.message;
								} else if (responseData && typeof responseData.error === 'string' && responseData.error.length > 0) {
									errorMsg = responseData.error;
								}
								UCP.showAlert(errorMsg);
							}

							// <<< START OF COMMENTED OUT BLOCK >>>
							/* 
							// --- Exit Bulk Mode on Success --- 
							var $widget = $("div.grid-stack-item[data-rawname='voicemail'][data-widget_type_id='" + extension + "']"); 
							if ($widget.length) { 
								var $toggleButton = $widget.find('.bulk-actions-toggle');
								var $bulkGroup = $widget.find('.bulk-actions-group');
								var $grid = $widget.find('.voicemail-grid'); // Still potentially problematic selector here
								
								if ($bulkGroup.is(':visible')) { 
									$grid.bootstrapTable('hideColumn', 'state'); // Error would happen here
									$bulkGroup.hide();
									$grid.bootstrapTable('uncheckAll'); 
									$toggleButton.find('i').removeClass('fa-times').addClass('fa-check-square-o');
									$toggleButton.find('span').text(_('Bulk Actions'));
									$toggleButton.removeClass('btn-warning').addClass('btn-default');
								}
							} else {
								console.warn("Could not find current widget to exit bulk mode automatically.");
							}
							// --- End Exit Bulk Mode --- 
							*/
							// <<< END OF COMMENTED OUT BLOCK >>>

						}); // End callback passed to moveVoicemailBulk
					}); // End #moveVM click handler
					$("#VMmove").keypress(function(event) {
						if (event.keyCode == 13) {
							$("#moveVM").prop("disabled",true);
							async.forEachOf(sel, function (v, i, callback) {
								self.moveVoicemail(v.msg_id, $("#VMmove").val(), extension, v.fromFolder, function(data) {
									if(data.status) {
										$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('remove', {field: "msg_id", values: [String(v.msg_id)]});
									}
									callback();
								});
							}, function(err) {
								if( err ) {
									$("#moveVM").prop("disabled",false);
									UCP.showAlert(err);
								} else {
									UCP.closeDialog();
									self.rebuildVM(extension);
								}
							});
						}
					});
					$(".delete-selection").prop("disabled",true);
					$(".forward-selection").prop("disabled",true);
					$(".move-selection").prop("disabled",true);
				}
			);
		});
		$("div[data-id='" + widget_id + "'] .delete-selection").click(function () {
			$('#modal_confirm_button').attr("data-dismiss", '');
			UCP.showConfirm(_("Are you sure you wish to delete these voicemails?"),'warning',function() {
				var extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");
				var sel = $("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('getSelections');
				var accept = $("#modal_confirm_button").text();
				$("#modal_confirm_button").html('<i class="fa fa-spinner fa-spin"></i>&nbsp;'+ accept);
				setTimeout(function () {
					var data = [];
					Object.keys(sel).forEach(function(key) {
						// Correctly gather data for delete: msg ID, extension, original folder, and imap flag
						data.push({
							msg: sel[key].msg_id,
							folder: sel[key].folder || 'INBOX', // Get original folder from row data, default to INBOX
							ext: extension,
							imap: sel[key].imap === true // Send boolean imap status
						});
					});
					self.deleteVoicemailBulk(data, extension, function (data) {
						if (data.status) {
							self.rebuildVM(extension);
							if (data.deleteStatus.includes(false)) {
								UCP.showAlert('Not able to delete some of the voicemails.');
							}
							setTimeout(function () {
								$("#modal_confirm_button").html(accept);
								$("#confirm_modal").modal('toggle');
							}, 2000);
						} else {
							UCP.showAlert(data.error);
						}
					});
				}, 50);
				$(".delete-selection").prop("disabled",true);
				$(".forward-selection").prop("disabled",true);
				$(".move-selection").prop("disabled",true);
			});
		});
		$("div[data-id='"+widget_id+"'] .forward-selection").click(function() {
			var sel = $("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('getSelections');
			UCP.showDialog(_("Forward Voicemail"),
				_("To")+":</label><input type='text' class='form-control' id='VMto'>",
				'<button class="btn btn-default" id="forwardVM">' + _("Forward") + '</button>',
				function() {
					$("#forwardVM").click(function() {
						setTimeout(function() {
							var recpt = $("#VMto").val();
							$.each(sel, function(i, v){
								self.forwardVoicemail(v.msg_id,extension,recpt, function(data) {
									if(data.status) {
										UCP.showAlert(sprintf(_("Successfully forwarded voicemail to %s"),recpt));
										$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('uncheckAll');
										UCP.closeDialog();
									}
								});
							});
						}, 50);
					});
					$("#VMto").keypress(function(event) {
						if (event.keyCode == 13) {
							var recpt = $("#VMto").val();
							$.each(sel, function(i, v){
								self.forwardVoicemail(v.msg_id,extension,recpt, function(data) {
									if(data.status) {
										UCP.showAlert(sprintf(_("Successfully forwarded voicemail to %s"),recpt));
										$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('uncheckAll');
										UCP.closeDialog();
									}
								});
							});
						}
					});
				}
			);
		});


		$("div[data-id='"+widget_id+"'] .voicemail-grid .clickable").click(function(e) {
			var text = $(this).text();
			if (UCP.validMethod("Contactmanager", "showActionDialog")) {
				UCP.Modules.Contactmanager.showActionDialog("number", text, "phone");
			}
		});

        // 3. Folder Click Handler <<<<< IMPORTANT FOR FOLDER SELECTION
        		$("div[data-id='"+widget_id+"'] .folder-table tr.folder").off('click').on('click', function(e) {
            e.preventDefault(); // Prevent default link behavior
            var clickedRow = $(this);
            var targetFolder = clickedRow.data('folder');
            var containingWidget = clickedRow.closest('.grid-stack-item');
            var extension = containingWidget.data("widget_type_id"); // Get extension from parent widget

            if (!extension) {
                //console.error("Could not determine extension for folder click.");
                return;
            }

            // Removes 'active' from all folder rows within THIS widget's folder table
            containingWidget.find('.folder-table tr.folder').removeClass("active");
            // Adds 'active' to the clicked folder row
            clickedRow.addClass("active");
            
            // Log which folder was clicked
            //console.log("Folder '" + targetFolder + "' clicked for extension '" + extension + "'. Refreshing grid...");
            
            // Explicitly remove old rows before refreshing
            //console.log("Calling removeAll() before refresh for folder: " + targetFolder);
            containingWidget.find('.table-voicemail').bootstrapTable('removeAll');
            
            // Refreshes the Bootstrap Table with a new URL for the clicked folder
            // Target the specific table WITHIN the widget
			// Use 'refresh' with the new url and explicit success/error handlers
            containingWidget.find('.table-voicemail').bootstrapTable('refresh',{
                url: UCP.ajaxUrl + "?module=voicemail&command=grid&ext="+extension+"&folder=" + targetFolder,
				silent: false // Explicitly ensure UI update
            });
            
                            // Refresh folder counts after folder change - only if not already refreshing
                if (!window.vm_refresh_in_progress) {
                    window.vm_refresh_in_progress = {};
                }
                if (!window.vm_refresh_in_progress[extension]) {
                    window.vm_refresh_in_progress[extension] = true;
                    setTimeout(function() {
                        self.refreshFolderCount(extension);
                        window.vm_refresh_in_progress[extension] = false;
                    }, 500);
                }
        });

		// 4. Create Folder Button Handler
		$("div[data-id='"+widget_id+"'] .create-folder-btn").off('click').on('click', function(e) {
			e.preventDefault();
			var $button = $(this);
			var extension = $button.data('extension');
			var containingWidget = $button.closest('.grid-stack-item');
			
			if (!extension) {
				console.error("Could not determine extension for create folder button.");
				return;
			}
			
			// Show folder creation dialog
			self.showCreateFolderDialog(extension, containingWidget);
		});

		// 5. Refresh Folders Button Handler
		$("div[data-id='"+widget_id+"'] .refresh-folders-btn").off('click').on('click', function(e) {
			e.preventDefault();
			var $button = $(this);
			var extension = $button.data('extension');
			var containingWidget = $button.closest('.grid-stack-item');
			
			if (!extension) {
				console.error("Could not determine extension for refresh folders button.");
				return;
			}
			
			// Add loading state to button
			var $icon = $button.find('i');
			var originalIcon = $icon.attr('class');
			$icon.attr('class', 'fa fa-spinner fa-spin');
			$button.prop('disabled', true);
			
			// Refresh folder list
			self.refreshFolderList(extension, containingWidget);
			
			// Restore button state after a short delay
			setTimeout(function() {
				$icon.attr('class', originalIcon);
				$button.prop('disabled', false);
			}, 1000);
		});

		// 6. Delete Folder Button Handler
		$("div[data-id='"+widget_id+"'] .delete-folder-btn").off('click').on('click', function(e) {
			e.preventDefault();
			e.stopPropagation(); // Prevent folder selection
			
			var $button = $(this);
			var extension = $button.data('extension');
			var folderName = $button.data('folder-name');
			var folderKey = $button.data('folder');
			var containingWidget = $button.closest('.grid-stack-item');
			
			if (!extension || !folderName) {
				console.error("Could not determine extension or folder name for delete button.");
				return;
			}
			
			// Show Bootstrap modal confirmation dialog
			self.showDeleteFolderDialog(extension, folderName, folderKey, containingWidget, $button);
		});

		// --- DRAG AND DROP IMPLEMENTATION --- 

		// 1. Make table rows draggable after table body is rendered
		$grid.on('post-body.bs.table', function (e, data) {
			$grid.find('tbody tr').attr('draggable', 'true');
			// Add cursor style for visual feedback
			$grid.find('tbody tr[draggable="true"]').css('cursor', 'move'); 
			// Re-initialize tooltips after table loads/refreshes
            $grid.find('[data-toggle="tooltip"]').tooltip();
		});

		// 2. Drag Start handler (delegated on the table body)
		$grid.on('dragstart', 'tbody tr', function(e) {
			var $row = $(this);
			var uniqueId = $row.data('uniqueid');
			var rowData = $grid.bootstrapTable('getRowByUniqueId', uniqueId);

			if (!rowData) {
				console.error("Could not get row data for dragstart");
				e.preventDefault(); // Prevent drag if data is missing
				return;
			}

			var dragData = JSON.stringify({
				msgId: rowData.msg_id,
				fromFolder: rowData.folder || 'INBOX' // Get source folder from row data
			});

			e.originalEvent.dataTransfer.setData('application/json', dragData);
			e.originalEvent.dataTransfer.effectAllowed = 'move';
			$row.addClass('dragging'); // Add visual cue
		});

		// 3. Drag End handler (delegated on the table body - for cleanup)
		$grid.on('dragend', 'tbody tr', function(e) {
			$(this).removeClass('dragging');
		});

		// 4. Drag Over handler (delegated on folder list)
		$folderList.on('dragover', 'tr.folder', function(e) {
			e.preventDefault(); // Necessary to allow dropping
			e.originalEvent.dataTransfer.dropEffect = 'move';
			// Remove highlight from all folders first to prevent stuck highlights
			$folderList.find('tr.folder').removeClass('drag-over');
			$(this).addClass('drag-over'); // Add visual cue
		});

		// 5. Drag Leave handler (delegated on folder list)
		$folderList.on('dragleave', 'tr.folder', function(e) {
			$(this).removeClass('drag-over'); // Remove visual cue
		});

		// 6. Drop handler (delegated on folder list)
		$folderList.on('drop', 'tr.folder', function(e) {
			e.preventDefault();
			$folderList.find('tr.folder').removeClass('drag-over'); // Remove from all, just in case
			var $targetFolderDiv = $(this);
			var toFolder = $targetFolderDiv.data('folder');

			try {
				var dragDataString = e.originalEvent.dataTransfer.getData('application/json');
				if (!dragDataString) {
					console.error("Drop event: No drag data found.");
					return;
				}
				var dragData = JSON.parse(dragDataString);
				var msgId = dragData.msgId;
				var fromFolder = dragData.fromFolder;

				// Prevent dropping onto the same folder
				if (fromFolder.toLowerCase() === toFolder.toLowerCase()) {
					console.log("Cannot drop message onto the same folder.");
					return;
				}

				if (!msgId || !fromFolder || !toFolder || !extension) {
					console.error("Drop Error: Missing data for move operation.", {msgId, fromFolder, toFolder, extension});
					UCP.showAlert(_("Error performing move operation."));
					return;
				}

				console.log(`Attempting to move msg ${msgId} from ${fromFolder} to ${toFolder} for ext ${extension}`);

				// Call the existing single move function (now updated)
				self.moveVoicemail(msgId, toFolder, extension, fromFolder, function(response) {
					if (response && response.status === true) {
						console.log("Move successful for msg:", msgId);
						// Remove the row from the current view visually
						$grid.bootstrapTable('remove', { field: 'msg_id', values: [msgId] });
						// Refresh folder counts to update sidebar badges
						self.refreshFolderCount(extension);
						UCP.showAlert(sprintf(_("Moved message to %s"), toFolder), 'success');
					} else {
						console.error("Move failed for msg:", msgId, response);
						UCP.showAlert(_("Failed to move message.") + (response && response.message ? ' Error: '+response.message : ''), 'danger');
					}
				});

			} catch (err) {
				console.error("Error processing drop event:", err);
				UCP.showAlert(_("Error processing drop."));
			}
		});

		// --- END DRAG AND DROP --- 

		// --- Bulk Actions Toggle Logic ---
		$widget.find('.bulk-actions-toggle').off('click').on('click', function() {
			var $toggleButton = $(this);
			var $bulkGroup = $widget.find('.bulk-actions-group');
			var $grid = $widget.find('.voicemail-grid');
			var isBulkMode = $bulkGroup.is(':visible');

			if (isBulkMode) {
				// Exit bulk mode
				$grid.bootstrapTable('hideColumn', 'state');
				$bulkGroup.hide();
				$grid.bootstrapTable('uncheckAll');
				// Restore button text/icon
				$toggleButton.find('i').removeClass('fa-times').addClass('fa-check-square-o');
				$toggleButton.find('span').text(_('Bulk Actions'));
				$toggleButton.removeClass('btn-warning').addClass('btn-default'); // Change class back if needed
			} else {
				// Enter bulk mode
				$grid.bootstrapTable('showColumn', 'state');
				$bulkGroup.show();
				// Change button text/icon to Cancel
				$toggleButton.find('i').removeClass('fa-check-square-o').addClass('fa-times');
				$toggleButton.find('span').text(_('Cancel Bulk'));
				$toggleButton.removeClass('btn-default').addClass('btn-warning'); // Optional: change color
			}
		});
		// --- END Bulk Actions Toggle Logic ---

		// --- Handler for Mark Read/Unread Buttons ---
		$("div[data-id='"+widget_id+"'] .voicemail-grid").on("click", "a.read, a.unread", function(e) {
			e.preventDefault();
			var $button = $(this);
			var msgId = $button.data("id");
			var $row = $button.closest("tr"); // Get the table row
			// Retrieve row data which should include folder and imap status
			var rowData = $grid.bootstrapTable('getRowByUniqueId', msgId);

			if (!rowData) {
				console.error("Could not get row data for mark read/unread click.");
				return;
			}

			var currentExt = extension; // Use extension from displayWidget scope
			var folder = rowData.folder || 'INBOX';
			var isImap = rowData.imap === true;
			var markRead = $button.hasClass('read'); // true if .read was clicked, false if .unread

			self.markAsRead(msgId, currentExt, folder, isImap, markRead);
			// Force grid refresh to update read status display
			setTimeout(function() {
				$grid.bootstrapTable('refresh');
			}, 100);
		});

		self.bindPlayers(widget_id);
	},
	greetingsDisplay: function(extension) {
		var self = this;
		$("#widget_settings .recording-controls .save").click(function() {
			var id = $(this).data("id");
			self.saveRecording(extension,id);
		});
		$("#widget_settings .recording-controls .delete").click(function() {
			var id = $(this).data("id");
			self.deleteRecording(extension,id);
		});
		$("#widget_settings .file-controls .record, .jp-record").click(function() {
			var id = $(this).data("id");
			self.recordGreeting(extension,id);
		});
		$("#widget_settings .file-controls .delete").click(function() {
			var id = $(this).data("id");
			self.deleteGreeting(extension,id);
		});
		$("#widget_settings .filedrop").on("dragover", function(event) {
			if (event.preventDefault) {
				event.preventDefault(); // Necessary. Allows us to drop.
			}
			$(this).addClass("hover");
		});
		$("#widget_settings .filedrop").on("dragleave", function(event) {
			$(this).removeClass("hover");
		});

		$("#widget_settings .greeting-control .jp-audio-freepbx").on("dragstart", function(event) {
			event.originalEvent.dataTransfer.effectAllowed = "move";
			event.originalEvent.dataTransfer.setData("type", $(this).data("type"));
			$(this).fadeTo( "fast", 0.5);
		});
		$("#widget_settings .greeting-control .jp-audio-freepbx").on("dragend", function(event) {
			$(this).fadeTo( "fast", 1.0);
		});
		$("#widget_settings .filedrop").on("drop", function(event) {
			if (event.originalEvent.dataTransfer.files.length === 0) {
				if (event.stopPropagation) {
					event.stopPropagation(); // Stops some browsers from redirecting.
				}
				if (event.preventDefault) {
					event.preventDefault(); // Necessary. Allows us to drop.
				}
				$(this).removeClass("hover");
				var target = $(this).data("type"),
				source = event.originalEvent.dataTransfer.getData("type");
				if (source === "") {
					alert(_("Not a valid Draggable Object"));
					return false;
				}
				if (source == target) {
					alert(_("Dragging to yourself is not allowed"));
					return false;
				}
				var data = { ext: extension, source: source, target: target },
				message = $(this).find(".message");
				message.text(_("Copying..."));
				$.post( UCP.ajaxUrl + "?module=voicemail&command=copy", data, function( data ) {
						if (data.status) {
							$("#"+target+" .filedrop .pbar").css("width", "0%");
							$("#"+target+" .filedrop .message").text($("#"+target+" .filedrop .message").data("message"));
							$("#freepbx_player_" + target).removeClass("greet-hidden");
							self.toggleGreeting(target, true);
						} else {
							return false;
						}
				});
			}
		});
		$("#widget_settings .greeting-control").each(function() {
			var id = $(this).attr("id");
			$("#"+id+" input[type=\"file\"]").fileupload({
				url: UCP.ajaxUrl + "?module=voicemail&command=upload&type="+id+"&ext=" + extension,
				dropZone: $("#"+id+" .filedrop"),
				dataType: "json",
				add: function(e, data) {
					//TODO: Need to check all supported formats
					var sup = "\\.("+self.staticsettings.supportedRegExp+")$",
							patt = new RegExp(sup),
							submit = true;
					$.each(data.files, function(k, v) {
						if(!patt.test(v.name)) {
							submit = false;
							UCP.showAlert(_("Unsupported file type"));
							return false;
						}
					});
					if(submit) {
						$("#"+id+" .filedrop .message").text(_("Uploading..."));
						data.submit();
					}
				},
				done: function(e, data) {
					if (data.result.status) {
						$("#"+id+" .filedrop .pbar").css("width", "0%");
						$("#"+id+" .filedrop .message").text($("#"+id+" .filedrop .message").data("message"));
						$("#freepbx_player_"+id).removeClass("greet-hidden");
						self.toggleGreeting(id, true);
					} else {
						//console.warn(data.result.message);
					}
				},
				progressall: function(e, data) {
					var progress = parseInt(data.loaded / data.total * 100, 10);
					$("#"+id+" .filedrop .pbar").css("width", progress + "%");
				},
				drop: function(e, data) {
					$("#"+id+" .filedrop").removeClass("hover");
				}
			});
		});
		//If browser doesnt support get user media requests then just hide it from the display
		if (!Modernizr.getusermedia) {
			$("#widget_settings .jp-record-wrapper").hide();
			$("#widget_settings .record-greeting-btn").hide();
		} else {
			$("#widget_settings .jp-record-wrapper").show();
			$("#widget_settings .jp-stop-wrapper").hide();
			$("#widget_settings .record-greeting-btn").show();
		}
	},
	//Delete a voicemail greeting
	deleteGreeting: function(extension,type) {
		var self = this, data = { msg: type, ext: extension };
		$.post( UCP.ajaxUrl + "?module=voicemail&command=delete", data, function( data ) {
			if (data.status) {
				$("#freepbx_player_" + type).jPlayer( "clearMedia" );
				self.toggleGreeting(type, false);
			} else {
				return false;
			}
		});
	},
	refreshFolderCount: function(extension) {
		console.log("Attempting to refresh folder counts for extension:", extension);
		var self = this;
		
		// Clear any existing refresh interval for this extension
		if (window.vm_refresh_intervals && window.vm_refresh_intervals[extension]) {
			clearInterval(window.vm_refresh_intervals[extension]);
		}

		$.ajax({
			type: "POST",
			url: UCP.ajaxUrl,
			data: {
				module: "voicemail",
				command: "refreshfoldercount",
				ext: extension
			},
			dataType: 'json',
			async: true, // Change to asynchronous
			success: function (response) {
				console.log("Successfully fetched folder counts:", response);
				if (response && response.status === true && response.folders) {
					// Update cache first
					if (!window.vm_data) {
						window.vm_data = {};
					}
					if (!window.vm_data[extension]) {
						window.vm_data[extension] = {};
					}
					window.vm_data[extension].folders = response.folders;

					// Update DOM inside the success callback
					var folderData = window.vm_data[extension].folders;
					if (folderData) {
						$.each(folderData, function(folderName, folderInfo) {
							// Revert to the original selector structure using data-name
							var folderElement = $(".grid-stack-item[data-rawname='voicemail'][data-widget_type_id='"+extension+"'] .mailbox .folder-list .folder[data-name='"+folderName+"']");

							if (folderElement.length) {
								// Update the three badges
								var totalBadge = folderElement.find('.badge-total');
								var unreadBadge = folderElement.find('.badge-unread');
								var highBadge = folderElement.find('.badge-high');
								
								// Update badge text
								totalBadge.text(folderInfo.count || 0);
								unreadBadge.text(folderInfo.unread || 0);
								highBadge.text(folderInfo.high_priority || 0);
								
								// Always keep badge colors, even when count is 0
								totalBadge.removeClass('badge-empty').addClass('badge-total');
								unreadBadge.removeClass('badge-empty').addClass('badge-unread');
								highBadge.removeClass('badge-empty').addClass('badge-high');
							} else {
								//console.warn("Could not find folder element for '" + folderName + "' using data-name.");
							}
						});
					} else {
						console.warn("No folder data found in cache after successful refresh for extension:", extension);
					}
				} else {
					console.error("Failed to refresh folder counts or invalid response format:", response);
				}
				
				// Set up periodic refresh every 30 seconds (ONLY ONCE)
				if (!window.vm_refresh_intervals) {
					window.vm_refresh_intervals = {};
				}
				if (!window.vm_refresh_intervals[extension]) {
					window.vm_refresh_intervals[extension] = setInterval(function() {
						self.refreshFolderCount(extension);
					}, 30000); // Refresh every 30 seconds
				}
			},
			error: function (jqXHR, textStatus, errorThrown) {
			}
		});
	},
	
	rebuildVM: function(extension){
		var data = {
			ext: extension
		},
		self = this;
		$.ajax({
			type: "POST",
			url: UCP.ajaxUrl + "?module=voicemail&command=rebuildVM",
			data: data,
					success: function(data) {
			// Only refresh if not already refreshing
			if (!window.vm_refresh_in_progress) {
				window.vm_refresh_in_progress = {};
			}
			if (!window.vm_refresh_in_progress[extension]) {
				window.vm_refresh_in_progress[extension] = true;
				setTimeout(function() {
					self.refreshFolderCount(extension);
					window.vm_refresh_in_progress[extension] = false;
				}, 1000);
			}
			if(typeof callback === "function") {
				callback(data);
			}	
		},
			error: function(data) {
				if(typeof callback === "function") {
					callback({status: false});
				}
			}
		});
	},
	moveVoicemail: function(msgid, folder, extension, fromFolder, callback) { // Added fromFolder parameter
		var data = {
			msg: msgid,
			folder: folder, // Target folder
			ext: extension,
			fromFolder: fromFolder // Source folder (ADDED)
		},
		self = this;
		$.ajax({
			type: "POST",
			url: UCP.ajaxUrl + "?module=voicemail&command=movetofolder", // Changed command to lowercase
			data: data,
			async: true,
			
			success: function(data) {
				// self.refreshFolderCount(extension); // Refresh counts AFTER success
				if(typeof callback === "function") {
					callback(data); // Pass response to callback
				}
			},
			error: function(jqXHR, textStatus, errorThrown) { // Changed data parameter to standard jqXHR etc.
				console.error("AJAX error moving voicemail:", textStatus, errorThrown);
				if(typeof callback === "function") {
					callback({status: false, message: _("AJAX Error:") + " " + textStatus}); // Pass error status back
				}
			}
		});
	},
	moveVoicemailBulk: function (data, extension, callback) {
		var formData = new FormData();
		formData.append('data', JSON.stringify(data));
		var self = this; // Ensure 'this' refers to the VoicemailC instance correctly
		$.ajax({
			type: "POST",
			enctype: 'multipart/form-data',
			url: UCP.ajaxUrl + "?module=voicemail&command=moveToFolderBulk",
			data: formData,
			async: true, // **** Changed from false to true ****
			processData: false,
			contentType: false,
			success: function (responseData) { // Use a different variable name than the outer 'data'
				// ADD CONSOLE LOG START
				console.log("MoveVoicemailBulk Success callback entered for extension:", extension); 
				// ADD CONSOLE LOG END
				var $widget = $("div.grid-stack-item[data-rawname='voicemail'][data-widget_type_id='" + extension + "']");
				// ADD CONSOLE LOG START
				console.log("Widget found?", $widget.length > 0, $widget); 
				// ADD CONSOLE LOG END

				if ($widget.length) { // Ensure widget was found
					var $toggleButton = $widget.find('.bulk-actions-toggle');
					var $bulkGroup = $widget.find('.bulk-actions-group');
					var $grid = $widget.find('.voicemail-grid').first(); // Find the grid within the specific widget
					// ADD CONSOLE LOG START
					console.log("Grid found within widget using '.voicemail-grid'.first()?", $grid.length > 0, $grid); 
					// ADD CONSOLE LOG END

					// --- Exit Bulk Mode ---
					if ($bulkGroup.is(':visible')) {
						// ADD CHECK START
						if ($grid.length && typeof $grid.bootstrapTable === 'function') { 
						// ADD CHECK END
							 console.log("Exiting bulk mode: Hiding state column, hiding group, unchecking all.");
							 // ADD TRY...CATCH START
							 try {
								$grid.bootstrapTable('hideColumn', 'state'); // Uses $grid
								$grid.bootstrapTable('uncheckAll'); // Uses $grid
							 // ADD TRY...CATCH END
							 } catch (e) {
								console.error("Error during bootstrapTable calls in bulk mode exit:", e);
							 }
						// ADD ELSE START
						} else {
							console.warn("Grid not found or bootstrapTable function missing when trying to exit bulk mode.");
						}
						// ADD ELSE END
						$bulkGroup.hide(); // Hide group even if grid ops fail
						// Reset toggle button appearance
						$toggleButton.find('i').removeClass('fa-times').addClass('fa-check-square-o');
						$toggleButton.find('span').text(_('Bulk Actions'));
						$toggleButton.removeClass('btn-warning').addClass('btn-default');
					}
                    
                    // --- Refresh Grid ---
                    // Keep outer check
					if ($grid.length && typeof $grid.bootstrapTable === 'function') {
						
						 console.log("Attempting to refresh bootstrapTable for extension:", extension);
						 
						 // <<< START setTimeout WRAPPER >>>
						 setTimeout(function() {
							 // try...catch is now inside setTimeout
							 try {
								$grid.bootstrapTable('refresh'); 
								console.log("BootstrapTable refresh called successfully (after delay).");
							 } catch (e) {
								// This is where the original error likely happens
								console.error("Error calling bootstrapTable('refresh') (after delay):", e);
							 }
						 }, 150); // 150ms delay
						 // <<< END setTimeout WRAPPER >>>

					} else {
						// Outer else remains
						console.error("Grid element not found (using '.voicemail-grid'.first()) or bootstrapTable function is not available. Cannot refresh.", $grid);
					}
					// End of outer else

				} else { // This else corresponds to if ($widget.length)
					// Use extension in the warning message
					console.warn("Could not find widget for extension " + extension + ". Cannot exit bulk mode or refresh grid.");
				}
				// --- End Exit Bulk Mode / Refresh ---

				if (typeof callback === "function") {
					callback(responseData);
				}
			}, // End success function
			error: function (jqXHR, textStatus, errorThrown) { // Use standard error arguments
				//console.error("Error in moveVoicemailBulk AJAX:", textStatus, errorThrown); // Add logging
				if (typeof callback === "function") {
					// Pass a more structured error object back
					callback({ status: false, error: "AJAX Error: " + textStatus + " - " + errorThrown });
				}
			}
		});
	},
	forwardVoicemail: function(msgid, extension, recpt, callback) {
		var data = {
			id: msgid,
			to: recpt
		};
		$.post( UCP.ajaxUrl + "?module=voicemail&command=forward&ext="+extension, data, function(data) {
			if(typeof callback === "function") {
				callback(data);
			}
		}).fail(function() {
			if(typeof callback === "function") {
				callback({status: false});
			}
		});
	},
	//Used to delete a voicemail message
	deleteVoicemail: function(msgid, extension, callback, folder, isImap) {
		console.log("deleteVoicemail called with:", { msgid, extension, folder, isImap });
		var data = {
			msg: msgid,
			ext: extension,
			folder: folder || 'INBOX',
			imap: isImap ? 'true' : 'false'
		},
		self = this;
		$.ajax({
			type: "POST",
			url: UCP.ajaxUrl + "?module=voicemail&command=delete",
			data: data,
			async: true,
			success: function(data) {
				console.log("deleteVoicemail success:", data);
				self.refreshFolderCount(extension);
				if(typeof callback === "function") {
					callback(data);
				}	
			},
			error: function(data) {
				console.error("deleteVoicemail error:", data);
				if(typeof callback === "function") {
					callback({status: false});
				}
			}
		});
	},
	deleteVoicemailBulk: function (data, extension, callback) {
		var formData = new FormData();
		formData.append('data', JSON.stringify(data));
		var self = this; // Define self for use in the incorporated code.

		$.ajax({
			type: "POST",
			enctype: 'multipart/form-data',
			url: UCP.ajaxUrl + "?module=voicemail&command=deleteBulk",
			data: formData,
			async: true, // Changed to async for better performance
			processData: false,
			contentType: false,
			success: function (responseData) { // Renamed 'data' to 'responseData' to avoid conflict
				self.refreshFolderCount(extension);
				// --- Exit Bulk Mode on Success ---
				// Find the current widget (assuming UCP.currentWidgetId is set, adjust if needed)
				var $widget = $("div.grid-stack-item[data-rawname='voicemail'][data-widget_type_id='" + extension + "']");
				if ($widget.length) { // Ensure widget was found
					var $toggleButton = $widget.find('.bulk-actions-toggle');
					var $bulkGroup = $widget.find('.bulk-actions-group');
					var $grid = $widget.find('.voicemail-grid');

					// Check if currently in bulk mode before trying to exit
					if ($bulkGroup.is(':visible')) {
						$grid.bootstrapTable('hideColumn', 'state');
						$bulkGroup.hide();
						$grid.bootstrapTable('uncheckAll');
						// Reset the toggle button appearance
						$toggleButton.find('i').removeClass('fa-times').addClass('fa-check-square-o');
						$toggleButton.find('span').text(_('Bulk Actions'));
						$toggleButton.removeClass('btn-warning').addClass('btn-default');
					}
				} else {
					console.warn("Could not find current widget to exit bulk mode automatically after delete.");
				}

				if (typeof callback === "function") {
					// Use responseData from the AJAX call
					callback(responseData);
				}
			},
			error: function (jqXHR, textStatus, errorThrown) { // Use standard error arguments
				//console.error("Error in deleteVoicemailBulk AJAX:", textStatus, errorThrown); // Add logging
				if (typeof callback === "function") {
					// Pass a more structured error object back
					callback({ status: false, error: "AJAX Error: " + textStatus + " - " + errorThrown });
				}
			}
		});
	},
	//Toggle the html5 player for greeting
	toggleGreeting: function(type, visible) {
		if (visible === true) {
			$("#" + type + " button.delete").show();
			$("#jp_container_" + type).removeClass("greet-hidden");
			$("#freepbx_player_"+ type).jPlayer( "clearMedia" );
		} else {
			$("#" + type + " button.delete").hide();
			$("#jp_container_" + type).addClass("greet-hidden");
		}
	},
	//Save Voicemail Settings
	saveVMSettings: function(extension) {
		$("#message").fadeOut("slow");
		var data = { ext: extension };
		$("div[data-rawname='voicemail'] .widget-settings-content input[type!='checkbox']").each(function( index ) {
			data[$( this ).attr("name")] = $( this ).val();
		});
		$("div[data-rawname='voicemail'] .widget-settings-content input[type='checkbox']").each(function( index ) {
			data[$( this ).attr("name")] = $( this ).is(":checked");
		});
		$.post( UCP.ajaxUrl + "?module=voicemail&command=savesettings", data, function( data ) {
			if (data.status) {
				$("#message").addClass("alert-success");
				$("#message").text(_("Your settings have been saved"));
				$("#message").fadeIn( "slow", function() {
					setTimeout(function() { $("#message").fadeOut("slow"); }, 2000);
				});
			} else {
				$("#message").addClass("alert-error");
				$("#message").text(data.message);
				return false;
			}
		});
	},
	recordGreeting: function(extension,type) {
		var self = this;
		if (!Modernizr.getusermedia) {
			UCP.showAlert(_("Direct Media Recording is Unsupported in your Broswer!"));
			return false;
		}
		counter = $("#jp_container_" + type + " .jp-current-time");
		title = $("#jp_container_" + type + " .title-text");
		filec = $("#" + type + " .file-controls");
		recc = $("#" + type + " .recording-controls");
		var controls = $("#jp_container_" + type + " .jp-controls");
		controls.toggleClass("recording");
		if (self.recording) {
			clearInterval(self.recordTimer);
			title.text(_("Recorded Message"));
			self.recorder.stop();
			self.recorder.exportWAV(function(blob) {
				self.soundBlobs[type] = blob;
				var url = (window.URL || window.webkitURL).createObjectURL(blob);
				$("#freepbx_player_" + type).jPlayer( "clearMedia" );
				$("#freepbx_player_" + type).jPlayer( "setMedia", {
					wav: url
				});
			});
			self.recording = false;
			recc.show();
			filec.hide();
		} else {
			window.AudioContext = window.AudioContext || window.webkitAudioContext;

			var context = new AudioContext();

			var gUM = Modernizr.prefixed("getUserMedia", navigator);
			gUM({ audio: true }, function(stream) {
				var mediaStreamSource = context.createMediaStreamSource(stream);
				self.recorder = new Recorder(mediaStreamSource,{ workerPath: "assets/js/recorderWorker.js" });
				self.recorder.record();
				self.startTime = new Date();
				self.recordTimer = setInterval(function () {
					var mil = (new Date() - self.startTime);
					var temp = (mil / 1000);
					var min = ("0" + Math.floor((temp %= 3600) / 60)).slice(-2);
					var sec = ("0" + Math.round(temp % 60)).slice(-2);
					counter.text(min + ":" + sec);
				}, 1000);
				title.text(_("Recording..."));
				self.recording = true;
				$("#jp_container_" + type).removeClass("greet-hidden");
				recc.hide();
				filec.show();
			}, function(e) {
				UCP.showAlert(_("Your Browser Blocked The Recording, Please check your settings"));
				self.recording = false;
			});
		}
	},
	saveRecording: function(extension,type) {
		var self = this,
				filec = $("#" + type + " .file-controls"),
				recc = $("#" + type + " .recording-controls");
				title = $("#" + type + " .title-text");
		if (self.recording) {
			UCP.showAlert(_("Stop the Recording First before trying to save"));
			return false;
		}
		if ((typeof(self.soundBlobs[type]) !== "undefined") && self.soundBlobs[type] !== null) {
			$("#" + type + " .filedrop .message").text(_("Uploading..."));
			var data = new FormData();
			data.append("file", self.soundBlobs[type]);
			$.ajax({
				type: "POST",
				url: UCP.ajaxUrl + "?module=voicemail&command=record&type=" + type + "&ext=" + extension,
				xhr: function()
				{
					var xhr = new window.XMLHttpRequest();
					//Upload progress
					xhr.upload.addEventListener("progress", function(evt) {
						if (evt.lengthComputable) {
							var percentComplete = evt.loaded / evt.total,
							progress = Math.round(percentComplete * 100);
							$("#" + type + " .filedrop .pbar").css("width", progress + "%");
						}
					}, false);
					return xhr;
				},
				data: data,
				processData: false,
				contentType: false,
				success: function(data) {
					$("#" + type + " .filedrop .message").text($("#" + type + " .filedrop .message").data("message"));
					$("#" + type + " .filedrop .pbar").css("width", "0%");
					self.soundBlobs[type] = null;
					$("#freepbx_player_" + type).jPlayer("supplied",self.staticsettings.supportedHTML5);
					$("#freepbx_player_" + type).jPlayer( "clearMedia" );
					title.text(title.data("title"));
					filec.show();
					recc.hide();
				},
				error: function() {
					//error
					filec.show();
					recc.hide();
				}
			});
		}
	},
	deleteRecording: function(extension,type) {
		var self = this,
				filec = $("#" + type + " .file-controls"),
				recc = $("#" + type + " .recording-controls");
		if (self.recording) {
			UCP.showAlert(_("Stop the Recording First before trying to delete"));
			return false;
		}
		if ((typeof(self.soundBlobs[type]) !== "undefined") && self.soundBlobs[type] !== null) {
			self.soundBlobs[type] = null;
			$("#freepbx_player_" + type).jPlayer("supplied",self.staticsettings.supportedHTML5);
			$("#freepbx_player_" + type).jPlayer( "clearMedia" );
			title.text(title.data("title"));
			filec.show();
			recc.hide();
			self.toggleGreeting(type, false);
		} else {
			UCP.showAlert(_("There is nothing to delete"));
		}
	},
	//This function is here solely because firefox caches media downloads so we have to force it to not do that
	generateRandom: function() {
		return Math.round(new Date().getTime() / 1000);
	},
	dateFormatter: function(value, row, index) {
		// Pass the Unix timestamp (row.origtime) to the formatter, not the pre-formatted string (value)
		return UCP.dateTimeFormatter(row.origtime);
	},
	calleridFormatter: function(value, row, index) {
		console.log("calleridFormatter raw value:", value);
		// Just strip all HTML and return the text content
		return value.replace(/<[^>]*>/g, '');
	},
	priorityFormatter: function(value, row, index) {
		console.log("priorityFormatter called with:", { value, row, index });
		// Format priority - show as badge
		if (value === 'high' || value === 'urgent') {
			return '<span class="label label-danger">High</span>';
		} else if (value === 'normal') {
			return '<span class="label label-default">Normal</span>';
		} else {
			return '<span class="label label-default">Normal</span>';
		}
	},

	listenVoicemail: function(msgid, extension, recpt) {
		var data = {
			id: msgid,
			to: recpt
		};
		$.post( UCP.ajaxUrl + "?module=voicemail&command=callme&ext="+extension, data, function( data ) {
			UCP.closeDialog();
		});
	},
	playbackFormatter: function (value, row, index) {
		var settings = UCP.Modules.Voicemail.staticsettings,
			rand = Math.floor(Math.random() * 10000);
		if(settings.showPlayback == "0" || row.duration === 0) {
			return '';
		}
		
		// Get the current extension using multiple fallbacks
		var extension;
		
		// 1. Try row.ext if available
		if (row.ext && row.ext !== 'undefined') {
			extension = row.ext;
			//console.log("Using row.ext:", extension);
		} 
		// 2. Try to get from active widget
		else if ($(".grid-stack-item.active[data-rawname='voicemail']").length) {
			extension = $(".grid-stack-item.active[data-rawname='voicemail']").data("widget_type_id");
			//console.log("Using active widget extension:", extension);
		}
		// 3. Try to get from active folder's parent
		else if ($(".folder.active").length && $(".folder.active").parents(".grid-stack-item").length) {
			extension = $(".folder.active").parents(".grid-stack-item").data("widget_type_id");
			//console.log("Using folder parent extension:", extension);
		}
		// 4. Try to get from any voicemail grid-stack-item
		else if ($(".grid-stack-item[data-rawname='voicemail']").length) {
			extension = $(".grid-stack-item[data-rawname='voicemail']").first().data("widget_type_id");
			//console.log("Using first voicemail widget extension:", extension);
		}
		// 5. Try to get from table URL
		else if ($(".voicemail-grid").length) {
			var url = $(".voicemail-grid").data("url") || "";
			var match = url.match(/ext=([^&]+)/);
			if (match && match[1]) {
				extension = match[1];
				//console.log("Using URL extension:", extension);
			}
		}
		
		// If no extension found, grab all data attributes for debugging
		if (!extension) {
			//console.error("No extension found for playback formatter");
			
			// Collect debugging information
			var debugData = {
				"row": row,
				"activeWidget": $(".grid-stack-item.active[data-rawname='voicemail']").length 
					? $(".grid-stack-item.active[data-rawname='voicemail']").data() 
					: "not found",
				"activeFolder": $(".folder.active").length 
					? $(".folder.active").data() 
					: "not found",
				"anyVoicemailWidget": $(".grid-stack-item[data-rawname='voicemail']").length 
					? $(".grid-stack-item[data-rawname='voicemail']").first().data() 
					: "not found",
				"voicemailGrid": $(".voicemail-grid").length 
					? $(".voicemail-grid").data() 
					: "not found"
			};
			//console.debug("Voicemail extension debug data:", debugData);
			
			// Try a hardcoded extension from URL as last resort
			var urlParams = new URLSearchParams(window.location.search);
			extension = urlParams.get('ext') || "";
			if (extension) {
				//console.log("Using URL parameter extension:", extension);
			} else {
				// Return an error placeholder
				return '<div class="alert alert-danger">' + _("Error: Missing extension") + '</div>';
			}
		}
		
		// Common player structure for both IMAP and non-IMAP
		return '<div id="jquery_jplayer_'+row.msg_id+'-'+rand+'" class="jp-jplayer" '+
			'data-container="#jp_container_'+row.msg_id+'-'+rand+'" '+
			'data-id="'+row.msg_id+'" '+
			'data-extension="'+extension+'" '+
			'data-imap="'+(row.imap === true ? 'true' : 'false')+'" '+
			'data-folder="'+(row.folder || 'INBOX')+'"></div>'+
			'<div id="jp_container_'+row.msg_id+'-'+rand+'" data-player="jquery_jplayer_'+row.msg_id+'-'+rand+'" '+
			'class="jp-audio-freepbx" role="application" aria-label="media player">'+
				'<div class="jp-type-single">'+
					'<div class="jp-gui jp-interface">'+
						'<div class="jp-controls">'+
							'<i class="fa fa-play jp-play"></i>'+
							'<i class="fa fa-undo jp-restart"></i>'+
						'</div>'+
						'<div class="jp-progress">'+
							'<div class="jp-seek-bar progress">'+
								'<div class="jp-current-time" role="timer" aria-label="time">&nbsp;</div>'+
								'<div class="progress-bar progress-bar-striped active" style="width: 100%;"></div>'+
								'<div class="jp-play-bar progress-bar"></div>'+
								'<div class="jp-play-bar">'+
									'<div class="jp-ball"></div>'+
								'</div>'+
								'<div class="jp-duration" role="timer" aria-label="duration">&nbsp;</div>'+
							'</div>'+
						'</div>'+
						'<div class="jp-volume-controls">'+
							'<i class="fa fa-volume-up jp-mute"></i>'+
							'<i class="fa fa-volume-off jp-unmute"></i>'+
						'</div>'+
					'</div>'+
					'<div class="jp-error"></div>'+
					'<div class="jp-no-solution">'+
						'<span>Update Required</span>'+
						sprintf(_("You are missing support for playback in this browser. To fully support HTML5 browser playback you will need to install programs that can not be distributed with the PBX. If you'd like to install the binaries needed for these conversions click <a href='%s'>here</a>"),"http://wiki.freepbx.org/display/FOP/Installing+Media+Conversion+Libraries")+
					'</div>'+
				'</div>'+
			'</div>';
	},
	durationFormatter: function (value, row, index) {
		return (typeof UCP.durationFormatter === 'function') ? UCP.durationFormatter(value) : sprintf(_("%s seconds"),value);
	},
	controlFormatter: function (value, row, index) {
		var html = '<div class="btn-group">';
		
		if(row.read) {
			html += '<a href="#" class="btn btn-default btn-sm unread" data-id="'+row.msg_id+'" data-toggle="tooltip" title="' + _("Mark as Unread") + '" data-placement="bottom"><i class="fa fa-envelope"></i></a>';
		} else {
			html += '<a href="#" class="btn btn-default btn-sm read" data-id="'+row.msg_id+'" data-toggle="tooltip" title="' + _("Mark as Read") + '" data-placement="bottom"><i class="fa fa-envelope-open"></i></a>';
		}
		html += '<a href="#" class="btn btn-default btn-sm forward" data-id="'+row.msg_id+'" data-toggle="tooltip" title="' + _("Forward") + '" data-placement="bottom"><i class="fa fa-share"></i></a>';
		
		// Get the current extension from the active widget
		var extension = $(".grid-stack-item.active[data-rawname='voicemail']").data("widget_type_id") || 
		               (row.ext && row.ext !== 'undefined' ? row.ext : $(".folder.active").parents(".grid-stack-item").data("widget_type_id"));
		var currentFolder = $(".folder.active").data("folder") || 'INBOX'; // Get current folder
		
		if($("div.mailbox").data("show-download")) {
			// Determine base path dynamically from UCP.ajaxUrl
			var ucpDir = "/ucp/"; // Default fallback
			if(typeof UCP !== 'undefined' && typeof UCP.ajaxUrl !== 'undefined') {
				ucpDir = UCP.ajaxUrl.substring(0, UCP.ajaxUrl.lastIndexOf('/') + 1);
			}
			// Construct download URL using dynamic base path, index.php, and stream command with force_download flag
			var downloadUrl = ucpDir + 'index.php?quietmode=1&module=voicemail&command=stream&force_download=1&msg='+row.msg_id+'&ext='+extension+'&folder='+currentFolder+'&fw_asset_path=/admin/assets' + "&t=" + new Date().getTime();
			html += '<a href="'+downloadUrl+'" class="btn btn-default btn-sm download" data-id="'+row.msg_id+'" data-toggle="tooltip" title="' + _("Download") + '" data-placement="bottom"><i class="fa fa-download"></i></a>';
		}
		
		html += '<a href="#" class="btn btn-danger btn-sm delete" data-id="'+row.msg_id+'" data-toggle="tooltip" title="' + _("Delete") + '" data-placement="bottom"><i class="fa fa-trash-o"></i></a>';
		html += '</div>';
		return html;
	},
	bindPlayers: function(widget_id) {
		// Get the extension from the widget ID or from the URL
		var extension, urlParams = new URLSearchParams(window.location.search);
		
		// First try to get from widget
		if ($("div[data-id='"+widget_id+"']").length && $("div[data-id='"+widget_id+"']").data("widget_type_id")) {
			extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");
			//console.log("bindPlayers: Using widget extension:", extension);
		}
		// Next try URL
		else if (urlParams.get('ext')) {
			extension = urlParams.get('ext');
			//console.log("bindPlayers: Using URL extension:", extension);
		}
		
		// If still no extension, try one more method
		if (!extension) {
			extension = $(".grid-stack-item[data-rawname='voicemail']").first().data("widget_type_id");
			//console.log("bindPlayers: Using first voicemail widget extension:", extension);
		}
		
		if (!extension) {
			//console.error("bindPlayers: No extension found for widget_id", widget_id);
		}
		
		var self = this;
		
		$(".grid-stack-item[data-id="+widget_id+"] .jp-jplayer").each(function() {
			var container = $(this).data("container"),
					player = $(this),
					msg_id = $(this).data("id"),
					isImap = $(this).data("imap") === true;
			
			// Store extension for this player for later use
			$(this).data("extension", extension);
			
			$(this).jPlayer({
				ready: function() {
					$(container + " .jp-play").click(function() {
						if($(this).parents(".jp-controls").hasClass("recording")) {
							var type = $(this).parents(".jp-audio-freepbx").data("type");
							self.recordGreeting(extension,type);
							return;
						}
						// If player doesn't have a source set yet
						if(!player.data("jPlayer").status.srcSet) {
							$(container).addClass("jp-state-loading");
							
							// Get current extension from player data, player element, or fallback to widget
							var currentExt = player.data("extension") || 
							               (player.attr("data-extension") || extension);
							var folder = player.data("folder") || 'INBOX';
							var msgId = player.data("id");

							//console.log("Player click: Constructing stream URL for ext=", currentExt, ", msg=", msgId, ", folder=", folder);

							// Determine base path dynamically from UCP.ajaxUrl
							var ucpDir = "/ucp/"; // Default fallback
							if(typeof UCP !== 'undefined' && typeof UCP.ajaxUrl !== 'undefined') {
								ucpDir = UCP.ajaxUrl.substring(0, UCP.ajaxUrl.lastIndexOf('/') + 1);
							}
							//console.log("Using UCP directory:", ucpDir);

							// Construct the direct stream URL using dynamic base path and index.php
							var streamUrl = ucpDir + "index.php?module=voicemail&command=stream" +
							                "&ext=" + encodeURIComponent(currentExt) +
							                "&msg=" + encodeURIComponent(msgId) +
							                "&folder=" + encodeURIComponent(folder) +
							                "&quietmode=1" +
							                "&fw_asset_path=/admin/assets" + // Needed for UCP routing
							                "&t=" + new Date().getTime(); // Cache buster
							
							//console.log("Player using direct stream URL:", streamUrl);

							player.on($.jPlayer.event.error, function(event) {
								$(container).removeClass("jp-state-loading");
								//console.warn("jPlayer error event:", event);
								var errorMsg = "Media URL could not be loaded";
								if(event.jPlayer.error) {
									errorMsg += ": " + (event.jPlayer.error.message || 'Unknown error');
									errorMsg += " (Type: " + (event.jPlayer.error.type || 'N/A') + ", Context: " + (event.jPlayer.error.context || 'N/A') + ")";
									//console.error("Detailed jPlayer error: Type="+event.jPlayer.error.type+", Message="+event.jPlayer.error.message+", Context="+event.jPlayer.error.context);
								} else {
									//console.error("jPlayer error event triggered, but no error details found in event.jPlayer.error");
								}
								$(container).find(".jp-error").text(errorMsg).show();
							});
							
							player.one($.jPlayer.event.canplay, function(event) {
								$(container).removeClass("jp-state-loading");
								player.jPlayer("play");

								// --- MARK AS READ ON CANPLAY (Better timing) ---
								if(msgId && currentExt) {
									var rowElement = player.closest('tr');
									// Only mark as read if the 'mark as read' button currently exists
									var readButton = rowElement.find('a.read');
									if (readButton.length > 0) {
										self.markAsRead(msgId, currentExt, folder, isImap, true); // true = mark as read
									}
								}
							});

							player.jPlayer("setMedia", { wav: streamUrl });

						} else {
							// If src is already set, just play/pause
							if (player.data("jPlayer").status.paused) {
								player.jPlayer("play");
								// --- MARK AS READ ON RE-PLAY (if currently unread) ---
								if(msgId && currentExt) {
									var rowElement = player.closest('tr');
									// Only mark as read if the 'mark as read' button currently exists
									var readButton = rowElement.find('a.read');
									if (readButton.length > 0) {
										self.markAsRead(msgId, currentExt, folder, isImap, true); // true = mark as read
									}
								}
							} else {
								player.jPlayer("pause");
							}
						}
					});
				
					$(container).find(".jp-restart").click(function() {
						if(player.data("jPlayer").status.paused) {
							player.jPlayer("pause",0);
						} else {
							player.jPlayer("play",0);
						}
					});
				},
				timeupdate: function(event) {
					$(container).find(".jp-ball").css("left",event.jPlayer.status.currentPercentAbsolute + "%");
				},
				ended: function(event) {
					$(container).find(".jp-ball").css("left","0%");
				},
				swfPath: "/js",
				supplied: "wav", // Explicitly WAV only
				solution: "html", // Force HTML5
				cssSelectorAncestor: container,
				wmode: "window",
				useStateClassSkin: true,
				remainingDuration: true,
				toggleDuration: true,
				// Extra event listeners for debugging
				//seeking: function(event) { console.log("jPlayer Seeking event", event); },
				//seeked: function(event) { console.log("jPlayer Seeked event", event); },
				//stalled: function(event) { console.error("jPlayer Stalled event", event); },
				//suspend: function(event) { console.warn("jPlayer Suspend event", event); },
				//waiting: function(event) { console.warn("jPlayer Waiting event", event); },
				//progress: function(event) { console.log("jPlayer Progress event", event); }
			});
			$(this).on($.jPlayer.event.play, function(event) {
				$(this).jPlayer("pauseOthers");
			});
		});

		var acontainer = null;
		$('.grid-stack-item[data-rawname=voicemail] .jp-play-bar').mousedown(function (e) {
			acontainer = $(this).parents(".jp-audio-freepbx");
			updatebar(e.pageX);
		});
		$(document).mouseup(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
				acontainer = null;
			}
		});
		$(document).mousemove(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
			}
		});

		//update Progress Bar control
		var updatebar = function (x) {
			var player = $("#" + acontainer.data("player")),
					progress = acontainer.find('.jp-progress'),
					maxduration = player.data("jPlayer").status.duration,
					position = x - progress.offset().left,
					percentage = 100 * position / progress.width();

			//Check within range
			if (percentage > 100) {
				percentage = 100;
			}
			if (percentage < 0) {
				percentage = 0;
			}

			player.jPlayer("playHead", percentage);

			//Update progress bar and video currenttime
			acontainer.find('.jp-ball').css('left', percentage+'%');
			acontainer.find('.jp-play-bar').css('width', percentage + '%');
			player.jPlayer.currentTime = maxduration * percentage / 100;
		};
	},
	bindGreetingPlayers: function(extension) {
		var settings = UCP.Modules.Voicemail.staticsettings,
				supportedHTML5 = settings.supportedHTML5,
				self = this;

		if(Modernizr.getusermedia) {
			supportedHTML5 = supportedHTML5.split(",");
			if(supportedHTML5.indexOf("wav") === -1) {
				supportedHTML5.push("wav");
			}
			supportedHTML5 = supportedHTML5.join(",");
		}

		// Select greeting players specifically (e.g., those within #widget_settings)
		$("#widget_settings .jp-jplayer").each(function() {
			var container = $(this).data("container"),
					player = $(this),
					// Retrieve greeting type from a data attribute on the container
					// We assume the container div (e.g., id="jp_container_unavail") has a data-type attribute
					greetingType = $(container).data("type"); // Example: data-type="unavail"

			// If greetingType is missing, log an error and skip this player
			if (!greetingType) {
				console.error("Greeting player init error: Missing data-type attribute on container:", container);
				return; // Skip to next player
			}

			$(this).jPlayer({
				ready: function() {
					$(container + " .jp-play").off('click').on('click', function() { // Use off().on() to prevent duplicates
						if($(this).parents(".jp-controls").hasClass("recording")) {
							// Handle recording start/stop - This part remains unchanged
							var typeFromButton = $(this).parents(".jp-audio-freepbx").data("type");
							self.recordGreeting(extension,typeFromButton);
							return;
						}

						// --- Refactored Playback Logic for Greetings ---
						if(!player.data("jPlayer").status.srcSet) {
							$(container).addClass("jp-state-loading");

							// Get greeting type from container
							var greetingMsgId = greetingType; // Use the already retrieved greetingType variable

							// Determine base path dynamically from UCP.ajaxUrl
							var ucpDir = "/ucp/"; // Default fallback
							if(typeof UCP !== 'undefined' && typeof UCP.ajaxUrl !== 'undefined') {
								ucpDir = UCP.ajaxUrl.substring(0, UCP.ajaxUrl.lastIndexOf('/') + 1);
							}

							// Construct the direct stream URL using the 'stream' command and type=greeting
							var streamUrl = ucpDir + "index.php?module=voicemail&command=stream"
											+ "&type=greeting" // <-- Send type parameter
											+ "&ext=" + encodeURIComponent(extension)
											+ "&msg=" + encodeURIComponent(greetingMsgId) // <-- Send greeting type as msg
											// Folder is irrelevant for greetings when using this method
											+ "&quietmode=1"
											+ "&fw_asset_path=/admin/assets"
											+ "&t=" + new Date().getTime(); // Cache buster

							player.one($.jPlayer.event.canplay, function(event) {
								$(container).removeClass("jp-state-loading");
								player.jPlayer("play");
							});

							player.one($.jPlayer.event.error, function(event) {
								$(container).removeClass("jp-state-loading");
								console.error("jPlayer error loading greeting stream URL: ", streamUrl, event);
								var errorMsg = "Greeting media could not be loaded";
								if(event.jPlayer.error) { errorMsg += ": " + (event.jPlayer.error.message || 'Unknown error'); }
								$(container).find(".jp-error").text(errorMsg).show();
							});

							// Set media directly with the constructed URL
							player.jPlayer("setMedia", { wav: streamUrl }); // Assume WAV

						} else {
							// If src is already set, just play/pause
							if (player.data("jPlayer").status.paused) {
								player.jPlayer("play");
							} else {
								player.jPlayer("pause");
							}
						}
						// --- End Refactored Playback Logic ---
					});

					// Restart button handler remains mostly the same
					$(container).find(".jp-restart").off('click').on('click', function() {
						if (player.data("jPlayer") && player.data("jPlayer").status.srcSet) {
							player.jPlayer('stop');
							player.jPlayer('play', 0);
						}
					});
				},
				timeupdate: function(event) {
					$(container).find(".jp-ball").css("left",event.jPlayer.status.currentPercentAbsolute + "%");
				},
				ended: function(event) {
					$(container).find(".jp-ball").css("left","0%");
				},
				swfPath: "/js",
				supplied: supportedHTML5,
				cssSelectorAncestor: container,
				wmode: "window",
				useStateClassSkin: true,
				remainingDuration: true,
				toggleDuration: true
			});
			$(this).on($.jPlayer.event.play, function(event) {
				$(this).jPlayer("pauseOthers");
			});
		});

		// Seek bar logic remains unchanged...
		var acontainer = null;
		$('#widget_settings .jp-play-bar').mousedown(function (e) { // Corrected selector if needed
			acontainer = $(this).parents(".jp-audio-freepbx");
			updatebar(e.pageX);
		});
		$(document).mouseup(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
				acontainer = null;
			}
		});
		$(document).mousemove(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
			}
		});

		//update Progress Bar control
		var updatebar = function (x) {
			var player = $("#" + acontainer.data("player")),
					progress = acontainer.find('.jp-progress'),
					maxduration = player.data("jPlayer").status.duration,
					position = x - progress.offset().left,
					percentage = 100 * position / progress.width();

			//Check within range
			if (percentage > 100) {
				percentage = 100;
			}
			if (percentage < 0) {
				percentage = 0;
			}

			player.jPlayer("playHead", percentage);

			//Update progress bar and video currenttime
			acontainer.find('.jp-ball').css('left', percentage+'%');
			acontainer.find('.jp-play-bar').css('width', percentage + '%');
			player.jPlayer.currentTime = maxduration * percentage / 100;
		};
	},
	initializePlayer: function(widget_id) {
		//console.log("*** initializePlayer CALLED for widget_id " + widget_id + " ***"); // DEBUG LOG
		var self = this;
		// Select players only within the specific widget context if widget_id is provided
		var playerSelector = widget_id ? "div[data-id='"+widget_id+"'] .jp-jplayer" : ".jp-jplayer";

		$(playerSelector).each(function() {
			var playerElement = $(this); // Reference to the current player element
			var id = playerElement.data("id"),
				container = playerElement.data("container"),
				extension = playerElement.data("extension") || // Get from player data first
				            $("div[data-id='"+widget_id+"']").data("widget_type_id") || // Fallback to widget
				            (new URLSearchParams(window.location.search)).get('ext'); // Fallback to URL param
				
			// Final check for extension
			if (!extension) {
				//console.error("initializePlayer: Could not determine extension for player", id, "widget", widget_id);
				return; // Skip this player if no extension found
			}
				
			// Store/update extension on the player element for consistency
			playerElement.data("extension", extension);
				
			// Check if this is an IMAP voicemail player
			if (playerElement.data("imap") === true) {
				// Setup player for IMAP voicemail
				playerElement.jPlayer({
					ready: function() {
						$(this).addClass("jp-state-ready");
						// Play button click handler for IMAP
						$(container).find('.jp-play').off('click').on('click', function() { // Use .off().on() to prevent duplicates
							var $player = playerElement; // Use the correct player element reference
							// If player doesn't have a source set yet
							if(!$player.data("jPlayer").status.srcSet) {
								$(container).addClass("jp-state-loading");

								var currentExt = $player.data("extension"); // Get extension from player data
								var folder = $player.data('folder') || 'INBOX';
								var msgId = $player.data('id'); // This is the UID

								// Determine base path dynamically from UCP.ajaxUrl
								var ucpDir = "/ucp/"; // Default fallback
								if(typeof UCP !== 'undefined' && typeof UCP.ajaxUrl !== 'undefined') {
									ucpDir = UCP.ajaxUrl.substring(0, UCP.ajaxUrl.lastIndexOf('/') + 1);
								}
								// Construct the direct stream URL using dynamic base path and index.php
								var streamUrl = ucpDir + "index.php?module=voicemail&command=stream"
												// Optional: + "&type=voicemail" // Default, so not strictly needed
												+ "&ext=" + encodeURIComponent(currentExt)
												+ "&msg=" + encodeURIComponent(msgId) // Send UID as msg
												+ "&folder=" + encodeURIComponent(folder)
												+ "&quietmode=1"
												+ "&fw_asset_path=/admin/assets" // Needed for UCP routing
												+ "&t=" + new Date().getTime(); // Cache buster

								$player.one($.jPlayer.event.canplay, function(event) {
									$(container).removeClass("jp-state-loading");
									$player.jPlayer("play");
								});

								$player.one($.jPlayer.event.error, function(event) {
									$(container).removeClass("jp-state-loading");
									//console.error("jPlayer error loading stream URL: ", streamUrl, event);
									var errorMsg = "Media URL could not be loaded";
									if(event.jPlayer.error) { errorMsg += ": " + (event.jPlayer.error.message || 'Unknown error'); }
									$(container).find(".jp-error").text(errorMsg).show();
								});

								// Set media directly with the constructed URL
								$player.jPlayer("setMedia", { wav: streamUrl }); // Assume WAV, backend sends correct content-type
							} else {
								// If src is already set, just play/pause
								if ($player.data("jPlayer").status.paused) {
									$player.jPlayer("play");
								} else {
									$player.jPlayer("pause");
								}
							}
						});

						// Reset button handler for IMAP
						$(container).find('.jp-restart').off('click').on('click', function() {
							var player = playerElement;
							if (player.data("jPlayer") && player.data("jPlayer").status.srcSet) { // Only restart if src is set
								player.jPlayer('stop');
								player.jPlayer('play', 0); // Play from beginning
							}
						});

					}, // End of ready function for IMAP
					play: function() {
						$(this).jPlayer("pauseOthers");
					},
					cssSelectorAncestor: container,
					supplied: "wav",
					timeupdate: function(event) {
						var percent = event.jPlayer.status.currentPercentAbsolute;
						if(percent > 1) {
							$(container).find('.jp-play-bar').css('width',percent+"%");
						}
					},
					solution: "html, flash", // Keep flash for older browser compatibility if needed
					swfPath: "assets/js",
					wmode: "window",
					preload: "none",
					volume: 1,
					smoothPlayBar: true,
					keyEnabled: true,
					remainingDuration: false,
					toggleDuration: false,
					click: function(event) { // Seek functionality
						if(!event.jPlayer.status.paused && event.jPlayer.status.srcSet) {
							var progressElement = $(event.currentTarget).find('.jp-progress');
							var offsetX = event.pageX - progressElement.offset().left;
							var width = progressElement.width();
							var percent = offsetX / width;
							var time = percent * event.jPlayer.status.duration;

							if (time >= 0 && time <= event.jPlayer.status.duration) {
								$(this).jPlayer("play", time);
							}
						}
					}
				}); // End jPlayer init for IMAP

			} else {
				// Original setup for non-IMAP (Local) voicemails, BUT modified to construct URL directly
				playerElement.jPlayer({
					ready: function() {
						var selfPlayer = $(this); // Reference to the player inside ready
						selfPlayer.addClass("jp-state-ready");

						// Construct the stream URL directly during ready event for non-IMAP
						var currentExt = selfPlayer.data("extension") || extension;
						var folder = selfPlayer.data('folder') || 'INBOX';
						var msgId = selfPlayer.data('id'); // This is the local file identifier

						var ucpDir = "/ucp/"; // Default fallback
						if(typeof UCP !== 'undefined' && typeof UCP.ajaxUrl !== 'undefined') {
							ucpDir = UCP.ajaxUrl.substring(0, UCP.ajaxUrl.lastIndexOf('/') + 1);
						}
						var streamUrl = ucpDir + "index.php?module=voicemail&command=stream"
										// Optional: + "&type=voicemail" // Default, so not strictly needed
										+ "&ext=" + encodeURIComponent(currentExt)
										+ "&msg=" + encodeURIComponent(msgId) // Send local identifier as msg
										+ "&folder=" + encodeURIComponent(folder)
										+ "&quietmode=1"
										+ "&fw_asset_path=/admin/assets"
										+ "&t=" + new Date().getTime();

						// Set media directly, assuming WAV - backend handles actual content type
						selfPlayer.jPlayer("setMedia", { wav: streamUrl });

						// Click handler for play button (needed even if media is set on ready)
						$(container).find('.jp-play').off('click').on('click', function() {
							if (selfPlayer.data("jPlayer").status.paused) {
								selfPlayer.jPlayer("play");
							} else {
								selfPlayer.jPlayer("pause");
							}
						});
						// Reset button handler for Local
						$(container).find('.jp-restart').off('click').on('click', function() {
							if (selfPlayer.data("jPlayer") && selfPlayer.data("jPlayer").status.srcSet) {
								selfPlayer.jPlayer('stop');
								selfPlayer.jPlayer('play', 0);
							}
						});

					}, // End of ready function for Local
					play: function() {
						$(this).jPlayer("pauseOthers");
					},
					cssSelectorAncestor: container,
					// Define supported formats if known, otherwise rely on backend content-type
					// Forcing 'wav' here as we set the URL with that assumption
					supplied: "wav",
					timeupdate: function(event) {
						var percent = event.jPlayer.status.currentPercentAbsolute;
						if(percent > 1) {
							$(container).find('.jp-play-bar').css('width',percent+"%");
						}
					},
					solution: "html, flash",
					swfPath: "assets/js",
					wmode: "window",
					preload: "auto", // Preload might be okay for local files
					volume: 1,
					smoothPlayBar: true,
					keyEnabled: true,
					remainingDuration: false,
					toggleDuration: false,
					click: function(event) { // Seek functionality
						if(!event.jPlayer.status.paused && event.jPlayer.status.srcSet) {
							var progressElement = $(event.currentTarget).find('.jp-progress');
							var offsetX = event.pageX - progressElement.offset().left;
							var width = progressElement.width();
							var percent = offsetX / width;
							var time = percent * event.jPlayer.status.duration;

							if (time >= 0 && time <= event.jPlayer.status.duration) {
								$(this).jPlayer("play", time);
							}
						}
					},
					error: function(event) { // Error handler for local player
						//console.error("jPlayer error loading local stream URL: ", $(this).data("jPlayer").status.src, event);
						var errorMsg = "Media could not be loaded";
						if(event.jPlayer.error) { errorMsg += ": " + (event.jPlayer.error.message || 'Unknown error'); }
						$(container).find(".jp-error").text(errorMsg).show();
					}
				}); // End jPlayer init for Local
			} // End if/else for IMAP vs Local
		}); // End .each player
	}, // End initializePlayer function
	openmodal: function(turl) {
		$.ajax({
			url: turl,
			type: "POST",
			success: function(result) {
				result = JSON.parse(result);
				$("#addtionalcontent").html(result.html);
				$("#addtionalcontent").appendTo("body");
				$("#datamodal").show();
			},
			error: function(xhr, status, error) {
				//console.error("Error occurred while opening modal:", status, error);
			}
		});
	},
	closemodal: function() {
		$("#datamodal").hide();
		$("#addtionalcontent").html("");	
	},
	markAsRead: function(msg_id, extension, folder, isImap, readStatus) {
		//console.log("Marking as read:", { msg_id, extension, folder, isImap, readStatus });
		if (!extension || !msg_id) {
			//console.error("markAsRead: Missing extension or msg_id");
			return;
		}
		$.ajax({
			type: "POST",
			url: UCP.ajaxUrl,
			data: {
				module: "voicemail",
				command: "markread",
				ext: extension,
				msg_id: msg_id,
				read: readStatus, // true to mark read, false to mark unread
				folder: folder || 'INBOX', // Default to INBOX if not provided
				imap: isImap ? 'true' : 'false' // Send as string 'true'/'false'
			},
			dataType: 'json',
			success: function (response) {
				if (response && response.status === true) {
					// Successfully marked on backend
					//console.log("Marked msg " + msg_id + " as " + (readStatus ? "read" : "unread") + " successfully.");

					// Optional: Update UI immediately
					var playerElement = $(".jp-jplayer[data-id='"+msg_id+"']"); // Find player again
					if(playerElement.length) {
						var rowElement = playerElement.closest('tr');
						if (rowElement.length) {
							var readButton = rowElement.find('a.read');
							var unreadButton = rowElement.find('a.unread');
							if (readStatus) { // Marking as read
								// Find the 'mark as read' button and change it to 'mark as unread'
								readButton.removeClass('read').addClass('unread')
									.attr('title', _('Mark as Unread'))
									.data('original-title', _('Mark as Unread')) // Update tooltip data
									.find('i').removeClass('fa-envelope-open').addClass('fa-envelope');
							} else { // Marking as unread
								// Find the 'mark as unread' button and change it to 'mark as read'
								unreadButton.removeClass('unread').addClass('read')
									.attr('title', _('Mark as Read'))
									.data('original-title', _('Mark as Read')) // Update tooltip data
									.find('i').removeClass('fa-envelope').addClass('fa-envelope-open');
							}
												// Re-initialize tooltips for the updated button
					rowElement.find('[data-toggle="tooltip"]').tooltip();
				}
				
				                 // Refresh folder counts immediately after marking as read/unread
                 var extension = $(".grid-stack-item.active[data-rawname='voicemail']").data("widget_type_id");
                 if (extension) {
                     // Only refresh if not already refreshing
                     if (!window.vm_refresh_in_progress) {
                         window.vm_refresh_in_progress = {};
                     }
                     if (!window.vm_refresh_in_progress[extension]) {
                         window.vm_refresh_in_progress[extension] = true;
                         setTimeout(function() {
                             self.refreshFolderCount(extension);
                             window.vm_refresh_in_progress[extension] = false;
                         }, 1000);
                     }
                 }
			}
		} else {
			//console.error("Failed to mark message as read/unread on backend.", response);
			UCP.showAlert(_("Failed to update message status.") + (response && response.message ? ' Error: '+response.message : ''));
		}
	},
			error: function (jqXHR, textStatus, errorThrown) {
				//console.error("AJAX Error marking message as read/unread:", textStatus, errorThrown);
				UCP.showAlert(_("AJAX Error updating message status.") + ' ' + textStatus);
			}
		});
	},
	showCreateFolderDialog: function(extension, containingWidget) {
		var self = this;
		
		// Create modal dialog HTML
		var modalHtml = '<div class="modal fade" id="createFolderModal" tabindex="-1" role="dialog">' +
			'<div class="modal-dialog" role="document">' +
			'<div class="modal-content">' +
			'<div class="modal-header">' +
			'<button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
			'<span aria-hidden="true">&times;</span>' +
			'</button>' +
			'<h4 class="modal-title">' + _('Create New Folder') + '</h4>' +
			'</div>' +
			'<div class="modal-body">' +
			'<form id="createFolderForm">' +
			'<div class="form-group">' +
			'<label for="folderName">' + _('Folder Name') + '</label>' +
			'<input type="text" class="form-control" id="folderName" name="folderName" maxlength="8" placeholder="' + _('Enter folder name (max 8 characters)') + '" required>' +
			'<small class="form-text text-muted">' + _('Folder names are limited to 8 characters and can only contain letters, numbers, hyphens, and underscores.') + '</small>' +
			'</div>' +
			'</form>' +
			'</div>' +
			'<div class="modal-footer">' +
			'<button type="button" class="btn btn-default" data-dismiss="modal">' + _('Cancel') + '</button>' +
			'<button type="button" class="btn btn-primary" id="createFolderBtn">' + _('Create Folder') + '</button>' +
			'</div>' +
			'</div>' +
			'</div>' +
			'</div>';
		
		// Remove existing modal if present
		$('#createFolderModal').remove();
		
		// Add modal to body
		$('body').append(modalHtml);
		
		// Show modal
		$('#createFolderModal').modal('show');
		
		// Handle form submission
		$('#createFolderBtn').off('click').on('click', function() {
			var folderName = $('#folderName').val().trim();
			
			// Validate folder name
			if (!folderName) {
				UCP.showAlert(_('Please enter a folder name.'));
				return;
			}
			
			// Check length
			if (folderName.length > 8) {
				UCP.showAlert(_('Folder name must be 8 characters or less.'));
				return;
			}
			
			// Check for invalid characters
			if (!/^[a-zA-Z0-9\-_]+$/.test(folderName)) {
				UCP.showAlert(_('Folder name can only contain letters, numbers, hyphens, and underscores.'));
				return;
			}
			
			// Check for existing folders (case-insensitive)
			var existingFolders = [];
			var folderTable = containingWidget.find('.folder-list-table tbody tr.folder');
			folderTable.each(function() {
				var folderName = $(this).data('folder');
				if (folderName) {
					existingFolders.push(folderName.toUpperCase());
				}
			});
			
			if (existingFolders.indexOf(folderName.toUpperCase()) !== -1) {
				UCP.showAlert(_('A folder with this name already exists.'));
				return;
			}
			
			// Disable button and show loading state
			$(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + _('Creating...'));
			
			// Send AJAX request to create folder
			$.ajax({
				type: 'POST',
				url: UCP.ajaxUrl,
				data: {
					module: 'voicemail',
					command: 'createfolder',
					ext: extension,
					folder_name: folderName
				},
				dataType: 'json',
				success: function(response) {
					if (response && response.status === true) {
						// Success - close modal and refresh folder list
						$('#createFolderModal').modal('hide');
						UCP.showAlert(_('Folder created successfully!'), 'success');
						
						// Refresh folder list and counts to show new folder
						if (!window.vm_refresh_in_progress) {
							window.vm_refresh_in_progress = {};
						}
						if (!window.vm_refresh_in_progress[extension]) {
							window.vm_refresh_in_progress[extension] = true;
							setTimeout(function() {
								self.refreshFolderList(extension, containingWidget);
								self.refreshFolderCount(extension);
								window.vm_refresh_in_progress[extension] = false;
							}, 500);
						}
					} else {
						// Error
						var errorMsg = response && response.message ? response.message : _('Failed to create folder.');
						UCP.showAlert(errorMsg);
						
						// Re-enable button
						$('#createFolderBtn').prop('disabled', false).html(_('Create Folder'));
					}
				},
				error: function(xhr, status, error) {
					UCP.showAlert(_('AJAX Error creating folder.') + ' ' + status);
					
					// Re-enable button
					$('#createFolderBtn').prop('disabled', false).html(_('Create Folder'));
				}
			});
		});
		
		// Handle modal hidden event to clean up
		$('#createFolderModal').on('hidden.bs.modal', function() {
			$(this).remove();
		});
		
		// Focus on input when modal is shown
		$('#createFolderModal').on('shown.bs.modal', function() {
			$('#folderName').focus();
		});
		
		// Handle Enter key in input field
		$('#folderName').off('keypress').on('keypress', function(e) {
			if (e.which === 13) { // Enter key
				e.preventDefault();
				$('#createFolderBtn').click();
			}
		});
	},
	showDeleteFolderDialog: function(extension, folderName, folderKey, containingWidget, $button) {
		var self = this;
		
		// Create modal dialog HTML
		var modalHtml = '<div class="modal fade" id="deleteFolderModal" tabindex="-1" role="dialog">' +
			'<div class="modal-dialog" role="document">' +
			'<div class="modal-content">' +
			'<div class="modal-header">' +
			'<button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
			'<span aria-hidden="true">&times;</span>' +
			'</button>' +
			'<h4 class="modal-title">' + _('Delete Folder') + '</h4>' +
			'</div>' +
			'<div class="modal-body">' +
			'<div class="alert alert-warning">' +
			'<i class="fa fa-exclamation-triangle"></i> ' +
			_('Are you sure you want to delete the folder "') + folderName + _('"?') +
			'</div>' +
			'<p>' + _('This action cannot be undone and will delete all messages in the folder.') + '</p>' +
			'</div>' +
			'<div class="modal-footer">' +
			'<button type="button" class="btn btn-default" data-dismiss="modal">' + _('Cancel') + '</button>' +
			'<button type="button" class="btn btn-danger" id="deleteFolderBtn">' + _('Delete Folder') + '</button>' +
			'</div>' +
			'</div>' +
			'</div>' +
			'</div>';
		
		// Remove existing modal if present
		$('#deleteFolderModal').remove();
		
		// Add modal to body
		$('body').append(modalHtml);
		
		// Show modal
		$('#deleteFolderModal').modal('show');
		
		// Handle delete button click
		$('#deleteFolderBtn').off('click').on('click', function() {
			// Add loading state to original button
			var $icon = $button.find('i');
			var originalIcon = $icon.attr('class');
			$icon.attr('class', 'fa fa-spinner fa-spin');
			$button.prop('disabled', true);
			
			// Disable modal button and show loading state
			$(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + _('Deleting...'));
			
			// Send delete request
			$.ajax({
				url: UCP.ajaxUrl + "?module=voicemail&command=deletefolder",
				type: "POST",
				data: {
					ext: extension,
					folder_name: folderKey
				},
				success: function(response) {
					// Close modal
					$('#deleteFolderModal').modal('hide');
					
					if (response.status) {
						// Show success message
						UCP.showAlert(_("Folder deleted successfully"), "success");
						// Refresh folder list
						self.refreshFolderList(extension, containingWidget);
					} else {
						// Show error message
						UCP.showAlert(response.message || _("Failed to delete folder"), "error");
					}
				},
				error: function(xhr, status, error) {
					// Close modal
					$('#deleteFolderModal').modal('hide');
					UCP.showAlert(_("Server error occurred while deleting folder"), "error");
				},
				complete: function() {
					// Restore original button state
					$icon.attr('class', originalIcon);
					$button.prop('disabled', false);
				}
			});
		});
		
		// Handle modal hidden event to clean up
		$('#deleteFolderModal').on('hidden.bs.modal', function() {
			$(this).remove();
		});
	},
	refreshFolderList: function(extension, containingWidget) {
		var self = this;
		
		// Get the current active folder to maintain selection
		var activeFolder = containingWidget.find('.folder-list-table tr.folder.active').data('folder') || 'INBOX';
		
		// Fetch updated folder list from server
		$.ajax({
			type: 'POST',
			url: UCP.ajaxUrl,
			data: {
				module: 'voicemail',
				command: 'refreshfoldercount',
				ext: extension
			},
			dataType: 'json',
			success: function(response) {
				if (response && response.folders) {
					// Update the folder table
					var folderTable = containingWidget.find('.folder-list-table tbody');
					folderTable.empty();
					
					// Sort folders: INBOX first, then alphabetically
					var sortedFolders = [];
					var inboxFolder = null;
					
					// Separate INBOX and other folders
					Object.keys(response.folders).forEach(function(folderKey) {
						var folderData = response.folders[folderKey];
						if (folderKey.toUpperCase() === 'INBOX') {
							inboxFolder = { key: folderKey, data: folderData };
						} else {
							sortedFolders.push({ key: folderKey, data: folderData });
						}
					});
					
					// Sort other folders alphabetically
					sortedFolders.sort(function(a, b) {
						return a.key.localeCompare(b.key);
					});
					
					// Add INBOX first, then other folders
					var allFolders = [];
					if (inboxFolder) {
						allFolders.push(inboxFolder);
					}
					allFolders = allFolders.concat(sortedFolders);
					
					// Build new folder rows
					allFolders.forEach(function(folder) {
						var isActive = folder.key === activeFolder;
						var isSystemFolder = folder.key.toUpperCase() === 'INBOX' || folder.key.toUpperCase() === 'OLD';
						var deleteButton = '';
						
						if (!isSystemFolder) {
							deleteButton = '<button type="button" class="btn btn-xs btn-danger delete-folder-btn" ' +
								'data-extension="' + extension + '" ' +
								'data-folder="' + folder.key + '" ' +
								'data-folder-name="' + folder.data.label + '" ' +
								'title="' + _('Delete Folder') + '">' +
								'<i class="fa fa-minus"></i>' +
								'</button>';
						}
						
						var rowHtml = '<tr class="folder' + (isActive ? ' active' : '') + '" data-name="' + folder.data.label + '" data-folder="' + folder.key + '">' +
							'<td class="folder-name">' + folder.data.label + '</td>' +
							'<td class="folder-badge"><span class="badge badge-total" title="Total Messages">' + (folder.data.count || 0) + '</span></td>' +
							'<td class="folder-badge"><span class="badge badge-unread" title="Unread Messages">' + (folder.data.unread || 0) + '</span></td>' +
							'<td class="folder-badge"><span class="badge badge-high" title="High Priority Messages">' + (folder.data.high_priority || 0) + '</span></td>' +
							'<td class="folder-actions">' + deleteButton + '</td>' +
							'</tr>';
						folderTable.append(rowHtml);
					});
					
					// Re-attach click handlers to new folder rows
					containingWidget.find('.folder-list-table tr.folder').off('click').on('click', function(e) {
						e.preventDefault();
						var clickedRow = $(this);
						var targetFolder = clickedRow.data('folder');
						
						// Remove active class from all folders
						containingWidget.find('.folder-list-table tr.folder').removeClass('active');
						// Add active class to clicked folder
						clickedRow.addClass('active');
						
						// Refresh the message grid for the selected folder
						containingWidget.find('.voicemail-grid').bootstrapTable('refresh', {
							url: UCP.ajaxUrl + "?module=voicemail&command=grid&ext=" + extension + "&folder=" + targetFolder,
							silent: false
						});
					});
					
					// Re-attach delete button handlers
					containingWidget.find('.delete-folder-btn').off('click').on('click', function(e) {
						e.preventDefault();
						e.stopPropagation(); // Prevent folder selection
						
						var $button = $(this);
						var extension = $button.data('extension');
						var folderName = $button.data('folder-name');
						var folderKey = $button.data('folder');
						var containingWidget = $button.closest('.grid-stack-item');
						
						if (!extension || !folderName) {
							console.error("Could not determine extension or folder name for delete button.");
							return;
						}
						
						// Show Bootstrap modal confirmation dialog
						self.showDeleteFolderDialog(extension, folderName, folderKey, containingWidget, $button);
					});
				}
			},
			error: function(xhr, status, error) {
				console.error('Failed to refresh folder list:', error);
			}
		});
	},
	startPeriodicRefresh: function(extension, containingWidget) {
		var self = this;
		
		// Clear any existing interval
		if (window.vm_periodic_refresh_interval) {
			clearInterval(window.vm_periodic_refresh_interval);
		}
		
		// Start periodic refresh every 30 seconds
		window.vm_periodic_refresh_interval = setInterval(function() {
			// Only refresh if the widget is visible and not already refreshing
			if (containingWidget.is(':visible') && (!window.vm_refresh_in_progress || !window.vm_refresh_in_progress[extension])) {
				self.refreshFolderList(extension, containingWidget);
				self.refreshFolderCount(extension);
			}
		}, 30000); // 30 seconds
	}
});



