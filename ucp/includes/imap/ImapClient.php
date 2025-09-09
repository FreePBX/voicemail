<?php
/**
 * IMAP Client for FreePBX UCP Voicemail
 * 
 * Handles interaction with IMAP servers for voicemail operations
 */

namespace UCP\Modules\Voicemail\Imap;

/**
 * IMAP Client for Voicemail
 */
#[\AllowDynamicProperties]
class ImapClient {
    /**
     * @var string The IMAP server hostname
     */
    public $imapserver;
    
    /**
     * @var int The IMAP server port
     */
    public $imapport;
    
    /**
     * @var string The IMAP username of the extension user
     */
    public $imapuser;
    
    /**
     * @var string The IMAP password of the extension user
     */
    private $imappassword;
    
    /**
     * @var string Admin user for impersonation (if applicable)
     */
    public $authuser;

    /**
     * @var string Admin password for impersonation (if applicable)
     */
    private $authpassword;
    
    /**
     * @var string Raw IMAP flags as read from voicemail.conf ("ssl", "tls", "notls", etc.).
     *             The constructor normalises this to include the protocol prefix ("imap/ssl")
     *             and adds "/novalidate-cert" when appropriate before a connection is made.
     */
    public $imapflags;
    
    /**
     * @var string Default message folder SETTING straight from voicemail.conf; may contain a path
     *             (e.g. "VoiceMail/INBOX").  Never modified after construction.  **Not** the same
     *             as the runtime folder a user clicks on in UCP – that value is passed at call time
     *             as $fetchFolder.
     */
    private $imapfolder;
    
    /**
     * @var resource The IMAP stream resource
     */
    private $stream;
    
    /** @var int Connection timeout in seconds */
    public $opentimeout = 5;
    /** @var int Read timeout in seconds */
    public $readtimeout = 30;
    /** @var int Write timeout in seconds */
    public $writetimeout = 30;
    /** @var int Close timeout in seconds */
    public $closetimeout = 5;
    
    /** @var string The connection method used */
    public $connectionMethod;
    /** @var string The separator used in the connection */
    public $connectionSeparator;
    /** @var string The auth method used */
    public $connectionAuthMethod;
    /** @var bool Whether this is an admin connection */
    public $adminConnection = false;
    
    /**
     * @var string Optional extra parent path from voicemail.conf.  Empty for most installs.
     *             Example: "Lucas" in a multi-tenant hierarchy "Lucas/PSI/VoiceMail".
     */
    public $imapparentfolder = null;
    /**
     * @var string Derived: first path component of $imapfolder (e.g. "VoiceMail" or "PSI/VoiceMail").
     *             Calculated once in the constructor.
     */
    private $basefolder = null;
    /**
     * @var string Simple folder name for greetings (relative to mailboxRoot).  Defaults to
     *             "Greetings".  If voicemail.conf provides a full path, only the last segment is
     *             stored here – the path prefix is folded into $mailboxRoot.
     */
    public $greetingsFolder = 'Greetings';
    /** @var string The required impersonation method (backslash, separator_X, authuser) - Set by test */
    public $imapimpersonationmethod = null; // Renamed from impersonationMethod
    /** @var string The required auth type (None, DISABLE_*) - Set by test */
    public $imapauthtype = 'None'; // Renamed from authType
    /** @var string Whether IMAP greetings are enabled ('yes'/'no') */
    private $imapgreetings = 'no';
    
    /** @var object Reference to UCP framework */
    protected $UCP;
    
    /** @var bool Stores whether impersonation should be used */
    private $useImpersonation = false; // Renamed and clarified

    /**
     * @var string Derived: mailbox root path built once in the constructor:  <imapparentfolder>/<basefolder>
     *             Examples:
     *                 "VoiceMail"
     *                 "Lucas/PSI/VoiceMail"
     *             Never contains the per-folder key such as "INBOX".
     */
    private $mailboxRoot = '';

    /**
     * Constructor - Accepts all necessary configuration parameters.
     * Config reading should happen in the calling class (Voicemail.class.php).
     * 
     * @param string $imapserver IMAP server hostname
     * @param int    $imapport IMAP port
     * @param string $imapflags IMAP flags (ssl, tls, notls)
     * @param string $imapuser The username for connection (extension user or admin)
     * @param string $imappassword The password for $imapuser
     * @param string $authuser The admin username for impersonation (optional)
     * @param string $authpassword The admin password for impersonation (optional)
     * @param string $imapparentfolder Parent folder for VM (e.g., VoiceMail)
     * @param string $greetingsFolder Greetings folder name (relative to parent)
     * @param string $imapfolder Default message folder (e.g., INBOX)
     * @param string $imapimpersonationmethod Specific method if known (optional)
     * @param string $imapauthtype Specific auth type if known (optional)
     * @param string $imapgreetings Whether IMAP greetings are enabled ('yes'/'no')
     */
    public function __construct(
        $imapserver, 
        $imapport, 
        $imapflags, 
        $imapuser, 
        $imappassword, 
        $authuser = null, 
        $authpassword = null, 
        $imapparentfolder = null, // No default value
        $greetingsFolder = 'INBOX', 
        $imapfolder = 'INBOX', 
        $imapimpersonationmethod = null, 
        $imapauthtype = 'None', 
        $imapgreetings = 'no'
    ) {
        
        
        global $UCP;
        $this->UCP = $UCP;

        // Assign core connection details
        $this->imapserver = $imapserver;
        $this->imapport = (int)$imapport;
        $this->imapflags = $imapflags ?: 'notls'; // Ensure flags are not empty
        $this->imapuser = $imapuser;
        $this->imappassword = $imappassword;
        $this->authuser = $authuser;
        $this->authpassword = $authpassword;

        // Assign folder configuration
        $this->imapparentfolder = $imapparentfolder; // Assign parent folder (default is null)
        $this->imapgreetings = strtolower($imapgreetings ?: 'no');
        
        // === DERIVE FOLDER STRUCTURE SETTINGS ===
        // Raw config values (keep originals for logging)
        $confImapParent   = $imapparentfolder ?: '';
        $confImapFolder   = $imapfolder ?: 'INBOX';
        $confGreetingsRaw = $greetingsFolder ?: 'Greetings';

        // Extract baseFolder + default folder name from confImapFolder
        $this->basefolder = '';
        $this->imapfolder = 'INBOX';
        if (strpos($confImapFolder, '/') !== false) {
            $parts             = explode('/', $confImapFolder);
            $this->imapfolder  = array_pop($parts);         // e.g. INBOX
            $this->basefolder  = implode('/', $parts);      // e.g. VoiceMail or PSI/VoiceMail
        } else {
            $this->imapfolder = $confImapFolder;            // already simple
        }

        // Build mailboxRoot  = optional parent + basefolder (no leading/trailing '/')
        $parentPrefix       = trim($confImapParent, '/');
        $rootParts          = [];
        if ($parentPrefix   !== '') $rootParts[] = $parentPrefix;
        if ($this->basefolder !== '') $rootParts[] = $this->basefolder;
        $this->mailboxRoot  = implode('/', $rootParts);     // may be ''

        // Greetings folder simple name
        if ($this->imapgreetings === 'yes') {
            $this->greetingsFolder = (strpos($confGreetingsRaw, '/') !== false) ? basename($confGreetingsRaw) : $confGreetingsRaw;
        } else {
            $this->greetingsFolder = null; // Greetings folder is irrelevant if IMAP greetings are off
        }


        
        // Assign optional known methods
        $this->imapimpersonationmethod = $imapimpersonationmethod;
        $this->imapauthtype = $imapauthtype ?: 'None'; // Default if empty

        // Initialize other properties
        $this->stream = null;


        // Determine if impersonation is intended based on provided credentials
        $this->useImpersonation = (!empty($this->authuser) && !empty($this->authpassword));

        // <<< Log assigned properties >>>

    }
        
    /**
     * Destructor to ensure we clean up resources
     */
    public function __destruct() {

    $this->close();
    }

    /**
     * Helper to consistently build the base IMAP connection string part.
     * Handles server, port, flags, and novalidate-cert logic.
     *
     * @return string The base mailbox string (e.g., '{host:port/flags/novalidate-cert}')
     */
    private function _buildServerString() { // Renamed from _buildBaseMailboxString
        $imapflags = $this->imapflags ?: 'notls'; // Ensure flags are not empty

        // Ensure the protocol prefix "imap/" is present (required when using /authuser etc.)
        if ($imapflags !== 'notls' && stripos($imapflags, 'imap/') === false) {
            $imapflags = 'imap/' . ltrim($imapflags, '/');
        }

        $finalFlags = $imapflags;

        // Append /novalidate-cert unless notls is the only flag or validate-cert already present
        if ($finalFlags !== 'notls' &&
            strpos($finalFlags, 'novalidate-cert') === false &&
            strpos($finalFlags, 'validate-cert') === false) {
            $finalFlags .= '/novalidate-cert';
        }
        // Ensure imapport is treated as integer
        $port = (int)$this->imapport; 
        return '{' . $this->imapserver . ':' . $port . '/' . $finalFlags . '}';
    }
    
        /**
     * PRIVATE: Streams the audio part of a specific message UID from a folder.
     *
     * Assumes connection is established.
     *
     * @param string $identifier The UID of the message to stream.
     * @param string $folderPath The full path to the folder (e.g., VoiceMail/INBOX).
     * @param bool $forceDownload Whether to force download.
     * @param bool $isUid True if $identifier is a UID, false if it's an MSN (default: true).
     * @param string|null $outputFilename Optional desired output filename. If null, a default will be generated.
     * @return bool True on success, false on error.
     */
    private function _streamAudioPart($identifier, $folderPath, $forceDownload, $isUid = true, $outputFilename = null)
    {
        $idType = $isUid ? 'UID' : 'MSN';
        $fetchOptions = $isUid ? FT_UID : 0;
        $fetchOptionsPeek = $isUid ? (FT_UID | FT_PEEK) : FT_PEEK;
        $fetchOptionsNoPeek = $isUid ? FT_UID : 0;
        


        try {
            // 1. Select the correct mailbox folder
            if (!$this->selectMailbox($folderPath)) {
                $errorMsg = "_streamAudioPart: Failed to select mailbox folder: $folderPath";
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] " . $errorMsg);
                if (!headers_sent()) header("HTTP/1.0 404 Not Found");
                echo $errorMsg;
            return false;
        }
    

            // 2. Fetch message structure using the identifier (UID or MSN)
            $structure = @imap_fetchstructure($this->stream, $identifier, $fetchOptions);
            if (!$structure) {
                $errorMsg = "_streamAudioPart: Failed to fetch structure for $idType $identifier. Last IMAP error: " . imap_last_error();
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] " . $errorMsg);
                if (!headers_sent()) header("HTTP/1.0 500 Internal Server Error");
                echo $errorMsg;
            return false;
        }
    

            // 3. Find the audio part
            $partNumber = null;
            $mimeType = null;
            $this->findAudioPartInfo($structure, $partNumber, $mimeType);

            if ($partNumber === null) {
                freepbx_log(FPBX_LOG_WARNING, "[IMAP] _streamAudioPart: No suitable audio part found in message structure for $idType $identifier. Falling back to part 1.");
                $partNumber = 1; // Assume first part is the body
                $mimeType = 'audio/wav'; // Guess WAV as a common fallback
            }
    

            // 4. Get the encoding for the part
            $encoding = $this->getPartEncoding($structure, $partNumber);
    

            // 5. Fetch the audio body part using the identifier (UID or MSN) and appropriate flags
            $audioData = @imap_fetchbody($this->stream, $identifier, $partNumber, $fetchOptionsPeek);
            if ($audioData === false) {
                $errorMsg = "_streamAudioPart: Failed to fetch audio body part $partNumber for $idType $identifier (PEEK). Error: " . imap_last_error();
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] " . $errorMsg);
                // Last resort: try fetching without FT_PEEK if that failed
                $audioData = @imap_fetchbody($this->stream, $identifier, $partNumber, $fetchOptionsNoPeek);
                if ($audioData === false) {
                    $errorMsg = "_streamAudioPart: Failed to fetch audio body part $partNumber for $idType $identifier (no PEEK). Error: " . imap_last_error();
                    freepbx_log(FPBX_LOG_ERROR, "[IMAP] " . $errorMsg);
                    if (!headers_sent()) header("HTTP/1.0 500 Internal Server Error");
                    echo $errorMsg;
                    return false;
                }
        
            } else {
        
            }

            // 6. Decode the data based on encoding
                switch ($encoding) {
                case ENCBASE64:
            
                    $audioData = base64_decode($audioData);
                        break;
                case ENCQUOTEDPRINTABLE:
            
                    $audioData = quoted_printable_decode($audioData);
                        break;
                // Other encodings assumed to need no decoding
            }

            if (empty($audioData)) {
                $errorMsg = "_streamAudioPart: Audio data is empty after fetching and decoding for $idType $identifier, part $partNumber.";
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] " . $errorMsg);
                if (!headers_sent()) header("HTTP/1.0 500 Internal Server Error");
                echo $errorMsg;
                return false;
            }
            $contentLength = strlen($audioData);
    

            // 7. Determine filename
            // Use provided filename if available, otherwise generate default
            $finalFilename = $outputFilename ?: "voicemail_{$identifier}.wav"; // Use provided or generate default
    

            // 8. Call the helper function to transcode and stream
            // Pass the final determined filename
            $result = $this->_transcodeAndStreamAudio($audioData, $forceDownload, $finalFilename);

            return $result;

                } catch (\Exception $e) {
            $errorMsg = "Exception in _streamAudioPart: " . $e->getMessage();
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] " . $errorMsg);
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Trace: " . $e->getTraceAsString());
            if (!headers_sent()) {
                header("HTTP/1.0 500 Internal Server Error");
                echo "Error streaming audio: " . $e->getMessage();
            }
                return false;
        } catch (\Error $e) {
            $errorMsg = "PHP Error in _streamAudioPart: " . $e->getMessage();
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] " . $errorMsg);
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Trace: " . $e->getTraceAsString());
            if (!headers_sent()) {
                header("HTTP/1.0 500 Internal Server Error");
                echo "PHP Error streaming audio: " . $e->getMessage();
            }
            return false;
        }
    }
    
    /**
     * Connect to the IMAP server
     * 
     * @param string $extension Extension to use for IMAP login
     * @param string $context Context for the extension (optional)
     * @return bool True on success, false on failure
     */
    public function connect($extension = null, $context = null) {


        // 1. Check if already connected and connection is valid
        if ($this->stream instanceof \IMAP\Connection) {
            try {
                if (@imap_ping($this->stream)) {
            
                    return true;
                }
            } catch (\Exception | \ValueError $e) {
        
                $this->stream = null;
            }
        }



        // 2. Check required parameters (already done in constructor essentially, but good safety check)
        if (empty($this->imapserver) || empty($this->imapport) || empty($this->imapuser) || (empty($this->imappassword) && empty($this->authpassword))) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] Missing required connection parameters.");
            return false;
        }
        if ($this->useImpersonation && (empty($this->authuser) || empty($this->authpassword))) {
             freepbx_log(FPBX_LOG_ERROR, "[IMAP] Impersonation specified but admin credentials missing.");
            return false;
        }
        
        // 3. Set IMAP timeout settings
        imap_timeout(IMAP_OPENTIMEOUT, $this->opentimeout);
        imap_timeout(IMAP_READTIMEOUT, $this->readtimeout);
        imap_timeout(IMAP_WRITETIMEOUT, $this->writetimeout);
        imap_timeout(IMAP_CLOSETIMEOUT, $this->closetimeout);
        
        // 4. Clear any previous errors
        imap_errors();
        
        // 5. Assemble connection parameters DIRECTLY from properties
        $baseMailbox = $this->_buildServerString(); // Call the helper
        $mailbox = $baseMailbox; // Start with the base
        $loginUser = $this->imapuser;
        $loginPass = $this->imappassword; // Assume direct login initially
        $imapOptions = [];

        // Apply impersonation logic if needed
        if ($this->useImpersonation) {
            $loginPass = $this->authpassword; // Use admin password for impersonation
            
            // Explicitly check for valid impersonation methods
            $validMethod = false;
            if ($this->imapimpersonationmethod === 'none') {
                $validMethod = true;
                // No impersonation - use per-extension credentials directly
                $loginUser = $this->imapuser;
                $loginPass = $this->imappassword;
                // Mailbox string remains the base one
            } elseif ($this->imapimpersonationmethod === 'authuser') {
                $validMethod = true;
                // Use the target user (imapuser) as login user, admin credentials are in mailbox string
                $loginUser = $this->imapuser;
                // Add authuser to mailbox string
                $mailbox = rtrim($baseMailbox, '}') . '/authuser=' . $this->authuser . '}';
            } elseif (strpos($this->imapimpersonationmethod, 'separator_') === 0) {
                $validMethod = true;
                 $separatorKey = substr($this->imapimpersonationmethod, strlen('separator_'));
                 // Handle the special case for backslash representation
                 $separator = ($separatorKey === 'backslash') ? '\\' : (($separatorKey === 'colon') ? ':' : (($separatorKey === 'slash') ? '/' : (($separatorKey === 'at') ? '@' : (($separatorKey === 'plus') ? '+' : null)))); // Fixed: use more specific separators
                 if ($separator === null) {
                     freepbx_log(FPBX_LOG_ERROR, "[IMAP] Invalid separator specified in imapimpersonationmethod: {$this->imapimpersonationmethod}");
                     return false;
                 }
                 $loginUser = $this->authuser . $separator . $this->imapuser;
                 // Mailbox string remains the base one ($mailbox = $baseMailbox already)
                 $mailbox .= '}'; // Ensure base mailbox is closed correctly even for separator method
            } 

            if (!$validMethod) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] Impersonation configured (authuser/authpassword provided), but required imapimpersonationmethod ('{$this->imapimpersonationmethod}') is missing or invalid. Cannot connect.");
                return false; // Prevent invalid login attempt
            }

        } 

        // Apply auth type disabling if needed
        if ($this->imapauthtype !== 'None' && strpos($this->imapauthtype, 'DISABLE_') === 0) {
            $authToDisable = substr($this->imapauthtype, strlen('DISABLE_'));
                        if (!defined('DISABLE_AUTHENTICATOR')) {
                            define('DISABLE_AUTHENTICATOR', 1);
                        }
            $imapOptions = ['DISABLE_AUTHENTICATOR' => $authToDisable];
        }
        
        // If imapauthtype is 'None' or any other value not starting with 'DISABLE_', 
        // we pass an empty options array to allow default negotiation, 
        // respecting the outcome of the testImpersonationConnection.
        
        // 6. Attempt connection
     
		
		try {
			// Pass 0 for the flags parameter (4th arg) when using the options array (6th arg) for authenticator control.
			$this->stream = @imap_open($mailbox, $loginUser, $loginPass, 0, 1, $imapOptions);

			if ($this->stream instanceof \IMAP\Connection) {
        
                        return true;
                    } else {
                $error = imap_last_error() ?: 'Unknown error during imap_open';
                        $errors = imap_errors();
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] IMAP connection failed: " . $error);
                        if (is_array($errors)) {
                    freepbx_log(FPBX_LOG_ERROR, "[IMAP] IMAP connection errors: " . implode(", ", $errors));
                }
                $this->stream = null;
                return false;
            }
        } catch (\Exception $e) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] Exception during imap_open: " . $e->getMessage());
            $this->stream = null;
            return false;
        }
    }
 
    /**
	 * Transcodes raw audio data (assumed GSM) to PCM WAV and streams it.
	 *
	 * @param string $rawAudioData The raw audio data (e.g., GSM fetched from IMAP).
	 * @param bool $forceDownload True to force download (attachment), false to stream inline.
	 * @param string $outputFilename The desired base filename for the output.
	 * @return bool True on success, false on error.
	 */
	private function _transcodeAndStreamAudio($rawAudioData, $forceDownload, $outputFilename) {
		

		if (empty($rawAudioData)) {
			freepbx_log(FPBX_LOG_ERROR, "[IMAP] _transcodeAndStreamAudio: Error - Raw audio data is empty.");
			// Avoid sending headers if possible
                    return false;
                }
		
		$inputTmpFile = null;
		$outputTmpFile = null;
		try {
			// Create temporary files
			$inputTmpFile = tempnam(sys_get_temp_dir(), 'audio_in_');
			$outputTmpFile = tempnam(sys_get_temp_dir(), 'audio_out_');
			// Use specific extensions for clarity
			$inputDataFile = $inputTmpFile . '.gsmdata'; // Input data (might be raw GSM or WAV/GSM)
			$outputWavFile = $outputTmpFile . '.wav'; // Output PCM WAV
			@rename($inputTmpFile, $inputDataFile);
			@rename($outputTmpFile, $outputWavFile);
			$inputTmpFile = $inputDataFile;
			$outputTmpFile = $outputWavFile;
			

			// Write fetched data to input file
			if (file_put_contents($inputTmpFile, $rawAudioData) === false) {
				throw new \Exception("Failed to write raw audio to temporary input file: $inputTmpFile");
			}

			// Construct ffmpeg command 
			$ffmpegCmd = sprintf(
				"ffmpeg -y -i %s -acodec pcm_s16le -ar 8000 -ac 1 %s 2>&1",
				escapeshellarg($inputTmpFile),
				escapeshellarg($outputTmpFile)
			);
			

			// Execute ffmpeg
			$ffmpegOutput = shell_exec($ffmpegCmd);
			

			// Check if output file exists and is not empty
			if (!file_exists($outputTmpFile) || filesize($outputTmpFile) == 0) {
				throw new \Exception("ffmpeg conversion failed or produced empty output. Output: " . $ffmpegOutput);
			}

			// Read the converted PCM data
			$pcmAudioData = file_get_contents($outputTmpFile);
			if ($pcmAudioData === false) {
				throw new \Exception("Failed to read converted output file: $outputTmpFile");
			}
			$pcmContentLength = filesize($outputTmpFile);
			

			// Clean up INPUT file now, keep output until sent
			if ($inputTmpFile && file_exists($inputTmpFile)) { @unlink($inputTmpFile); $inputTmpFile = null; }

			// --- Send Headers and Data ---
			$contentType = 'audio/wav'; // Output is standard PCM WAV
			$contentLength = $pcmContentLength;
			
			
			$this->sendAudioHeaders($contentType, $contentLength, $forceDownload, $outputFilename);
			

			// Output the transcoded PCM audio data
			echo $pcmAudioData;

			// Clean up OUTPUT temp file after successful streaming
			if ($outputTmpFile && file_exists($outputTmpFile)) { @unlink($outputTmpFile); $outputTmpFile = null; }

			
                        return true;
            
		} catch (\Exception $e) {
			freepbx_log(FPBX_LOG_ERROR, "[IMAP] Transcoding/Streaming Error in _transcodeAndStreamAudio: " . $e->getMessage());
			// Clean up temp files on error
			if ($inputTmpFile && file_exists($inputTmpFile)) { @unlink($inputTmpFile); }
			if ($outputTmpFile && file_exists($outputTmpFile)) { @unlink($outputTmpFile); }
			// Send error response only if headers not already sent
			if (!headers_sent()) {
				header("HTTP/1.0 500 Internal Server Error");
				echo "Error processing audio file.";
			}
			return false; // Indicate failure
		}
	}
     /**
     * PRIVATE HELPER: Deletes an item (message or greeting) by UID from a specific folder.
     * Assumes connection is already established.
     *
     * @param string $uid The UID of the item to delete.
     * @param string $folderPath The full path of the folder containing the item (e.g., VoiceMail/INBOX).
     * @return bool True on success, false on failure.
     */
    private function _deleteItemByUid($uid, $folderPath) {

        if (empty($uid) || empty($folderPath)) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] _deleteItemByUid: Missing UID or folder path.");
            return false;
        }
        
        try {
            // Select the target mailbox - use just the folder name, not the full path
            $folderName = basename($folderPath);
            if (!$this->selectMailbox($folderName)) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] _deleteItemByUid: Failed to select mailbox: $folderName (from path: $folderPath)");
                return false;
            }

            // Flag the message for deletion using UID
            $result = @imap_delete($this->stream, $uid, FT_UID);
            if (!$result) {
                $error = imap_last_error() ?: 'Unknown error';
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] _deleteItemByUid: Failed to flag UID $uid for deletion: " . $error);
                return false;
            }
    

            // Expunge the mailbox to actually delete the message
            $result = @imap_expunge($this->stream);
            if (!$result) {
                // This might return false if no messages were actually expunged (e.g., already deleted)
                // So, log as warning unless there's a specific last error.
                        $error = imap_last_error();
                if ($error) {
                    freepbx_log(FPBX_LOG_WARNING, "[IMAP] _deleteItemByUid: Failed to expunge mailbox after deletion: " . $error);
                    // Don't necessarily return false here, expunge might fail if nothing to expunge
                } else {
                    freepbx_log(FPBX_LOG_INFO, "[IMAP] _deleteItemByUid: Expunge completed (or no messages to expunge).");
                }
            }

     
            return true; // Return true even if expunge reported no messages deleted

        } catch (\Exception $e) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] Exception in _deleteItemByUid (UID $uid, Folder $folderPath): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * PRIVATE HELPER: Build the full folder path relative to the server root.
     * Handles the hierarchical folder structure correctly.
     * 
     * @param string $subFolder The relative subfolder name (e.g., 'INBOX', 'Greetings', or '' for parent).
     * @return string The full path (e.g., 'INBOX/VoiceMail/INBOX', 'INBOX/VoiceMail/Greetings').
     */
    private function _buildFolderPath($subFolder = '') {
        // Build full path relative to server root using pre-computed mailboxRoot.
        $path = '';
        if ($this->mailboxRoot !== '') {
            $path = $this->mailboxRoot;
            if ($subFolder !== '') {
                $path .= '/' . ltrim($subFolder, '/');
            }
        } else {
            $path = ltrim($subFolder, '/');
        }

        return $path;
    }
    
    /**
     * Get messages from the mailbox.
     * This method supports both new format (folder, fetchAll) and old format (extension, context, folder, fetchAll).
     * 
     * @param string $param1 Either folder name or extension number
     * @param mixed $param2 Either fetchAll flag or context string
     * @param string $param3 Optional folder name when using old format
     * @param bool $param4 Optional fetchAll flag when using old format
     * @return array Messages
     */
    public function getMessages($param1 = 'INBOX', $param2 = false, $param3 = null, $param4 = false) {
        $imapfolder = $param1;
        $fetchAll = $param2;
        
        // Some methods pass additional parameters, so let's preserve backward compatibility
        if (is_bool($param1) && is_string($param2)) {
            $imapfolder = $param2;
            $fetchAll = $param1;
        }
        

        
        // Check if we have cached messages for this folder
        if (!$fetchAll && isset($this->messages[$imapfolder]) && 
            (time() - $this->messageTimestamps[$imapfolder]) < 60) {
    
            return $this->messages[$imapfolder];
        }
        
        // Check connection
        try {
            $this->checkConnection();
        } catch (\Exception $e) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] getMessages: Failed to check connection");
            return [];
        }
        
        // Try to get messages using the simplified processMessages
        // Pass the folder determined earlier
        $messages = $this->processMessages($imapfolder);
        
        // Cache the result if successful
        if (!empty($messages)) {
        $this->messages[$imapfolder] = $messages;
        $this->messageTimestamps[$imapfolder] = time();
        }
        

        return $messages;
    }
        /**
     * PRIVATE HELPER: Finds the UID of the first message in a folder matching a specific X-Asterisk-VM-Message-Type.
     * Uses the _fetchAndParseHeaders helper.
     *
     * @param string $folderPath The full path to the IMAP folder (e.g., VoiceMail/Greetings).
     * @param string $messageType The exact value to match in the X-Asterisk-VM-Message-Type header.
     * @return string|null The UID of the matching message, or null if not found or error.
     */
    private function _findUidByMessageType(string $folderPath, string $messageType): ?string
    {

        freepbx_log(FPBX_LOG_INFO, "[IMAP] _findUidByMessageType: Searching folder '$folderPath' for type '$messageType'.");

        if (!$this->stream instanceof \IMAP\Connection) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] _findUidByMessageType: No active connection.");
            return null;
        }

        // 1. Select the target mailbox (required before search)
        if (!$this->selectMailbox($folderPath)) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] _findUidByMessageType: Failed to select folder: $folderPath");
            return null;
        }

        // 2. Search for ALL message UIDs in the folder
        $allUids = @imap_search($this->stream, 'ALL', SE_UID);
        if ($allUids === false) {
            $error = imap_last_error();
            freepbx_log(FPBX_LOG_WARNING, "[IMAP] _findUidByMessageType: imap_search failed for ALL UIDs in '$folderPath'" . ($error ? ": $error" : ". Treating as empty."));
            return null; // Treat search failure or empty folder as not found
        }
        if (empty($allUids)) {
            freepbx_log(FPBX_LOG_INFO, "[IMAP] _findUidByMessageType: No messages (UIDs) found in folder '$folderPath'.");
            return null;
        }



        // 3. Iterate through UIDs and fetch/check headers using the helper
        foreach ($allUids as $uid) {
            $currentUidStr = (string)$uid; // Cast UID to string
            

            // Call helper
            $parsedHeaders = $this->_fetchAndParseHeaders($currentUidStr, $folderPath);

            if ($parsedHeaders === null) {
                freepbx_log(FPBX_LOG_WARNING, "[IMAP] _findUidByMessageType: Header parsing failed for UID $currentUidStr.");
                continue; // Skip to next UID
            }

            $parsedType = $parsedHeaders['type'] ?? '[Not Set]';
    

            // Check if the type matches (case-insensitive check for robustness)
            if (isset($parsedHeaders['type']) && strcasecmp($parsedHeaders['type'], $messageType) === 0) {
                 
                return $currentUidStr; // Found the UID, return it as a string
                } else {
                 freepbx_log(FPBX_LOG_INFO, "[IMAP] _findUidByMessageType: No match for UID $currentUidStr (Needed: '$messageType', Found: '$parsedType').");
            }
            // If helper returned null or type didn't match, continue to next UID
        }

        // 4. If loop completes, no match was found

        return null;
    }

    /**
     * PRIVATE HELPER: Fetches and parses relevant headers for a given message UID.
     *
     * @param string $uid The UID of the message.
     * @param string $folderPath The full path to the IMAP folder.
     * @return array|null An associative array with parsed header values (type, callerid_num, etc.), or null on failure.
     */
    private function _fetchAndParseHeaders(string $uid, string $folderPath): ?array
    {
        if (!$this->stream instanceof \IMAP\Connection) {
            return null; 
        }

        // Extract folder name from full path if needed
        $folderName = $folderPath;
        if (strpos($folderPath, '/') !== false) {
            // This is a full path, extract just the folder name
            $parts = explode('/', $folderPath);
            $folderName = end($parts);
    
        }

        // 1. Select the target mailbox (might be redundant if caller already selected, but safer)
        // Note: Selecting the same mailbox repeatedly is usually very fast.
        if (!$this->selectMailbox($folderName)) {
            return null;
        }

        // 2. Fetch header string
        $headerString = @imap_fetchheader($this->stream, $uid, FT_UID);
        if (!$headerString) {
            return null;
        }

        // 3. Parse headers into an associative array
        $parsedHeaders = [
            'type' => 'Message', // Default
            'callerid_num' => '',
            'callerid_name' => '',
            'duration' => 0,
            'origtime' => 0,
            'priority_num' => 2, // Default normal priority
            'message_id' => '', // X-Asterisk-VM-Message-ID
            'subject' => '',
            'error' => null
        ];

        // Use preg_match_all for potentially multiple headers? No, stick to single match for simplicity.
        // Extract values using case-insensitive and multiline matching (^...$)        
        if (preg_match('/^X-Asterisk-VM-Message-Type:\s*(.*)$/mi', $headerString, $m)) {
            $parsedHeaders['type'] = trim($m[1]);
        }
        if (preg_match('/^X-Asterisk-VM-Caller-ID-Num:\s*(.*)$/mi', $headerString, $m)) {
            $parsedHeaders['callerid_num'] = trim($m[1]);
        } elseif (preg_match('/^X-Asterisk-CallerID:\s*(.*)$/mi', $headerString, $m)) { // Fallback
             $parsedHeaders['callerid_num'] = trim($m[1]);
        }
        if (preg_match('/^X-Asterisk-VM-Caller-ID-Name:\s*(.*)$/mi', $headerString, $m)) {
            $parsedHeaders['callerid_name'] = trim($m[1]);
        } elseif (preg_match('/^X-Asterisk-CallerIDName:\s*(.*)$/mi', $headerString, $m)) { // Fallback
            $parsedHeaders['callerid_name'] = trim($m[1]);
        }
        if (preg_match('/^X-Asterisk-VM-Duration:\s*(\d+)/mi', $headerString, $m)) {
            $parsedHeaders['duration'] = (int)trim($m[1]);
        }
        if (preg_match('/^X-Asterisk-VM-Orig-time:\s*(\d+)/mi', $headerString, $m)) {
            $ts = (int)trim($m[1]);
            $parsedHeaders['origtime'] = ($ts > 0) ? $ts : 0; // Use 0 if invalid
        }
        if (preg_match('/^X-Asterisk-VM-Priority:\s*(\d+)/mi', $headerString, $m)) {
            $parsedHeaders['priority_num'] = (int)trim($m[1]);
        }
        if (preg_match('/^X-Asterisk-VM-Message-ID:\s*([^\r\n]+)/mi', $headerString, $m)) {
             $msgIdHeader = trim($m[1]);
             // Store only if it's not the literal string '(null)'
             $parsedHeaders['message_id'] = ($msgIdHeader !== '(null)') ? $msgIdHeader : ''; 
        }
        if (preg_match('/^Subject:\s*(.*)$/mi', $headerString, $m)) {
            $parsedHeaders['subject'] = trim($m[1]);
        }
        
        // Fallback for origtime using Date header if X-Asterisk one wasn't found or invalid
        if ($parsedHeaders['origtime'] === 0 && preg_match('/^Date:\s*(.*)$/mi', $headerString, $m)) {
            $parsedDate = strtotime(trim($m[1]));
            if ($parsedDate) {
                 $parsedHeaders['origtime'] = $parsedDate;
         
             }
        }
        
        // If still no origtime, use current time as last resort
        if ($parsedHeaders['origtime'] === 0) {
             $parsedHeaders['origtime'] = time();
        }


        return $parsedHeaders;
    }
    
    /**
     * Process a list of messages from the mailbox - SIMPLIFIED VERSION
     * Assumes Asterisk headers are reliable and sufficient.
     * Always fetches all messages in the folder and processes headers.
     * 
     * @param string $imapfolder Folder name (e.g., INBOX, Greetings)
     * @return array|false Processed messages array, or false on critical error.
     */
    private function processMessages($imapfolder) {

        
        // Try to select the mailbox
        try {
            if (!$this->selectMailbox($imapfolder)) {
                return false; // Indicate critical error selecting mailbox
            }
        } catch (\Exception $e) {
            return false; // Indicate critical error selecting mailbox
        }
        
        // <<< Calculate full folder path ONCE >>>
        $fullFolderPath = $this->_buildFolderPath($imapfolder);


        // <<< ADD LOG HERE >>>


        // Search for ALL messages using UIDs
        $searchResults = false;
        $lastError = null; // Initialize lastError
        try {
            // Clear previous errors before the call
            imap_errors(); 
            $searchResults = @imap_search($this->stream, 'ALL', SE_UID);
            $lastError = imap_last_error(); // Capture error *after* the call

            // === FIX: Handle false return from imap_search ===
            if ($searchResults === false) {
                 // Check if there was an actual IMAP error associated with the 'false' return
                 if ($lastError) {
                    // Consider this a failure to get messages for this folder
                    return []; // Return empty array, but log indicates an issue
                } else {
                     // If imap_search returns false WITHOUT an error, treat it as an empty folder
                     return []; // Return empty array, signifying no messages found
                 }
             } 
             // === END FIX ===
             elseif (empty($searchResults)) { // Explicitly check for empty array result
                 return []; // Return empty if folder is empty
            }
    
            
                } catch (\Exception $e) {
            // Log unexpected exceptions as errors
            return false; // Indicate critical error during search
        }
        
        // --- If we reach here, $searchResults is a non-empty array of UIDs ---
        
        // Process the found messages
        $messages = array();
        
        // Limit to a reasonable number? Let's keep 50 for now to avoid overwhelming UCP.
        $searchResults = array_slice($searchResults, 0, 50);
        
        // Use a batched approach for fetching headers (can still be slow for many messages)
        $messageCount = count($searchResults);
        $batchSize = 10; // Process 10 messages at a time
        

        
        for ($i = 0; $i < $messageCount; $i += $batchSize) {
            $batch = array_slice($searchResults, $i, $batchSize);
            
            try {
                 // Process one UID at a time within the batch
                 foreach ($batch as $uidStr) { // Ensure UID is treated as string
                     $uid = (string)$uidStr;
                     
                     // Fetch overview first (still needed for flags)
                     $overviewData = @imap_fetch_overview($this->stream, $uid, FT_UID);
                     $overview = (!empty($overviewData[0])) ? $overviewData[0] : null;
                     $isRead = $overview ? !empty($overview->seen) : false;
                     
                     // Use the new helper to get parsed headers
                     $parsedHeaders = $this->_fetchAndParseHeaders($uid, $imapfolder); // Pass just the folder name, not the full path

                     // Check if header parsing was successful
                     if ($parsedHeaders === null) {
                         continue; // Skip this message if headers couldn't be parsed
                     }

                     // --- Build Message Array using Parsed Headers --- 
                    $message = array(
                        'id' => $uid,
                        'msg_id' => !empty($parsedHeaders['message_id']) ? $parsedHeaders['message_id'] : $uid, // Use parsed msg_id, fallback to UID
                        'fid' => $uid,    
                        'folder' => $imapfolder,
                        'read' => $isRead, 
                        'priority' => ($parsedHeaders['priority_num'] === 1) ? 'high' : 'normal', // Use parsed priority
                        // Debug priority values
                        'priority_debug' => $parsedHeaders['priority_num'], // Keep original number for debugging
                        'date' => '',      // Will be set from origtime
                        'time' => '',      // Will be set from origtime
                        'origtime' => $parsedHeaders['origtime'], // Use parsed origtime
                        'duration' => $parsedHeaders['duration'],    // Use parsed duration
                        'callerid' => 'Unknown', // Will be formatted below
                        'type' => $parsedHeaders['type'], // Use parsed type
                        'subject' => $parsedHeaders['subject'], // Use parsed subject
                        'imap' => true   
                    );

                    // Set the final timestamp and formatted date/time
                        $dateObj = new \DateTime();
                    $dateObj->setTimestamp($message['origtime']);
                        $message['date'] = $dateObj->format('Y-m-d');
                        $message['time'] = $dateObj->format('H:i:s');

                    // Format Caller ID using parsed values
                    $callerIDNum = $parsedHeaders['callerid_num'];
                    $callerIDName = $parsedHeaders['callerid_name'];
                    $finalCallerId = "Unknown";
                    $callerIDNum = preg_replace('/^"(.+)"$/', '$1', $callerIDNum); // Clean quotes
                    $callerIDName = preg_replace('/^"(.+)"$/', '$1', $callerIDName); // Clean quotes
                    if (!empty($callerIDName) && !empty($callerIDNum) && $callerIDName != $callerIDNum) {
                        $finalCallerId = $callerIDName . " <" . $callerIDNum . ">";
                    } else if (!empty($callerIDNum)) {
                        $finalCallerId = $callerIDNum;
                    } else if (!empty($callerIDName)) {
                        $finalCallerId = $callerIDName;
                    }
                    $message['callerid'] = $finalCallerId;
                    
                    // Add to messages array
                    $messages[] = $message;
                 }
                } catch (\Exception $e) {
                 // Continue processing other batches if possible
            }
        }
        

        return $messages; // Return the array of processed messages
    }
    
    /**
     * Stream audio attachment from a voicemail message
     * 
     * Optimized method to stream audio attachments with better performance
     * 
     * @param string $extension Extension/mailbox number
     * @param string $context   Voicemail context
     * @param string $messageId Message ID to stream
     * @param string $fetchFolder    Folder name (default: INBOX)
     * @param bool   $forceDownload Whether to force download instead of inline streaming
     * @return bool True if successful, false otherwise
     */
    public function streamMessage($extension, $context, $messageId, $fetchFolder, $forceDownload = false) {
        $startTime = microtime(true);



        // Ensure error reporting is on for debugging
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        // Initialize session if needed
        if (session_status() == PHP_SESSION_NONE) {
            $cookieParams = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Session started in streamMessage with ID: " . session_id());
        } else {
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Session already active in streamMessage with ID: " . session_id());
        }

        // Determine if download is forced via session flag (legacy check)
        if (isset($_SESSION['vm_force_download']) && $_SESSION['vm_force_download'] === true) {
            $forceDownload = true;
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Force download flag set in session");
            unset($_SESSION['vm_force_download']); // Clear flag after checking
            }
            
        try {
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Attempting to connect/reconnect IMAP for streamMessage");
                if (!$this->connect($extension, $context)) {
                $errorMsg = "Failed to connect to IMAP server";
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] " . $errorMsg);
                header("HTTP/1.0 500 Internal Server Error");
                echo $errorMsg;
                    return false;
                }
            freepbx_log(FPBX_LOG_INFO, "[IMAP] IMAP Connection successful or reused for streamMessage");

            // Set time limit for safety
            set_time_limit(300); // 5 minutes should be plenty

            // Determine the full path only for logging purposes
            $fullFolderPath = $this->_buildFolderPath($fetchFolder);

            // --- Fetch details to construct filename --- 
            $filename = "voicemail_{$messageId}.wav"; // Default filename
            $parsedHeaders = $this->_fetchAndParseHeaders((string)$messageId, $fetchFolder);
            if ($parsedHeaders !== null) {
                $cidNum = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $parsedHeaders['callerid_num'] ?? 'Unknown'); // Sanitize CID number
                $cidName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $parsedHeaders['callerid_name'] ?? ''); // Sanitize CID name
                $cidPart = !empty($cidNum) ? $cidNum : (!empty($cidName) ? $cidName : 'Unknown'); // Prefer number, then name
                $dateTimeStr = date('Ymd-His', $parsedHeaders['origtime'] ?? time());
                $filename = "VM-{$extension}-{$cidPart}-{$dateTimeStr}.wav";
                freepbx_log(FPBX_LOG_INFO, "[IMAP] streamMessage: Constructed filename: $filename");
            } else {
                freepbx_log(FPBX_LOG_WARNING, "[IMAP] streamMessage: Could not fetch headers for UID $messageId to create custom filename. Using default: $filename");
            }
            // --- End filename construction ---

            // Call the internal helper to handle the actual streaming (helper will expand folder)
            $result = $this->_streamAudioPart($messageId, $fetchFolder, $forceDownload, true, $filename);

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000);
            freepbx_log(FPBX_LOG_INFO, "[IMAP] streamMessage completed in {$duration}ms for UID $messageId. Result: " . ($result ? 'Success' : 'Failure'));

            return $result;

                } catch (\Exception $e) {
            $errorMsg = "Exception in streamMessage setup: " . $e->getMessage();
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] " . $errorMsg);
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Trace: " . $e->getTraceAsString());
            if (!headers_sent()) {
                header("HTTP/1.0 500 Internal Server Error");
                echo "Error preparing to stream audio: " . $e->getMessage();
            }
                return false;
        } catch (\Error $e) {
            $errorMsg = "PHP Error in streamMessage setup: " . $e->getMessage();
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] " . $errorMsg);
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Trace: " . $e->getTraceAsString());
            if (!headers_sent()) {
                header("HTTP/1.0 500 Internal Server Error");
                echo "PHP Error preparing to stream audio: " . $e->getMessage();
            }
            return false;
        }
    }

    
    /**
     * Find audio part information in a message structure
     * 
     * @param object $structure   Message structure
     * @param string &$partNumber Output parameter for part number
     * @param string &$mimeType   Output parameter for MIME type
     * @param string $prefix      Current part number prefix
     * @return bool True if audio part found
     */
    private function findAudioPartInfo($structure, &$partNumber, &$mimeType, $prefix = '') {
        // Log message structure for debugging
        freepbx_log(FPBX_LOG_INFO, "[IMAP] Examining message structure for audio part");
        
        // Add fallback value for part number
        $fallbackPartNumber = '';
        $fallbackMimeType = '';

        // Simple case: top-level is audio
        if ($structure->type == 4) { // TYPEAUDIO
            $partNumber = "1";
            $subtype = strtolower($structure->subtype ?? 'wav');
            $mimeType = "audio/$subtype";
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Found audio part at top level: $mimeType");
            return true;
        }
        
        // For multipart messages, look for audio parts
        if ($structure->type == 1 && !empty($structure->parts)) { // TYPEMULTIPART
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Searching for audio in multipart message with " . count($structure->parts) . " parts");
            
            // Search through all parts for audio content
            foreach ($structure->parts as $partIndex => $part) {
                $currentPartNumber = $prefix ? $prefix . '.' . ($partIndex + 1) : ($partIndex + 1);
                
                // Direct match for audio type
                if ($part->type == 4) { // TYPEAUDIO
                    $partNumber = $currentPartNumber;
                    $subtype = strtolower($part->subtype ?? 'wav');
                    $mimeType = "audio/$subtype";
                    freepbx_log(FPBX_LOG_INFO, "[IMAP] Found audio part at $partNumber: $mimeType");
                    return true;
                }
                
                // Check application type with wav subtype (some servers do this)
                if ($part->type == 3 && // TYPEAPPLICATION
                    isset($part->subtype) && 
                    (strtolower($part->subtype) == 'wav' || 
                     strtolower($part->subtype) == 'wave' || 
                     strtolower($part->subtype) == 'x-wav' || 
                     strtolower($part->subtype) == 'octet-stream' ||
                     strtolower($part->subtype) == 'audio/wav')) {
                    $partNumber = $currentPartNumber;
                    $mimeType = "audio/x-wav"; // Use most compatible MIME type
                    freepbx_log(FPBX_LOG_INFO, "[IMAP] Found application/wav part at $partNumber");
                    return true;
                }
                
                // Check for any octet-stream as a potential fallback
                if ($part->type == 3 && isset($part->subtype) && 
                    strtolower($part->subtype) == 'octet-stream') {
                    $fallbackPartNumber = $currentPartNumber;
                    $fallbackMimeType = "audio/x-wav"; // Assume wav as fallback
                    freepbx_log(FPBX_LOG_INFO, "[IMAP] Found potential fallback octet-stream at $currentPartNumber");
                    // Don't return yet, keep looking for better matches
                }
                
                // Check if there are parameters with audio content-type
                    if (isset($part->parameters) && is_array($part->parameters)) {
                        foreach ($part->parameters as $param) {
                        if (strtolower($param->attribute) == 'content-type' && 
                            strpos(strtolower($param->value), 'audio') !== false) {
                            $partNumber = $currentPartNumber;
                            $mimeType = strtolower($param->value);
                            freepbx_log(FPBX_LOG_INFO, "[IMAP] Found audio content-type in parameters: $mimeType");
                            return true;
                        }
                        
                        // Look for filename with audio extension
                        if ((strtolower($param->attribute) == 'name' || 
                             strtolower($param->attribute) == 'filename') && 
                            preg_match('/\.(wav|mp3|WAV|MP3|aiff|gsm|vox)$/i', $param->value)) {
                            $partNumber = $currentPartNumber;
                            $ext = strtolower(pathinfo($param->value, PATHINFO_EXTENSION));
                            $mimeType = ($ext == 'mp3') ? 'audio/mpeg' : 'audio/x-wav';
                            freepbx_log(FPBX_LOG_INFO, "[IMAP] Found audio filename in parameters: {$param->value}, using mime type: $mimeType");
                            return true;
                        }
                    }
                }
                
                // Check for dparameters (additional parameters set)
                if (isset($part->dparameters) && is_array($part->dparameters)) {
                    foreach ($part->dparameters as $param) {
                        // Look for filename with audio extension in disposition parameters
                        if ((strtolower($param->attribute) == 'filename' || 
                             strtolower($param->attribute) == 'name') && 
                            preg_match('/\.(wav|mp3|WAV|MP3|aiff|gsm|vox)$/i', $param->value)) {
                            $partNumber = $currentPartNumber;
                            $ext = strtolower(pathinfo($param->value, PATHINFO_EXTENSION));
                            $mimeType = ($ext == 'mp3') ? 'audio/mpeg' : 'audio/x-wav';
                            freepbx_log(FPBX_LOG_INFO, "[IMAP] Found audio filename in disposition parameters: {$param->value}, using mime type: $mimeType");
                            return true;
                        }
                    }
                }
                
                // Recursively check nested multipart
                if ($part->type == 1 && !empty($part->parts)) { // Nested TYPEMULTIPART
                    if ($this->findAudioPartInfo($part, $partNumber, $mimeType, $currentPartNumber)) {
                        return true;
                    }
                }
            }
        }
        
        // If we've found a fallback but no definitive match, use the fallback
        if (!empty($fallbackPartNumber)) {
            $partNumber = $fallbackPartNumber;
            $mimeType = $fallbackMimeType;
            freepbx_log(FPBX_LOG_INFO, "[IMAP] No definitive audio part found, using fallback: part $partNumber with MIME type $mimeType");
            return true;
        }
        
        freepbx_log(FPBX_LOG_INFO, "[IMAP] No audio part found in message structure");
        return false;
    }
    
    /**
     * Get encoding for a specific part number
     * 
     * @param object $structure  Message structure
     * @param string $partNumber Part number to find
     * @param string $prefix     Current part number prefix
     * @return int Encoding value (3=BASE64, 4=QUOTED-PRINTABLE, etc.)
     */
    private function getPartEncoding($structure, $partNumber, $prefix = '') {
        $currentPartNumber = $prefix ?: '1';
        
        if ($currentPartNumber == $partNumber) {
            return $structure->encoding;
        }
        
        if (!isset($structure->parts) || !is_array($structure->parts)) {
            return 0;
        }
        
        for ($i = 0; $i < count($structure->parts); $i++) {
            $newPrefix = empty($prefix) ? ($i + 1) : $prefix . '.' . ($i + 1);
            $encoding = $this->getPartEncoding($structure->parts[$i], $partNumber, $newPrefix);
            if ($encoding !== 0) {
                return $encoding;
            }
        }
        
        return 0;
    }
    
    /**
     * Mark a message as read or unread
     * 
     * @param string $extension Extension/mailbox number
     * @param string $context   Voicemail context
     * @param string $messageId Message ID to mark
     * @param bool   $read      True to mark as read, false for unread
     * @param string $imapfolder    Folder name (default: INBOX)
     * @return bool True if successful, false otherwise
     */
    public function markMessage($extension, $context, $messageId, $read = true, $imapfolder = 'INBOX') {
        
        if (!$this->stream instanceof \IMAP\Connection) {
            if (!$this->connect($extension, $context)) {
                return false;
            }
        }
        
        try {
            // Select the mailbox
            if (!$this->selectMailbox($imapfolder)) {
                return false;
            }
        
            // Set the flag
            $flag = "\\\\Seen"; // Always operate on the Seen flag
            $result = false;
            
        if ($read) {
                // Mark as read: Set the \\Seen flag
                $result = imap_setflag_full($this->stream, $messageId, $flag, ST_UID);
        } else {
                // Mark as unread: Clear the \\Seen flag
                $result = imap_clearflag_full($this->stream, $messageId, $flag, ST_UID);
            }
            
            if ($result) {
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Move a message to another folder
     * 
     * @param string $extension Extension/mailbox number
     * @param string $context   Voicemail context
     * @param string $messageId Message ID to move
     * @param string $toFolder  Destination folder name
     * @param string $fromFolder Source folder name (default: INBOX)
     * @return bool True if successful, false otherwise
     */
    public function moveMessage($extension, $context, $messageId, $toFolder, $fromFolder = 'INBOX') {
            freepbx_log(FPBX_LOG_INFO, "[IMAP] moveMessage called for extension $extension, context $context, msgId $messageId, fromFolder=$fromFolder, toFolder=$toFolder");
        
        if (!$this->stream instanceof \IMAP\Connection) {
            freepbx_log(FPBX_LOG_INFO, "[IMAP] IMAP connection not active, attempting to connect");
            if (!$this->connect($extension, $context)) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] Failed to connect to IMAP server");
            return false;
            }
        }
        
        try {
            // Select the source mailbox
            if (!$this->selectMailbox($fromFolder)) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] Failed to select source mailbox: $fromFolder");
            return false;
        }
        
            // Make sure the destination folder exists
            $allFoldersResult = $this->getVoicemailFolders(); // Corrected method call
            // Extract just the paths from the structured result
            $allFolderPaths = array_map(function($folderInfo) { return $folderInfo['path']; }, $allFoldersResult);

            // Use the _buildFolderPath helper to get the correct hierarchical path
            $fullCalculatedPath = $this->_buildFolderPath($toFolder);
            
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Consistent Full Path (for check/create/move): $fullCalculatedPath");

            // 2. Check if the calculated folder path exists (case-insensitive)
            $folderExists = false;
            foreach ($allFolderPaths as $serverPath) {
                if (strcasecmp($fullCalculatedPath, $serverPath) === 0) {
                    $folderExists = true;
                    freepbx_log(FPBX_LOG_INFO, "[IMAP] Target folder structure $fullCalculatedPath exists (case-insensitive match with $serverPath).");
                    break;
                }
            }
            
            // 3. Create folder only if it doesn't exist (case-insensitive check failed)
            if (!$folderExists) {
                freepbx_log(FPBX_LOG_INFO, "[IMAP] Target folder structure $fullCalculatedPath does not exist in paths: " . implode(", ", $allFolderPaths) . ". Attempting to create it.");
                $baseMailbox = $this->_buildServerString();
                // Use $fullCalculatedPath for creation attempt
                if (!@imap_createmailbox($this->stream, $baseMailbox . $fullCalculatedPath)) {
                    $error = imap_last_error() ?: 'Unknown create error';
                    if (stripos($error, 'Mailbox already exists') !== false) {
                        freepbx_log(FPBX_LOG_WARNING, "[IMAP] moveMessage: Attempted to create folder $fullCalculatedPath but it already exists (possibly created concurrently?). Continuing.");
                    } else {
                        freepbx_log(FPBX_LOG_ERROR, "[IMAP] moveMessage: Failed to create folder $fullCalculatedPath: " . $error);
            return false;
        }
                } else {
                    freepbx_log(FPBX_LOG_INFO, "[IMAP] moveMessage: Successfully created folder: $fullCalculatedPath");
                }
            }
            
            // 4. Move the message using the consistently calculated full path
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Preparing to move message UID {$messageId} to folder '{$fullCalculatedPath}' using stream resource ID: " . (is_resource($this->stream) ? get_resource_id($this->stream) : 'Not a resource'));
            // Use $fullCalculatedPath for the actual move
            $result = @imap_mail_move($this->stream, $messageId, $fullCalculatedPath, CP_UID);
            $last_error = imap_last_error() ?: 'None'; // Get error immediately after
            freepbx_log(FPBX_LOG_INFO, "[IMAP] imap_mail_move result: " . ($result ? 'true' : 'false') . ". Last IMAP error: " . $last_error);
            
            if (!$result) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] Failed to move message: " . $last_error); // Log again on explicit failure
                return false;
            }
            
            // Expunge the mailbox to complete the move
            if (!@imap_expunge($this->stream)) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] Warning: Failed to expunge after move: " . imap_last_error());
            }
            
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Successfully moved message to $fullCalculatedPath");
            return true;
        } catch (\Exception $e) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] Exception in moveMessage: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Close the IMAP connection
     */
    public function close() {
            // freepbx_log(FPBX_LOG_INFO, "[IMAP] Closing IMAP connection"); // Commented out for testing
        
        // First check if we have a stream variable at all
        if (!$this->stream) {
            // freepbx_log(FPBX_LOG_INFO, "[IMAP] No IMAP connection to close"); // Commented out for testing
            return false;
        }
        
        // Check if it's a valid IMAP connection object
        if (!($this->stream instanceof \IMAP\Connection)) {
            // freepbx_log(FPBX_LOG_INFO, "[IMAP] IMAP connection is not valid, clearing reference"); // Commented out for testing
            $this->stream = null;
            return false;
        }
        
            try {
                // First check if the connection is still valid, but wrap in try-catch
                // because imap_ping can throw an error on already closed connections
                $pingResult = false;
                try {
                    $pingResult = @imap_ping($this->stream);
                } catch (\Exception $e) {
                    // freepbx_log(FPBX_LOG_INFO, "[IMAP] Error checking connection: " . $e->getMessage()); // Commented out for testing
                } catch (\ValueError $ve) {
                    // freepbx_log(FPBX_LOG_INFO, "[IMAP] Connection already closed: " . $ve->getMessage()); // Commented out for testing
                $this->stream = null;
                return true;
                }
                
                if ($pingResult) {
                try {
                    @imap_close($this->stream);
                    // freepbx_log(FPBX_LOG_INFO, "[IMAP] IMAP connection closed successfully"); // Commented out for testing
                } catch (\Exception $e) {
                    // freepbx_log(FPBX_LOG_INFO, "[IMAP] Error during imap_close: " . $e->getMessage()); // Commented out for testing
                } catch (\ValueError $ve) {
                    // freepbx_log(FPBX_LOG_INFO, "[IMAP] Connection already closed during imap_close: " . $ve->getMessage()); // Commented out for testing
                }
                } else {
                    // freepbx_log(FPBX_LOG_INFO, "[IMAP] IMAP connection already closed or invalid, clearing reference"); // Commented out for testing
                }
                
                // Clear the reference regardless
            $this->stream = null;
                return true;
            } catch (\Exception $e) {
                // freepbx_log(FPBX_LOG_ERROR, "[IMAP] Error closing IMAP connection: " . $e->getMessage()); // Commented out for testing
                // Clear the reference even if there was an error
                $this->stream = null;
            return false;
        }
    }
    
    /**
     * Check if IMAP greetings are enabled
     */
    public function areImapGreetingsEnabled() {
        freepbx_log(FPBX_LOG_INFO, "[IMAP] areImapGreetingsEnabled check: Value of \$this->imapgreetings is '{$this->imapgreetings}'");
        return $this->imapgreetings === 'yes';
    }
    
    /**
     * Get greetings for a specific extension
     */
    public function getGreetings($extension, $context) {
            freepbx_log(FPBX_LOG_INFO, "[IMAP] getGreetings called for extension $extension, context $context");
            
         // Use main connect method
        if (!$this->stream instanceof \IMAP\Connection) {
            freepbx_log(FPBX_LOG_INFO, "[IMAP] IMAP connection not active, attempting to connect for getGreetings");
            if (!$this->connect($extension, $context)) {
                 freepbx_log(FPBX_LOG_ERROR, "[IMAP] Failed to connect to IMAP server for getGreetings");
                return [];
            }
        }
        
        try {
             // Use the simple folder name for processMessages
             $fetchFolder = $this->greetingsFolder ?: 'Greetings';
             freepbx_log(FPBX_LOG_INFO, "[IMAP] getGreetings: greetings folder full path resolves to '" . $this->_buildFolderPath($fetchFolder) . "'");
             freepbx_log(FPBX_LOG_INFO, "[IMAP] getGreetings: using fetchFolder='$fetchFolder', greetingsFolder='$this->greetingsFolder'");
             
             // Process messages using the simplified, shared function
             $detailedMessages = $this->processMessages($fetchFolder);

             if (empty($detailedMessages)) {
                freepbx_log(FPBX_LOG_INFO, "[IMAP] No greetings found in folder $folderPath by processMessages");
                        return [];
                    }
                    
             // Transform the detailed output into the simple format needed for greetings view
            $greetings = [];
             foreach ($detailedMessages as $msg) {
                 $greetingType = 'unknown';
                 // Determine greeting type ONLY from the reliable X-Asterisk header
                 if (!empty($msg['type']) && $msg['type'] !== 'Message') {
                     $greetingType = $msg['type'];
                 }

                 // Only add if we determined a valid type from the header
                 if ($greetingType !== 'unknown') {
                     $greetings[] = [
                         'type' => $greetingType,    // Use type directly from header
                         'timestamp' => $msg['origtime'] // Use the reliable origtime
                     ];
                     freepbx_log(FPBX_LOG_INFO, "[IMAP] getGreetings: Found greeting type '$greetingType' with timestamp " . $msg['origtime']);
                 } else {
                     freepbx_log(FPBX_LOG_WARNING, "[IMAP] getGreetings: Message has type '" . ($msg['type'] ?? 'null') . "', skipping");
                 }
             }

             // Sort greetings alphabetically by type for consistent UI display
             usort($greetings, function($a, $b) {
                 return strcmp($a['type'], $b['type']);
             });

             freepbx_log(FPBX_LOG_INFO, "[IMAP] Transformed detailed messages into " . count($greetings) . " greetings summary");
        return $greetings;
        } catch (\Exception $e) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] Exception in getGreetings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Stream a greeting file
     * 
     * @param string $extension The extension
     * @param string $context The voicemail context
     * @param string $greetingType The greeting type
     * @param bool $forceDownload Whether to force download instead of inline playback
     * @return bool True if successful
     */
    public function streamGreeting($extension, $context, $greetingType, $forceDownload = false) { // Added $forceDownload parameter
		freepbx_log(FPBX_LOG_INFO, "[IMAP] streamGreeting called for extension $extension, context $context, greeting $greetingType, Download=" . ($forceDownload ? 'true' : 'false')); // Updated log
        
        if (!$this->areImapGreetingsEnabled()) {
			freepbx_log(FPBX_LOG_ERROR, "[IMAP] streamGreeting: IMAP greetings are not enabled");
            // Send 403 Forbidden if headers not already sent
            if (!headers_sent()) {
                header("HTTP/1.0 403 Forbidden");
                echo "IMAP greetings are not enabled.";
            }
            return false;
        }
        
        if (!$this->stream instanceof \IMAP\Connection) {
			freepbx_log(FPBX_LOG_INFO, "[IMAP] streamGreeting: IMAP connection not active, attempting to connect");
            if (!$this->connect($extension, $context)) {
				freepbx_log(FPBX_LOG_ERROR, "[IMAP] streamGreeting: Failed to connect to IMAP server");
                // Send 500 Internal Server Error if headers not already sent
                if (!headers_sent()) {
                    header("HTTP/1.0 500 Internal Server Error");
                    echo "Failed to connect to IMAP server.";
                }
            return false;
        }
        }
        
        try {
            // Use the simple folder name for selection
            $folderName = $this->greetingsFolder;
            
            // Select the greetings mailbox - Needed for the helper
            if (!$this->selectMailbox($folderName)) {
				freepbx_log(FPBX_LOG_ERROR, "[IMAP] streamGreeting: Failed to select greetings folder: $folderName");
                // Send 500 Internal Server Error if headers not already sent
                if (!headers_sent()) {
                    header("HTTP/1.0 500 Internal Server Error");
                    echo "Failed to select greetings folder.";
                }
                return false;
            }
            
			// --- CORRECTED LOGIC: Use the helper to find the UID --- 
			freepbx_log(FPBX_LOG_INFO, "[IMAP] streamGreeting: Searching for UID with Message Type \"$greetingType\" using helper _findUidByMessageType in folder $folderName");
			$uid = $this->_findUidByMessageType($folderName, $greetingType);

            // Check if the UID was found
            if ($uid === null) {
                freepbx_log(FPBX_LOG_WARNING, "[IMAP] streamGreeting: Greeting type '$greetingType' not found via helper in folder '$folderName'.");
                if (!headers_sent()) {
                    header("HTTP/1.0 404 Not Found");
                    echo "Greeting type '$greetingType' not found.";
                }
				return false; // Return false if UID not found
            }

			freepbx_log(FPBX_LOG_INFO, "[IMAP] streamGreeting: Found greeting with UID $uid. Proceeding to stream.");

			// <<< FIX: Construct custom filename if downloading >>>
			$outputFilename = null;
			if ($forceDownload) {
				// Sanitize inputs slightly for filename
				$safeExt = preg_replace('/[^a-zA-Z0-9_-]/', '', $extension);
				$safeType = preg_replace('/[^a-zA-Z0-9_-]/', '', $greetingType);
				$outputFilename = "{$safeExt}-{$safeType}.wav"; // Example: 203-unavail.wav
				freepbx_log(FPBX_LOG_INFO, "[IMAP] streamGreeting: Download requested, using filename: $outputFilename");
			}
			// <<< END FIX >>>

			// Call the internal helper to handle the actual streaming, passing the UID
			// and the custom filename if it was generated for download.
			$result = $this->_streamAudioPart($uid, $folderName, $forceDownload, true, $outputFilename);

            // If _streamAudioPart fails internally, it might have already sent headers/output.
            // We only log here. The return value might not directly correlate to HTTP status anymore.
			freepbx_log(FPBX_LOG_INFO, "[IMAP] streamGreeting completed. _streamAudioPart Result: " . ($result ? 'Success(likely)' : 'Failure(likely)'));
			// Return the result of _streamAudioPart, but note that the HTTP response might already be determined.
            return $result;

        } catch (\Exception $e) {
			freepbx_log(FPBX_LOG_ERROR, "[IMAP] Exception in streamGreeting: " . $e->getMessage());
            // Send 500 error if headers not already sent
            if (!headers_sent()) {
                header("HTTP/1.0 500 Internal Server Error");
                echo "Server error processing greeting request.";
            }
            return false;
        }
    }
    
    /**
     * Delete a greeting from IMAP
     * 
     * @param string $extension The extension
     * @param string $context The voicemail context
     * @param string $greetingType The greeting type or message ID
     * @return bool True if deletion was successful
     */
    public function deleteGreeting($extension, $context, $greetingType) {
        freepbx_log(FPBX_LOG_INFO, "[IMAP] Params: Ext=$extension, Ctx=$context, Type/ID=$greetingType");
        
        if (!$this->areImapGreetingsEnabled()) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] IMAP greetings are not enabled");
            return false;
        }
        
        // Use the main connect method to ensure consistent connection/impersonation
        if (!$this->stream instanceof \IMAP\Connection) {
            freepbx_log(FPBX_LOG_INFO, "[IMAP] IMAP connection not active, attempting to connect for deleteGreeting");
            if (!$this->connect($extension, $context)) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] Failed to connect to IMAP server for deleteGreeting");
                return false;
            }
        }
        
        // --- CORRECTED LOGIC: Find UID by Type Name First --- 
        try {
            // Get the greetings folder path
            $folderPath = $this->_buildFolderPath($this->greetingsFolder);
            
            // Select the greetings mailbox - Needed for the helper
            if (!$this->selectMailbox($folderPath)) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] deleteGreeting: Failed to select greetings folder: $folderPath");
                return false;
            }
            
            // Find the UID using the helper function based on the type name
            freepbx_log(FPBX_LOG_INFO, "[IMAP] deleteGreeting: Searching for UID with Message Type \"$greetingType\" using helper in folder $folderPath");
            $uidToDelete = $this->_findUidByMessageType($folderPath, $greetingType);

            // Check if UID was found
            if ($uidToDelete === null) {
                freepbx_log(FPBX_LOG_WARNING, "[IMAP] deleteGreeting: Greeting type '$greetingType' not found via helper in folder '$folderPath'. Cannot delete.");
                return false; // Indicate greeting not found
            }

            freepbx_log(FPBX_LOG_INFO, "[IMAP] deleteGreeting: Found UID '$uidToDelete' for type '$greetingType'. Proceeding with deletion.");
            
            // Call the internal helper method to delete the item by the found UID
            return $this->_deleteItemByUid($uidToDelete, $folderPath); 

        } catch (\Exception $e) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] Exception in deleteGreeting while finding/deleting UID: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get the IMAP connection
     * @return resource|null The IMAP connection resource or null if not connected
     */
    public function getConnection() {
        // Check if the connection variable exists
        if (!$this->stream) {
            return null;
        }
        
        // Check if this is a valid connection object
        if (!($this->stream instanceof \IMAP\Connection)) {
            freepbx_log(FPBX_LOG_WARNING, "[IMAP] Warning: Stream variable exists but is not a valid IMAP connection");
            $this->stream = null;
            return null;
        }
        
        // Try a minimal connection test
        try {
            // Only do a simple check to avoid over-checking connections
            $pingOK = @imap_ping($this->stream);
            if (!$pingOK) {
                freepbx_log(FPBX_LOG_WARNING, "[IMAP] Warning: IMAP connection failed ping test");
                return null;
            }
        } catch (\Exception $e) {
            freepbx_log(FPBX_LOG_WARNING, "[IMAP] Exception in getConnection: " . $e->getMessage());
            return null;
        } catch (\ValueError $ve) {
            // This happens when the connection is already closed
            freepbx_log(FPBX_LOG_WARNING, "[IMAP] ValueError in getConnection: " . $ve->getMessage());
            $this->stream = null;
            return null;
        }
        
        return $this->stream;
    }

    /**
     * Refactored: Get the list of relevant voicemail folders from IMAP.
     *
     * Lists folders under the configured parent folder (or root if parent is empty),
     * excluding the greetings folder. Returns a structured array.
     *
     * @return array Associative array keyed by simple folder name:
     *               ['FOLDER_KEY' => ['label' => 'Translated Label', 'path' => 'Full/IMAP/Path', 'key' => 'FOLDER_KEY']]
     */
    public function getVoicemailFolders() {
        freepbx_log(FPBX_LOG_INFO, "[IMAP] getVoicemailFolders: Fetching IMAP folder list...");
        error_log("[DEBUG] getVoicemailFolders called at " . date('Y-m-d H:i:s'));

        $imapparentfolder = $this->imapparentfolder; // Can be empty/null
        $greetingsFolderSimpleName = $this->greetingsFolder ?: 'Greetings';
        $structuredFolders = [];

        // Ensure connection
        if (!$this->stream instanceof \IMAP\Connection) {
            freepbx_log(FPBX_LOG_INFO, "[IMAP] getVoicemailFolders: No active connection, attempting to connect");
        if (!$this->connect()) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] getVoicemailFolders: Failed to connect to IMAP server");
                return [];
            }
        }
        
        // Double-check connection is still valid
        if (!$this->stream instanceof \IMAP\Connection) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] getVoicemailFolders: Connection is still not valid after connect attempt");
            return [];
        }

        try {
            // Use the helper function for the base string
            $baseString = $this->_buildServerString(); 
            $delimiter = null; // We need the delimiter to correctly identify direct children

            // Determine folder root and wildcard pattern (use slash so parent mailbox itself is suppressed)
            $baseFolderPath = $this->mailboxRoot; // Already pre-computed
            $searchPattern = ($baseFolderPath !== '' ? rtrim($baseFolderPath, '/') . '/*' : '*');
            $reference = $baseString;
            freepbx_log(FPBX_LOG_INFO, "[IMAP] getVoicemailFolders: Searching under '$baseFolderPath' ('$searchPattern')");

                        // List mailboxes based on the pattern
            // Check connection before using it
            if (!$this->stream instanceof \IMAP\Connection) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] getVoicemailFolders: Connection is not valid, cannot list folders");
                return [];
            }
            
            $mailboxes = @imap_list($this->stream, $reference, $searchPattern);
            
            freepbx_log(FPBX_LOG_INFO, "[IMAP] getVoicemailFolders: Search pattern: '$searchPattern'");
            freepbx_log(FPBX_LOG_INFO, "[IMAP] getVoicemailFolders: Reference: '$reference'");
            
            // Check for IMAP errors after the call
            $imapError = imap_last_error();
            if ($imapError) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] getVoicemailFolders: IMAP error after imap_list: $imapError");
                return [];
            }
                
                if ($mailboxes) {
                freepbx_log(FPBX_LOG_INFO, "[IMAP] getVoicemailFolders: Raw folders found: " . implode(", ", $mailboxes));
                foreach ($mailboxes as $mailboxPathRaw) {
                    $fullPath = str_replace($reference, '', $mailboxPathRaw); // Get path relative to server root
                    $simpleName = basename($fullPath); // Get the last part of the path
                    
                    freepbx_log(FPBX_LOG_INFO, "[IMAP] getVoicemailFolders: Processing folder - Raw: '$mailboxPathRaw', FullPath: '$fullPath', SimpleName: '$simpleName'");

                    // Skip the dedicated greetings folder
                    freepbx_log(FPBX_LOG_INFO, "[IMAP] getVoicemailFolders: Comparing simpleName='$simpleName' with greetingsFolderSimpleName='$greetingsFolderSimpleName'");
                    if (strcasecmp($simpleName, $greetingsFolderSimpleName) === 0) {
                        freepbx_log(FPBX_LOG_INFO, "[IMAP] getVoicemailFolders: Skipping greetings folder: '$fullPath'");
                        continue;
                    }
                    
                    // Determine label (Translate standard folders)
                    $label = '';
                    switch (strtoupper($simpleName)) { // Use strtoupper for case-insensitive matching
                        case 'INBOX': $label = _('INBOX'); break;
                        case 'OLD': $label = _('Old'); break;
                        case 'WORK': $label = _('Work'); break;
                        case 'FAMILY': $label = _('Family'); break;
                        case 'FRIENDS': $label = _('Friends'); break;
                        case 'URGENT': $label = _('Urgent'); break;
                        default: $label = ucfirst($simpleName);
                    }

                    // Use simple name as the key for the associative array
                    // Normalize the key for Inbox to always be uppercase 'INBOX'
                    $folderKey = (strtoupper($simpleName) === 'INBOX') ? 'INBOX' : $simpleName;
                    if (!isset($structuredFolders[$folderKey])) {
                         $structuredFolders[$folderKey] = [
                            'label' => $label,
                            'path' => $fullPath,
                            'key' => $folderKey // Include the potentially normalized key itself for convenience
                        ];
                        freepbx_log(FPBX_LOG_INFO, "[IMAP] getVoicemailFolders: Adding folder: Key='$folderKey', Path='$fullPath', Label='$label'");
                    }
                }
            } else {
                $error = imap_last_error();
                freepbx_log(FPBX_LOG_WARNING, "[IMAP] getVoicemailFolders: imap_list failed or returned no mailboxes for pattern '$searchPattern'." . ($error ? " Error: $error" : ""));
            }
        } catch (\Exception $e) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] getVoicemailFolders: Exception getting IMAP folders: " . $e->getMessage());
            return []; // Return empty on exception
        }

        // --- Sort the folders: INBOX first, then alphabetically by label ---
        $sortedFolders = [];
        $inboxEntry = null;

        // Extract Inbox if present (using normalized key 'INBOX')
        if (isset($structuredFolders['INBOX'])) {
            $inboxEntry = $structuredFolders['INBOX'];
            unset($structuredFolders['INBOX']);
        }

        // Sort the remaining folders alphabetically by label (case-insensitive)
        uasort($structuredFolders, function($a, $b) {
            return strcasecmp($a['label'], $b['label']);
        });

        // Add Inbox back to the beginning if it existed
        if ($inboxEntry !== null) {
            $sortedFolders['INBOX'] = $inboxEntry;
        }

        // Merge the sorted remaining folders
        $finalFolders = array_merge($sortedFolders, $structuredFolders);
        // --- End Sorting ---


        freepbx_log(FPBX_LOG_INFO, "[IMAP] getVoicemailFolders: Returning " . count($finalFolders) . " structured folders.");
        error_log("[DEBUG] getVoicemailFolders returning " . count($finalFolders) . " folders: " . implode(', ', array_keys($finalFolders)));
        return $finalFolders; // Return the sorted array
    }

    /**
     * Create a new folder on the IMAP server
     * 
     * @param string $folderName The name of the folder to create
     * @return bool True if successful, false otherwise
     */
    public function createFolder($folderName) {
        freepbx_log(FPBX_LOG_INFO, "[IMAP] createFolder: Creating folder '$folderName'");
        
        // Ensure connection
        if (!$this->stream instanceof \IMAP\Connection) {
            freepbx_log(FPBX_LOG_INFO, "[IMAP] createFolder: No active connection, attempting to connect");
            if (!$this->connect()) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] createFolder: Failed to connect to IMAP server");
                return false;
            }
        }
        
        // Double-check connection is still valid
        if (!$this->stream instanceof \IMAP\Connection) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] createFolder: Connection is still not valid after connect attempt");
            return false;
        }

        try {
            // Build the full folder path
            $baseString = $this->_buildServerString();
            $fullFolderPath = $this->mailboxRoot . '/' . $folderName;
            
            freepbx_log(FPBX_LOG_INFO, "[IMAP] createFolder: Creating folder at path: '$fullFolderPath'");
            
            // Create the folder using IMAP CREATE command
            $result = @imap_createmailbox($this->stream, $baseString . $fullFolderPath);
            
            // Check for IMAP errors
            $imapError = imap_last_error();
            if ($imapError) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] createFolder: IMAP error after create: $imapError");
                return false;
            }
            
            if ($result) {
                freepbx_log(FPBX_LOG_INFO, "[IMAP] createFolder: Successfully created folder '$folderName'");
                return true;
            } else {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] createFolder: Failed to create folder '$folderName'");
                return false;
            }
            
        } catch (\Exception $e) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] createFolder: Exception creating folder: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a folder from the IMAP server
     * 
     * @param string $folderName The name of the folder to delete
     * @return bool True if successful
     */
    public function deleteFolder($folderName) {
        freepbx_log(FPBX_LOG_INFO, "[IMAP] deleteFolder: Deleting folder '$folderName'");
        
        // Ensure connection
        if (!$this->stream instanceof \IMAP\Connection) {
            freepbx_log(FPBX_LOG_INFO, "[IMAP] deleteFolder: No active connection, attempting to connect");
            if (!$this->connect()) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] deleteFolder: Failed to connect to IMAP server");
                return false;
            }
        }
        
        // Double-check connection is still valid
        if (!$this->stream instanceof \IMAP\Connection) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] deleteFolder: Connection is still not valid after connect attempt");
            return false;
        }

        try {
            // Build the full folder path
            $baseString = $this->_buildServerString();
            $fullFolderPath = $this->mailboxRoot . '/' . $folderName;
            
            freepbx_log(FPBX_LOG_INFO, "[IMAP] deleteFolder: Deleting folder at path: '$fullFolderPath'");
            
            // Delete the folder using IMAP DELETE command
            $result = @imap_deletemailbox($this->stream, $baseString . $fullFolderPath);
            
            // Check for IMAP errors
            $imapError = imap_last_error();
            if ($imapError) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] deleteFolder: IMAP error after delete: $imapError");
                return false;
            }
            
            if ($result) {
                freepbx_log(FPBX_LOG_INFO, "[IMAP] deleteFolder: Successfully deleted folder '$folderName'");
                return true;
            } else {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] deleteFolder: Failed to delete folder '$folderName'");
                return false;
            }
            
        } catch (\Exception $e) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] deleteFolder: Exception deleting folder: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a greeting in the IMAP mailbox
     * 
     * @param string $extension The extension
     * @param string $context The context
     * @param string $greetingData The greeting data
     * @param string $greetingName The greeting name
     * @return bool True if successful
     */
    public function createGreeting($extension, $context, $greetingData, $greetingName) {
        freepbx_log(FPBX_LOG_INFO, "[IMAP] === createGreeting called [" . date('Y-m-d H:i:s') . "] ===");
        freepbx_log(FPBX_LOG_INFO, "[IMAP] Extension: $extension, Context: $context, Name: $greetingName");
        
        // Check if IMAP greetings are enabled
        if (!$this->areImapGreetingsEnabled()) {
            freepbx_log(FPBX_LOG_INFO, "[IMAP] IMAP greetings are not enabled");
            return false;
        }
        
        // Ensure connected using the main connect method
        if (!$this->stream instanceof \IMAP\Connection) {
            freepbx_log(FPBX_LOG_INFO, "[IMAP] IMAP connection not active, attempting to connect for createGreeting");
            if (!$this->connect($extension, $context)) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] Failed to connect to IMAP server for createGreeting");
            return false;
            }
        }

        // Get necessary folder paths
        $greetingsFolder = $this->greetingsFolder ?: 'Greetings';
        $greetingsFolderPath = $this->_buildFolderPath($greetingsFolder);
        $greetingsMailboxFullPath = $this->_buildFolderPath($greetingsFolder); // Use helper for full path

        // Ensure Greetings folder exists using our generic method
        if (!$this->createFolder($greetingsFolder)) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] Failed to create Greetings folder for greeting creation");
            return false;
        }
        
        // Select the greetings folder (important before search/append)
        if (!$this->selectMailbox($greetingsFolderPath)) { // Use the path, selectMailbox handles base string
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] Failed to select greetings folder '$greetingsFolderPath' before append.");
                return false;
            }
        freepbx_log(FPBX_LOG_INFO, "[IMAP] Selected greetings folder '$greetingsFolderPath'");

        try {
            // Check if greeting already exists by message type header
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Checking for existing greeting of type '$greetingName'");
            
            // Get all messages in the greetings folder
            $allMessages = @imap_search($this->stream, 'ALL', SE_UID);
            if ($allMessages && !empty($allMessages)) {
                foreach ($allMessages as $uid) {
                    // Fetch headers for this message
                    $headers = @imap_fetchheader($this->stream, $uid, FT_UID);
                    if ($headers) {
                        // Check if this message has the same greeting type
                        if (preg_match('/X-Asterisk-VM-Message-Type:\s*' . preg_quote($greetingName, '/') . '/i', $headers)) {
                            freepbx_log(FPBX_LOG_INFO, "[IMAP] Found existing greeting of type '$greetingName' with UID $uid, deleting it");
                            $this->_deleteItemByUid($uid, $greetingsFolderPath);
                        }
                    }
                }
            }
        
        // Get voicemail configuration
        global $astman;
        $vmConfig = $astman->database_get('voicemail', 'general');
        
        // Get user information from voicemail.conf
        $userName = $this->_getUserNameFromConfig($extension, $context);
        $serverName = $vmConfig['fromstring'] ?? 'PBX Phone System';
        $fromUser = $this->imapuser; // Use the currently connected user
        $currentTime = time();
        $messageId = "Asterisk-0-" . rand(100000000, 999999999) . "-" . $extension . "-" . rand(1000000, 9999999) . "@" . parse_url($this->imapserver, PHP_URL_HOST);
        
        // Create email headers that emulate Asterisk voicemail format
        $subject = "New greeting '$greetingName' on " . date('l, F j, Y \a\t g:i:s A', $currentTime) . ".";
        
        $headers = "From: \"" . $serverName . "\" <asterisk@" . parse_url($this->imapserver, PHP_URL_HOST) . ">\r\n";
        $headers .= "To: \"" . $userName . "\" <" . $fromUser . ">\r\n"; 
        $headers .= "Subject: " . $subject . "\r\n";
        $headers .= "Message-ID: <" . $messageId . ">\r\n";
        $headers .= "X-Asterisk-VM-Message-Num: 0\r\n";
        $headers .= "X-Asterisk-VM-Server-Name: " . $serverName . "\r\n";
        $headers .= "X-Asterisk-VM-Context: " . $context . "\r\n";
        $headers .= "X-Asterisk-VM-Extension: " . $extension . "\r\n";
        $headers .= "X-Asterisk-VM-Flag:\r\n";
        $headers .= "X-Asterisk-VM-Priority: 16\r\n";
        $headers .= "X-Asterisk-VM-Caller-ID-Num: " . $extension . "\r\n";
        $headers .= "X-Asterisk-VM-Caller-ID-Name: " . $userName . "\r\n";
        $headers .= "X-Asterisk-VM-Duration: 3\r\n";
        $headers .= "X-Asterisk-VM-Category:\r\n";
        $headers .= "X-Asterisk-VM-Message-Type: " . $greetingName . "\r\n";
        $headers .= "X-Asterisk-VM-Orig-date: " . date('l, F j, Y \a\t g:i:s A', $currentTime) . "\r\n";
        $headers .= "X-Asterisk-VM-Orig-time: " . $currentTime . "\r\n";
        $headers .= "X-Asterisk-VM-Message-ID: (null)\r\n";
        $headers .= "X-Asterisk-CallerID: " . $extension . "\r\n";
        $headers .= "X-Asterisk-CallerIDName: " . $userName . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        
        // Create boundary for multipart message
        $boundary = "----voicemail_" . rand(100000000000000000000, 999999999999999999999);
        $headers .= "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"\r\n";
        
        // Create email body with attachment
        $body = "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= "This message is to let you know that your greeting '$greetingName' was changed on " . date('l, F j, Y \a\t g:i:s A', $currentTime) . ".\r\n";
        $body .= "Please do not delete this message, lest your greeting vanish with it.\r\n\r\n";
        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Type: audio/wav; name=\"$greetingName.wav\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"$greetingName.wav\"\r\n\r\n";
        $body .= chunk_split(base64_encode($greetingData)) . "\r\n";
        $body .= "--" . $boundary . "--";
        
        // Append the message to the folder
            // imap_append needs the *full path* including the base string
            $fullMailboxForAppend = $this->_buildServerString() . $greetingsFolderPath; // Renamed call
            if (@imap_append($this->stream, $fullMailboxForAppend, $headers . "\r\n" . $body, "\\Seen")) {
                freepbx_log(FPBX_LOG_INFO, "[IMAP] Greeting created successfully via imap_append to $fullMailboxForAppend");
            return true;
        } else {
                $error = imap_last_error() ?: 'Unknown error appending greeting';
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] Failed to create greeting: " . $error);
            return false;
        }
        } catch (\Exception $e) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] Exception in createGreeting: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user name from voicemail configuration
     * 
     * @param string $extension The extension
     * @param string $context The context
     * @return string The user name
     */
    private function _getUserNameFromConfig($extension, $context) {
        // Try to get user name from voicemail.conf
        $vmConfig = parse_ini_file('/etc/asterisk/voicemail.conf', true);
        
        if (isset($vmConfig[$context][$extension])) {
            $mailboxConfig = $vmConfig[$context][$extension];
            // Parse the mailbox configuration: password,name,email,pager,options
            $parts = explode(',', $mailboxConfig);
            if (count($parts) >= 2 && !empty(trim($parts[1]))) {
                return trim($parts[1]); // Return the name part
            }
        }
        
        // Fallback to extension if no name found
        return $extension;
    }


    /**
     * Delete a message from the mailbox
     * 
     * @param string $extension Extension/mailbox number
     * @param string $context   Voicemail context
     * @param string $messageId Message ID to delete
     * @param string $imapfolder    Folder name (default: INBOX)
     * @return bool True if successful, false otherwise
     */
    public function deleteMessage($extension, $context, $messageId, $imapfolder = 'INBOX') {
        
        if (!$this->stream instanceof \IMAP\Connection) {
            if (!$this->connect($extension, $context)) {
                return false;
            }
        }
        
        // Use the _buildFolderPath helper to get the correct full path
        $fullFolderPath = $this->_buildFolderPath($imapfolder);

        // Call the helper method
        return $this->_deleteItemByUid($messageId, $fullFolderPath);
    }

    /**
     * Process a list of messages from the mailbox
     * @param array $messages The messages to process
     * @param string $imapfolder The folder name
     * @return array Processed messages
     */

    /**
     * Select a mailbox/folder to work with
     * 
     * @param string $imapfolder The folder name to select
     * @return bool True if successful, false otherwise
     */
    private function selectMailbox($imapfolder = 'INBOX') {
        // Sanity check for connection
        if (!$this->stream instanceof \IMAP\Connection) {
            return false;
        }
        
        // Determine the full folder path using the other helper
        $fullFolder = $this->_buildFolderPath($imapfolder);
        
        // Build the mailbox string using the base helper
        $baseMailbox = $this->_buildServerString();
        $mailboxString = $baseMailbox . $fullFolder;
        
        freepbx_log(FPBX_LOG_INFO, "[DEBUG] selectMailbox: Attempting to select folder '$imapfolder' -> fullFolder '$fullFolder' -> mailboxString '$mailboxString'");
                
                try {
                    freepbx_log(FPBX_LOG_INFO, "[DEBUG] selectMailbox: Attempting imap_reopen with mailboxString: '$mailboxString'");
                    $result = @imap_reopen($this->stream, $mailboxString);
                    
                    if ($result) {
                        freepbx_log(FPBX_LOG_INFO, "[DEBUG] selectMailbox: Successfully selected mailbox: '$mailboxString'");
                        // Check what mailbox is actually selected
                        $currentMailbox = @imap_mailboxmsginfo($this->stream);
                        if ($currentMailbox) {
                            freepbx_log(FPBX_LOG_INFO, "[DEBUG] selectMailbox: Current mailbox info - Name: '" . $currentMailbox->Mailbox . "', Messages: " . $currentMailbox->Nmsgs);
                        }
                        return true;
                    } else {
                        freepbx_log(FPBX_LOG_INFO, "[DEBUG] selectMailbox: Failed to select mailbox: '$mailboxString', error: " . (imap_last_error() ?: 'none'));
                        $error = imap_last_error();
                
                // Fallback logic (simplified example - might need adjustment based on exact server behavior)
                if ($imapfolder === 'INBOX' && $fullFolder !== 'INBOX') { // If we tried Parent/INBOX and failed
                    $directMailboxString = $this->_buildServerString() . 'INBOX'; // Renamed call
                        $result = @imap_reopen($this->stream, $directMailboxString);
                        if ($result) {
                            return true;
                    }
                        $error = imap_last_error();
                }
                // Add more fallback logic for other folders if needed

                // Check if it's a connection issue that requires reconnect (example)
                if (strpos($error, 'Connection broken') !== false || strpos($error, 'CLOSED') !== false) {
                    $this->close();
                    if ($this->connect()) { // Assumes connect uses stored credentials if needed
                        $result = @imap_reopen($this->stream, $mailboxString); // Retry original path
                        if ($result) return true;
                    }
                }
                return false; // Selection failed
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if the IMAP connection is active and valid
     * If not, attempt to reconnect
     * 
     * @return bool True if connection is valid
     */
    private function checkConnection() {
        freepbx_log(FPBX_LOG_INFO, "[IMAP] Checking IMAP connection status");
        
        // If there's no connection, try to connect
        if (!$this->stream) {
            if (!empty($this->extension) && !empty($this->context)) {
                freepbx_log(FPBX_LOG_INFO, "[IMAP] No active connection, reconnecting with saved credentials");
                return $this->connect($this->extension, $this->context);
            } else {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] No active connection and no saved credentials");
                throw new \Exception("No active IMAP connection and no saved credentials");
            }
        }
        
        // Check if the connection is still valid
        try {
            // Ping the server to see if the connection is still active
            $now = time();
            if (isset($this->lastPingTime) && ($now - $this->lastPingTime < 30)) {
                // We recently checked, assume still valid
                freepbx_log(FPBX_LOG_INFO, "[IMAP] Using cached ping result (valid for 30 seconds)");
                return true;
            }
            
            // First check if stream is a valid resource/object
            if (!($this->stream instanceof \IMAP\Connection)) {
                freepbx_log(FPBX_LOG_WARNING, "[IMAP] IMAP connection is not valid");
                $this->stream = null;
                
                // Try to reconnect if we have saved credentials
                if (!empty($this->extension) && !empty($this->context)) {
                    freepbx_log(FPBX_LOG_INFO, "[IMAP] Attempting to reconnect after invalid connection detection");
                    return $this->connect($this->extension, $this->context);
                } else {
                    throw new \Exception("Connection is not valid and no saved credentials for reconnect");
                }
            }
            
            // Try to ping the server as a lightweight check
            $pingResult = false;
            try {
            $pingResult = @imap_ping($this->stream);
            } catch (\Exception $e) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] Error during ping: " . $e->getMessage());
            } catch (\ValueError $ve) {
                freepbx_log(FPBX_LOG_ERROR, "[IMAP] ValueError during ping (connection likely closed): " . $ve->getMessage());
                $this->stream = null;
                
                // Try to reconnect if we have saved credentials
                if (!empty($this->extension) && !empty($this->context)) {
                    freepbx_log(FPBX_LOG_INFO, "[IMAP] Attempting to reconnect after ValueError during ping");
                    return $this->connect($this->extension, $this->context);
                } else {
                    throw new \Exception("Connection failed during ping and no saved credentials for reconnect");
                }
            }
            
            if ($pingResult) {
                // Connection is still good
                $this->lastPingTime = $now;
                freepbx_log(FPBX_LOG_INFO, "[IMAP] Connection is still active and valid");
                return true;
        } else {
                // If ping fails, try a more complete check
                try {
                $check = @imap_check($this->stream);
                if ($check) {
                    // Connection is still good despite ping failure
                    $this->lastPingTime = $now;
                    freepbx_log(FPBX_LOG_INFO, "[IMAP] Connection is still active and valid (ping failed but check passed)");
                    return true;
                    }
                } catch (\Exception $e) {
                    freepbx_log(FPBX_LOG_ERROR, "[IMAP] Error during connection check: " . $e->getMessage());
                } catch (\ValueError $ve) {
                    freepbx_log(FPBX_LOG_ERROR, "[IMAP] ValueError during check (connection likely closed): " . $ve->getMessage());
                }
                
                // Clear errors since we're just checking
                @imap_errors();
                
                // If we fall through to here, the connection is stale and we need to reconnect
                freepbx_log(FPBX_LOG_INFO, "[IMAP] Connection check failed, will reconnect");
                
                // Close the stale connection properly
                $this->close();
                
                // Try to reconnect if we have saved credentials
                if (!empty($this->extension) && !empty($this->context)) {
                    freepbx_log(FPBX_LOG_INFO, "[IMAP] Attempting to reconnect");
                    return $this->connect($this->extension, $this->context);
                } else {
                    freepbx_log(FPBX_LOG_ERROR, "[IMAP] Cannot reconnect - no saved credentials");
                    throw new \Exception("Connection failed and no saved credentials for reconnect");
                }
            }
        } catch (\Exception $e) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] Error during connection check: " . $e->getMessage());
            
            // If there was an exception, the connection is probably invalid - reset it
            $this->stream = null;
            
            // Try to reconnect if possible
            if (!empty($this->extension) && !empty($this->context)) {
                freepbx_log(FPBX_LOG_INFO, "[IMAP] Attempting to reconnect after exception");
                return $this->connect($this->extension, $this->context);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Send headers for audio streaming
     * 
     * @param string $contentType MIME type for the audio file
     * @param int $contentLength Size of the data in bytes
     * @param bool $forceDownload Whether to force download instead of inline playback
     * @param string $filename Filename to use in Content-Disposition header
     */
    private function sendAudioHeaders($contentType, $contentLength, $forceDownload = false, $filename = 'voicemail.wav') {
        // Ensure output buffer is clean before sending headers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Set specific Content-Type if we know it's GSM
        $finalContentType = $contentType;
        if (stripos($contentType, 'gsm') !== false) {
            $finalContentType = 'audio/gsm';
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Setting ContentType to audio/gsm");
        }

        header("Content-Type: " . $finalContentType);
        header("Content-Length: " . $contentLength);
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");
        header('Accept-Ranges: bytes'); // Restore range request support

        $disposition = $forceDownload ? 'attachment' : 'inline';
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($filename) . '"');

        freepbx_log(FPBX_LOG_INFO, "[IMAP] Finished sending headers");
    }

    /**
     * Tests various IMAP impersonation connection methods without establishing a permanent connection.
     *
     * @param string $testUsername The target user mailbox/username to impersonate.
     * @param string $authUsername The impersonation (authuser) username.
     * @param string $authPassword The impersonation (authuser) password.
     * @return array Structured results detailing success/failure and errors for each method.
     */
    public function testImapConnection($testUsername, $authUsername, $authPassword) {
        $results = [
            'success' => false,
            'error_summary' => [],
            'successful_method' => null,
            'successful_separator' => null,
            'successful_port' => null,
            'successful_flags' => null,
            'successful_auth_type' => null, // Added to store successful auth type
            'tests_run' => [],
            'auth_errors' => [] // Store specific auth errors for analysis
        ];
        $this->autoDetectMode = false;

        // --- Define connection parameters to try ---
        $portsToTry = [$this->imapport ?: 143]; // Start with configured/default port
        // Use the specific flags string if provided, otherwise prepare for auto-detect
        $flagsToTry = [];
        if (!empty($this->imapflags)) {
            // Add /novalidate-cert if not explicitly excluded by user providing it
            $providedFlags = $this->imapflags;
            if (strpos($providedFlags, 'novalidate-cert') === false && strpos($providedFlags, 'validate-cert') === false) {
                $providedFlags .= '/novalidate-cert';
            }
            $flagsToTry[] = $providedFlags;
        } 

        $methodsToTry = $this->imapimpersonationmethod ? [$this->imapimpersonationmethod] : [
            'none',              // No impersonation - per-extension credentials
            'separator_backslash', // Renamed for consistency
            'separator_slash', 
            'separator_at',    
            'separator_plus',  
            'separator_colon',  // Use word
            'authuser'
        ];
        
        // Define Auth Types to iterate through - ORDER IS IMPORTANT (most secure to least secure)
        $authTypesToTry = [
            // Try secure/standard options first
            'None', // First try default with all methods
            
            // If default fails or has issues, try disabling specific auth types
            'DISABLE_GSSAPI', // Disable Kerberos if causing problems
            'DISABLE_NTLM',   // Disable Windows auth if causing problems
            'DISABLE_CRAM_MD5', // Disable challenge-response if causing problems
            
            // Last resort options - less secure
            'DISABLE_PLAIN',  // Try disabling less secure mechanisms
            'DISABLE_LOGIN'   // Last resort
        ];

        // Auto-detect ports/flags if not explicitly set
        if (empty($this->imapport) || empty($this->imapflags) || $this->imapport == 143 || empty($flagsToTry)) {
            $this->autoDetectMode = true;
            $portsToTry = [993, 143]; // Common SSL and non-SSL ports
            // Prioritized list of full flag strings for auto-detection (most secure first)
            $flagsToTry = [
                'imap/ssl/secure/novalidate-cert', // NEW: Standard secure + secure auth PRIORITY 1
                'imap/ssl/novalidate-cert',      // Standard secure 1
                'imap/tls/secure/novalidate-cert', // NEW: Standard STARTTLS + secure auth PRIORITY 2
                'imap/tls/novalidate-cert',      // Standard STARTTLS
                'ssl/imap/secure/novalidate-cert', // NEW: Alternative secure + secure auth PRIORITY 3
                'ssl/imap/novalidate-cert',      // Alternative secure 1
                'tls/imap/secure/novalidate-cert', // NEW: Alternative STARTTLS + secure auth PRIORITY 4
                'tls/imap/novalidate-cert',      // Alternative STARTTLS
                'notls/novalidate-cert'          // Insecure fallback (assuming /imap not needed/implied)
            ];
            // Ensure methodsToTry includes all options when auto-detecting
            if (count($methodsToTry) === 1 && !empty($this->imapimpersonationmethod)) { 
                 $methodsToTry = [
                    'none',              // No impersonation - per-extension credentials
                    'separator_backslash', // Renamed for consistency
                    'separator_slash',
                    'separator_at',
                    'separator_plus',
                    'separator_colon',
                    'authuser',
                    'direct_admin'
                ];
            }
        }

        // Clear previous IMAP errors
        imap_errors();

        // --- Fetch Baseline Admin Folder List (Best Effort) ---
        $baselineAdminFolders = null;
        $baselineError = null;
        try {
            // Use the first secure flag option and primary port for the baseline check
            $baselinePort = $this->imapport ?: 993; 
            $baselineFlags = !empty($this->imapflags) ? $flagsToTry[0] : 'imap/ssl/secure/novalidate-cert'; 
            $baselineMailbox = '{' . $this->imapserver . ':' . $baselinePort . '/' . $baselineFlags . '}';
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Attempting to fetch baseline admin folder list using: $baselineMailbox");
            $adminStream = @imap_open($baselineMailbox, $authUsername, $authPassword, OP_HALFOPEN, 1); 
            if ($adminStream instanceof \IMAP\Connection) {
                $listRef = '{' . $this->imapserver . ':' . $baselinePort . '/' . $baselineFlags . '}';
                $listedFolders = @imap_list($adminStream, $listRef, '*');
                if ($listedFolders !== false) {
                    $baselineAdminFolders = $listedFolders;
                    sort($baselineAdminFolders);
                    freepbx_log(FPBX_LOG_INFO, "[IMAP] Successfully fetched baseline admin folder list.");
                } else {
                    $baselineError = "Failed to list baseline admin folders: " . (imap_last_error() ?: 'Unknown list error');
                    freepbx_log(FPBX_LOG_WARNING, "[IMAP] " . $baselineError);
                }
                @imap_close($adminStream);
            } else {
                $baselineError = "Failed to connect as admin for baseline folder list: " . (imap_last_error() ?: 'Unknown connection error');
                freepbx_log(FPBX_LOG_WARNING, "[IMAP] " . $baselineError);
            }
        } catch (\Exception $e) {
            $baselineError = "Exception fetching baseline admin folders: " . $e->getMessage();
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] " . $baselineError);
        }
        // --- End Baseline Fetch ---

        // --- IMPROVED: Store and analyze auth errors to determine optimal settings ---
        $foundSuccessfulMethod = false;
        $authErrorPatterns = [
            'GSSAPI' => [
                'Unknown GSSAPI failure',
                'No Kerberos credentials available',
                'GSSAPI mechanism',
                'GSSAPI authentication failure'
            ],
            'NTLM' => [
                'NTLM authentication failure',
                'Cannot determine NTLM domain',
                'NTLM mechanism'
            ],
            'CRAM_MD5' => [
                'CRAM-MD5 authentication failure',
                'CRAM-MD5 mechanism'
            ],
            'PLAIN' => [
                'PLAIN authentication failure',
                'PLAIN mechanism'
            ],
            'LOGIN' => [
                'LOGIN authentication failure',
                'LOGIN mechanism'
            ]
        ];

        // --- Loop through all combinations --- 
        foreach ($authTypesToTry as $currentAuthType) {
            // Skip further auth types if we've already found a working method
            if ($foundSuccessfulMethod) {
                break;
            }
            
            $imapOptions = [];
            if ($currentAuthType !== 'None' && strpos($currentAuthType, 'DISABLE_') === 0) {
                $authToDisable = substr($currentAuthType, strlen('DISABLE_'));
                $imapOptions['DISABLE_AUTHENTICATOR'] = $authToDisable;
            }

            foreach ($portsToTry as $imapport) {
                if ($foundSuccessfulMethod) break;
                
                foreach ($flagsToTry as $imapflags) {
                    if ($foundSuccessfulMethod) break;
                    
                    foreach ($methodsToTry as $method) {
                        // Skip incompatible combinations based on port and flag type
                        if ((strpos($imapflags, 'ssl') !== false || strpos($imapflags, 'tls') !== false) && $imapport == 143 && strpos($imapflags, 'tls') === false) continue; // Skip SSL on 143 unless it's STARTTLS
                        if (strpos($imapflags, 'notls') !== false && $imapport == 993) continue; // Skip NOTLS on 993

                        $testRun = [
                            'port' => $imapport,
                            'flags' => $imapflags,
                            'method' => $method,
                            'authType' => $currentAuthType,
                            'status' => 'pending',
                            'error' => null,
                            'baseline_folders' => $baselineAdminFolders, // Add baseline list 
                            'impersonated_folders' => null, // Placeholder for impersonated list
                            'baseline_fetch_error' => $baselineError, // Record baseline error if any
                            'auth_errors_detected' => [] // Track specific auth errors
                        ];

                        $loginUser = null;
                        $loginPass = $authPassword;
                        $mailbox = '{' . $this->imapserver . ':' . $imapport . '/' . $imapflags . '/novalidate-cert'; // Base mailbox string

                        if ($method === 'none') {
                            // No impersonation - use test user credentials directly
                            $loginUser = $testUsername;
                            $loginPass = $authPassword; // Use admin password as test user password
                            $mailbox .= '}'; // Close mailbox string
                        } elseif ($method === 'direct_admin') {
                            $loginUser = $authUsername;
                            $mailbox .= '}'; // Close mailbox string
                        } elseif ($method === 'authuser') {
                            $mailbox .= '/authuser=' . $authUsername . '}';
                            $loginUser = $testUsername; // User whose mailbox we access
                        } elseif (strpos($method, 'separator_') === 0) { // Handle all separators here now
                            $separatorWord = substr($method, strlen('separator_')); // e.g., 'colon', 'backslash'
                            $separator = null;
                            switch ($separatorWord) {
                                case 'backslash': $separator = '\\'; break; // Added case
                                case 'slash': $separator = '/'; break;
                                case 'at':    $separator = '@'; break;
                                case 'plus':  $separator = '+'; break;
                                case 'colon': $separator = ':'; break;
                            }
                            if (!$separator) {
                                freepbx_log(FPBX_LOG_ERROR, "[IMAP] Unknown separator type in method: $method");
                                continue; // Skip this invalid method
                            }
                            $loginUser = $authUsername . $separator . $testUsername;
                            $mailbox .= '}';
                        }

                        // Clear errors before attempt
                        imap_errors();
                        $stream = null; // Ensure stream is null before attempt
                        
                        freepbx_log(FPBX_LOG_INFO, "[IMAP] Testing: Port=$imapport, Flags=$imapflags, Method=$method, AuthType=$currentAuthType, LoginUser=$loginUser");

                        try {
                            // Use 0 for imap_open flags (4th arg) when using options array (6th arg)
                            $stream = @imap_open($mailbox, $loginUser, $loginPass, 0, 1, $imapOptions);

                            if ($stream instanceof \IMAP\Connection) {
                                // --- SUCCESSFUL CONNECTION ---
                                // Fetch and analyze any warnings/notices during successful connection
                                $imapErrors = imap_errors();
                                $authErrorsDetected = [];
                                
                                if (is_array($imapErrors)) {
                                    foreach ($imapErrors as $error) {
                                        foreach ($authErrorPatterns as $authType => $patterns) {
                                            foreach ($patterns as $pattern) {
                                                if (stripos($error, $pattern) !== false) {
                                                    $authErrorsDetected[] = [
                                                        'auth_type' => $authType,
                                                        'error' => $error
                                                    ];
                                                    $results['auth_errors'][] = [
                                                        'auth_type' => $authType,
                                                        'error' => $error,
                                                        'during_successful_connection' => true
                                                    ];
                                                    break 2; // Found a match for this error
                                                }
                                            }
                                        }
                                    }
                                }
                                
                                $testRun['auth_errors_detected'] = $authErrorsDetected;
                                
                                // --- Fetch Impersonated Folder List (for this attempt) ---
                                $impersonatedFolders = null;
                                $listError = null;
                                try {
                                    $listRef = '{' . $this->imapserver . ':' . $imapport . '/' . $imapflags . '}';
                                    $listedImpFolders = @imap_list($stream, $listRef, '*');
                                    if ($listedImpFolders !== false) {
                                        $impersonatedFolders = $listedImpFolders;
                                        sort($impersonatedFolders);
                                        $testRun['impersonated_folders'] = $impersonatedFolders; // Store in test run data
                                        freepbx_log(FPBX_LOG_INFO, "[IMAP] Successfully fetched impersonated folder list for this attempt.");
                } else {
                                        $listError = "Failed to list impersonated folders: " . (imap_last_error() ?: 'Unknown list error');
                                        freepbx_log(FPBX_LOG_WARNING, "[IMAP] " . $listError);
                                    }
                                } catch (\Exception $e) {
                                    $listError = "Exception fetching impersonated folders: " . $e->getMessage();
                                    freepbx_log(FPBX_LOG_ERROR, "[IMAP] " . $listError);
                                }
                                
                                $testRun['status'] = 'success';
                                
                                // --- IMPORTANT: Found a working combination - save results ---
                                    $results['success'] = true;
                                $foundSuccessfulMethod = true;
                                
                                // Store the successful parameters
                                    $results['successful_method'] = $method;
                                if (strpos($method, 'separator_') === 0) {
                                    $results['successful_separator'] = $separator;
                                }
                                    $results['successful_port'] = $imapport;
                                    $results['successful_flags'] = $imapflags;
                                
                                // === AUTH TYPE RECOMMENDATION ===
                                // Use the auth type that actually worked - don't recommend disabling if it works
                                $results['successful_auth_type'] = $currentAuthType;
                                
                                // Close the stream
                                    @imap_close($stream);
                                } else {
                                $errors = imap_errors() ?: ['Unknown connection error'];
                                $errorMsg = $errors ? implode("; ", $errors) : 'Unknown connection error';
                                $testRun['status'] = 'failed_open';
                                $testRun['error'] = $errorMsg;
                                
                                // --- IMPROVED: Store auth-specific errors for analysis ---
                                $authErrorsDetected = [];
                                
                                foreach ($errors as $error) {
                                    foreach ($authErrorPatterns as $authType => $patterns) {
                                        foreach ($patterns as $pattern) {
                                            if (stripos($error, $pattern) !== false) {
                                                $authErrorsDetected[] = [
                                                    'auth_type' => $authType,
                                                    'error' => $error
                                                ];
                                                $results['auth_errors'][] = [
                                                    'auth_type' => $authType,
                                                    'error' => $error,
                                                    'during_successful_connection' => false
                                                ];
                                                break 2; // Found a match for this error
                                            }
                                        }
                                    }
                                }
                                
                                $testRun['auth_errors_detected'] = $authErrorsDetected;
                                
                                // Add to error summary if not already present
                                if (!in_array($errorMsg, $results['error_summary'])) {
                                    $results['error_summary'][] = $errorMsg;
                                }
                            }
                        } catch (\Exception $e) {
                            $testRun['status'] = 'failed_exception';
                            $testRun['error'] = "Exception during connection: " . $e->getMessage();
                            
                            // Add to error summary if not already present
                            if (!in_array($testRun['error'], $results['error_summary'])) {
                                $results['error_summary'][] = $testRun['error'];
                            }
                        }
                        
                        // Add the test run to the results
                        $results['tests_run'][] = $testRun;
                    }
                }
            }
        }

        // If we have no successful method but collected auth errors, analyze to provide recommendation
        if (!$results['success'] && !empty($results['auth_errors'])) {
            freepbx_log(FPBX_LOG_INFO, "[IMAP] No successful connection found, but auth errors were detected. Analyzing for recommendation.");
            freepbx_log(FPBX_LOG_INFO, "[IMAP] Auth errors: " . json_encode($results['auth_errors']));
            
            // Count frequency of each auth error type
            $authErrorCount = [];
            foreach ($results['auth_errors'] as $errorData) {
                $authType = $errorData['auth_type'];
                if (!isset($authErrorCount[$authType])) {
                    $authErrorCount[$authType] = 0;
                }
                $authErrorCount[$authType]++;
            }
            
            // Find most frequent auth error
            arsort($authErrorCount);
            $mostFrequentAuthError = key($authErrorCount);
            
            if ($mostFrequentAuthError) {
                $results['recommended_auth_type'] = 'DISABLE_' . $mostFrequentAuthError;
                freepbx_log(FPBX_LOG_INFO, "[IMAP] Based on error analysis, recommending auth type: " . $results['recommended_auth_type']);
            }
        }

        return $results;
    }

    /**
     * Returns whether the last call to testImpersonationConnection ran in auto-detect mode.
     *
     * @return bool True if auto-detect was used, false otherwise.
     */
    public function wasAutoDetectMode() {
        return $this->lastTestAutoDetect;
    }

    /**
     * Sets the basic connection details needed for testing or connecting.
     *
     * @param string $server IMAP server hostname or IP.
     * @param int    $imapport   IMAP server port.
     * @param string|null $imapflags  IMAP connection flags (e.g., 'imap/ssl').
     */
    public function setConnectionDetails($server, $imapport, $imapflags = null) {
        $this->server = $server;
        $this->imapport = (int)$imapport; // Ensure port is integer
        $this->imapflags = $imapflags; // Allow null flags
    }

    /**
     * Get details for a single message by UID.
     * 
     * @param string $uid The UID of the message.
     * @param string $imapfolder The folder containing the message.
     * @return array|null The message details array or null if not found/error.
     */
    public function getSingleMessageDetails($uid, $imapfolder) {
		freepbx_log(FPBX_LOG_INFO, "[IMAP] getSingleMessageDetails: Getting details for UID $uid in folder $imapfolder");

		// Ensure connection
		try {
			$this->checkConnection();
        } catch (\Exception $e) {
			freepbx_log(FPBX_LOG_ERROR, "[IMAP] getSingleMessageDetails: Failed to check connection");
			return null;
            }
            
            // Select mailbox
		try {
            if (!$this->selectMailbox($imapfolder)) {
				freepbx_log(FPBX_LOG_ERROR, "[IMAP] getSingleMessageDetails: Could not select mailbox $imapfolder");
				return null;
			}
		} catch (\Exception $e) {
			freepbx_log(FPBX_LOG_ERROR, "[IMAP] getSingleMessageDetails: Exception selecting mailbox $imapfolder: " . $e->getMessage());
			return null;
		}

		try {
			// Fetch overview first (still needed for flags)
			$overviewData = @imap_fetch_overview($this->stream, $uid, FT_UID);
			$overview = (!empty($overviewData[0])) ? $overviewData[0] : null;
			$isRead = $overview ? !empty($overview->seen) : false;

			// Use the new helper to get parsed headers
			$parsedHeaders = $this->_fetchAndParseHeaders((string)$uid, $imapfolder); // Cast UID to string

			// Check if header parsing was successful
			if ($parsedHeaders === null) {
				 freepbx_log(FPBX_LOG_WARNING, "[IMAP] getSingleMessageDetails: Failed to parse headers for UID: $uid using helper. Returning null.");
				return null; 
			}
			
			freepbx_log(FPBX_LOG_INFO, "[IMAP] getSingleMessageDetails: Parsed headers for UID $uid: " . json_encode($parsedHeaders));

			// --- Build Message Array using Parsed Headers --- 
			$message = [
				'id' => $uid,
				'msg_id' => !empty($parsedHeaders['message_id']) ? $parsedHeaders['message_id'] : $uid, // Use parsed msg_id, fallback to UID
				'fid' => $uid,    
				'folder' => $imapfolder,
				'read' => $isRead, 
				'priority' => ($parsedHeaders['priority_num'] === 1) ? 'high' : 'normal', // Use parsed priority
				'date' => '',      // Will be set from origtime
				'time' => '',      // Will be set from origtime
				'origtime' => $parsedHeaders['origtime'], // Use parsed origtime
				'duration' => $parsedHeaders['duration'],    // Use parsed duration
				'callerid' => 'Unknown', // Will be formatted below
				'type' => $parsedHeaders['type'], // Use parsed type
				'subject' => $parsedHeaders['subject'], // Use parsed subject
				'imap' => true   
			];

			// Set the final timestamp and formatted date/time
			$dateObj = new \DateTime();
			$dateObj->setTimestamp($message['origtime']);
			$message['date'] = $dateObj->format('Y-m-d');
			$message['time'] = $dateObj->format('H:i:s');

			// Format Caller ID using parsed values
			$callerIDNum = $parsedHeaders['callerid_num'];
			$callerIDName = $parsedHeaders['callerid_name'];
			$finalCallerId = "Unknown";
			$callerIDNum = preg_replace('/^\"(.+)\"$/', '$1', $callerIDNum); // Clean quotes
			$callerIDName = preg_replace('/^\"(.+)\"$/', '$1', $callerIDName); // Clean quotes
			if (!empty($callerIDName) && !empty($callerIDNum) && $callerIDName != $callerIDNum) {
				$finalCallerId = $callerIDName . " <" . $callerIDNum . ">";
			} else if (!empty($callerIDNum)) {
				$finalCallerId = $callerIDNum;
			} else if (!empty($callerIDName)) {
				$finalCallerId = $callerIDName;
			}
			$message['callerid'] = $finalCallerId;

			freepbx_log(FPBX_LOG_INFO, "[IMAP] getSingleMessageDetails: Successfully processed details for UID $uid");
			return $message;

		} catch (\Exception $e) {
			freepbx_log(FPBX_LOG_ERROR, "[IMAP] getSingleMessageDetails: Exception processing UID $uid: " . $e->getMessage());
			return null;
		}
	}

    /**
     * Get message count for a specific folder
     * 
     * @param string $folderPath Full path to the folder
     * @param bool $onlyUnread True to count only unread messages
     * @return int|false Count of messages, or false on failure
     */
    public function getMessageCount($folderPath, $onlyUnread = false) {
        freepbx_log(FPBX_LOG_INFO, "[IMAP] getMessageCount: Getting " . ($onlyUnread ? 'UNREAD' : 'ALL') . " count for Folder: [$folderPath] using imap_search");
        freepbx_log(FPBX_LOG_INFO, "[DEBUG] getMessageCount called for folder: $folderPath, onlyUnread: " . ($onlyUnread ? 'true' : 'false'));
        $this->checkConnection(); // Ensure connection
        
        if (!$this->stream instanceof \IMAP\Connection) {
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] getMessageCount: No active connection.");
            return false;
        }

        // Select the correct mailbox first
        freepbx_log(FPBX_LOG_INFO, "[DEBUG] getMessageCount: Attempting to select mailbox: $folderPath");
        if (!$this->selectMailbox($folderPath)) { // Use full path
            freepbx_log(FPBX_LOG_ERROR, "[IMAP] getMessageCount: Failed to select mailbox [$folderPath]");
            freepbx_log(FPBX_LOG_INFO, "[DEBUG] getMessageCount: Failed to select mailbox: $folderPath");
            return false;
        }
        freepbx_log(FPBX_LOG_INFO, "[DEBUG] getMessageCount: Successfully selected mailbox: $folderPath");
        
        $criteria = $onlyUnread ? 'UNSEEN' : 'ALL';
        freepbx_log(FPBX_LOG_INFO, "[IMAP] getMessageCount: Attempting imap_search with criteria [$criteria]");
        
        // Clear previous errors
        imap_errors();
        freepbx_log(FPBX_LOG_INFO, "[DEBUG] getMessageCount: Searching with criteria: $criteria");
        $searchResult = @imap_search($this->stream, $criteria, SE_UID);
        
        // === FIX: Treat false result (often means 0 found) as 0 count ===
        freepbx_log(FPBX_LOG_INFO, "[DEBUG] getMessageCount: Search result type: " . gettype($searchResult));
        if ($searchResult === false) {
            $lastError = imap_last_error(); // Check if there was a *real* error
            freepbx_log(FPBX_LOG_INFO, "[DEBUG] getMessageCount: Search returned false, last error: " . ($lastError ?: 'none'));
            if ($lastError) {
                // If there was an actual IMAP error, log it and return false
                freepbx_log(FPBX_LOG_WARNING, "[IMAP] getMessageCount: imap_search call failed for criteria [$criteria] in folder [$folderPath]. Last IMAP error: [$lastError]");
                return false; // Indicate failure
            } else {
                // If imap_search returned false but there's no specific IMAP error, assume 0 messages found
                freepbx_log(FPBX_LOG_INFO, "[IMAP] getMessageCount: imap_search returned false (likely 0 messages) for criteria [$criteria] in folder [$folderPath]. Returning 0.");
                return 0; // Return 0 count
            }
        } else {
            // If $searchResult is an array (even an empty one), count it
            $count = is_array($searchResult) ? count($searchResult) : 0; // Ensure we handle empty array case
            freepbx_log(FPBX_LOG_INFO, "[IMAP] getMessageCount: Success. imap_search found $count messages for criteria [$criteria] in folder [$folderPath].");
            freepbx_log(FPBX_LOG_INFO, "[DEBUG] getMessageCount: Returning count: $count");
            return $count;
        }
    }
}