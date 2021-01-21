<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/Table/classes/class.ilTable2GUI.php';

/**
 * Permission manager summary table gui
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilPermissionManagerSummaryTableGUI extends ilTable2GUI
{
    private $action = null;
    private $settings = null;

    /**
     * @var ilObjectDefinition
     */
    private $objDefinition;

    /**
     * @param object $a_parent_obj
     * @param string $a_parent_cmd
     * @param string $a_template_context
     */
    public function __construct($a_parent_obj, $a_parent_cmd = "", $a_template_context = "")
    {
        global $DIC;

        $this->objDefinition = $DIC['objDefinition'];
        $this->lng = $DIC->language();

        $this->setId('lfpm');
        parent::__construct($a_parent_obj, $a_parent_cmd, $a_template_context);
    }

    /**
     * @return ilPermissionManagerSettings
     */
    public function getSettings()
    {
        return $this->settings;
    }

    public function setSettings(ilPermissionManagerSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Init table
     */
    public function init()
    {
        $this->setFormAction($GLOBALS['ilCtrl']->getFormAction($this->getParentObject(), $this->getParentCmd()));
        $this->setTitle(ilPermissionManagerPlugin::getInstance()->txt('table_summary_title'));
        $this->addColumn(ilPermissionManagerPlugin::getInstance()->txt('table_col_type'), 'type', '80%');
        $this->addColumn(ilPermissionManagerPlugin::getInstance()->txt('table_col_num'), 'number', '20%');

        $this->setRowTemplate("tpl.summary_row.html", substr(ilPermissionManagerPlugin::getInstance()->getDirectory(), 2));

        $this->setDefaultOrderField('number');
        $this->setDefaultOrderDirection('desc');

        $this->addCommandButton('performUpdate', $GLOBALS['lng']->txt('execute'));
        $this->addCommandButton('configure', $GLOBALS['lng']->txt('cancel'));
    }

    /**
     *
     */
    public function parse()
    {
        $data = array();
        foreach ($this->getAction()->doSummary() as $obj_type => $info) {
            $row['type'] = $obj_type;
            $row['num']  = $info['num'];

            $data[] = $row;
        }

        $this->setData($data);
    }

    /**
     * @return ilPermissionManagerAction
     */
    public function getAction()
    {
        return $this->action;
    }

    public function setAction(ilPermissionManagerAction $action)
    {
        $this->action = $action;
    }

    public function fillRow($set)
    {
        if ($this->objDefinition->isPlugin($set['type'])) {
            $type_str = ilObjectPlugin::lookupTxtById($set['type'], 'objs_' . $set['type']);
        } else {
            $type_str = $this->lng->txt('objs_' . $set['type']);
        }
        $this->tpl->setVariable('OBJ_TYPE', $type_str);
        $this->tpl->setVariable('OBJ_NUM', $set['num']);
    }

}

?>