<?php if (!defined('APPLICATION')) exit(); // …

$PluginInfo['SmartLocalization'] = array(
	'Name' => 'Smart Localization',
	'Description' => 'Allows overwrite translation code depending on the application (controller/method).',
	'Version' => '2.4.9',
	'Author' => 'Flak Monkey',
	'Date' => '14 Apr 2011',
	'RequiredApplications' => array('Dashboard' => '>=2.0.17')
);

# ABOUT
# =====
# Help to achieve good localization.
# The problem: We cannot achieve good localization because same translation codes are used on different
# pages. For example, T('Edit') on discussion index and while editing role permissions in dashboard.

class SmartLocalizationPlugin implements Gdn_IPlugin {
	
	private $_Definition = array();
	private $_Locale;
	
	public function __construct() {
		$this->_Locale = Gdn::Locale();
		$this->Load();
	}
	
	public function Gdn_Dispatcher_BeforeControllerMethod_Handler($Sender) {
		$Controller =& $Sender->EventArguments['Controller'];
		$this->SetTranslation($Controller);
	}
	
	protected function SetTranslation($Sender) {
		// Get sender info
		$Application = mb_convert_case($Sender->Application, 2);
		$Controller = mb_convert_case(substr($Sender->ControllerName, 0, -10), 2);
		$Method = mb_convert_case($Sender->RequestMethod, 2);
		//d($Application.$Controller.$Method, $this->_Definition);
		
		// Search custom definitions for this application and this controller
		$Codes = array();
		if (array_key_exists($Application, $this->_Definition))
			$Codes = array_merge($Codes, (array)$this->_Definition[$Application]);
		if (array_key_exists($Application.$Controller, $this->_Definition))
			$Codes = array_merge($Codes, (array)$this->_Definition[$Application.$Controller]);
		if (array_key_exists($Application.$Controller.$Method, $this->_Definition))
			$Codes = array_merge($Codes, (array)$this->_Definition[$Application.$Controller.$Method]);
		
		// set translation
		$this->_Locale->SetTranslation($Codes);
	}
	
	protected function Load($ForceRemapping = False) {
		$LocaleName = $this->_Locale->Current();
		$ApplicationWhiteList = $EnabledApplications = Gdn::Config('EnabledApplications', array());
		$EnabledPlugins = Gdn::Config('EnabledPlugins', array());
		// TODO: REPLACE $PluginWhiteList => $EnabledPlugins
		// TODO: REPLACE $ApplicationWhiteList => $EnabledApplications
		$SafeLocaleName = preg_replace('/([^\w\d_-])/', '', $LocaleName);
		$LocaleSources = array();
		
		if (!is_array($EnabledApplications)) $EnabledApplications = array();
		if (!is_array($EnabledPlugins)) $EnabledPlugins = array();
		
		Gdn_LibraryMap::PrepareCache('distinct', NULL, 'tree');
		$LocaleSources = Gdn_LibraryMap::GetCache('distinct', $SafeLocaleName);
		if ($ForceRemapping === TRUE || !Gdn_LibraryMap::CacheReady('distinct') || $LocaleSources === NULL) {
			$LocaleSources = array();
			// Get application-based locale special definition files
			// 2.0.11 (TODO: REMOVE)
			$ApplicationLocaleSources = Gdn_FileSystem::FindAll(PATH_APPLICATIONS, CombinePaths(array('locale', $LocaleName, 'distinctions.php')), $EnabledApplications);
			if ($ApplicationLocaleSources !== FALSE) $LocaleSources = array_merge($LocaleSources, $ApplicationLocaleSources);
			// 2.0.11+
			$ApplicationLocaleSources = Gdn_FileSystem::FindAll(PATH_APPLICATIONS, CombinePaths(array('locale', $LocaleName.'.distinct.php')), $EnabledApplications);
			if ($ApplicationLocaleSources !== FALSE) $LocaleSources = array_merge($LocaleSources, $ApplicationLocaleSources);

			// Get plugin-based locale special definition files
			// 2.0.11 (TODO: REMOVE)
			$PluginLocaleSources = Gdn_FileSystem::FindAll(PATH_PLUGINS, CombinePaths(array('locale', $LocaleName, 'distinctions.php')), $EnabledPlugins);
			if ($PluginLocaleSources !== FALSE) $LocaleSources = array_merge($LocaleSources, $PluginLocaleSources);
			// 2.0.11+
			$PluginLocaleSources = Gdn_FileSystem::FindAll(PATH_PLUGINS, CombinePaths(array('locale', $LocaleName.'.distinct.php')), $EnabledPlugins);
			if ($PluginLocaleSources !== FALSE) $LocaleSources = array_merge($LocaleSources, $PluginLocaleSources);
			
			// Get theme-based locale special definition files.
			$Theme = C('Garden.Theme');
			if ($Theme) {
				$ThemeLocalePath = PATH_THEMES."/$Theme/locale/$LocaleName.distinct.php";
				if (file_exists($ThemeLocalePath)) $LocaleSources[] = $ThemeLocalePath;
			}

			// Get locale-based locale special definition files.
			$EnabledLocales = C('EnabledLocales');
			if (is_array($EnabledLocales)) {
				foreach ($EnabledLocales as $Key => $Locale) {
					if ($Locale != $LocaleName) continue; // skip locales that aren't in effect.
					// Grab all of the files in the locale's folder.
					$Paths = SafeGlob(PATH_ROOT."/locales/$Key/*.distinct.php");
					if (is_array($Paths)) foreach($Paths as $Path) $LocaleSources[] = $Path;
				}
			}
			
			// Save the mappings
			$FileContents = array();
			for($Count = count($LocaleSources), $i = 0; $i < $Count; ++$i) {
				$FileContents[$SafeLocaleName][] = Gdn_Format::ArrayValueForPhp($LocaleSources[$i]);
			}
			
			// Look for a global
			$ConfigLocale = PATH_LOCAL_CONF.'/distinct.php';
			if (file_exists($ConfigLocale)) $FileContents[$SafeLocaleName][] = $ConfigLocale;
		
			Gdn_LibraryMap::PrepareCache('distinct', $FileContents);
		}

		// Set up defaults
		$Definition = array();
		$this->_Definition =& $Definition;

		// Import all of the sources.
		$LocaleSources = Gdn_LibraryMap::GetCache('distinct', $SafeLocaleName);
		if (is_null($SafeLocaleName)) $LocaleSources = array();

		for($Count = count($LocaleSources), $i = 0; $i < $Count; ++$i) {
			if (file_exists($LocaleSources[$i])) include($LocaleSources[$i]);
		}

		$ConfLocaleOverride = PATH_LOCAL_CONF . "/locale-$LocaleName.distinct.php";
		if (file_exists($ConfLocaleOverride)) include($ConfLocaleOverride);
	}
	
	public function Setup() {
		if (!function_exists('mb_convert_case')) 
			throw new Exception('mbstring extension (Multibyte String Functions) is required.');
	}
	
	
}










