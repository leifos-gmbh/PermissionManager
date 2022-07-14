<?php declare(strict_types=1);

/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class lfPermissionManagerPlugin
 * @author  Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilPermissionManagerPlugin extends ilUserInterfaceHookPlugin
{
    private const PLUGIN_ID = 'lfpm';
    private const PLUGIN_NAME = 'PermissionManager';
    private static ?ilPermissionManagerPlugin $instance = null;

    public static function getInstance() : ilPermissionManagerPlugin
    {
        global $DIC;

        if (self::$instance instanceof self) {
            return self::$instance;
        }
        return self::$instance = new self(
            $DIC->database(),
            $DIC["component.repository"],
            self::PLUGIN_ID
        );
    }

    protected function init() : void
    {
        global $DIC;

        // set configured log level
        foreach ($DIC->logger()->lfpm()->getLogger()->getHandlers() as $handler) {
            $handler->setLevel(ilPermissionManagerSettings::getInstance()->getLogLevel());
        }
    }

    /**
     * drop database tables and delete ilSetting entrys
     */
    protected function uninstallCustom() : void
    {
    }

    public function getPluginName() : string
    {
        return self::PLUGIN_NAME;
    }
}
