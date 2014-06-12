<?php
// vim: set ai ts=4 sw=4 ft=php:
namespace FreePBX\modules\Voicemail;
class Vmx {
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Not given a FreePBX Object");
		}
		$this->FreePBX = $freepbx;
		$this->astman = $this->FreePBX->astman;
	}

	/**
	 * Get all VmX Settings from Extension
	 * @param {int} $ext Extension Number
	 */
	public function getSettings($ext) {
		$vmx = $this->astman->database_show('AMPUSER/'.$ext.'/vmx');
		$final = array();
		foreach($vmx as $family => $value) {
			$family = str_replace('/AMPUSER/'.$ext.'/vmx/','',$family);
			$e = explode("/",$family);
			switch(count($e)) {
				case 1:
					$final[$e[0]] = $value;
				break;
				case 2:
					$final[$e[0]][$e[1]] = $value;
				break;
				case 3:
					$final[$e[0]][$e[1]][$e[2]] = $value;
				break;
				case 4:
					$final[$e[0]][$e[1]][$e[2]][$e[3]] = $value;
				break;
				case 5:
					$final[$e[0]][$e[1]][$e[2]][$e[3]][$e[4]] = $value;
				break;
				default:
				break;
			}
		}
		return $final;
	}

	/**
	 * Set VmX State per mode
	 * @param {int} $ext             Extension Number
	 * @param {string} $mode="unavail"  The mode, can be: unavail, busy, temp
	 * @param {string} $state="enabled" State: enabled, disabled, blocked
	 */
	public function setState($ext,$mode="unavail",$state="enabled") {
		if ($this->astman && ($mode == "unavail" || $mode == "busy" || $mode == "temp")) {
			$this->astman->database_put("AMPUSER", $ext."/vmx/".$mode."/state", "$state");
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get VmX State per mode
	 * @param {int} $ext             Extension Number
	 * @param {string} $mode="unavail"  The mode, can be: unavail, busy, temp
	 */
	public function getState($ext,$mode="unavail") {
		if ($this->astman && ($mode == "unavail" || $mode == "busy" || $mode == "temp")) {
			return trim($this->astman->database_get("AMPUSER",$ext."/vmx/$mode/state"));
		} else {
			return false;
		}
	}

	/**
	 * Disable All VmX Modes
	 * @param  {int} $ext Extension Number
	 */
	public function disable($ext) {
		return $this->setState($ext,'busy','blocked') && $this->setState($ext,'unavail','blocked') && $this->setState($ext,'temp','blocked');
	}

	/**
	 * Check if VmX is initialized for this mode
	 * @param {int} $ext             Extension Number
	 * @param {string} $mode="unavail"  The mode, can be: unavail, busy, temp
	 */
	public function isInitialized($ext,$mode="unavail") {
		if ($this->astman && ($mode == "unavail" || $mode == "busy" || $mode == "temp")) {
			$vmx_state=trim($this->astman->database_get("AMPUSER",$ext."/vmx/$mode/state"));
			if (isset($vmx_state) && ($vmx_state == 'enabled' || $vmx_state == 'disabled') || $vmx_state == 'blocked') {
				return true;
			} else {
				return false;
			}
		}
		return false;
	}

	/**
	 * Check if VmX is enabled for this mode
	 * @param {int} $ext             Extension Number
	 * @param {string} $mode="unavail"  The mode, can be: unavail, busy, temp
	 */
	public function isEnabled($ext,$mode="unavail") {
		if ($this->astman && ($mode == "unavail" || $mode == "busy" || $mode == "temp")) {
			$vmx_state=trim($this->astman->database_get("AMPUSER",$ext."/vmx/$mode/state"));
			if (isset($vmx_state) && ($vmx_state == 'enabled' || $vmx_state == 'disabled')) {
				return true;
			} else {
				return false;
			}
		}
		return false;
	}

	/**
	 * Get the Voicemail Play Instructions state
	 * @param {int} $ext            Extension Number
	 * @param {string} $mode="unavail" The mode, can be: unavail, busy, temp
	 */
	public function getVmPlay($ext,$mode="unavail") {
		if ($this->astman && ($mode == "unavail" || $mode == "busy" || $mode == "temp")) {
			return (trim($this->astman->database_get("AMPUSER",$ext."/vmx/$mode/vmxopts/timeout")) != 's');
		} else {
			return false;
		}
	}

	/**
	 * Set the Voicemail Play Instructions state
	 * @param {int} $ext            Extension Number
	 * @param {string} $mode="unavail" The mode, can be: unavail, busy, temp
	 * @param {bool} $opts Whether to play voicemail instructions or not
	 */
	public function setVmPlay($ext, $mode="unavail", $opts=true) {
		if ($this->astman && ($mode == "unavail" || $mode == "busy" || $mode == "temp")) {
			$val = $opts ? '' : 's';
			$this->astman->database_put("AMPUSER", $ext."/vmx/$mode/vmxopts/timeout", $val);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check if Extension has Find Me Follow Me enabled
	 * @param {int} $ext            Extension Number
	 */
	public function hasFollowMe($ext) {
		if ($this->astman) {
			return ($this->astman->database_get("AMPUSER",$ext."/followme/ddial")) == "" ? false : true;
		} else {
			return false;
		}
	}

	/**
	 * Check if the mode for this VmX is set to Find Me Follow Me mode
	 * @param {int} $ext            Extension Number
	 * @param {int} $digit="1"      The VmX Option (0-2)
	 * @param {string} $mode="unavail" The mode, can be: unavail, busy, temp
	 */
	public function isFollowMe($ext, $digit="1", $mode="unavail") {
		if ($this->astman && ($mode == "unavail" || $mode == "busy" || $mode == "temp")) {
			return $this->astman->database_get("AMPUSER",$ext."/vmx/$mode/$digit/ext") == 'FM'.$ext ? true : false;
		} else {
			return false;
		}
	}

	/**
	 * Set the Find Me Follow Me Mode for this VmX Option
	 * @param {int} $ext                        Extension Number
	 * @param {int} $digit="1"                  The VmX Option (0-2)
	 * @param {string} $mode="unavail"             The mode, can be: unavail, busy, temp
	 * @param {string} $context='ext-findmefollow' The Find Me Follow Me Context
	 * @param {int} $priority='1'               The Find Me Follow Me Priority
	 */
	public function setFollowMe($ext, $digit="1", $mode="unavail", $context='ext-findmefollow', $priority='1') {
		if ($this->astman && ($mode == "unavail" || $mode == "busy" || $mode == "temp")) {
			$this->astman->database_put("AMPUSER", $ext."/vmx/$mode/$digit/ext", "FM".$ext);
			$this->astman->database_put("AMPUSER", $ext."/vmx/$mode/$digit/context", $context);
			$this->astman->database_put("AMPUSER", $ext."/vmx/$mode/$digit/pri", $priority);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get VmX Menu Option Desination
	 * @param {int} $ext            Extension Number
	 * @param {int} $digit="0"      The VmX Menu Option
	 * @param {string} $mode="unavail" The mode, can be: unavail, busy, temp
	 */
	public function getMenuOpt($ext, $digit="0", $mode="unavail") {
		if ($this->astman && ($mode == "unavail" || $mode == "busy" || $mode == "temp")) {
			return trim($this->astman->database_get("AMPUSER",$ext."/vmx/$mode/$digit/ext"));
		} else {
			return false;
		}
	}

	/**
	 * Set VmX Menu Option Desination
	 * @param {int} $ext                        Extension Number
	 * @param {int} $opt=""                  The Destination
	 * @param {int} $digit="0"                  The VmX Option (0-2)
	 * @param {string} $mode="unavail"             The mode, can be: unavail, busy, temp
	 * @param {string} $context='from-internal' The Find Me Follow Me Context
	 * @param {int} $priority='1'               The Find Me Follow Me Priority
	 */
	public function setMenuOpt($ext,$opt="", $digit="0", $mode="unavail", $context="from-internal", $priority="1") {
		if ($this->astman && ($mode == "unavail" || $mode == "busy" || $mode == "temp")) {
			if ($opt != "" && ctype_digit($opt)) {
				$opt = preg_replace("/[^0-9]/" ,"", $opt);
				$this->astman->database_put("AMPUSER", $ext."/vmx/$mode/$digit/ext", $opt);
				$this->astman->database_put("AMPUSER", $ext."/vmx/$mode/$digit/context", $context);
				$this->astman->database_put("AMPUSER", $ext."/vmx/$mode/$digit/pri", $priority);
			} else {
				$this->astman->database_deltree("AMPUSER/".$ext."/vmx/$mode/$digit");
			}
			return true;
		} else {
			return false;
		}
	}
}
