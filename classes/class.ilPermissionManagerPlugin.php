<?php

include_once './Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php';

/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class lfPermissionManagerPlugin
 *
 * @author  Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilPermissionManagerPlugin extends ilUserInterfaceHookPlugin
{
	private static $instance = null;
	
	const PLUGIN_NAME = 'PermissionManager';
	
	/**
	 * Get singleton instance
	 * @return lfPermissionManagerPlugin
	 */
	public static function getInstance()
	{
		if(self::$instance instanceof self)
		{
			return self::$instance;
		}
		return self::$instance = new self();
	}


	/**
	 * Get plugin name
	 * @return string
	 */
	public function getPluginName()
	{
		return self::PLUGIN_NAME;
	}
	
	/**
	 * Init vitero
	 */
	protected function init()
	{
		$this->initAutoLoad();

		// set configured log level
		foreach(ilLoggerFactory::getLogger('lfpm')->getLogger()->getHandlers() as $handler)
		{
			$handler->setLevel(ilPermissionManagerSettings::getInstance()->getLogLevel());
		}
		
	}

	/**
	 * Init auto loader
	 * @return void
	 */
	protected function initAutoLoad()
	{
		spl_autoload_register(
			array($this,'autoLoad')
		);
	}

	/**
	 * Auto load implementation
	 *
	 * @param string class name
	 */
	private final function autoLoad($a_classname)
	{
		$class_file = $this->getClassesDirectory().'/class.'.$a_classname.'.php';
		@include_once($class_file);
	}
	
	
	/**
	 * drop database tables and delete ilSetting entrys
	 */
	protected function uninstallCustom()
	{
	}


}
