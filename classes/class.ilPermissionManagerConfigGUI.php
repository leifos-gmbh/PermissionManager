<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/Component/classes/class.ilPluginConfigGUI.php';
 
/**
 * Permission manager confguration 
 * 
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 * 
 */
class ilPermissionManagerConfigGUI extends ilPluginConfigGUI
{
	/**
	 * Handles all commmands, default is "configure"
	 */
	public function performCommand($cmd)
	{
		global $ilTabs;

		$ilTabs->addTab(
			'configure',
			ilPermissionManagerPlugin::getInstance()->txt('tab_configure'),
			$GLOBALS['ilCtrl']->getLinkTarget($this,'configure')
		);

		switch ($cmd)
		{
			case "configure":
			case "actions":
			case 'showAffected':
			case 'listAffected':
			case 'execute':
				$this->$cmd();
				break;

		}
	}
	
	

	
	/**
	 * Configure plugin 
	 */
	protected function configure(ilPropertyFormGUI $form = null)
	{
		$GLOBALS['ilTabs']->activateTab('configure');
		
		if(!$form instanceof ilPropertyFormGUI)
		{
			$form = $this->initConfigurationForm();
		}
		$GLOBALS['tpl']->setContent($form->getHTML());
	}
	
	/**
	 * Init config form
	 * @return \ilPropertyFormGUI
	 */
	protected function initConfigurationForm()
	{
		$action = ilPermissionManagerSettings::getInstance()->getAction();
		ilLoggerFactory::getLogger('lfpm')->dump($action, ilLogLevel::DEBUG);
		
		include_once './Services/Form/classes/class.ilPropertyFormGUI.php';
		$form = new ilPropertyFormGUI();
		$form->setFormAction($GLOBALS['ilCtrl']->getFormAction($this));
		$form->setTitle($this->getPluginObject()->txt('form_tab_settings'));

		
		#include_once './Services/Form/classes/class.ilRepositorySelectorInputGUI.php';
		#$rep_node = new ilRepositorySelectorInputGUI($this->getPluginObject()->txt('form_rep_node'),'node');
		#$rep_node->setRequired(true);
		#$rep_node->setInfo($this->getPluginObject()->txt('form_rep_node_info'));
		#$rep_node->setValue(1);
		#$form->addItem($rep_node);
		
		$rep_node = new ilNumberInputGUI($this->getPluginObject()->txt('form_rep_node'), 'node');
		$rep_node->setRequired(true);
		$rep_node->setSize(7);
		$rep_node->setValue($action->getRepositoryNode());
		$rep_node->setInfo($this->getPluginObject()->txt('form_rep_node_info'));
		$form->addItem($rep_node);
		
		$type_filter = new ilCheckboxGroupInputGUI($this->getPluginObject()->txt('form_type_filter'),'type_filter');
		$type_filter->setValue($action->getTypeFilter());
		$type_filter->setRequired(true);
		
		foreach($GLOBALS['objDefinition']->getAllRepositoryTypes() as $type_str)
		{
			if(!$GLOBALS['objDefinition']->isRbacObject($type_str))
			{
				continue;
			}
			$type_option = new ilRadioOption($GLOBALS['lng']->txt('objs_'.$type_str), $type_str);
			$type_filter->addOption($type_option);
		}
		$form->addItem($type_filter);
		
		$adv_filter = new ilSelectInputGUI($this->getPluginObject()->txt('form_type_adv_filter'), 'adv_type_filter');
		$adv_filter->setValue($action->getAdvancedTypeFilter());
		$adv_filter->setOptions(ilPermissionManagerAction::getAdvancedTypeFilterOptions());
		$adv_filter->setRequired(true);
		$form->addItem($adv_filter);
		
		$templates = new ilSelectInputGUI($this->getPluginObject()->txt('form_rolt'), 'template');
		$templates->setValue($action->getTemplate());
		$templates->setRequired(true);
		$templates->setOptions(ilPermissionManagerAction::getTemplateOptions());
		$form->addItem($templates);
		
		$action_type = new ilRadioGroupInputGUI($this->getPluginObject()->txt('form_action'), 'action');
		$action_type->setValue($action->getAction());
		$action_type->setRequired(true);
		
		$options_add = new ilRadioOption($this->getPluginObject()->txt('action_add'), ilPermissionManagerAction::ACTION_ADD);
		$action_type->addOption($options_add);
		
		$options_remove = new ilRadioOption($this->getPluginObject()->txt('action_remove'), ilPermissionManagerAction::ACTION_REMOVE);
		$action_type->addOption($options_remove);
		
		$form->addItem($action_type);
		
		$adapt_templates = new ilCheckboxInputGUI($this->getPluginObject()->txt('form_action_templates'),'adapt_templates');
		$adapt_templates->setChecked($action->getChangeRoleTemplates());
		$adapt_templates->setValue(1);
		$form->addItem($adapt_templates);
		

		$role_filter = new ilTextInputGUI($this->getPluginObject()->txt('form_role_filter'), 'role_filter');
		#$role_filter->setValue($action->getRoleFilter());
		$role_filter->setMulti(true);
		$role_filter->setRequired(false);
		$form->addItem($role_filter);
		
		$form->addCommandButton('showAffected', $this->getPluginObject()->txt('btn_show_affected'));
		return $form;
		
	}
	
	/**
	 * Save settings
	 */
	protected function showAffected()
	{
		ilLoggerFactory::getLogger('lfpm')->debug('Saving confguration options...');
		$form = $this->initConfigurationForm();
		if($form->checkInput())
		{
			ilLoggerFactory::getLogger('lfpm')->dump($_POST,  ilLogLevel::DEBUG);
			$action = new ilPermissionManagerAction();
			$action->setRepositoryNode($form->getInput('node'));
			$action->setTypeFilter($form->getInput('type_filter'));
			$action->setAdvancedTypeFilter($form->getInput('adv_type_filter'));
			$action->setTemplate($form->getInput('template'));
			$action->setChangeRoleTemplates($form->getInput('adapt_templates'));
			$action->setRoleFilter($form->getInput('role_filter'));
			$action->setAction($form->getInput('action'));
			
			ilPermissionManagerSettings::getInstance()->setAction($action);
			ilPermissionManagerSettings::getInstance()->update();
			
			$GLOBALS['ilCtrl']->redirect($this,'listAffected');
			return true;
		}
		ilUtil::sendFailure($GLOBALS['lng']->txt('err_check_input'));
		$this->configure($form);
		return TRUE;
	}
	
	
	/**
	 * List affected objects by configuration
	 */
	protected function listAffected()
	{
		$GLOBALS['ilTabs']->activateTab('configure');
		
		$table = new ilPermissionManagerSummaryTableGUI($this, 'listAffected');
		$table->setAction(ilPermissionManagerSettings::getInstance()->getAction());
		$table->setSettings(ilPermissionManagerSettings::getInstance());
		$table->init();
		$table->parse();
		
		$GLOBALS['tpl']->setContent($table->getHTML());
	}
	
	/**
	 * Execute action
	 */
	protected function execute()
	{
		$action = ilPermissionManagerSettings::getInstance()->getAction();
		$action->start();
		
	}

}
?>