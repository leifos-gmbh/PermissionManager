<?php

include_once './Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php';

/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class lfPermissionManagerPlugin
 *
 * @author  Stefan Meyer <smeyer.ilias@gmx.de>
 */
class lfPermissionManagerPlugin extends ilUserInterfaceHookPlugin
{
	const PLUGIN_NAME = 'lfPermissionManager';
	
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
