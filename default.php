<?php if (!defined('APPLICATION')) exit(); // …

$PluginInfo['SmartLocalization'] = array(
	'Name' => 'Smart Localization',
	'Description' => 'Allows overwrite translation code depending on the application (controller/method).',
	'Version' => '2.3.7',
	'Author' => 'Flak Monkey',
	'Date' => '2 Jan 2010',
	'RequiredApplications' => array('Dashboard' => '>=2.0.17')
);

/**
ABOUT
=====
Help to achieve good localization.

The problem: We cannot achieve good localization because same translation codes are used on different
pages. For example, T('Edit') on discussion index and while editing role permissions in dashboard.

CHANGELOG
=========
1.0 / 2 Apr 2010
[new] first release
1.1 / 5 Apr 2010
[alt] removed plugin "Plugin Utils" dependency
[alt] changed identifier names to camel-case style
[add] custom definitions for current locale (locale.php - global)
2.0 / 19 Jun 2010
[alt] all custom definitions now stored near locale files
	/applications/application-folder/locale/locale-name-folder/distinctions.php 
	or /plugins/plugin-folder/locale/locale-name-folder/distinctions.php
2.1 / 22 Jul 2010
[alt] Gdn_FileCache removed. Gdn_FileCache => Gdn_LibraryMap
2.2 / 2 Aug 2010
[fix] typo
2.3 / 2 Jan 2011

TODO
distinctions.php => custom.php
4. GUI for custom translations

*/

class SmartLocalizationPlugin implements Gdn_IPlugin {
	
	private $_Definition = array();
	private $_Locale;
	
	public function __construct() {
		$this->_Locale = Gdn::Locale();
		$this->LoadLocaleDistinctions();
	}
	
	public function Gdn_Dispatcher_BeforeControllerMethod_Handler($Sender) {
		$Controller =& $Sender->EventArguments['Controller'];
		if ($Controller) $this->SetTranslation($Controller);
	}
	
	protected function SetTranslation($Sender) {
	//public function Base_Render_Before(&$Sender) {

		// get sender info
		$Application = mb_convert_case($Sender->Application, 2);
		$Controller = mb_convert_case(substr($Sender->ControllerName, 0, -10), 2);
		$Method = mb_convert_case($Sender->RequestMethod, 2);
		//d($Application.$Controller.$Method, $this->_Definition);
		
		// searching custom definitions for this application and this controller
		$Codes = array();
		if (array_key_exists($Application, $this->_Definition)) {
			$Codes = array_merge($Codes, $this->_Definition[$Application]);
		}
		
		if (array_key_exists($Application.$Controller, $this->_Definition)) {
			$Codes = array_merge($Codes, $this->_Definition[$Application.$Controller]);
		}
		
		if (array_key_exists($Application.$Controller.$Method, $this->_Definition)) {
			$Codes = array_merge($Codes, $this->_Definition[$Application.$Controller.$Method]);
		}
		
		// set translation
		$this->_Locale->SetTranslation($Codes);
	}
	
	protected function LoadLocaleDistinctions($ForceRemapping = False) {
		$SafeLocaleName = preg_replace('/([^\w\d_-])/', '', $this->_Locale->Current());
		$EnabledApplications = Gdn::Config('EnabledApplications', array());
		$EnabledPlugins = Gdn::Config('EnabledPlugins', array());

		Gdn_LibraryMap::PrepareCache('distinctions', NULL, 'tree');
		
		$Sources = Gdn_LibraryMap::GetCache('distinctions', $SafeLocaleName);
		if ($ForceRemapping === True || !Gdn_LibraryMap::CacheReady('distinctions') || $Sources === Null) {
			$Sources = array();
			$ApplicationSources = Gdn_FileSystem::FindAll(PATH_APPLICATIONS, CombinePaths(array('locale', $SafeLocaleName, 'distinctions.php')), $EnabledApplications);
			if ($ApplicationSources != False) $Sources = array_merge($Sources, $ApplicationSources);
			$PluginSources = Gdn_FileSystem::FindAll(PATH_PLUGINS, CombinePaths(array('locale', $SafeLocaleName, 'distinctions.php')), $EnabledPlugins);
			if ($PluginSources != False) $Sources = array_merge($Sources, $PluginSources);
			$Theme = C('Garden.Theme');
			if ($Theme != False) {
				$ThemeLocalePath = ConcatSep(DS, PATH_THEMES, $Theme, 'distinctions', $SafeLocaleName.'.php');
				if (file_exists($ThemeLocalePath)) $Sources[] = $ThemeLocalePath;
			}
			// Save the mappings
			$FileContents = array();
			for ($Count = count($Sources), $i = 0; $i < $Count; ++$i) {
				$FileContents[$SafeLocaleName][] = Gdn_Format::ArrayValueForPhp($Sources[$i]);
			}
			Gdn_LibraryMap::PrepareCache('distinctions', $FileContents);
			
			$Sources = Gdn_LibraryMap::GetCache('distinctions', $SafeLocaleName);
		}
	
		for($Count = count($Sources), $i = 0; $i < $Count; ++$i) {
			if (file_exists($Sources[$i])) include_once($Sources[$i]);
		}
		
		// TODO: conf?
		//$ConfigDistinction = PATH_CONF . DS . 'distinctions.php';
		//if(file_exists($ConfigDistinction)) include $ConfigDistinction;
		$PluginDistinction = dirname(__FILE__) . DS . 'distinctions.php';
		if (file_exists($PluginDistinction)) include $PluginDistinction;
		
		if (isset($Definition) && is_array($Definition)) $this->_Definition = $Definition;
		unset($Definition);
	}
	
	public function Setup() {
	}
	
	
	
	
}










