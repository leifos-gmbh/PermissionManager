<?php declare(strict_types=1);
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Permission manager summary table gui
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilPermissionManagerSummaryTableGUI extends ilTable2GUI
{
    private ?ilPermissionManagerAction $action = null;
    private ?ilPermissionManagerSettings $settings = null;
    private ilObjectDefinition $objDefinition;

    private ilPermissionManagerPlugin $plugin;

    public function __construct(object $a_parent_obj, string $a_parent_cmd = "", string $a_template_context = "")
    {
        global $DIC;

        $this->objDefinition = $DIC['objDefinition'];

        $this->plugin = ilPermissionManagerPlugin::getInstance();

        $this->setId('lfpm');
        parent::__construct($a_parent_obj, $a_parent_cmd, $a_template_context);
    }


    public function getSettings() : ?ilPermissionManagerSettings
    {
        return $this->settings;
    }

    public function setSettings(ilPermissionManagerSettings $settings) : void
    {
        $this->settings = $settings;
    }


    public function init() : void
    {
        $this->setFormAction($this->ctrl->getFormAction($this->getParentObject(), $this->getParentCmd()));
        $this->setTitle($this->plugin->txt('table_summary_title'));
        $this->addColumn(
            $this->plugin->txt('table_col_type'),
            'type',
            '80%'
        );
        $this->addColumn(
            $this->plugin->txt('table_col_num'),
            'number',
            '20%'
        );

        $this->setRowTemplate(
            "tpl.summary_row.html",
            $this->plugin->getDirectory()
        );

        $this->setDefaultOrderField('number');
        $this->setDefaultOrderDirection('desc');

        $this->addCommandButton('performUpdate', $this->lng->txt('execute'));
        $this->addCommandButton('configure', $this->lng->txt('cancel'));
    }

    public function parse() : void
    {
        $data = array();
        foreach ($this->getAction()->doSummary() as $obj_type => $info) {
            $row['type'] = $obj_type;
            $row['num'] = $info['num'];

            $data[] = $row;
        }

        $this->setData($data);
    }

    public function getAction() : ?ilPermissionManagerAction
    {
        return $this->action;
    }

    public function setAction(ilPermissionManagerAction $action) : void
    {
        $this->action = $action;
    }

    protected function fillRow($a_set) : void
    {
        if ($this->objDefinition->isPlugin($a_set['type'])) {
            $type_str = ilObjectPlugin::lookupTxtById($a_set['type'], 'objs_' . $a_set['type']);
        } else {
            $type_str = $this->lng->txt('objs_' . $a_set['type']);
        }
        $this->tpl->setVariable('OBJ_TYPE', $type_str);
        $this->tpl->setVariable('OBJ_NUM', $a_set['num']);
    }
}
