<?php
/**
 * This is the User Control Panel Object.
 *
 * Copyright (C) 2013 Schmooze Com, INC
 * Copyright (C) 2013 Andrew Nagy <andrew.nagy@schmoozecom.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package   FreePBX UCP BMO
 * @author   Andrew Nagy <andrew.nagy@schmoozecom.com>
 * @license   AGPL v3
 */
namespace UCP\Modules;

use \UCP\Modules as Modules;

class Voicemail extends Modules {
	protected $module = 'Voicemail';
	private $limit = 15;
	private $break = 5;
	private $boxes = array();
	private $extensions = array();
	private $user = array();


	function __construct($Modules) {
		$this->Modules = $Modules;
		$this->Vmx     = $this->UCP->FreePBX->Voicemail->Vmx;
		if ($this->UCP->Session->isMobile) {
			$this->limit = 7;
		}

		$this->user       = $this->UCP->User->getUser();
		$this->extensions = $this->UCP->getCombinedSettingByID($this->user['id'] ?? '', $this->module, 'assigned');
		$this->ext        = $this->user["default_extension"] ?? '';
		$this->voicemails = $this->getVoicemails($this->ext);
		$this->enabled    = !empty($this->extensions) && $this->UCP->getCombinedSettingByID($this->user['id'] ?? '', $this->module, 'enable');
		$this->vmxenabled = !empty($this->extensions) && $this->UCP->getCombinedSettingByID($this->user['id'] ?? '', $this->module, 'vmxlocator');
		$this->playback   = $this->UCP->getCombinedSettingByID($this->user['id'] ?? '', $this->module, 'playback');
		$this->playback   = !is_null($this->playback) ? $this->playback : true;
		$this->download   = $this->UCP->getCombinedSettingByID($this->user['id'] ?? '', $this->module, 'download');
		$this->download   = !is_null($this->download) ? $this->download : true;
		$this->settings   = $this->UCP->getCombinedSettingByID($this->user['id'] ?? '', $this->module, 'settings');
		$this->settings   = !is_null($this->settings) ? $this->settings : true;
		$this->greetings  = $this->UCP->getCombinedSettingByID($this->user['id'] ?? '', $this->module, 'greetings');
		$this->greetings  = !is_null($this->greetings) ? $this->greetings : true;
	}
	
	/**
	 * Set up and cache an IMAP client instance for a specific extension/context.
	 *
	 * Attempts to retrieve a cached, connected client first. If not available or invalid,
	 * it creates, connects, and authenticates a new client based on global and
	 * extension-specific IMAP settings.
	 *
	 * @param string|int|null $extension Extension number. Optional, used for extension-specific credentials and caching.
	 * @param string|null $context Voicemail context. Optional, used for caching key.
	 * @return \UCP\Modules\Voicemail\Imap\ImapClient|false ImapClient instance if successful, false otherwise.
	 */
	private function _setupImapClient($extension = null, $context = null) {
		try {
			// Cache IMAP clients by extension to prevent frequent connect/disconnect cycles
			static $imapClientCache = [];
			$cacheKey = $extension ? "$extension@$context" : 'default';
			
			// Check if we have a cached and valid client for this extension
			if (isset($imapClientCache[$cacheKey]) && 
				$imapClientCache[$cacheKey] instanceof \UCP\Modules\Voicemail\Imap\ImapClient) {
				
				// Verify the connection is still active
				try {
					// Get the cached client
					$cachedClient = $imapClientCache[$cacheKey];
					
					// Test the connection
					if ($cachedClient->getConnection() instanceof \IMAP\Connection) {
						return $cachedClient;
					} else {
					}
				} catch (\Exception $e) {
					// Continue to create a new client
				}
			}
			
			// Load the IMAP client class if needed
			if (!class_exists('\\UCP\\Modules\\Voicemail\\Imap\\ImapClient')) {
				require_once(__DIR__ . '/includes/imap/ImapClient.php');
			}
			if (!class_exists('\\UCP\\Modules\\Voicemail\\Imap\\ImapClient')) {
				return false;
			}
			
			// Get global voicemail configuration
			$vmconf = $this->_getVoicemailConf();
			
			$vmbox = []; // Initialize safely
			if ($extension) {
				$vmbox = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($extension);
				if (empty($vmbox)) {
					freepbx_log(FPBX_LOG_WARNING, "[_setupImapClient] Mailbox config not found for extension: $extension");
					$vmbox = []; // Ensure it's an array even if not found
				}
			}
			
			// <<< ADDED DEBUG LOGGING >>>
			$constructorParamsLog = [
				'server' => $vmconf['imapserver'] ?? null,
				'port' => $vmconf['imapport'] ?? null,
				'flags' => $vmconf['imapflags'] ?? 'notls',
				'ext_user' => $vmbox['options']['imapuser'] ?? null,
				'ext_pass_set' => isset($vmbox['options']['imappassword']) && !empty($vmbox['options']['imappassword']) ? 'Yes' : 'No',
				'admin_user' => $vmconf['authuser'] ?? null,
				'admin_pass_set' => isset($vmconf['authpassword']) && !empty($vmconf['authpassword']) ? 'Yes' : 'No',
				'parent_folder' => $vmconf['imapparentfolder'] ?? null,
				'method' => $vmconf['imapimpersonationmethod'] ?? null,
				'authType' => $vmconf['imapauthtype'] ?? 'None'
			];
			freepbx_log(FPBX_LOG_INFO, "[IMAP Setup] Params for ImapClient constructor: " . json_encode($constructorParamsLog));
			// <<< END DEBUG LOGGING >>>
			
			// Create the IMAP client with global settings
			$imapClient = new \UCP\Modules\Voicemail\Imap\ImapClient(
				$vmconf['imapserver'] ?? null,         // server
				$vmconf['imapport'] ?? null,           // port
				$vmconf['imapflags'] ?? 'notls',     // flags (default notls)
				$vmbox['options']['imapuser'] ?? null, // username (extension specific)
				$vmbox['options']['imappassword'] ?? null, // password (extension specific)
				$vmconf['authuser'] ?? null,         // authuser (global admin)
				$vmconf['authpassword'] ?? null,     // authpassword (global admin)
				$vmconf['imapparentfolder'] ?? null,     // Parent Folder
				$vmconf['greetingsfolder'] ?? null,    // Greetings Folder
				$vmconf['imapfolder'] ?? 'INBOX',           // Default Folder (INBOX)
				$vmconf['imapimpersonationmethod'] ?? null, // Method from saved settings
				$vmconf['imapauthtype'] ?? 'None',          // AuthType from saved settings
				$vmconf['imapgreetings'] ?? 'no'           // Added: IMAP greetings setting
			);
			
			// Now connect and authenticate
			if ($extension && $context) {
				
				// Use the already fetched $vmbox
				if (!empty($vmbox) && isset($vmbox['options'])) {
					if (is_array($vmbox['options'])) {
						// Now look for IMAP settings in the mailbox options
							
							// If mailbox has an IMAP user set, pass it to the client
							if (isset($vmbox['options']['imapuser']) && !empty($vmbox['options']['imapuser'])) {
								$imapUser = $vmbox['options']['imapuser'];
								
								// Connect and authenticate
								if ($imapClient->connect($extension, $context)) {
									// Cache the successful connection
									$imapClientCache[$cacheKey] = $imapClient;
									
									return $imapClient;
								} else {
								}
							}
					}
				} else {
					$reason = empty($vmbox) ? "No mailbox found" : "No options property in mailbox";
				}
			} else {
			}
			
			return false;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Get the list of available Voicemail widgets for the current user.
	 *
	 * Checks permissions and returns a list of widgets, one for each assigned extension.
	 * Includes error information if validation fails.
	 *
	 * @return array An array describing the widget list, including metadata and potential errors.
	 */
	public function getWidgetList() {
		$responseData = array(
			"rawname" => "voicemail",
			"display" => _("Voicemail"),
			"icon" => "fa fa-inbox",
			"list" => []
		);
		$errors = $this->validate();
		if ($errors['hasError']) {
			return array_merge($responseData, $errors);
		}

		$widgets = array();

		$extensions = $this->extensions;
		if (!empty($extensions)) {
			foreach ($extensions as $extension) {
				$data = $this->UCP->FreePBX->Core->getDevice($extension);
				if (empty($data) || empty($data['description'])) {
					$data = $this->UCP->FreePBX->Core->getUser($extension);
					$name = $data['name'] ?? '';
				}
				else {
					$name = $data['description'];
				}
				$widgets[$extension] = array(
					"display"     => $name,
					"hasSettings" => true,
					"minsize"     => array( "height" => 5, "width" => 5 ),
					"defaultsize" => array( "height" => 7, "width" => 6 )
				);
			}
		}

		$responseData['list'] = $widgets;
		return $responseData;
	}

	/**
	 * Validate UCP Voicemail module access rules for the current user and optionally a specific extension.
	 *
	 * Checks if the module is enabled, if the user has assigned extensions,
	 * and (if an extension is provided) if the extension is valid, assigned, and enabled for voicemail.
	 *
	 * @param string|int|false $extension The specific extension to validate, or false to skip extension-specific checks.
	 * @return array An array containing 'hasError' (bool) and 'errorMessages' (array).
	 */
	private function validate($extension = false) {
		$data = array(
			'hasError' => false,
			'errorMessages' => []
		);

		if (!$this->enabled) {
			$data['hasError'] = true;
			$data['errorMessages'][] = _('UCP Voicemail is not enabled for this user.');
		}
		if (empty($this->extensions)) {
			$data['hasError'] = true;
			$data['errorMessages'][] = _('There are no assigned extensions.');
		}
		if ($extension !== false) {
			if (empty($extension)) {
				$data['hasError'] = true;
				$data['errorMessages'][] = _('The given extension is empty.');
			}
			if (!$this->_checkExtension($extension)) {
				$data['hasError'] = true;
				$data['errorMessages'][] = _('This extension is not assigned to this user.');
			}
			if (!$this->UCP->FreePBX->Voicemail->checkVoicemailEnabled($extension)) {
				$data['hasError'] = true;
				$data['errorMessages'][] = _('Voicemail is not enabled for this extension.');
			}
		}

		return $data;
	}

	/**
	 * Get the HTML content for displaying a Voicemail widget.
	 *
	 * Validates access for the given extension ID, fetches folder information (IMAP or local),
	 * and loads the widget view template.
	 *
	 * @param string|int $id The extension ID for which to display the widget.
	 * @return array An array containing the widget title and HTML, or error information if validation fails.
	 */
	public function getWidgetDisplay($id) {
		$errors = $this->validate($id);
		if ($errors['hasError']) {
			return $errors;
		}
		$html = '';
		$ext = !empty($id) ? $id : '';
		$view     = !empty($_REQUEST['view']) ? $_REQUEST['view'] : 'folder';
		$page     = !empty($_REQUEST['page']) ? $_REQUEST['page'] : 1;
		
		// === FIX: Use correct folder retrieval based on IMAP status ===
		$folders = [];
		$total = 0;
		$imapEnabled = $this->isImapEnabled($ext);
		
		if ($imapEnabled) {
			$mailbox = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($ext);
			$context = !empty($mailbox) ? ($mailbox['vmcontext'] ?? 'default') : 'default';
			$imapClient = $this->_setupImapClient($ext, $context);
			if ($imapClient) {
				freepbx_log(FPBX_LOG_INFO, "[UCP] getWidgetDisplay: Calling getVoicemailFolders for extension $ext");
				$fetchedFolders = $imapClient->getVoicemailFolders(); // Use the correct IMAP method
				freepbx_log(FPBX_LOG_INFO, "[UCP] getWidgetDisplay: getVoicemailFolders returned " . count($fetchedFolders) . " folders: " . implode(',', array_keys($fetchedFolders)));
				// Get counts for each fetched folder
				foreach ($fetchedFolders as $key => $folderData) {
					$count = $imapClient->getMessageCount($folderData['path'], false); // Get total count
					$fetchedFolders[$key]['count'] = ($count !== false) ? $count : 0;
					$total += $fetchedFolders[$key]['count'];
				}
				$folders = $fetchedFolders;
			} else {
				// Fallback or show error?
			}
		} else {
			// Original logic for non-IMAP
			$localFolders = $this->UCP->FreePBX->Voicemail->getFolders(); 
			foreach ($localFolders as $folder) {
				// Assuming $folder structure is ['folder' => 'FOLDER_NAME']
				$folderName = $folder['folder'];
				$count = $this->UCP->FreePBX->Voicemail->getMessagesCountByExtensionFolder($ext, $folderName);
				$folders[$folderName] = [
					'key' => $folderName,
					'label' => ucfirst(strtolower($folderName)), // Simple label generation
					'path' => $folderName, // Path might not be relevant for local
					'count' => $count
				];
				$total += $count;
			}
			// Ensure INBOX exists for local mode
			if (!isset($folders['INBOX'])) {
				$inboxCount = $this->UCP->FreePBX->Voicemail->getMessagesCountByExtensionFolder($ext, 'INBOX');
				$folders['INBOX'] = [
					'key' => 'INBOX',
					'label' => 'INBOX',
					'path' => 'INBOX',
					'count' => $inboxCount
				];
				$total += $inboxCount;
			}
		}
		// === END FIX ===

		// === FIX: Sort folders: INBOX first, then alphabetically ===
		uksort($folders, function($a, $b) {
			if ($a == 'INBOX') return -1;
			if ($b == 'INBOX') return 1;
			// Ensure comparison is case-insensitive for folder names
			return strcasecmp($a, $b);
		});
		// === END FIX ===
		
		$messages = array(); // Messages likely loaded by ajax later via bootstrap table url

		$displayvars            = array(
			"showPlayback" => $this->playback,
			"showDownload" => $this->download,
		);
		$displayvars['ext']     = $ext;
		$displayvars['folders'] = $folders; // Pass the correctly fetched folders

		$sf = $this->UCP->FreePBX->Media->getSupportedFormats();
		if (!empty($this->UCP->FreePBX->Voicemail->displayMessage['message'])) {
			$displayvars['message'] = $this->UCP->FreePBX->Voicemail->displayMessage;
		}

		$displayvars['settings']        = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($ext);
		$reqFolder                      = 'INBOX';
		$c                              = isset($folders[$reqFolder]['count']) ? $folders[$reqFolder]['count'] : 0; 
		$displayvars['folder']          = $reqFolder;
		$displayvars['total']           = $total;
		$displayvars['extension']       = $ext;
		$displayvars['supportedRegExp'] = implode("|", array_keys($sf['in']));
		$displayvars['supportedHTML5']  = implode(",", $this->UCP->FreePBX->Media->getSupportedHTML5Formats());
		$displayvars['mailboxes']       = $this->extensions;

		$mainDisplay = $this->load_view(__DIR__ . '/views/widget.php', $displayvars);

		$html .= $mainDisplay;

		$display = array(
			'title' => _("Voicemail"),
			'html'  => $html
		);

		return $display;
	}

	/**
	 * Get the HTML content for the settings panel of a Voicemail widget.
	 *
	 * Checks if settings are enabled for the user. If so, builds tabbed content for
	 * Voicemail Settings, Greetings (if enabled), and VMX Locator (if enabled and applicable).
	 * Uses appropriate data sources (IMAP/local) for greetings.
	 *
	 * @param string|int $id The extension ID for which to display settings.
	 * @return array|null An array containing the settings title and HTML, or null if settings are disabled.
	 */
	public function getWidgetSettingsDisplay($id) {
		$ext = !empty($id) ? $id : '';

		if (!$this->settings) {
			return null;
		}

		$tabcontent = array();

		// Voicemail Settings Tab (Always shown if settings are enabled)
		$displayvars_vm = [];
		$displayvars_vm['settings'] = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($id);
		$tabcontent['vmsettings'] = array(
			"name"    => _("Voicemail Settings"),
			"content" => $this->load_view(__DIR__ . '/views/vmsettings.php', $displayvars_vm)
		);

		// Greetings Tab (Conditionally shown and uses correct data source)
		if ($this->greetings) {
			$displayvars_greet = [];
			$sf = $this->UCP->FreePBX->Media->getSupportedFormats();
			$displayvars_greet['supported'] = $sf;
			$displayvars_greet['settings'] = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($id); // Still need mailbox settings for context
			
			// === FIX: Use getGreetings which handles IMAP vs Local ===
			$displayvars_greet['greetings'] = $this->getGreetings($id); 
			// === END FIX ===
			
			$displayvars_greet['short_greetings'] = $this->UCP->FreePBX->Voicemail->greetings; // Keep this for standard greeting types
			
			// Debug: Log what we have
			freepbx_log(FPBX_LOG_INFO, "[UCP] Greetings debug - greetings keys: " . implode(',', array_keys($displayvars_greet['greetings'])));
			freepbx_log(FPBX_LOG_INFO, "[UCP] Greetings debug - short_greetings keys: " . implode(',', array_keys($displayvars_greet['short_greetings'])));
			
			$tabcontent['greetings'] = array(
				"name"    => _("Greetings"),
				"content" => $this->load_view(__DIR__ . '/views/greetings.php', $displayvars_greet)
			);
		}

		// VmX Tab (Logic seems correct, no changes needed)
		if ($this->_checkVmX($id)) {
			$displayvars_vmx = array(
				'settings' => $this->Vmx->getSettings($ext),
				'fmfm'     => 'FM' . $ext,
				'enabled'  => $this->Vmx->isInitialized($ext) && $this->Vmx->isEnabled($ext)
			);
			$tabcontent['vmx'] = array(
				"name"    => "VMX",
				"content" => $this->load_view(__DIR__ . '/views/vmx.php', $displayvars_vmx)
			);
		}

		// Prepare final display variables
		$displayvars_final = [];
		$displayvars_final['tabcontent'] = $tabcontent;

		// Load the main settings view
		$display = array(
			'title' => _("Voicemail"),
			'html'  => $this->load_view(__DIR__ . '/views/settings.php', $displayvars_final)
		);

		return $display;
	}

	/**
	 * Get static settings required by the frontend JavaScript.
	 *
	 * Includes playback/download permissions, supported media formats,
	 * assigned mailboxes/extensions, and Scribe status.
	 *
	 * @return array An associative array of static settings.
	 */
	public function getStaticSettings() {
		$sf = $this->UCP->FreePBX->Media->getSupportedFormats();
		return array(
			"showPlayback"    => $this->playback,
			"showDownload"    => $this->download,
			"supportedRegExp" => implode("|", array_keys($sf['in'])),
			"supportedHTML5"  => implode(",", $this->UCP->FreePBX->Media->getSupportedHTML5Formats()),
			"mailboxes"       => $this->voicemails,
			"extensions"      => $this->extensions,
			"isScribeEnabled" => ($this->UCP->FreePBX->Modules->checkStatus("scribe") && $this->UCP->FreePBX->Scribe->isLicensed())
		);
	}

	/**
	 * Get a list of voicemail boxes available within the context of a given extension.
	 *
	 * @param string|int $ext The reference extension to determine the voicemail context.
	 * @return array A sorted list of voicemail box numbers within the context.
	 */
	public function getVoicemails($ext) {
		$Voicemails = [];
		if (!empty($ext)) {
			$vmref = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($ext);
			if (!empty($vmref["vmcontext"])) {
				$vmcontext = $vmref["vmcontext"];
				$vms       = $this->UCP->FreePBX->Voicemail->getVoicemail();
				if (!empty($vms[$vmcontext])) {
					foreach ($vms[$vmcontext] as $vm => $content) {
						if ($content["name"] != "FreePBXUCPTemplateCreator") {
							$Voicemails[$vm] = $vm;
						}
					}
					sort($Voicemails);
				}
			}
		}
		return $Voicemails;
	}

	/**
	 * Poll assigned extensions for new voicemail counts (for notifications).
	 *
	 * Iterates through extensions assigned to the user, determines if IMAP or local storage
	 * is used, and fetches the count of *unread* messages in the INBOX.
	 *
	 * @return array Status and counts for each assigned extension's INBOX.
	 */
	public function poll() {
		try {
			$extensions = $this->extensions;
			if(empty($extensions)) {
				return array("status" => false, "message" => "No extensions found");
			}
			
			$out = array();
			$notify = false;
			foreach($extensions as $extension) {
				// Get mailbox data to check IMAP status
				$mailbox = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($extension);
				$context = !empty($mailbox) ? ($mailbox['vmcontext'] ?? 'default') : 'default'; // Get context safely
				
				$imapEnabled = $this->isImapEnabled($extension); // Use helper method
				
				$count = 0; // Default count
				if ($imapEnabled) {
					$imapClient = $this->_setupImapClient($extension, $context);

					if ($imapClient) {
					    // Use the new efficient count method for UNREAD in INBOX
						$imapCount = $imapClient->getMessageCount($inboxPath, true); // true = only unread
						if ($imapCount !== false) {
							$count = $imapCount;
						} else {
							// IMAP count failed, log error and report 0
							$count = 0; 
						}
					} else {
						// IMAP client setup failed, log error and report 0
						$count = 0; 
					}
				} else {
					// IMAP not enabled, use local count (only for INBOX new messages)
					$count = $this->UCP->FreePBX->Voicemail->getMessagesCountByExtensionFolder($extension, 'INBOX', false); // false = only new
				}
				
				$out[$extension] = array("status" => $count, 'imap' => $imapEnabled);
			}
			
			return array("status" => true, "boxes" => $out);
		} catch (\Exception $e) {
			return array("status" => false, "message" => $e->getMessage());
		}
	}

	/**
	 * Refactored: Refresh folder list and counts for the sidebar.
	 * Delegates to IMAP or local methods based on configuration.
	 *
	 * @param string $extension Extension number
	 * @return array Structured data for the UCP widget
	 */
	public function refreshfoldercount($extension) {
		$data = [
			'status' => false,
			'message' => '',
			'show' => false,
			'total' => 0,
			'totalInboxCount' => 0,
			'imap' => false,
			'imapConnection' => false, // Track connection success specifically for IMAP
			'folders' => []
		];
		
		try {
			if (!$this->_checkExtension($extension)) {
				$data['message'] = _("Extension not authorized");
				return $data;
			}
			
			$mailbox = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($extension);
			$context = !empty($mailbox) ? ($mailbox['vmcontext'] ?? 'default') : 'default';
			$imapEnabled = $this->isImapEnabled($extension);
			$data['imap'] = $imapEnabled; // Reflect configuration

			$totalCount = 0;
			$inboxCount = 0;
			$folders = [];

			if ($imapEnabled) {
				// --- IMAP Path ---
				$imapClient = $this->_setupImapClient($extension, $context);
				if ($imapClient) {
					$data['imapConnection'] = true; // Mark connection successful

					// Get folder list directly from IMAP client
					$availableFolders = $imapClient->getVoicemailFolders(); // Returns ['key' => ..., 'path' => ..., 'label' => ...]

					// Get counts for each folder using the efficient imap_status method
					foreach ($availableFolders as $folderInfo) {
						$folderPath = $folderInfo['path'];
						$folderKey = $folderInfo['key']; // Use the key provided by getVoicemailFolders
						$count = 0; // Default count
						
						// Use the new efficient count method for ALL messages
						// Use the folder key (simple name) that's already provided by getVoicemailFolders
						$folderName = $folderInfo['key'];
						
						// Get total count
						$folderCount = $imapClient->getMessageCount($folderName, false); // false = all messages
						$count = ($folderCount !== false) ? $folderCount : 0;
						
						// Get unread count
						$unreadCount = $imapClient->getMessageCount($folderName, true); // true = unread only
						$unread = ($unreadCount !== false) ? $unreadCount : 0;
						
										// Get high priority count by fetching messages and counting high priority ones
				$highPriority = 0;
				try {
					$messages = $imapClient->getMessages($folderName, true); // Get all messages
					if (is_array($messages)) {
						foreach ($messages as $message) {
							if (isset($message['priority']) && $message['priority'] === 'high') {
								$highPriority++;
							}
						}
					}
				} catch (\Exception $e) {
				}
						
						$folders[$folderKey] = [
							'count' => $count,
							'unread' => $unread,
							'high_priority' => $highPriority,
							'label' => $folderInfo['label'],
							'path' => $folderPath
						];
										$totalCount += $count;
										if ($folderKey === 'INBOX') {
											$inboxCount = $count;
									}
					}
				} else {
					// IMAP configured but connection failed
					$data['message'] = _("IMAP connection failed. Folder counts may be inaccurate.");
					// We intentionally return empty folders here as IMAP is the expected source
					$folders = [];
					$totalCount = 0;
					$inboxCount = 0;
				}
			} else {
				// --- Local Storage Path ---
				$localFolders = $this->_getLocalVoicemailFolders($extension, $context);

				// Get counts for each local folder
				foreach ($localFolders as $folderKey => $folderLabel) {
					// Pass false to get NEW messages count for INBOX, true for others?
					// For consistency with sidebar display, let's get TOTAL count for all folders for now.
					$count = $this->UCP->FreePBX->Voicemail->getMessagesCountByExtensionFolder($extension, $folderKey, true); // true = All messages
					$folders[$folderKey] = [
						'count' => $count,
						'label' => $folderLabel,
						'path' => $folderKey // Local path is just the key name
					];
					$totalCount += $count;
					if ($folderKey === 'INBOX') {
						$inboxCount = $count;
					}
				}
			}
			
			// Sort folders (INBOX first, then alphabetically)
			uksort($folders, function ($a, $b) {
				if ($a === 'INBOX') return -1;
				if ($b === 'INBOX') return 1;
				return strcasecmp($a, $b);
			});
			
			// Finalize response data
			$data['status'] = true;
			$data['folders'] = $folders;
			$data['total'] = $totalCount;
			$data['totalInboxCount'] = $inboxCount;
			$data['show'] = ($totalCount > 0);
			

		} catch (\Exception $e) {
			$data['status'] = false; // Ensure status is false on exception
			$data['message'] = _("Error refreshing folder counts: ") . $e->getMessage();
			// Reset counts/folders on error
			$data['folders'] = [];
			$data['total'] = 0;
			$data['totalInboxCount'] = 0;
		}

			return $data;
	}

	/**
	 * Get list of voicemail folders from the local filesystem
	 * 
	 * @param string $extension Extension number
	 * @param string $context Voicemail context
	 * @return array Associative array of folder names mapped to display names
	 */
	private function _getLocalVoicemailFolders($extension, $context = 'default') {
		$folders = [];
		$vmSpoolDir = '/var/spool/asterisk/voicemail';
		$extensionDir = "$vmSpoolDir/$context/$extension";
		
		
		if (file_exists($extensionDir) && is_dir($extensionDir)) {
			// Scan for folders in this extension directory
			$dirItems = scandir($extensionDir);
			
			if ($dirItems) {
				foreach ($dirItems as $item) {
					// Skip . and .. and files
					if ($item === '.' || $item === '..' || !is_dir("$extensionDir/$item")) {
						continue;
					}
					
					// Add to folders list
					$displayName = ucfirst($item); // Capitalize for display
					$folders[$item] = $displayName;
					
				}
			}
		} else {
		}
		
		// Ensure INBOX is included
		if (!isset($folders['INBOX'])) {
			$folders['INBOX'] = _('INBOX');
		}
		
		return $folders;
	}



	/**
	 * Determine what commands are allowed
	 *
	 * Used by Ajax Class to determine what commands are allowed by this class
	 *
	 * @param string $command The command requested via AJAX.
	 * @param array $settings The settings passed via the AJAX request (typically $_REQUEST or $_POST).
	 * @return bool True if pass
	 */
	public function ajaxRequest($command, $settings) {
		switch ($command) {
			case 'getfolders':
			case 'grid':
			case 'listen':
			case 'rebuildVM':
			case 'movetofolder':
			case 'forwards':
			case 'callme':
			case 'forward':
			case 'refreshfoldercount':
				$extension = $_REQUEST['ext'] ?? null;
				$checkResult = $this->_checkExtension($extension);
				return $checkResult;
				break;
			case 'delete':
				$extension = $_REQUEST['ext'] ?? null;
				$type = $_REQUEST['type'] ?? '';
				
				// Check if this is a greeting deletion
				$isGreeting = ($type === 'greeting') || 
							  (isset($_REQUEST['is_greeting']) && $_REQUEST['is_greeting'] === 'true') ||
							  (isset($_REQUEST['context']) && $_REQUEST['context'] === 'greeting');
				
				if ($isGreeting) {
					// For greeting deletion, require both greetings permission and extension check
					$checkResult = $this->greetings && $this->_checkExtension($extension);
				} else {
					// For regular message deletion, just check extension
					$checkResult = $this->_checkExtension($extension);
				}
				
				if (!$checkResult) {
					return false;
				}
				// If authorized, return true to allow ajaxHandler to process the command
				return true;
			case "stream":
				$requestedExt = $_REQUEST['ext'] ?? $_REQUEST['mailbox'] ?? '';
				$playbackAllowed = $this->playback;
				$extensionAllowed = $this->_checkExtension($requestedExt);
				$result = $playbackAllowed && $extensionAllowed;
				return $result;
				break;
			case 'record':
			case "copy":
			case 'upload':
				return $this->greetings && $this->_checkExtension($_REQUEST['ext']);
				break;
			case 'createfolder':
				return $this->_checkExtension($_REQUEST['ext']);
				break;
			case 'deletefolder':
				return $this->_checkExtension($_REQUEST['ext']);
				break;
			case 'savesettings':
				return $this->settings && $this->_checkExtension($_REQUEST['ext']);
				break;
			case 'vmxsettings':
				$ext = $_REQUEST['ext'];
				return $this->_checkExtension($ext);
				break;
			case 'checkextension':
				return true;
			case 'checkextensions':
				return true;
			case 'checkboxes':
				return true;
			case 'moveToFolderBulk':
				return true;
			case 'deleteBulk':
				return true;
			case 'markread': // Added case for mark read/unread
				$extension = $_REQUEST['ext'] ?? null;
				return $this->_checkExtension($extension);
				break;
			case 'downloadgreeting':
				$extension = $_REQUEST['ext'] ?? null;
				// Check if greetings are generally allowed and if user has access to the specific extension
				return $this->greetings && $this->_checkExtension($extension);
				break;
			default:
				return false;
				break;
		}
	}

	/**
	 * The Handler for all ajax events releated to this class
	 *
	 * Used by Ajax Class to process commands
	 *
	 * @return mixed Output if success, otherwise false will generate a 500 error serverside
	 */
	public function ajaxHandler() {
		try {
			// Turn off direct error output to prevent it from corrupting JSON responses
			@ini_set('display_errors', 0);
			
			$command = strtolower($_REQUEST['command'] ?? 'unknown');
			$ret = array("status" => false, "message" => "");
			
			switch($command) {
				case 'checkextensions':
					try {
						$result = $this->_checkExtensions($_REQUEST);
						return $result;
					} catch (\Exception $e) {
						$errorMsg = "Error in checkextensions: " . $e->getMessage();
						return array("status" => false, "error" => $errorMsg);
					}
				case 'checkextension':
					return $this->_checkExtension($_REQUEST['ext']) ? "ok" : "ko";
					break;
				case 'refreshfoldercount':
					try {
						$result = $this->refreshfoldercount($_REQUEST['ext']);
						// Return the structure exactly as it is to prevent any potential issues
						return $result;
					} catch (\Exception $e) {
						$errorMsg = "Error in refreshfoldercount: " . $e->getMessage();
						return array("status" => false, "error" => $errorMsg);
					}
					break;
				case 'stream':
					$forceDownload = !empty($_REQUEST['force_download']) && $_REQUEST['force_download'] == '1';
					$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'voicemail'; // Default to voicemail
						$ext = isset($_REQUEST['ext']) ? $_REQUEST['ext'] : '';
					$msgId = isset($_REQUEST['msg']) ? $_REQUEST['msg'] : ''; // Can be message ID (local/imap) or greeting type
					$folder = isset($_REQUEST['folder']) ? $_REQUEST['folder'] : 'INBOX'; // Relevant for voicemails

					if (empty($ext) || empty($msgId)) {
						// Log error or send 400 Bad Request
						header("HTTP/1.1 400 Bad Request");
						echo "Missing required parameters.";
							exit;
						}
						
					// Permission check
					if (!$this->_checkExtension($ext)) {
						header("HTTP/1.1 403 Forbidden");
						echo "Access Denied.";
								exit;
							}
							
					// Determine context (needed for IMAP setup if used)
					$contextData = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($ext);
					$context = !empty($contextData['vmcontext']) ? $contextData['vmcontext'] : 'default';


					// Initialize IMAP Client (might be needed for both paths if greetings are stored there)
					$this->imap = $this->_setupImapClient($ext, $context);
					if (empty($this->imap)) {
						// Decide how to handle this - maybe fallback to local for greetings? Or fail?
						header("HTTP/1.1 500 Internal Server Error");
						echo "Failed to initialize backend services.";
								exit;
					}


					try {
						if ($type === 'greeting') {
							// Stream Greeting - Pass extension, greeting name (msgId), and forceDownload flag
							// folder is not needed for streamGreeting based on current implementation
							// <<< FIX: Add missing context parameter and ensure correct order >>>
							$contextData = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($ext);
							$context = !empty($contextData['vmcontext']) ? $contextData['vmcontext'] : 'default';
							
							$this->imap->streamGreeting($ext, $context, $msgId, $forceDownload); // Pass ext, context, greetingType (msgId), forceDownload
							// <<< END FIX >>>
							// streamGreeting should handle exit/headers internally
									} else {
							// Stream Voicemail (Check if IMAP is needed - StreamVoicemail doesn't exist, use streamMessage)
							if ($this->isImapEnabled($ext)) {
								$this->imap->streamMessage($ext, $context, $msgId, $folder, $forceDownload); // Use streamMessage for IMAP voicemail
								} else {
								// Local file streaming (assuming a method exists or needs implementation)
								// $this->localStreamVoicemail($ext, $msgId, $folder, $forceDownload);
								header("HTTP/1.1 501 Not Implemented");
								echo "Local voicemail streaming is not implemented.";
							exit;
						}
							// Stream methods should handle exit/headers internally
						}
					} catch (\Throwable $t) {
						// Log the specific error
						// Send appropriate error header
						header("HTTP/1.1 500 Internal Server Error");
						echo "Error processing stream request: " . htmlspecialchars($t->getMessage()); // Avoid echoing stack trace details
						exit;
					}
					// Exit should happen within the stream methods
					exit; // Add a final exit just in case the stream methods don't
					break;
				case 'delete':
					$message_id = $_REQUEST['msg_id'] ?? $_REQUEST['msg'] ?? null;
					$ext = $_REQUEST['ext'];
					$type = $_REQUEST['type'] ?? '';
					
					if (!$this->_checkExtension($ext)) {
						return array("status" => false, "message" => _("Not Authorized"));
					}
					
					// Check if this is a greeting deletion
					$isGreeting = ($type === 'greeting') || 
								  (isset($_REQUEST['is_greeting']) && $_REQUEST['is_greeting'] === 'true') ||
								  (isset($_REQUEST['context']) && $_REQUEST['context'] === 'greeting');
					
					if ($isGreeting) {
						// Handle greeting deletion
						$status = $this->deleteGreeting($ext, $message_id);
						return array( 
							"status" => $status, 
							"message" => $status ? _("Greeting deleted successfully") : _("Failed to delete greeting") 
						);
					}
					
					// Check if this is an IMAP message
					if (isset($_REQUEST['imap']) && $_REQUEST['imap'] === 'true' && $this->isImapEnabled($ext)) {
						// --- IMAP Path ---
						$mailboxInfo = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($ext);
						$context = !empty($mailboxInfo) ? ($mailboxInfo['vmcontext'] ?? 'default') : 'default';
						$folder = isset($_REQUEST['folder']) ? $_REQUEST['folder'] : 'INBOX';
						$result = false; // Default to failure
						
						$imapClient = $this->_setupImapClient($ext, $context); // Pass ext and context
						if ($imapClient) {
						    $result = $imapClient->deleteMessage($ext, $context, $message_id, $folder); // Use deleteMessage
						} else {
						}
						
						return array(
							"status" => $result,
							"message" => $result ? _("Message deleted") : _("Failed to delete message via IMAP")
						);
					}
					
					// --- Local Storage Path ---
					$message = $this->UCP->FreePBX->Voicemail->getMessageByMessageIDExtension($message_id, $ext);
					if (!empty($message)) {
						$status = $this->UCP->FreePBX->Voicemail->deleteMessageByIDExtension($message_id, $ext);
						return array("status" => $status, "message" => $status ? _("Message deleted") : _("Failed to delete local message"));
					}
					return array("status" => false, "message" => _("Message not found"));
					break;
				case 'movetofolder': // Renamed from 'move' for clarity, matches JS function name
					$ext = basename($_POST['ext'] ?? '');
					$msg = basename($_POST['msg'] ?? '');
					$toFolder = basename($_POST['folder'] ?? ''); // This is the target folder
					$fromFolder = basename($_POST['fromFolder'] ?? 'INBOX'); // Get source folder from request
					
					if (!$this->_checkExtension($ext)) {
						return array("status" => false, "message" => _("Not Authorized"));
					}
					
					if (empty($ext) || empty($msg) || empty($toFolder) || empty($fromFolder)) {
					    return array("status" => false, "message" => _("Missing required parameters."));
					}


					// Check if IMAP is enabled for this extension
					if ($this->isImapEnabled($ext)) {
						// --- IMAP Path ---
						$mailboxInfo = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($ext);
						$context = !empty($mailboxInfo) ? ($mailboxInfo['vmcontext'] ?? 'default') : 'default';
						$result = false; // Default to failure
						$imapClient = $this->_setupImapClient($ext, $context);
						if ($imapClient) {
						    try {
						        $result = $imapClient->moveMessage($ext, $context, $msg, $toFolder, $fromFolder);
						    } catch (\Exception $e) {
						        $result = false;
						    }
						} else {
							$result = false;
						}

						$ret = array(
							"status" => $result,
							"message" => $result ? _("Message moved") : _("Failed to move message via IMAP")
						);
					} else {
						// --- Local Storage Path ---
						// Check message exists before trying to move
						$message = $this->UCP->FreePBX->Voicemail->getMessageByMessageIDExtension($msg, $ext, $fromFolder); // Check in original folder
					if (!empty($message)) {
						    try {
						        $status = $this->UCP->FreePBX->Voicemail->moveMessageByIDExtension($msg, $ext, $toFolder);
						        $ret = array("status" => $status, "message" => $status ? _("Message moved") : _("Failed to move local message"));
						    } catch (\Exception $e) {
						        $ret = array("status" => false, "message" => _("Error moving local message."));
						    }
						} else {
							$ret = array("status" => false, "message" => _("Message not found in original folder"));
						}
					}
					break; // Added break statement
				case 'markread':
					$message_id = $_REQUEST['msg_id'] ?? null;
					$ext = $_REQUEST['ext'] ?? null;
					// Convert incoming string 'true'/'false' to boolean
					$read = isset($_REQUEST['read']) && strtolower($_REQUEST['read']) === 'true';
					$folder = $_REQUEST['folder'] ?? 'INBOX';
					$isImapRequest = isset($_REQUEST['imap']) && strtolower($_REQUEST['imap']) === 'true';

					if (empty($message_id) || empty($ext)) {
						return ["status" => false, "message" => _("Missing required parameters (msg_id or ext).")];
					}
					
					if (!$this->_checkExtension($ext)) {
						return ["status" => false, "message" => _("Not Authorized")];
					}
					
					$result = false; // Default to failure
					$messageText = "";

					// Check if IMAP is configured for this extension
					if ($isImapRequest && $this->isImapEnabled($ext)) {
						// --- IMAP Path ---
						$mailboxInfo = $this->getVoicemailBoxByExtension($ext);
						$context = !empty($mailboxInfo) ? ($mailboxInfo['vmcontext'] ?? 'default') : 'default';
						$imapClient = $this->_setupImapClient($ext, $context);

						if ($imapClient) {
						$result = $imapClient->markMessage($ext, $context, $message_id, $read, $folder);
							$messageText = $result ? _("Message status updated via IMAP") : _("Failed to update message status via IMAP");
						} else {
							$messageText = _("Failed to initialize IMAP client for marking message.");
						}
					} else {
						// --- Local Storage Path ---
						// Check message exists before trying to mark (optional but good practice)
						$message = $this->UCP->FreePBX->Voicemail->getMessageByMessageIDExtension($message_id, $ext, $folder);
					if (!empty($message)) {
							$result = $this->UCP->FreePBX->Voicemail->markMessageReadByIDExtension($message_id, $ext, $read);
							$messageText = $result ? _("Message status updated locally") : _("Failed to update local message status");
						} else {
							$messageText = _("Local message not found to mark.");
						}
					}

					// Handle moveheard functionality if message was marked as read
					if ($result && $read && $this->isMoveheardEnabled()) {
						$this->moveMessageToOld($ext, $message_id, $folder);
					}

					return ["status" => $result, "message" => $messageText];
					break;
				case 'grid':
					try {
						
						// First, verify that the user is authenticated in UCP
						$authenticated = $this->verifyUcpAuthentication();
						if (!$authenticated) {
							// For direct API calls, allow proceeding if the extension check passes
						}
						
						// Get request parameters
						$folder = isset($_REQUEST['folder']) ? $_REQUEST['folder'] : 'INBOX';
						$limit = isset($_REQUEST['limit']) ? $_REQUEST['limit'] : 20;
						$order = isset($_REQUEST['order']) ? $_REQUEST['order'] : 'desc';
						$orderby = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'date'; // Changed from 'orderby' to 'sort' for consistency?
						$ext = isset($_REQUEST['ext']) ? $_REQUEST['ext'] : null;
						$search = isset($_REQUEST['search']) ? $_REQUEST['search'] : null;
						$offset = isset($_REQUEST['offset']) ? $_REQUEST['offset'] : 0;
						
						
						// Log session information for debugging
						$userInfo = $this->UCP->User->getUser();
						// Check if extension is valid
						if (empty($ext)) {
							return array("status" => false, "message" => _("Missing extension parameter"));
						}

						// Check permission using _checkExtension method
						$authorized = $this->_checkExtension($ext);
                        // <<< Log Check Extension Result >>>
						if (!$authorized) {
							return array("status" => false, "message" => _("Not Authorized"));
						}

						$context = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($ext);
                        // <<< Log Voicemail Box Info (using variable $context before reassignment) >>>
						$context = !empty($context['vmcontext']) ? $context['vmcontext'] : 'default';
						
						$messages = array();

						// Check if IMAP is enabled for this extension
						$mailbox = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($ext);
						
						// Check if imapuser exists in the options array
						$imapEnabled = false;
						if (!empty($mailbox) && !empty($mailbox['options']) && is_array($mailbox['options'])) {
							$imapEnabled = isset($mailbox['options']['imapuser']) && !empty($mailbox['options']['imapuser']);
						}
						
						$folder = isset($_REQUEST['folder']) ? $_REQUEST['folder'] : 'INBOX';

						if($imapEnabled) {
							// === START NEW ROBUST TRY/CATCH FOR IMAP PATH ===
							try {

								// Use IMAP to get messages
								$imapClient = $this->_setupImapClient($ext, $context);
								
								if(empty($imapClient)) {
									throw new \Exception("Failed to setup IMAP client."); // Throw exception if setup failed
								}

								// Test IMAP connection before proceeding (Optional but recommended)
										if (!$imapClient->getConnection()) {
									throw new \Exception("IMAP connection not established after setup.");
								}

								$imapMessages = $imapClient->getMessages($folder, true); // Get all messages first

								// Robust check if we got an array
								if (!is_array($imapMessages)) {
									$imapMessages = [];
								}

								$totalMessages = count($imapMessages);

								if($totalMessages > 0) {
									// Apply sorting (Simplified - check keys exist)
									if ($orderby == 'origtime' || $orderby == 'date') { // Assuming 'date' might come from request 'sort' param
												usort($imapMessages, function($a, $b) use ($order) {
											$dateA = $a['origtime'] ?? ($a['date'] ? strtotime($a['date']) : 0);
											$dateB = $b['origtime'] ?? ($b['date'] ? strtotime($b['date']) : 0);
											return ($order == 'asc') ? ($dateA <=> $dateB) : ($dateB <=> $dateA);
												});
											} else {
										// Add other sorting logic if needed, or log warning about unsupported sort key
											}
											
											// Apply offset and limit
									$paginatedMessages = array_slice($imapMessages, $offset, $limit);
											
											// Format messages for UCP grid
									foreach ($paginatedMessages as $message) {
												// Format the message structure to match what UCP expects
												$formattedMsg = array(
											'msg_id' => $message['id'] ?? uniqid('vm_'), // Ensure ID exists
											'fid' => $message['id'] ?? '', // Ensure ID exists
													'callerid' => htmlentities($message['callerid'] ?? 'Unknown', ENT_COMPAT | ENT_HTML401, "UTF-8"),
													// <<< DEBUG LOGGING ADDED >>>
													'date' => $message['date'] ?? '', // Use 'date' key from ImapClient (VALUE LOGGED BELOW)
													// <<< END DEBUG LOGGING >>>
													'time' => isset($message['origtime']) ? date('H:i:s', $message['origtime']) : '',
													'duration' => $message['duration'] ?? 0,
													'folder' => $message['folder'] ?? $folder,
													'read' => $message['read'] ?? false,
													'priority' => $message['priority'] ?? 'normal',
													'origtime' => $message['origtime'] ?? time(),
													'context' => $ext . '@' . $context, // Add context for playback
													'imap' => true  // Mark as IMAP message
												);
												// <<< DEBUG LOGGING ADDED >>>
												// <<< END DEBUG LOGGING >>>
												
												// Process caller ID for clickable links
												$formattedMsg['callerid'] = preg_replace("/&lt;(.*)&gt;/i", "&lt;<span class='clickable' data-type='number' data-primary='phone'>$1</span>&gt;", $formattedMsg['callerid']);
												
												$messages[] = $formattedMsg;
									}
								} else { // totalMessages was 0
									$messages = []; // Ensure messages is empty if no source messages
											}
											
											$result = array(
												"status" => true,
									"total" => $totalMessages, // Return the total count BEFORE pagination
									"rows" => $messages, // Return the paginated rows
												"imap" => true
											);
											return $result;

							} catch (\Throwable $t) { // Catch ANY error or exception in the IMAP path
								// Return a safe, empty, but successful response directly from the catch block
								return [
									'status' => true, // Indicate success to client-side
									'total' => 0,
									'rows' => [],
									'imap' => true, // Still indicate IMAP was attempted
									'error_caught' => $t->getMessage() // Optional: add error info for client debugging if needed
								];
							}
						} // End if($imapEnabled)
						
						// Legacy storage (This part remains unchanged, only executed if $imapEnabled was false initially)
							// Get messages from filesystem
							$data = $this->UCP->FreePBX->Voicemail->getMessagesByExtensionFolder($ext, $folder, $order, $orderby, $offset, $limit);
							$messages = array();
							if (!empty($data['messages'])) {
								foreach ($data['messages'] as $message) {
									$message['callerid'] = htmlentities($message['callerid'], ENT_COMPAT | ENT_HTML401, "UTF-8");
									$message['callerid'] = preg_replace("/&lt;(.*)&gt;/i", "&lt;<span class='clickable' data-type='number' data-primary='phone'>$1</span>&gt;", $message['callerid']);
									$messages[] = $message;
								}
							} else {
							}
							
							$totalCount = $this->UCP->FreePBX->Voicemail->getMessagesCountByExtensionFolder($ext, $folder);
						
						$result = array(
							"status" => true,
							"total" => $totalCount,
							"rows" => $messages,
							"imap" => false
						);
						return $result;

				} catch (\Exception $e) { // Catch exceptions in the initial setup part (before IMAP/Legacy split)
						$errorResult = array(
						"status" => false,
						"total" => 0,
						"rows" => array(),
						"error" => "Error retrieving messages: " . $e->getMessage(),
							"imap" => $imapEnabled ?? false // Use null coalescing for safety
					);
						return $errorResult;
				}
				break;
			case 'callme':
				$validUsers = array();
				$users = $this->UCP->FreePBX->Voicemail->getUsersList(true);
				foreach ($users as $user) {
					$validUsers[] = $user[0];
				}
				if (!in_array($_POST['to'], $validUsers)) {
					$return['message'] = _("Invalid Recipient");
					return $return;
				}
				$message = $this->UCP->FreePBX->Voicemail->getMessageByMessageIDExtension($_POST['id'], $_REQUEST['ext']);
				if (!empty($message)) {
					$astman = $this->UCP->FreePBX->astman;
					$status = $astman->originate(
						array(
							"Channel"  => "Local/" . $_POST['to'] . "@from-internal",
							"Exten"    => "s",
							"Context"  => "vm-callme",
							"Priority" => 1,
							"Async"    => "yes",
							"CallerID" => _("Voicemail Message") . " <" . _("VMAIL") . ">",
							"Variable" => "MSG=" . $message['path'] . "/" . $message['fid'] . ",MBOX=" . $_REQUEST['ext']
						)
					);
					if ($status['Response'] == "Success") {
						$return['status'] = true;
					}
					else {
						$return['message'] = $status['Message'];
					}
				}
				$return['message'] = ("Invalid Message ID");
				return $return;
				break;
			case 'forward':
				$validUsers = array();
				$users = $this->UCP->FreePBX->Voicemail->getUsersList(true);
				foreach ($users as $user) {
					$validUsers[] = $user[0];
				}
				if (!in_array($_POST['to'], $validUsers)) {
					$return['message'] = _("Invalid Recipient");
					return $return;
				}
				$message = $this->UCP->FreePBX->Voicemail->getMessageByMessageIDExtension($_POST['id'], $_REQUEST['ext']);
				if (!empty($message)) {
					$this->UCP->FreePBX->Voicemail->forwardMessageByExtension($_POST['id'], $_REQUEST['ext'], $_POST['to']);
					$return['status'] = true;
				}
				else {
					$return['message'] = ("Invalid Message ID");
				}
				return $return;
				break;
			case 'forwards':
				$return = array();
				$users = $this->UCP->FreePBX->Voicemail->getUsersList(true);
				$search = !empty($_REQUEST['search']) ? $_REQUEST['search'] : '';
				foreach ($users as $user) {
					if (preg_match('/' . $search . '/i', $user[1]) || preg_match('/' . $search . '/i', $user[0])) {
						$return[] = array(
							"value" => $user[0],
							"text"  => $user[1] . " (" . $user[0] . ")"
						);
					}
				}
				return $return;
				break;
			case 'vmxsettings':
				if (!$this->_checkVmX($_POST['ext'])) {
					return false;
				}

				switch ($_POST['settings']['key']) {
					case 'vmx-state':
						$m = ($_POST['settings']['value'] == 'true') ? 'enabled' : 'disabled';
						if ($m == "disabled" && $this->Vmx->isInitialized($_POST['ext'])) {
							$this->Vmx->disable($_POST['ext']);
						}
						else {
							$this->Vmx->setState($_POST['ext'], 'unavail', 'disabled');
						}
						break;
					case 'vmx-usewhen-unavailable':
						$m = ($_POST['settings']['value'] == 'true') ? 'enabled' : 'disabled';
						$this->Vmx->setState($_POST['ext'], 'unavail', $m);
						break;
					case 'vmx-usewhen-busy':
						$m = ($_POST['settings']['value'] == 'true') ? 'enabled' : 'disabled';
						$this->Vmx->setState($_POST['ext'], 'busy', $m);
						break;
					case 'vmx-usewhen-temp':
						$m = ($_POST['settings']['value'] == 'true') ? 'enabled' : 'disabled';
						$this->Vmx->setState($_POST['ext'], 'temp', $m);
						break;
					case 'vmx-opt0':
						$this->Vmx->setMenuOpt($_POST['ext'], $_POST['settings']['value'], '0', 'unavail');
						$this->Vmx->setMenuOpt($_POST['ext'], $_POST['settings']['value'], '0', 'busy');
						$this->Vmx->setMenuOpt($_POST['ext'], $_POST['settings']['value'], '0', 'temp');
						break;
					case 'vmx-opt1':
						if (empty($_POST['settings']['value'])) {
							$this->Vmx->setFollowMe($_POST['ext'], '1', 'unavail');
							$this->Vmx->setFollowMe($_POST['ext'], '1', 'busy');
							$this->Vmx->setFollowMe($_POST['ext'], '1', 'temp');
						}
						else {
							$this->Vmx->setMenuOpt($_POST['ext'], $_POST['settings']['value'], '1', 'unavail');
							$this->Vmx->setMenuOpt($_POST['ext'], $_POST['settings']['value'], '1', 'busy');
							$this->Vmx->setMenuOpt($_POST['ext'], $_POST['settings']['value'], '1', 'temp');
						}
						break;
					case 'vmx-opt2':
						$this->Vmx->setMenuOpt($_POST['ext'], $_POST['settings']['value'], '2', 'unavail');
						$this->Vmx->setMenuOpt($_POST['ext'], $_POST['settings']['value'], '2', 'busy');
						$this->Vmx->setMenuOpt($_POST['ext'], $_POST['settings']['value'], '2', 'temp');
						break;
					default:
						return false;
						break;
				}
				$return = array( "status" => true, "message" => "Saved", "alert" => "success" );
				break;
			case 'checkboxes':
				$boxes = $this->getMailboxCount();
				return array( "status" => true, "total" => $boxes['total'], "boxes" => $boxes['extensions'] );
				break;
			case 'rebuildVM':
				$ext = basename($_POST['ext']);
				$folder = basename($_POST['folder'] ?? '');
				$status = $this->UCP->FreePBX->Voicemail->rebuildVM($ext, $folder);
				$return = array( "status" => $status, "message" => "" );
				break;
			case 'movetofolder':
				$ext = basename($_POST['ext']);
				$msg = basename($_POST['msg'] ?? '');
				$folder = basename($_POST['folder'] ?? '');
				
				// Check if this mailbox uses IMAP
				if ($this->isImapEnabled($ext)) {
					// Use ImapClient for IMAP voicemails instead of AriClient
					$imapClient = $this->_setupImapClient();
					
					// Get the current folder
					$message = $this->getMessageDetails($ext, $msg);
					$currentFolder = !empty($message['folder']) ? $message['folder'] : 'INBOX';
					
					// Move message using IMAP
					$status = $imapClient->moveMessage($ext, 'default', $msg, $folder, $currentFolder);
				} else {
					// Original code for non-IMAP voicemails
					$status = $this->UCP->FreePBX->Voicemail->moveMessageByExtensionFolder($msg, $ext, $folder);
				}
				
				$return = array( "status" => $status, "message" => "" );
				break;
			case 'delete':
				$ext = basename($_POST['ext']);
				$msg = basename($_POST['msg'] ?? '');
				$type = $_POST['type'] ?? ''; // Optional: 'greeting' or 'message'
				
				// Debug logging
				
				if (empty($ext) || empty($msg)) {
					$return = array( "status" => false, "message" => _("Missing required parameters") );
					break;
				}
				
				// Determine if this is a greeting or message based on context
				// If type is explicitly set to 'greeting', or if we're in a greeting context
				$isGreeting = ($type === 'greeting') || 
							  (isset($_POST['is_greeting']) && $_POST['is_greeting'] === 'true') ||
							  (isset($_POST['context']) && $_POST['context'] === 'greeting');
				
				
				if ($isGreeting) {
					// Delete greeting
					$status = $this->deleteGreeting($ext, $msg);
					$return = array( 
						"status" => $status, 
						"message" => $status ? _("Greeting deleted successfully") : _("Failed to delete greeting") 
					);
				} else {
					// Delete voicemail message
					if ($this->isImapEnabled($ext)) {
						// Use ImapClient for IMAP voicemails instead of AriClient
						$imapClient = $this->_setupImapClient();
						
						// Get message details to determine the folder
						$message = $this->getMessageDetails($ext, $msg);
						$folder = !empty($message['folder']) ? $message['folder'] : 'INBOX';
						
						// Delete message using IMAP
						$status = $imapClient->deleteMessage($ext, 'default', $msg, $folder);
					} else {
						// Original code for non-IMAP voicemails
						$status = $this->UCP->FreePBX->Voicemail->deleteMessageByID($msg, $ext);
					}
					
					$return = array( "status" => $status, "message" => "" );
				}
				break;
			case 'movetofolderbulk': // <<< Corrected case
				// <<< Simplified Log #2 (Check if this appears) >>>

				$raw_post_data = $_POST['data'] ?? null;
				
				$moveStatus = [];
				$formData = null;

				// Check the type of the input data
				if (is_array($raw_post_data)) {
				    $formData = $raw_post_data;
				} elseif (is_string($raw_post_data)) {
				    $formData = json_decode($raw_post_data, true);
				    if (json_last_error() !== JSON_ERROR_NONE) {
				        $formData = null; // Ensure formData is null on decode failure
				    }
				} else {
				}

				// Check if we have a valid array to process
				if (!is_array($formData)) {
					return ["status" => false, "message" => _("Invalid request data format.")];
				}

				$overallStatus = true; // Assume success unless one fails
				foreach ($formData as $key => $data) {
					$ext = basename($data['ext'] ?? '');
					$msg = basename($data['msg'] ?? '');
					$toFolder = basename($data['folder'] ?? '');
					$fromFolder = basename($data['fromFolder'] ?? 'INBOX'); // Get original folder
					$status = false;

					if (empty($ext) || empty($msg) || empty($toFolder)) {
						$moveStatus[$key] = false;
						$overallStatus = false;
						continue;
					}

					if (!$this->_checkExtension($ext)) {
						$moveStatus[$key] = false;
						$overallStatus = false;
						continue;
					}
					
					// Check if IMAP is enabled for this extension
					if ($this->isImapEnabled($ext)) {
					    // --- IMAP Path ---
						$mailboxInfo = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($ext);
						$context = !empty($mailboxInfo) ? ($mailboxInfo['vmcontext'] ?? 'default') : 'default';
						$imapClient = $this->_setupImapClient($ext, $context);
						if ($imapClient) {
							try {
							    $status = $imapClient->moveMessage($ext, $context, $msg, $toFolder, $fromFolder);
							} catch (\Exception $e) {
							    $status = false;
							}
						} else {
							$status = false;
						}
					} else {
						// --- Local Storage Path ---
						// Assuming the core function needs the 'from' folder implicitly or handles it
						try {
						    $status = $this->UCP->FreePBX->Voicemail->moveMessageByIDExtension($msg, $ext, $toFolder);
						} catch (\Exception $e) {
						    $status = false;
						}
					}
					$moveStatus[$key] = $status;
					if (!$status) {
						$overallStatus = false; // Mark overall failure if any item fails
					}
				}
				// Assign the calculated result to $ret, which is the variable returned by the function
				$ret = array( "status" => $overallStatus, 'moveStatus' => $moveStatus, "message" => $overallStatus ? _("Messages moved") : _("One or more messages failed to move") );
				break;
			case 'deletebulk': // <<< Corrected case
				$deleteStatus = [];
				$formData = json_decode($_POST['data'], true);
				$overallStatus = true; // Assume success unless one fails
				foreach ($formData as $key => $data) {
					$ext = basename($data['ext'] ?? '');
					$msg = basename($data['msg'] ?? '');
					$folder = basename($data['folder'] ?? 'INBOX'); // Get original folder for IMAP delete
					$status = false;

					if (empty($ext) || empty($msg)) {
						$deleteStatus[$key] = false;
						$overallStatus = false;
						continue;
					}

					if (!$this->_checkExtension($ext)) {
						$deleteStatus[$key] = false;
						$overallStatus = false;
						continue;
					}

					// Check if IMAP is enabled for this extension
					if ($this->isImapEnabled($ext)) {
					    // --- IMAP Path ---
						$mailboxInfo = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($ext);
						$context = !empty($mailboxInfo) ? ($mailboxInfo['vmcontext'] ?? 'default') : 'default';
						$imapClient = $this->_setupImapClient($ext, $context);
						if ($imapClient) {
							$status = $imapClient->deleteMessage($ext, $context, $msg, $folder);
						} else {
							$status = false;
						}
					} else {
						// --- Local Storage Path ---
						// Note: deleteMessageByIDExtension doesn't need the folder
						$status = $this->UCP->FreePBX->Voicemail->deleteMessageByIDExtension($msg, $ext);
					}
					$deleteStatus[$key] = $status;
					if (!$status) {
						$overallStatus = false; // Mark overall failure if any item fails
					}
				}
				// Assign the calculated result to $ret for deleteBulk as well
				$ret = array( "status" => $overallStatus, 'deleteStatus' => $deleteStatus, "message" => $overallStatus ? _("Messages deleted") : _("One or more messages failed to delete") );
				break;
			case 'savesettings':
				$ext = $_POST['ext'];
				$saycid = ($_POST['saycid'] == 'true') ? true : false;
				$envelope = ($_POST['envelope'] == 'true') ? true : false;
				$delete = ($_POST['vmdelete'] == 'true') ? true : false;
				$attach = ($_POST['attach'] == 'true') ? true : false;
				$status = $this->UCP->FreePBX->Voicemail->saveVMSettingsByExtension($ext, $_POST['pwd'], $_POST['email'], $_POST['pager'], $saycid, $envelope, $attach, $delete);
				$return = array( "status" => $status, "message" => "" );
				break;
			case "upload":
				$return = array( "status" => true, "message" => "" );
				foreach ($_FILES["files"]["error"] as $key => $error) {
					if ($error == UPLOAD_ERR_OK) {
						$tmp_path = \FreePBX::Config()->get("ASTSPOOLDIR") . "/tmp";
						if (!file_exists($tmp_path)) {
							mkdir($tmp_path, 0777, true);
						}

						$extension = pathinfo($_FILES["files"]["name"][$key], PATHINFO_EXTENSION);
						$basename  = pathinfo($_FILES["files"]["name"][$key], PATHINFO_BASENAME);
						$supported = $this->UCP->FreePBX->Media->getSupportedFormats();
						if (in_array($extension, $supported['in'])) {
							$tmp_name = $_FILES["files"]["tmp_name"][$key];
							$name     = \Media\Media::cleanFileName($basename);
							if (!file_exists($tmp_path . "/vmtmp")) {
								mkdir($tmp_path . "/vmtmp");
							}
							$name = $name . "." . $extension;
							move_uploaded_file($tmp_name, $tmp_path . "/vmtmp/" . $name);
							if (!file_exists($tmp_path . "/vmtmp/" . $name)) {
								$return = array( "status" => false, "message" => sprintf(_("Voicemail not moved to %s"), $tmp_path . "/vmtmp/" . $name) );
								break;
							}
							
							// Check if IMAP greetings are enabled
							require_once(__DIR__ . '/includes/imap/ImapClient.php');
							$imapClient = new \UCP\Modules\Voicemail\Imap\ImapClient();
							
							if ($imapClient->areImapGreetingsEnabled()) {
								// Get the voicemail box context
								$vmbox = $this->getVoicemailBoxByExtension($_REQUEST['ext']);
								if (!empty($vmbox)) {
									// Try to save greeting to IMAP
									$result = $imapClient->saveGreeting($_REQUEST['ext'], $vmbox['context'], $_REQUEST['type'], $tmp_path . "/vmtmp/" . $name);
									if ($result) {
										// Successfully saved to IMAP
										$return = array("status" => true, "message" => _("Greeting saved to IMAP"));
										break;
									}
									// If IMAP failed, fall back to local files
								}
							}
							
							// Original local file method
							$this->UCP->FreePBX->Voicemail->saveVMGreeting($_REQUEST['ext'], $_REQUEST['type'], $extension, $tmp_path . "/vmtmp/" . $name);
						}
						else {
							$return = array( "status" => false, "message" => _("Unsupported file format") );
							break;
						}
					}
				}
				break;
			case "copy":
				$ext = basename($_POST['ext']);
				$source = basename($_POST['source']);
				$target = basename($_POST['target']);
				$status = $this->UCP->FreePBX->Voicemail->copyVMGreeting($ext, $source, $target);
				$return = array( "status" => $status, "message" => "" );
				break;
			case "record":
				$return = array( "status" => true, "message" => "" );
				//validate file extension
				$allowed = array( "WAV", "sln192", "sln96", "sln48", "sln44", "sln32", "sln24", "sln16", "sln12", "sln", "gsm", "g722", "alaw", "ulaw", "wav16", "wav", "aiff", "flac", "ogg", "oga", "mp3", "m4a", "mp4" );
				$filename = $_FILES['file']['name'];
				$ext = pathinfo($filename, PATHINFO_EXTENSION);
				if (empty($ext)) {
					$ext = explode("/", $_FILES['file']['type']);
					$ext = is_array($ext) && count($ext) > 0 ? $ext[1] : "";
				}
				if (!in_array($ext, $allowed)) {
					return array( "status" => false, "message" => _("Unsupported file type") );
				}
				if ($_FILES["file"]["error"] == UPLOAD_ERR_OK) {
					$tmp_path = sys_get_temp_dir();
					$tmp_path = !empty($tmp_path) ? $tmp_path : '/tmp';

					$tmp_name = $_FILES["file"]["tmp_name"];
					$name     = $_FILES["file"]["name"];
					$name     = basename($name);
					if (!file_exists($tmp_path . "/vmtmp")) {
						mkdir($tmp_path . "/vmtmp");
					}
					move_uploaded_file($tmp_name, $tmp_path . "/vmtmp/" . $name);
					if (!file_exists($tmp_path . "/vmtmp/" . $name)) {
						$return = array( "status" => false, "message" => sprintf(_("Voicemail not moved to %s"), $tmp_path . "/vmtmp/" . $name) );
						break;
					}
					
					// Use our updated saveGreeting method which handles both IMAP and local files
					$result = $this->saveGreeting($_REQUEST['ext'], $_REQUEST['type'], $tmp_path . "/vmtmp/" . $name);
					
					if ($result) {
						$return = array("status" => true, "message" => _("Greeting saved successfully"));
					} else {
						$return = array("status" => false, "message" => _("Failed to save greeting"));
					}
				}
				else {
					$return = array( "status" => false, "message" => _("Unknown Error") );
					break;
				}
				break;
			case 'listen':
				$ext = $_REQUEST['ext'];
				$msgid = $_REQUEST['msgid'];
				
				// Original local file handling
				if (!$this->_checkExtension($ext)) {
					header("HTTP/1.0 403 Forbidden");
					echo _("Forbidden");
					exit;
				}
				
				$media = $this->UCP->FreePBX->Media();
				$media->getHTML5File($msgid);
				break;
			case 'downloadgreeting':
				$extension = $_REQUEST['ext'] ?? null;
				$greetingType = $_REQUEST['greetingType'] ?? null;
				$return = ['status' => false, 'message' => 'Download failed']; // Default failure response

				if (empty($extension) || empty($greetingType)) {
					$return['message'] = _("Missing required parameters (extension or greeting type)");
					return $return;
				}

				// Check if user is authorized for this extension
				if (!$this->_checkExtension($extension)) {
					$return['message'] = _("Unauthorized");
					return $return; // Return JSON error, don't proceed to stream
				}
				
				try {
					// Get context
					$vconf = $this->UCP->FreePBX->Voicemail->getVoicemailmain($extension);
					$context = $vconf['context'] ?? 'default';
					
					// Setup IMAP Client (only if necessary and enabled)
					$imapClient = $this->_setupImapClient($extension, $context);
					
					if ($imapClient && $imapClient->areImapGreetingsEnabled()) {
						// Call streamGreeting with forceDownload = true
						$result = $imapClient->streamGreeting($extension, $context, $greetingType, true);
						
						// If streamGreeting succeeded, it handled the output and headers.
						// If it failed, it might have output an error or returned false.
						// We should exit here regardless to prevent further JSON output.
						if ($result) {
							die(); // Success, streaming handled
						} else {
							// streamGreeting failed, likely outputted an error, or returned false silently
							// Log an additional error here just in case
							freepbx_log(FPBX_LOG_ERROR, "[Voicemail] ajaxHandler/downloadgreeting: ImapClient::streamGreeting returned false for ext $extension, type $greetingType");
							// Avoid sending JSON if streamGreeting might have sent headers/output
							die(); 
						}
					} else {
						// Handle case where IMAP greetings are disabled or client setup failed
						// TODO: Add local greeting download logic if needed
						$return['message'] = _("IMAP Greetings are disabled or IMAP client failed to initialize.");
						// Note: Local download might not be implemented.
						return $return;
					}
				} catch (\Exception $e) {
					freepbx_log(FPBX_LOG_ERROR, "[Voicemail] Exception in ajaxHandler/downloadgreeting: " . $e->getMessage());
					$return['message'] = _("Server error occurred during download attempt.");
					return $return;
				}
				break;
			case 'createfolder':
				$ext = $_REQUEST['ext'] ?? '';
				$folderName = $_REQUEST['folder_name'] ?? '';
				
				if (!$this->_checkExtension($ext)) {
					return array("status" => false, "message" => _("Not Authorized"));
				}
				
				if (empty($ext) || empty($folderName)) {
					return array("status" => false, "message" => _("Missing required parameters"));
				}
				
				$result = $this->createFolder($ext, $folderName);
				return $result;
				break;
			case 'deletefolder':
				$ext = $_REQUEST['ext'] ?? '';
				$folderName = $_REQUEST['folder_name'] ?? '';
				
				if (!$this->_checkExtension($ext)) {
					return array("status" => false, "message" => _("Not Authorized"));
				}
				
				if (empty($ext) || empty($folderName)) {
					return array("status" => false, "message" => _("Missing required parameters"));
				}
				
				$result = $this->deleteFolder($ext, $folderName);
				return $result;
				break;
			default:
				$ret = ["status" => false, "message" => "Unknown command: " . $command];
				break;
		}
		
		return $ret;

				} catch (\Exception $e) {
		// Return error structure
		return array('status' => false, 'message' => "Exception: " . $e->getMessage());
	} catch (\Error $e) { // Catch fatal errors too
		// Return error structure
		return array('status' => false, 'message' => "Fatal Error: " . $e->getMessage());
	}
	}

	/**
	 * The Handler for quiet events
	 *
	 * Used by Ajax Class to process commands in which custom processing is needed
	 *
	 * @return mixed Output if success, otherwise false will generate a 500 error serverside
	 */
	public function ajaxCustomHandler() {
		switch ($_REQUEST['command']) {
			default:
				return false;
				break;
		}
		
		// return $return; // REMOVED: Redundant as default case handles returning false
	}

	/**
	 * Check multiple extensions for authorization and gather their folder counts.
	 * 
	 * Used primarily by the 'checkextensions' AJAX command for polling updates 
	 * across potentially multiple assigned voicemail widgets.
	 *
	 * @param array $VMextensions Associative array of extensions (key=extension, value=ignored).
	 * @return array An array where keys are authorized extensions and values are the result of refreshfoldercount().
	 */
	private function _checkExtensions(array $VMextensions) {
		unset($VMextensions["module"]);
		unset($VMextensions["command"]);
		$result = [];
		foreach ($VMextensions as $vm => $v) {
			if ($this->_checkExtension($vm)) {
				$result[$vm] = $this->refreshfoldercount($vm);
				continue;
			}
		}
		return $result;
	}

	/**
	 * Check if the current UCP user is authorized for a specific extension.
	 * 
	 * Verifies that the Voicemail module is enabled for the user and that the
	 * provided extension is in the list of extensions assigned to the user.
	 *
	 * @param string|int $extension The extension number to check.
	 * @return bool True if the user is authorized for the extension, false otherwise.
	 */
	private function _checkExtension($extension) {
		// Check if module is enabled
		if (!$this->enabled) {
			return false;
		}
		
		// Check if extension is empty
		if (empty($extension)) {
			return false;
		}
		
		// Get allowed extensions for this user
		$extensions = $this->extensions;
		
		// Check if the requested extension is in the allowed list
		$authorized = in_array($extension, $extensions);
		
		return $authorized;
	}

	/**
	 * Check if the VMX Locator feature is enabled and the user is authorized for the extension.
	 *
	 * @param string|int $extension The extension number to check.
	 * @return bool True if VMX is enabled for the user AND the user is authorized for the extension, false otherwise.
	 */
	private function _checkVmX($extension) {
		if (!$this->vmxenabled) {
			return false;
		}
		$extensions = $this->extensions;
		return in_array($extension, $extensions);
	}

	/**
	 * Get Mailbox Counts
	 * @return array Count of the mailboxes
	 */
	public function getMailboxCount($force=false) {
		// <<< TEMPORARY TEST: Always force refresh to bypass cache >>>
		// $force = true; // REVERTED
		// <<< END TEMPORARY TEST >>>

		$return = array();
		$extensions = $this->extensions;
		$total = 0;
		$extensions = is_array($extensions) ? $extensions : array();

		foreach ($extensions as $extension) {
			$fvm = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($extension);
			$context = !empty($fvm['vmcontext']) ? $fvm['vmcontext'] : 'default';
			if (empty($context)) {
				continue; // Skip if context is missing
			}
			
			$newMessages = 0;

			// Check if IMAP is enabled for this extension
			if ($this->isImapEnabled($extension)) {
				$imapClient = $this->_setupImapClient($extension, $context);
				if ($imapClient) {
					$imapCount = $imapClient->getInboxUnseenCount();
					if ($imapCount !== false) {
						$newMessages = $imapCount;
					} else {
						$newMessages = 0; // Default to 0 on IMAP failure
					}
				} else {
					$newMessages = 0; // Default to 0 on IMAP setup failure
				}
			} else {
				// IMAP not enabled, use local count for INBOX folder
				$newMessages = $this->UCP->FreePBX->Voicemail->getMessagesCountByExtensionFolder($extension, 'INBOX', false); // Only count new messages
			}

			// Add the count to the results
			$total += $newMessages;
			$return['extensions'][$extension] = $newMessages;
		}
		
		$return['total'] = $total;
		return $return;
	}

	public function grid($id, $page = 1, $saesearch = '', $searching = false, $read = '', $sort = '', $order = '') {
		$start = microtime(true);
		
		// Initialize response
		$response = [
			'status' => true,
			'total' => 0,
			'rows' => [],
			'imap' => false // Default, will be updated by getMessages
		];
		
		// Get request parameters for pagination/sorting
		$folder = !empty($_REQUEST['folder']) ? $_REQUEST['folder'] : 'INBOX';
		$limit = isset($_REQUEST['limit']) ? intval($_REQUEST['limit']) : 20;
		$order = isset($_REQUEST['order']) && in_array(strtolower($_REQUEST['order']), ['asc', 'desc']) ? strtolower($_REQUEST['order']) : 'desc';
		$orderby = isset($_REQUEST['sort']) ? $_REQUEST['sort'] : 'origtime'; // 'sort' is often used by bootstrap-table
		$offset = isset($_REQUEST['offset']) ? intval($_REQUEST['offset']) : 0;
		$ext = $id; // Use the passed $id as the extension
		
		// Ensure valid extension
		if (!$this->_checkExtension($ext)) {
			return ['status' => false, 'message' => 'Invalid Extension'];
		}
		// Call the unified getMessages method
		try {
			$messageData = $this->getMessages($ext, $folder, $offset, $limit, $orderby, $order);
			// <<< DEBUG LOGGING ADDED (Immediately after getMessages) >>>
			// <<< END DEBUG LOGGING >>>

			// Update response with data from getMessages
			$response['total'] = $messageData['total'];
			$response['imap'] = $messageData['imap'];
			$messages = $messageData['rows'];

			// Apply UI formatting (Clickable CID)
			$formattedMessages = [];
							if (!empty($messages)) {
					foreach ($messages as $message) {
						// Ensure callerid exists before formatting
						if (isset($message['callerid'])) {
							$raw_cid = $message['callerid'];
							// Format CID only if it contains the <...> pattern
							if (preg_match("/(.*?)&lt;(.*?)&gt;/(?s)", $raw_cid, $matches)) {
								$name = trim(htmlentities($matches[1], ENT_COMPAT | ENT_HTML401, "UTF-8"));
								$number = trim(htmlentities($matches[2], ENT_COMPAT | ENT_HTML401, "UTF-8"));
								$message['callerid'] = $name . " &lt;<span class='clickable' data-type='number' data-primary='phone'>" . $number . "</span>&gt;";
			} else {
								// Otherwise, just escape the raw CID
								$message['callerid'] = htmlentities($raw_cid, ENT_COMPAT | ENT_HTML401, "UTF-8");
							}
						}
						
						// Ensure other critical fields exist (add defaults if missing - defensive coding)
						$message['msg_id'] = $message['msg_id'] ?? ($message['id'] ?? uniqid('vm_'));
						$message['duration'] = $message['duration'] ?? 0;
						
						$formattedMessages[] = $message;
					}
				}
			$response['rows'] = $formattedMessages;
			
		} catch (\Exception $e) {
			return ['status' => false, 'message' => _('Error retrieving messages: ') . $e->getMessage()];
		}
			
			$endTime = microtime(true);
			$executionTime = ($endTime - $start) * 1000; // Convert to milliseconds

			// --- ADD DEBUG LOG --- 
			if (!empty($response['rows'])) {
				} else {
			}
			// --- END DEBUG LOG ---

			return $response;
	}

	/**
	 * Check if IMAP is enabled for the given extension
	 *
	 * @param string $extension Extension to check
	 * @return bool True if IMAP is enabled for this extension
	 */
	private function isImapEnabled($extension) {
		
		try {
			// Get the mailbox data for this extension
			$mailbox = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($extension);
			
			// Check if options are set and imapuser exists and is not empty
			if (!empty($mailbox) && 
				isset($mailbox['options']) && 
				is_array($mailbox['options']) &&
				isset($mailbox['options']['imapuser']) && 
				!empty($mailbox['options']['imapuser'])) {
				
				// If imapuser is set, IMAP is enabled
				return true;
			}
			
			// If we get here, IMAP is not enabled
			return false;
		} catch (\Exception $e) {
		return false;
		}
	}

	/**
	 * Check if moveheard is enabled in voicemail.conf
	 *
	 * @return bool True if moveheard is enabled
	 */
	private function isMoveheardEnabled() {
		try {
			$vmconf = $this->_getVoicemailConf();
			return isset($vmconf['moveheard']) && $vmconf['moveheard'] === 'yes';
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Move a message to the Old folder when it's marked as read (moveheard behavior)
	 *
	 * @param string $extension The extension
	 * @param string $messageId The message ID
	 * @param string $fromFolder The source folder
	 * @return bool True if successful
	 */
	private function moveMessageToOld($extension, $messageId, $fromFolder) {
		try {
			// Don't move if already in Old folder
			if ($fromFolder === 'Old') {
				return true;
			}

			// Ensure Old folder exists
			$this->ensureFolderExists($extension, 'Old');

			// Use existing moveVoicemail method
			$result = $this->moveVoicemail($messageId, 'Old', $extension, $fromFolder);
			
			if ($result && $result['status']) {
				freepbx_log(FPBX_LOG_INFO, "[Voicemail UCP] Moved message $messageId to Old folder for extension $extension");
				return true;
			} else {
				freepbx_log(FPBX_LOG_ERROR, "[Voicemail UCP] Failed to move message $messageId to Old folder for extension $extension");
				return false;
			}
		} catch (\Exception $e) {
			freepbx_log(FPBX_LOG_ERROR, "[Voicemail UCP] Exception in moveMessageToOld: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Ensure a folder exists for the given extension
	 *
	 * @param string $extension The extension
	 * @param string $folderName The folder name to ensure exists
	 * @return bool True if folder exists or was created successfully
	 */
	private function ensureFolderExists($extension, $folderName) {
		try {
			// Check if folder already exists
			$existingFolders = array();
			
			if ($this->isImapEnabled($extension)) {
				// Check IMAP folders
				$mailboxInfo = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($extension);
				$context = !empty($mailboxInfo) ? ($mailboxInfo['vmcontext'] ?? 'default') : 'default';
				$imapClient = $this->_setupImapClient($extension, $context);
				if ($imapClient) {
					$imapFolders = $imapClient->getVoicemailFolders();
					if (!empty($imapFolders)) {
						foreach ($imapFolders as $folderKey => $folderData) {
							$existingFolders[] = strtoupper($folderKey);
						}
					}
				}
			} else {
				// Check local folders
				$localFolders = $this->UCP->FreePBX->Voicemail->getFolders();
				if (!empty($localFolders)) {
					foreach ($localFolders as $folder) {
						$existingFolders[] = strtoupper($folder['folder']);
					}
				}
			}

			// If folder doesn't exist, create it
			if (!in_array(strtoupper($folderName), $existingFolders)) {
				freepbx_log(FPBX_LOG_INFO, "[Voicemail UCP] Creating $folderName folder for extension $extension");
				$result = $this->createFolder($extension, $folderName);
				return $result && $result['status'];
			}

			return true;
		} catch (\Exception $e) {
			freepbx_log(FPBX_LOG_ERROR, "[Voicemail UCP] Exception in ensureFolderExists: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Helper method to get [general] section settings from voicemail.conf.
	 *
	 * @return array An associative array of the settings found in the [general] section.
	 */
	private function _getVoicemailConf() {
		$vmconf = array();
		if (file_exists('/etc/asterisk/voicemail.conf')) {
			$conf = file_get_contents('/etc/asterisk/voicemail.conf');
			$lines = explode("\n", $conf);
			$inGeneral = false;
			
			foreach ($lines as $line) {
				$line = trim($line);
				if (empty($line) || $line[0] == ';' || $line[0] == '#') {
					continue;
				}
				
				// Check for [general] section
				if ($line == '[general]') {
					$inGeneral = true;
					continue;
				} else if ($line[0] == '[') {
					$inGeneral = false;
					continue;
				}
				
				// Get settings from general section
				if ($inGeneral && strpos($line, '=') !== false) {
					list($key, $value) = explode('=', $line, 2);
					$key = trim($key);
					$value = trim($value);
					$vmconf[$key] = $value;
				}
			}
		}
		return $vmconf;
	}

	/**
	 * Get greetings for an extension.
	 * If IMAP is enabled, ONLY IMAP is used. Failure returns empty array.
	 *
	 * @param string $extension The extension
	 * @return array Array of greetings (associative, keyed by type)
	 */
	public function getGreetings($extension) {
		$vmbox = $this->getVoicemailBoxByExtension($extension);

		if ($this->isImapEnabled($extension)) {
		if (empty($vmbox)) {
				return [];
			}
			$context = $vmbox['context'] ?? 'default';
			$imapClient = $this->_setupImapClient($extension, $context);

			if ($imapClient && $imapClient->areImapGreetingsEnabled()) {
				$greetings = $imapClient->getGreetings($extension, $context);

				// Check for IMAP fetch failure
				if ($greetings === null || $greetings === false) {
					return []; // Return empty on IMAP failure
				}

				// Process successful fetch (even if empty)
				$associativeGreetings = [];
				if (!empty($greetings)) {
					foreach ($greetings as $greeting) {
						$type = $greeting['type'] ?? 'unknown';
						if (!isset($associativeGreetings[$type]) || ($greeting['timestamp'] ?? 0) > ($associativeGreetings[$type]['timestamp'] ?? 0)) {
							$associativeGreetings[$type] = $greeting;
						}
					}
			} else {
				}
				return $associativeGreetings; // Return processed list (might be empty)

			} else {
				return []; // Return empty on IMAP connection/setup failure
			}
				} else {
			// IMAP not enabled - Use local file method
			$localGreetingsRaw = $this->UCP->FreePBX->Voicemail->getGreetingsByExtension($extension); // Using core function for local
			// Convert local format to associative array expected by view
			$associativeGreetings = [];
			if (!empty($localGreetingsRaw)) {
				foreach ($localGreetingsRaw as $greetingInfo) {
					if (!empty($greetingInfo['exists']) && !empty($greetingInfo['type'])) {
						// Try to find the actual file to get timestamp
						$filePath = '';
						$formats = $this->UCP->FreePBX->Media->getSupportedFormats()['in'];
						$basePath = $greetingInfo['path'] . '/' . $greetingInfo['filename']; // Base path without extension
						
						foreach (array_keys($formats) as $format) {
							if (file_exists($basePath . '.' . $format)) {
								$filePath = $basePath . '.' . $format;
						break;
					}
				}
						
						$associativeGreetings[$greetingInfo['type']] = [
							'msg_id' => $greetingInfo['type'], // Use type as ID for local? Needs review if unique ID needed.
							'type' => $greetingInfo['type'],
							'timestamp' => $filePath ? filemtime($filePath) : 0
						];
					}
				}
			}
			return $associativeGreetings;
		}
	}

	/**
	 * Save a greeting
	 * If IMAP is enabled, ONLY IMAP is used. Failure returns false.
	 * 
	 * @param string $ext Extension
	 * @param string $type Greeting type (unavail, busy, etc.)
	 * @param mixed $file File data (string) OR path (string) to the source file
	 * @return bool True on success, false on failure
	 */
	public function saveGreeting($ext, $type, $file) {
		try {
			// Check if user is authorized for this extension
			if (!$this->_checkExtension($ext)) {
				return false;
			}
			
			// --- Get Greeting Data ---
			$greetingData = null;
			$sourcePath = null; // Store path if $file is a path

			if (is_string($file) && file_exists($file)) {
				// Handle file path
				$greetingData = file_get_contents($file);
				$sourcePath = $file; // Keep track of the path for local save
			} elseif (is_string($file)) {
                // Assume $file is raw data if it's a string but not an existing path
				$greetingData = $file;
            } elseif (is_array($file) && isset($file['tmp_name']) && file_exists($file['tmp_name'])) {
				// Handle uploaded file array (legacy?) - Should probably be standardized upstream
				$greetingData = file_get_contents($file['tmp_name']);
				$sourcePath = $file['tmp_name']; // Use temp path for local save if needed
			}
			
			if (empty($greetingData)) {
				return false;
			}
			// --- End Greeting Data ---

			$vmbox = $this->getVoicemailBoxByExtension($ext);
		if (empty($vmbox)) {
			return false;
		}
		
			// --- Try IMAP First ---
			if ($this->isImapEnabled($ext)) {
				$context = $vmbox['context'] ?? 'default';
				$imapClient = $this->_setupImapClient($ext, $context);

				if ($imapClient && $imapClient->areImapGreetingsEnabled()) {
			// Try to save greeting to IMAP
					$result = $imapClient->createGreeting($ext, $context, $greetingData, $type);
			
			if ($result) {
						// Successfully saved to IMAP - We are done.
			return true;
		} else {
						// If IMAP save failed, log it and RETURN FAILURE
			return false;
		}
		} else {
					// If IMAP client setup failed, log it and RETURN FAILURE
			return false;
		}
			} else {
				// --- IMAP Not Enabled - Use Local Storage Path ---

				// Determine format - saveVMGreeting needs a path
				$format = 'wav'; // Default
				$savePath = $sourcePath; // Use original path if available

				// If we only had raw data, save it to a temp file first
				if ($savePath === null) {
					$tmpFile = tempnam(sys_get_temp_dir(), 'ucp_greeting_');
					if (file_put_contents($tmpFile, $greetingData) !== false) {
						$savePath = $tmpFile;

			} else {
						return false;
					}
				}

				// Determine format from save path if possible
				if ($savePath && strpos($savePath, '.') !== false) {
					$format = pathinfo($savePath, PATHINFO_EXTENSION);
				}

				if ($savePath === null) {
			return false;
		}
		
				// Call the core save function with the file path
				$result = $this->UCP->FreePBX->Voicemail->saveVMGreeting($ext, $type, $format, $savePath);

				// Clean up temp file if created
				if (isset($tmpFile) && file_exists($tmpFile)) {
					@unlink($tmpFile);
				}
				
				if ($result) {
					return true;
				} else {
					return false;
				}
			}
		} catch (\Exception $e) {
			// Clean up temp file if it exists from exception path
			if (isset($tmpFile) && file_exists($tmpFile)) {
				@unlink($tmpFile);
			}
			return false;
		}
	}

	/**
	 * Delete a greeting
	 * 
	 * @param string $extension Extension
	 * @param string $type Greeting type (unavail, busy, etc.)
	 * @return bool True on success, false on failure
	 */
	public function deleteGreeting($extension, $type) {
		try {
			// Check if user is authorized for this extension
			if (!$this->_checkExtension($extension)) {
				return false;
			}

			// Get the voicemail box context
		$vmbox = $this->getVoicemailBoxByExtension($extension);
		if (empty($vmbox)) {
			return false;
		}
			$context = $vmbox['context'] ?? 'default';

			if ($this->isImapEnabled($extension)) {
				// --- IMAP Path --- 
				$imapClient = $this->_setupImapClient($extension, $context);

				if ($imapClient && $imapClient->areImapGreetingsEnabled()) {
					// Assumes $type passed in from UCP is the message UID (msg_id) for IMAP greetings
					$result = $imapClient->deleteGreeting($extension, $context, $type);

					if ($result) {
						return true; // Success via IMAP
			} else {
						// If IMAP delete failed, log it and RETURN FAILURE
						return false; 
			}
		} else {
					// If IMAP client setup failed or greetings disabled, log and RETURN FAILURE
		return false;
	}
		} else {
				// --- Local Storage Path ---
				$result = $this->UCP->FreePBX->Voicemail->deleteVMGreeting($extension, $type);

				if ($result) {
					return true;
				} else {
			return false;
		}
			}
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Get the voicemail box for an extension
	 * @param string $extension The extension
	 * @return array|null Voicemail box details or null if not found
	 */
	public function getVoicemailBoxByExtension($extension) {
		
		// Get the voicemail box information from FreePBX API
		$vmbox = $this->UCP->FreePBX->Voicemail->getMailbox($extension);
		
		if (empty($vmbox)) {
			return null;
		}
		
		// Make sure we have a valid context
		if (!isset($vmbox['vmcontext']) || empty($vmbox['vmcontext'])) {
			$vmbox['context'] = 'default';
			} else {
			$vmbox['context'] = $vmbox['vmcontext'];
		}
		
		return $vmbox;
	}

	/**
	 * Verify the UCP authentication status
	 * @return boolean True if user is authenticated, false otherwise
	 */
	private function verifyUcpAuthentication() {
		// Check if we're running within UCP context
		if (isset($this->UCP) && isset($this->UCP->user)) {
			return $this->UCP->user->isAuthenticated();
		}
		
		return true;
	}

	/**
	 * Refactored: Get message details for playback or other actions.
	 * Delegates to IMAP or local methods based on configuration.
	 * If IMAP is enabled, ONLY IMAP is used. Failure returns null.
	 *
	 * @param string $extension  Extension number
	 * @param string $messageId  Message ID (UID for IMAP, filename base for local)
	 * @param string $folder     Folder name (default: INBOX)
	 * @return array|null        Message details or null if not found
	 */
	private function getMessageDetails($extension, $messageId, $folder = 'INBOX') {
		try {
			if (!$this->_checkExtension($extension)) {
					return null;
				}
				
			if ($this->isImapEnabled($extension)) {
				// --- IMAP Path ---
				$mailboxInfo = $this->getVoicemailBoxByExtension($extension);
				$context = !empty($mailboxInfo) ? ($mailboxInfo['vmcontext'] ?? 'default') : 'default';
				$imapClient = $this->_setupImapClient($extension, $context);

				if ($imapClient) {
					// Get SINGLE message details using the efficient method in ImapClient
					$message = $imapClient->getSingleMessageDetails($messageId, $folder);

					if ($message === false || $message === null) {
				return null;
			} else {
						return $message; // Return the details array
					}
				} else {
					// IMAP client setup failed
					return null; // Return null as IMAP is the expected source
				}
			} else {
				// --- Local Storage Path ---
				// Local storage uses getMessageByMessageIDExtension (pass folder)
				$message = $this->UCP->FreePBX->Voicemail->getMessageByMessageIDExtension($messageId, $extension, $folder);

				if (empty($message)) {
				return null;
				} else {
					return $message; // Return the details array
				}
			}
		} catch (\Exception $e) {
			return null; // Return null on any exception
		}
	}

	
	/**
	 * Extract duration from audio file using system command
	 * 
	 * @param string $filePath Path to audio file
	 * @return int Duration in seconds
	 */
	private function getAudioFileDuration($filePath) {
		// Default duration
		$duration = 0;
		
		// Check if file exists
		if (!file_exists($filePath)) {
			return $duration;
		}
		
		// Try to use soxi if available (part of SoX)
		$command = "which soxi 2>/dev/null";
		$soxiPath = trim(shell_exec($command));
		
		if (!empty($soxiPath)) {
			$command = "$soxiPath -D " . escapeshellarg($filePath) . " 2>/dev/null";
			$output = trim(shell_exec($command));
			
			if (is_numeric($output)) {
				$duration = (int)round($output);
				return $duration;
			}
		}
		
		// Try ffprobe as a fallback
		$command = "which ffprobe 2>/dev/null";
		$ffprobePath = trim(shell_exec($command));
		
		if (!empty($ffprobePath)) {
			$command = "$ffprobePath -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($filePath) . " 2>/dev/null";
			$output = trim(shell_exec($command));
			
			if (is_numeric($output)) {
				$duration = (int)round($output);
				return $duration;
			}
		}
		
		// Return default duration if extraction failed
		return $duration;
	}

	/**
	 * Stream a voicemail message through the UCP framework
	 *
	 * This method handles both streaming (for playback) and downloading
	 * of voicemail messages, regardless of whether they're stored locally
	 * or in an IMAP server.
	 *
	 * @return void
	 */
	public function stream() {
		// VERY EARLY LOGGING
		$ucpAvailable = isset($this->UCP) ? 'Yes' : 'No';
		$userAvailable = isset($this->UCP->user) ? 'Yes' : 'No';
		$freepbxAvailable = isset($this->UCP->FreePBX) ? 'Yes' : 'No';

		// Check if we have the required parameters
		if (!isset($_REQUEST['ext']) || !isset($_REQUEST['msg'])) {
			header('HTTP/1.0 400 Bad Request');
			echo "Missing required parameters";
			// Use return false instead of exit to allow potential further processing if needed
			return false;
		}

		$extension = $_REQUEST['ext'];
		$messageId = $_REQUEST['msg'];
		$folder = isset($_REQUEST['folder']) ? $_REQUEST['folder'] : 'INBOX';

		// Check for the force_download parameter
		$forceDownloadFlag = isset($_REQUEST['force_download']) && $_REQUEST['force_download'] == '1';

		// Call the internal helper method, passing the download flag
		$this->_streamOrDownloadFile($extension, $messageId, $folder, $forceDownloadFlag);

		// Exit after streaming attempt (regardless of success/failure as headers are sent)
							exit;
	}

	/**
	 * Unified method to handle streaming or downloading a voicemail file
	 *
	 * @param string $extension The extension number
	 * @param string $messageId The message ID
	 * @param string $folder The folder name
	 * @param bool $forceDownload True to force download, false to stream inline
	 * @return bool False on error, otherwise exits after sending data.
	 */
	private function _streamOrDownloadFile($extension, $messageId, $folder, $forceDownload) {
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}

		// Perform necessary checks (already done in public methods, but good practice)
		if (!$this->verifyUcpAuthentication()) {
			header('HTTP/1.0 401 Unauthorized');
			echo "Authentication required";
			return false;
		}

		if (!$this->_checkExtension($extension)) {
			header('HTTP/1.0 403 Forbidden');
			echo "Access denied";
			return false;
		}
		// Get mailbox context
		$mailboxInfo = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($extension);
		$context = !empty($mailboxInfo) ? $mailboxInfo['vmcontext'] : 'default';

		// Try IMAP first if enabled
		$imapEnabled = $this->isImapEnabled($extension);
		if ($imapEnabled) {
			$imapClient = $this->_setupImapClient($extension, $context);
			if ($imapClient) {
				// Use the existing forceDownload parameter in streamMessage
				$result = $imapClient->streamMessage($extension, $context, $messageId, $folder, $forceDownload);
				if ($result) {
					exit; // IMAP client handles exit
				} else {
				}
			} else {
			}
		}

		// Fallback to local storage
		$message = $this->UCP->FreePBX->Voicemail->getMessageByMessageIDExtension($messageId, $extension, $folder); // Pass folder

		if (!empty($message) && isset($message['path']) && isset($message['file'])) {
			// Adjust path for local files - use fid if available, otherwise file
			$fileName = $message['file'];
			$filePath = $message['path'] . "/" . $fileName;

			// Double check file existence
			if (!is_file($filePath)) {
				// Try using fid if 'file' didn't work (older structure?)
				if (isset($message['fid'])) {
					$fileName = $message['fid'] . '.wav'; // Assume wav if using fid
					$filePath = $message['path'] . "/" . $fileName;
				}
			}

			$fileExists = is_file($filePath);

			if ($fileExists) {
				$media = $this->UCP->FreePBX->Media;
				$mimetype = $media->getMIMEtype($filePath);
				$filesize = filesize($filePath);


				// Clean output buffer
				while (ob_get_level()) {
					ob_end_clean();
				}

				// Send headers
				header("Content-Type: " . $mimetype);
				header("Content-Length: " . $filesize);
				header("Cache-Control: no-cache, no-store, must-revalidate");
				header("Pragma: no-cache");
				header("Expires: 0");
				header('Accept-Ranges: bytes');

				$disposition = $forceDownload ? 'attachment' : 'inline';
				header('Content-Disposition: ' . $disposition . '; filename="' . basename($fileName) . '"');


				// Stream the file
				readfile($filePath);
				exit;
			} else {
			}
			} else {
		}
		
		// If we reach here, all methods failed
		header("HTTP/1.0 404 Not Found");
		echo "Voicemail message not found.";
		return false;
	}

	/**
	 * PRIVATE: Unified method to get messages (list) for a folder.
	 * Handles IMAP vs. Local storage and applies sorting/pagination for IMAP.
	 * If IMAP is enabled, ONLY IMAP is used. Failure returns empty results.
	 *
	 * @param string $extension Extension number.
	 * @param string $folder Folder name (e.g., INBOX).
	 * @param int $offset Starting offset for pagination.
	 * @param int $limit Number of messages per page.
	 * @param string $orderby Field to sort by (e.g., 'origtime').
	 * @param string $order Sort direction ('asc' or 'desc').
	 * @return array Structured result: ['rows' => [...], 'total' => X, 'imap' => true/false]
	 */
	private function getMessages($extension, $folder, $offset = 0, $limit = 20, $orderby = 'origtime', $order = 'desc') {

		$messages = [];
		$totalCount = 0;
		$imapEnabled = $this->isImapEnabled($extension);
		$result = [
			'rows' => [],
			'total' => 0,
			'imap' => $imapEnabled // Reflect if IMAP is configured
		];
		$fetchFolder = $folder; // Always fetch from the requested folder

		try {
			if ($imapEnabled) {
				$mailboxInfo = $this->getVoicemailBoxByExtension($extension);
		$context = !empty($mailboxInfo) ? $mailboxInfo['vmcontext'] : 'default';
		
				$imapClient = $this->_setupImapClient($extension, $context);
			
			if ($imapClient) {

					try {
						// Fetch ALL messages from the fetchFolder first. Filtering/Pagination happens after.
						$allMessages = []; // Initialize
						$allMessages = $imapClient->getMessages($fetchFolder, true); // Call the ImapClient method
						// Handle potential failure from getMessages more robustly
						if (!is_array($allMessages)) {
							$allMessages = []; // Force to empty array if not an array
						}

						$totalCount = count($allMessages); // Count after potential filtering

						// === FIX: Check if array is empty before sorting/slicing ===
						if ($totalCount > 0) {
							// --- Apply sorting ---
							$sortKey = $orderby ?: 'origtime'; // Default sort key
							// Check if the sort key exists in the first message (assuming uniform structure)
							if (!empty($sortKey) && isset($allMessages[0][$sortKey])) {
								usort($allMessages, function($a, $b) use ($sortKey, $order) {
									// Use null coalescing operator for safety
									$valA = $a[$sortKey] ?? 0;
									$valB = $b[$sortKey] ?? 0;

									// Handle numeric vs string comparison if necessary, assume numeric/timestamp for now
									if (is_numeric($valA) && is_numeric($valB)) {
										return ($order == 'asc') ? ($valA <=> $valB) : ($valB <=> $valA);
				} else {
										// Basic string comparison as fallback
										return ($order == 'asc') ? strcmp((string)$valA, (string)$valB) : strcmp((string)$valB, (string)$valA);
				}
								});
			} else {
								// Fallback sort if key invalid or not present - sort by origtime descending
								usort($allMessages, function($a, $b) {
									return ($b['origtime'] ?? 0) <=> ($a['origtime'] ?? 0); // Newest first default
								});
			}
							// --- End Sorting ---

							// --- Apply pagination ---
							$messages = array_slice($allMessages, $offset, $limit);
							
							// Add metadata to each message for JavaScript formatters
							foreach ($messages as &$message) {
								$message['extension'] = $extension;
								$message['folder'] = $folder;
								$message['imap'] = true;
							}
							// --- End Pagination ---
				} else {
							// If filtering resulted in zero messages, set messages to empty array
							$messages = [];
						}
					} catch (\Throwable $t) {
						// Ensure safe defaults on error
						$messages = [];
						$totalCount = 0;
					}
					// --- End Try/Catch around IMAP processing ---
					
			} else {
					// IMAP client setup failed, return empty results as IMAP is expected.
					$messages = [];
					$totalCount = 0;
					// $result['imap'] remains true because IMAP was the intended method
			}
				// Assign results for IMAP path
				$result['rows'] = $messages;
				$result['total'] = $totalCount;

		} else {
				// --- IMAP is not enabled, use local storage ---

				// Handle local folders
				$legacyData = $this->UCP->FreePBX->Voicemail->getMessagesByExtensionFolder($extension, $folder, $order, $orderby, $offset, $limit);
				$messages = $legacyData['messages'] ?? [];
				
				// Add metadata to each message for JavaScript formatters
				foreach ($messages as &$message) {
					$message['extension'] = $extension;
					$message['folder'] = $folder;
					$message['imap'] = false;
				}
				
				$result['rows'] = $messages;
				// Explicitly get total count for local storage for certainty
				$result['total'] = $this->UCP->FreePBX->Voicemail->getMessagesCountByExtensionFolder($extension, $folder, true); // true = All messages
			}

		} catch (\Exception $e) {
			// Return empty set on error
			$result['rows'] = [];
			$result['total'] = 0;
		}

		return $result;
	}

	/**
	 * Create a new voicemail folder for the specified extension.
	 * 
	 * Creates the folder both locally (if local storage is used) and on IMAP server
	 * (if IMAP is enabled for the extension).
	 *
	 * @param string $extension The extension number
	 * @param string $folderName The name of the folder to create
	 * @return array Status array with 'status' (bool) and 'message' (string)
	 */
	public function createFolder($extension, $folderName) {
		try {
			// Sanitize folder name - only allow alphanumeric, hyphens, and underscores (no spaces)
			$sanitizedFolderName = preg_replace('/[^a-zA-Z0-9\-_]/', '', $folderName);
			$sanitizedFolderName = trim($sanitizedFolderName);
			
			// Check if folder name is empty after sanitization
			if (empty($sanitizedFolderName)) {
				return array("status" => false, "message" => _("Invalid folder name"));
			}
			
			// Check if folder name is too long (Asterisk typically uses 8.3 format)
			if (strlen($sanitizedFolderName) > 8) {
				return array("status" => false, "message" => _("Folder name too long (max 8 characters)"));
			}
			
			// Check if folder name conflicts with existing folders
			$existingFolders = array();
			
			// Get existing folders from the appropriate location based on IMAP status
			if ($this->isImapEnabled($extension)) {
				// IMAP is enabled - check IMAP folders only
				$mailboxInfo = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($extension);
				$context = !empty($mailboxInfo) ? ($mailboxInfo['vmcontext'] ?? 'default') : 'default';
				$imapClient = $this->_setupImapClient($extension, $context);
				if ($imapClient) {
					$imapFolders = $imapClient->getVoicemailFolders();
					if (!empty($imapFolders)) {
						foreach ($imapFolders as $folderKey => $folderData) {
							$existingFolders[] = strtoupper($folderKey);
						}
					}
				}
			} else {
				// IMAP is not enabled - check local folders only
				$localFolders = $this->UCP->FreePBX->Voicemail->getFolders();
				if (!empty($localFolders)) {
					foreach ($localFolders as $folder) {
						$existingFolders[] = strtoupper($folder['folder']);
					}
				}
			}
			
			// Check for conflicts (case-insensitive)
			if (in_array(strtoupper($sanitizedFolderName), $existingFolders)) {
				return array("status" => false, "message" => _("A folder with this name already exists"));
			}
			
			$success = true;
			$messages = array();
			
			// Create folder in the appropriate location based on IMAP status
			if ($this->isImapEnabled($extension)) {
				// IMAP is enabled - create folder on IMAP server only
				$imapSuccess = $this->createImapFolder($extension, $sanitizedFolderName);
				if (!$imapSuccess) {
					$success = false;
					$messages[] = _("Failed to create IMAP folder");
				}
			} else {
				// IMAP is not enabled - create folder locally only
				$localSuccess = $this->createLocalFolder($extension, $sanitizedFolderName);
				if (!$localSuccess) {
					$success = false;
					$messages[] = _("Failed to create local folder");
				}
			}
			
			if ($success) {
				return array("status" => true, "message" => _("Folder created successfully"));
			} else {
				return array("status" => false, "message" => implode("; ", $messages));
			}
			
		} catch (\Exception $e) {
			freepbx_log(FPBX_LOG_ERROR, "[Voicemail UCP] Exception in createFolder: " . $e->getMessage());
			return array("status" => false, "message" => _("Server error occurred"));
				}
	}

	/**
	 * Delete a voicemail folder for the specified extension.
	 * 
	 * Deletes the folder both locally (if local storage is used) and on IMAP server
	 * (if IMAP is enabled for the extension).
	 *
	 * @param string $extension The extension number
	 * @param string $folderName The name of the folder to delete
	 * @return array Status array with 'status' (bool) and 'message' (string)
	 */
	public function deleteFolder($extension, $folderName) {
		try {
			// Sanitize folder name
			$sanitizedFolderName = preg_replace('/[^a-zA-Z0-9\-_]/', '', $folderName);
			$sanitizedFolderName = trim($sanitizedFolderName);
			
			// Check if folder name is empty after sanitization
			if (empty($sanitizedFolderName)) {
				return array("status" => false, "message" => _("Invalid folder name"));
			}
			
			// Prevent deletion of system folders
			$systemFolders = array('INBOX', 'OLD');
			if (in_array(strtoupper($sanitizedFolderName), $systemFolders)) {
				return array("status" => false, "message" => _("Cannot delete system folders"));
			}
			
			$success = true;
			$messages = array();
			
			// Delete folder from the appropriate location based on IMAP status
			if ($this->isImapEnabled($extension)) {
				// IMAP is enabled - delete folder from IMAP server only
				$imapSuccess = $this->deleteImapFolder($extension, $sanitizedFolderName);
				if (!$imapSuccess) {
					$success = false;
					$messages[] = _("Failed to delete IMAP folder");
				}
			} else {
				// IMAP is not enabled - delete folder locally only
				$localSuccess = $this->deleteLocalFolder($extension, $sanitizedFolderName);
				if (!$localSuccess) {
					$success = false;
					$messages[] = _("Failed to delete local folder");
				}
			}
			
			if ($success) {
				return array("status" => true, "message" => _("Folder deleted successfully"));
			} else {
				return array("status" => false, "message" => implode("; ", $messages));
			}
			
		} catch (\Exception $e) {
			freepbx_log(FPBX_LOG_ERROR, "[Voicemail UCP] Exception in deleteFolder: " . $e->getMessage());
			return array("status" => false, "message" => _("Server error occurred"));
		}
	}

	/**
	 * Create a folder in the local filesystem for the specified extension.
	 *
	 * @param string $extension The extension number
	 * @param string $folderName The sanitized folder name
	 * @return bool True if successful, false otherwise
	 */
	private function createLocalFolder($extension, $folderName) {
		try {
			// Get voicemail directory path
			$vmconf = $this->_getVoicemailConf();
			$voicemailDir = $vmconf['voicemaildir'] ?? '/var/spool/asterisk/voicemail';
			
			// Get the correct context for this extension
			$mailboxInfo = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($extension);
			$context = !empty($mailboxInfo) ? ($mailboxInfo['vmcontext'] ?? 'default') : 'default';
			
			// Create extension directory path using correct context
			$extensionDir = $voicemailDir . '/' . $context . '/' . $extension;
			
			// Create folder path
			$folderPath = $extensionDir . '/' . $folderName;
			
			// Check if folder already exists
			if (is_dir($folderPath)) {
				return true; // Folder already exists, consider this success
			}
			
			// Create the folder
			$result = mkdir($folderPath, 0755, true);
			
			if ($result) {
				freepbx_log(FPBX_LOG_INFO, "[Voicemail UCP] Created local folder: $folderPath");
			} else {
				freepbx_log(FPBX_LOG_ERROR, "[Voicemail UCP] Failed to create local folder: $folderPath");
			}
			
			return $result;
			
		} catch (\Exception $e) {
			freepbx_log(FPBX_LOG_ERROR, "[Voicemail UCP] Exception in createLocalFolder: " . $e->getMessage());
			return false;
		}
	}
	
	/**
	 * Create a folder on the IMAP server for the specified extension.
	 *
	 * @param string $extension The extension number
	 * @param string $folderName The sanitized folder name
	 * @return bool True if successful, false otherwise
	 */
	private function createImapFolder($extension, $folderName) {
		try {
			// Get voicemail box info for context
			$mailboxInfo = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($extension);
			$context = !empty($mailboxInfo) ? ($mailboxInfo['vmcontext'] ?? 'default') : 'default';
			
			// Setup IMAP client
			$imapClient = $this->_setupImapClient($extension, $context);
			if (!$imapClient) {
				freepbx_log(FPBX_LOG_ERROR, "[Voicemail UCP] Failed to setup IMAP client for folder creation");
				return false;
			}
			
			// Create folder on IMAP server
			$result = $imapClient->createFolder($folderName);
			
			if ($result) {
				freepbx_log(FPBX_LOG_INFO, "[Voicemail UCP] Created IMAP folder: $folderName for extension $extension");
			} else {
				freepbx_log(FPBX_LOG_ERROR, "[Voicemail UCP] Failed to create IMAP folder: $folderName for extension $extension");
			}
			
			return $result;
			
		} catch (\Exception $e) {
			freepbx_log(FPBX_LOG_ERROR, "[Voicemail UCP] Exception in createImapFolder: " . $e->getMessage());
			return false;
		}
	}
	
	/**
	 * Delete a folder from the local filesystem for the specified extension.
	 *
	 * @param string $extension The extension number
	 * @param string $folderName The sanitized folder name
	 * @return bool True if successful, false otherwise
	 */
	private function deleteLocalFolder($extension, $folderName) {
		try {
			// Get voicemail directory path
			$vmconf = $this->_getVoicemailConf();
			$voicemailDir = $vmconf['voicemaildir'] ?? '/var/spool/asterisk/voicemail';
			
			// Get the correct context for this extension
			$mailboxInfo = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($extension);
			$context = !empty($mailboxInfo) ? ($mailboxInfo['vmcontext'] ?? 'default') : 'default';
			
			// Create extension directory path using correct context
			$extensionDir = $voicemailDir . '/' . $context . '/' . $extension;
			
			// Create folder path
			$folderPath = $extensionDir . '/' . $folderName;
			
			// Check if folder exists
			if (!is_dir($folderPath)) {
				return true; // Folder doesn't exist, consider this success
			}
			
			// Check if folder is empty (optional safety check)
			$files = glob($folderPath . '/*');
			if (!empty($files)) {
				freepbx_log(FPBX_LOG_WARNING, "[Voicemail UCP] Attempting to delete non-empty folder: $folderPath");
			}
			
			// Delete the folder and all contents
			$result = $this->recursiveDelete($folderPath);
			
			if ($result) {
				freepbx_log(FPBX_LOG_INFO, "[Voicemail UCP] Deleted local folder: $folderPath");
			} else {
				freepbx_log(FPBX_LOG_ERROR, "[Voicemail UCP] Failed to delete local folder: $folderPath");
			}
			
			return $result;
			
		} catch (\Exception $e) {
			freepbx_log(FPBX_LOG_ERROR, "[Voicemail UCP] Exception in deleteLocalFolder: " . $e->getMessage());
			return false;
		}
	}
	
	/**
	 * Delete a folder from the IMAP server for the specified extension.
	 *
	 * @param string $extension The extension number
	 * @param string $folderName The sanitized folder name
	 * @return bool True if successful, false otherwise
	 */
	private function deleteImapFolder($extension, $folderName) {
		try {
			// Get voicemail box info for context
			$mailboxInfo = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($extension);
			$context = !empty($mailboxInfo) ? ($mailboxInfo['vmcontext'] ?? 'default') : 'default';
			
			// Setup IMAP client
			$imapClient = $this->_setupImapClient($extension, $context);
			if (!$imapClient) {
				freepbx_log(FPBX_LOG_ERROR, "[Voicemail UCP] Failed to setup IMAP client for folder deletion");
				return false;
			}
			
			// Delete folder from IMAP server
			$result = $imapClient->deleteFolder($folderName);
			
			if ($result) {
				freepbx_log(FPBX_LOG_INFO, "[Voicemail UCP] Deleted IMAP folder: $folderName for extension $extension");
			} else {
				freepbx_log(FPBX_LOG_ERROR, "[Voicemail UCP] Failed to delete IMAP folder: $folderName for extension $extension");
			}
			
			return $result;
			
		} catch (\Exception $e) {
			freepbx_log(FPBX_LOG_ERROR, "[Voicemail UCP] Exception in deleteImapFolder: " . $e->getMessage());
			return false;
		}
	}
	
	/**
	 * Recursively delete a directory and all its contents.
	 *
	 * @param string $dir The directory to delete
	 * @return bool True if successful, false otherwise
	 */
	private function recursiveDelete($dir) {
		if (!is_dir($dir)) {
			return true;
		}
		
		$files = array_diff(scandir($dir), array('.', '..'));
		foreach ($files as $file) {
			$path = $dir . '/' . $file;
			if (is_dir($path)) {
				$this->recursiveDelete($path);
			} else {
				unlink($path);
			}
		}
		
		return rmdir($dir);
	}
}
