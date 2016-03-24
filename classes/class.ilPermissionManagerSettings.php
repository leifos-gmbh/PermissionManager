<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class lfPermissionManagerPlugin
 *
 * @author  Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilPermissionManagerSettings
{
	private static $instance = null;
	
	
	private $storage = null;
	private $log_level = 100;
	private $action = null;
	
	
	/**
	 * Singeleton constructor
	 */
	private function __construct()
	{
		include_once './Services/Administration/classes/class.ilSetting.php';
		$this->storage = new ilSetting('lfpm');
		$this->init();
	}
	
	/**
	 * Get songeleton instance
	 * @return ilPermissionManagerSettings
	 */
	public static function getInstance()
	{
		if(!self::$instance)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function getLogLevel()
	{
		return $this->log_level;
	}
	
	public function setLogLevel($a_level)
	{
		$this->log_level = $a_level;
	}
	
	public function setAction(ilPermissionManagerAction $action = null)
	{
		$this->action = $action;
	}
	
	/**
	 * Get action
	 * @return  \ilPermissionManagerAction
	 */
	public function getAction()
	{
		return $this->action;
	}


	
	/**
	 * Update settings
	 */
	public function update()
	{
		$this->getStorage()->set('log_level', $this->getLogLevel());
		$this->getStorage()->set('action',  serialize($this->getAction()));
	}
	
	/**
	 * @return ilSetting 
	 */
	protected function getStorage()
	{
		return $this->storage;
	}

	

	/**
	 * Init (read) settings
	 */
	protected function init()
	{
		$this->setLogLevel($this->getStorage()->get('log_level',$this->log_level));
		
		$ser_action = $this->getStorage()->get('action', serialize(new ilPermissionManagerAction()));
		$this->setAction(unserialize($ser_action));
	}
	

}
?>