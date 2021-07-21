<?php

/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class lfPermissionManagerPlugin
 * @author  Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilPermissionManagerPlugin extends ilUserInterfaceHookPlugin
{
    const PLUGIN_NAME = 'PermissionManager';
    private static $instance = null;

    public static function getInstance() : ilPermissionManagerPlugin
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }
        return self::$instance = new self();
    }

    final private function autoLoad(string $a_classname)
    {
        $class_file = $this->getClassesDirectory() . '/class.' . $a_classname . '.php';
        if (file_exists($class_file)) {
            include_once $class_file;
        }
    }

    /**
     * Init vitero
     */
    protected function init()
    {
        $this->initAutoLoad();

        // set configured log level
        foreach (ilLoggerFactory::getLogger('lfpm')->getLogger()->getHandlers() as $handler) {
            $handler->setLevel(ilPermissionManagerSettings::getInstance()->getLogLevel());
        }
    }

    protected function initAutoLoad() : void
    {
        spl_autoload_register(
            array($this, 'autoLoad')
        );
    }

    /**
     * drop database tables and delete ilSetting entrys
     */
    protected function uninstallCustom()
    {
    }

    public function getPluginName() : string
    {
        return self::PLUGIN_NAME;
    }
}
