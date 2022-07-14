<?php declare(strict_types=1);
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class lfPermissionManagerPlugin
 * @author  Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilPermissionManagerSettings
{
    private static ?ilPermissionManagerSettings $instance = null;
    private ilSetting $storage;
    private int $log_level = 100;
    private ?ilPermissionManagerAction $action = null;

    private function __construct()
    {
        $this->storage = new ilSetting('lfpm');
        $this->init();
    }

    protected function init() : void
    {
        $this->setLogLevel((int) $this->getStorage()->get('log_level', (string) $this->log_level));

        $ser_action = $this->getStorage()->get('action', serialize(new ilPermissionManagerAction()));
        $this->setAction(unserialize($ser_action));
    }

    protected function getStorage() : ilSetting
    {
        return $this->storage;
    }

    public static function getInstance() : ilPermissionManagerSettings
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function update() : void
    {
        $this->getStorage()->set('log_level', (string) $this->getLogLevel());
        $this->getStorage()->set('action', serialize($this->getAction()));
    }

    public function getLogLevel() : int
    {
        return $this->log_level;
    }

    public function setLogLevel(int $a_level) : void
    {
        $this->log_level = $a_level;
    }

    public function getAction() : ilPermissionManagerAction
    {
        return $this->action;
    }

    public function setAction(ilPermissionManagerAction $action = null) : void
    {
        $this->action = $action;
    }
}
