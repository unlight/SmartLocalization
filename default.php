<?php if (!defined('APPLICATION')) exit(); // …

$PluginInfo['SmartLocalization'] = array(
	'Name' => 'Smart Localization',
	'Description' => 'Allows overwrite translation code depending on the application (controller/method).',
	'Version' => '2.5.1',
	'Author' => 'Flak Monkey',
	'AuthorUrl' => 'http://vanillaforums.org/profile/addons/8576/8576',
	'Date' => 'Summer 2011',
	'RequiredApplications' => array('Dashboard' => '>=2.0.18')
);

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
	
	private function PrepareCache($LocaleName = False) {
		
		if ($LocaleName === False) $LocaleName = $this->_Locale->Current();
		$EnabledApplications = Gdn::Config('EnabledApplications', array());
		$EnabledPlugins = Gdn::Config('EnabledPlugins', array());
		$LocaleSources = array();

		// Get application-based locale special definition files
		// 2.0.0+ (TODO: REMOVE)
		$ApplicationLocaleSources = Gdn_FileSystem::FindAll(PATH_APPLICATIONS, CombinePaths(array('locale', $LocaleName, 'distinctions.php')), $EnabledApplications);
		if ($ApplicationLocaleSources !== False) {
			if (C('Debug')) Deprecated("Move all application's locale distinctions.php files to [applicationname]/locale/[localename].custom.php, distinctions.php filenames");
			$LocaleSources = array_merge($LocaleSources, $ApplicationLocaleSources);
		}
		// 2.0.11+ (TODO: REMOVE)
		$ApplicationLocaleSources = Gdn_FileSystem::FindAll(PATH_APPLICATIONS, CombinePaths(array('locale', $LocaleName.'.distinct.php')), $EnabledApplications);
		if ($ApplicationLocaleSources !== False) {
			if (C('Debug')) Deprecated("Rename all application's locale [localename].distinct.php files to [localename].custom.php, [localename].distinct.php filenames");
			$LocaleSources = array_merge($LocaleSources, $ApplicationLocaleSources);
		}
		
		// 2.0.18+
		$ApplicationLocaleSources = Gdn_FileSystem::FindAll(PATH_APPLICATIONS, CombinePaths(array('locale', $LocaleName.'.custom.php')), $EnabledApplications);
		if ($ApplicationLocaleSources !== False) $LocaleSources = array_merge($LocaleSources, $ApplicationLocaleSources);

		// Get plugin-based locale special definition files
		// 2.0.0+ (TODO: REMOVE)
		$PluginLocaleSources = Gdn_FileSystem::FindAll(PATH_PLUGINS, CombinePaths(array('locale', $LocaleName, 'distinctions.php')), $EnabledPlugins);
		if ($PluginLocaleSources !== False) {
			if (C('Debug')) Deprecated("Move all plugin's locale distinctions.php files to [pluginname]/locale/[localename].custom.php, distinctions.php filenames");
			$LocaleSources = array_merge($LocaleSources, $PluginLocaleSources);
		}
		// 2.0.11+ (TODO: REMOVE)
		$PluginLocaleSources = Gdn_FileSystem::FindAll(PATH_PLUGINS, CombinePaths(array('locale', $LocaleName.'.distinct.php')), $EnabledPlugins);
		if ($PluginLocaleSources !== False) {
			if (C('Debug')) Deprecated("Rename all plugin's locale [localename].distinct.php files to [localename].custom.php, [localename].distinct.php filenames");
			$LocaleSources = array_merge($LocaleSources, $PluginLocaleSources);
		}
		
		// 2.0.18+
		$PluginLocaleSources = Gdn_FileSystem::FindAll(PATH_PLUGINS, CombinePaths(array('locale', $LocaleName.'.custom.php')), $EnabledPlugins);
		if ($PluginLocaleSources !== False) $LocaleSources = array_merge($LocaleSources, $PluginLocaleSources);

		// Get theme-based locale special definition files.
		$Theme = Gdn::Config('Garden.Theme');
		if ($Theme) {
			// 2.0.11+, TODO: REMOVE
			$ThemeLocalePath = PATH_THEMES."/$Theme/locale/$LocaleName.distinct.php";
			if (file_exists($ThemeLocalePath)) {
				$LocaleSources[] = $ThemeLocalePath;
				if (C('Debug')) Deprecated("Rename file to $LocaleName.custom.php, $LocaleName.distinct.php filename");
			}
			// 2.0.18+ 
			$ThemeLocalePath = PATH_THEMES."/$Theme/locale/$LocaleName.distinct.php";
			if (file_exists($ThemeLocalePath)) $LocaleSources[] = $ThemeLocalePath;
		}

		// Get locale-based locale special definition files.
		$EnabledLocales = Gdn::Config('EnabledLocales');
		if (is_array($EnabledLocales)) {
			foreach ($EnabledLocales as $Key => $Locale) {
				if ($Locale != $LocaleName) continue;
				// Grab all of the files in the locale's folder (subdirectory custom)
				$Paths = glob(PATH_ROOT."/locales/$Key/custom/*.php");
				if (is_array($Paths)) foreach($Paths as $Path) $LocaleSources[] = $Path;
			}
		}

		$PhpLocaleName = var_export($LocaleName, True);
		$PhpLocaleSources = var_export($LocaleSources, True);
		$PhpArrayCode = "\n\$_[$PhpLocaleName] = $PhpLocaleSources;";
		
		$CacheFile = PATH_CACHE . '/customtranslation_map.ini';
		if (!file_exists($CacheFile)) $PhpArrayCode = '<?php' . $PhpArrayCode;
		file_put_contents($CacheFile, $PhpArrayCode, FILE_APPEND | LOCK_EX);
	}
	
	protected function Load($ForceRemapping = False) {
		$LocaleName = $this->_Locale->Current();
		$CacheFile = PATH_CACHE . '/customtranslation_map.ini';
		if (!file_exists($CacheFile) || $ForceRemapping === True) $this->PrepareCache($LocaleName);
		// Load CacheFile.
		include $CacheFile;
		$LocaleSources = ArrayValue($LocaleName, $_, array());
		
		// Look for a config locale that is locale-specific.
		$ConfigCustomLocale = PATH_LOCAL_CONF."/locale-$LocaleName.custom.php";
		$LocaleSources[] = $ConfigCustomLocale;
		
		// Set up defaults.
		$Definition = array();
		$this->_Definition =& $Definition;

		// Import all of the sources.
		for ($Count = count($LocaleSources), $i = 0; $i < $Count; ++$i) {
			if (file_exists($LocaleSources[$i])) include($LocaleSources[$i]);
		}
	}
	
	public function Setup() {
		if (!function_exists('mb_convert_case')) 
			throw new Exception('mbstring extension (Multibyte String Functions) is required.');
	}
	
	
}










