<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/Table/classes/class.ilTable2GUI.php';
 
/**
 * Permission manager summary table gui
 * 
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 * 
 */
class ilPermissionManagerSummaryTableGUI extends ilTable2GUI
{
	private $action = null;
	private $settings = null;

	/**
	 * 
	 * @param type $a_parent_obj
	 * @param type $a_parent_cmd
	 * @param type $a_template_context
	 */
	public function __construct($a_parent_obj, $a_parent_cmd = "", $a_template_context = "")
	{
		$this->setId('lfpm');
		parent::__construct($a_parent_obj, $a_parent_cmd, $a_template_context);
	}
	
	public function setAction(ilPermissionManagerAction $action)
	{
		$this->action = $action;
	}
	
	public function setSettings(ilPermissionManagerSettings $settings)
	{
		$this->settings = $settings;
	}
	
	/**
	 * 
	 * @return ilPermissionManagerSettings
	 */
	public function getSettings()
	{
		return $this->settings;
	}
	
	/**
	 * 
	 * @return ilPermissionManagerAction
	 */
	public function getAction()
	{
		return $this->action;
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
		
        $this->setRowTemplate("tpl.summary_row.html",substr(ilPermissionManagerPlugin::getInstance()->getDirectory(),2));
		
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
		foreach($this->getAction()->doSummary() as $obj_type => $info)
		{
			$row['type'] = $obj_type;
			$row['num'] = $info['num'];
			
			$data[] = $row;
		}
		
		$this->setData($data);
	}
	
	public function fillRow($set)
	{
		$this->tpl->setVariable('OBJ_TYPE',$GLOBALS['lng']->txt('objs_'.$set['type']));
		$this->tpl->setVariable('OBJ_NUM',$set['num']);
	}
	
}
?>