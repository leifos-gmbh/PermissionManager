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
			case 'save':
			case "actions":
			case 'showAffected':
			case 'listAffected':
			case 'performUpdate':
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
		
		// log level
		$GLOBALS['lng']->loadLanguageModule('log');
		$level = new ilSelectInputGUI($this->getPluginObject()->txt('form_tab_settings_loglevel'),'log_level');
		$level->setOptions(ilLogLevel::getLevelOptions());
		$level->setValue(ilPermissionManagerSettings::getInstance()->getLogLevel());
		$form->addItem($level);
		

		$rep_node = new ilNumberInputGUI($this->getPluginObject()->txt('form_rep_node'), 'node');
		$rep_node->setRequired(true);
		$rep_node->setSize(7);
		$rep_node->setValue($action->getRepositoryNode());
		$rep_node->setInfo($this->getPluginObject()->txt('form_rep_node_info'));
		$form->addItem($rep_node);
		
		$type_filter = new ilCheckboxGroupInputGUI($this->getPluginObject()->txt('form_type_filter'),'type_filter');
		$type_filter->setValue($action->getTypeFilter());
		$type_filter->setRequired(true);
		
		$options = array();
		foreach($GLOBALS['objDefinition']->getAllRepositoryTypes() as $type_str)
		{
			if(
				$GLOBALS['objDefinition']->isSystemObject($type_str) ||
				!$GLOBALS['objDefinition']->isRBACObject($type_str))
			{
				continue;
			}
			$options[$type_str] = $GLOBALS['lng']->txt('objs_'.$type_str);
		}
		asort($options);
		foreach($options as $type_str => $translation)
		{
			$type_option = new ilRadioOption($translation, $type_str);
			$type_filter->addOption($type_option);
		}
		$form->addItem($type_filter);
		
		$adv_filter = new ilSelectInputGUI($this->getPluginObject()->txt('form_type_adv_filter'), 'adv_type_filter');
		$adv_filter->setValue($action->getAdvancedTypeFilter());
		$adv_filter->setOptions(ilPermissionManagerAction::getAdvancedTypeFilterOptions());
		$adv_filter->setRequired(true);
		$form->addItem($adv_filter);
		
		
		$action_type = new ilRadioGroupInputGUI($this->getPluginObject()->txt('action_type'),'action_type');
		$action_type->setValue($action->getActionType());
		$action_type->setRequired(true);
		
		$action_perm = new ilRadioOption($this->getPluginObject()->txt('action_type_adjust_perm'), ilPermissionManagerAction::ACTION_TYPE_PERMISSIONS);
		$action_type->addOption($action_perm);
		
		$action_availability = new ilRadioOption($this->getPluginObject()->txt('action_type_adjust_availability'), ilPermissionManagerAction::ACTION_TYPE_AVAILABILITY);
		$action_type->addOption($action_availability);
		
		
		$templates = new ilSelectInputGUI($this->getPluginObject()->txt('form_rolt'), 'template');
		$templates->setValue($action->getTemplate());
		$templates->setRequired(true);
		$templates->setOptions(ilPermissionManagerAction::getTemplateOptions());
		$action_perm->addSubItem($templates);
		
		$action_ar = new ilRadioGroupInputGUI($this->getPluginObject()->txt('form_action'), 'action');
		$action_ar->setValue($action->getAction());
		$action_ar->setRequired(true);
		
		$options_add = new ilRadioOption($this->getPluginObject()->txt('action_add'), ilPermissionManagerAction::ACTION_ADD);
		$action_ar->addOption($options_add);
		
		$options_remove = new ilRadioOption($this->getPluginObject()->txt('action_remove'), ilPermissionManagerAction::ACTION_REMOVE);
		$action_ar->addOption($options_remove);
		$action_perm->addSubItem($action_ar);
		
		
		$adapt_templates = new ilCheckboxInputGUI($this->getPluginObject()->txt('form_action_templates'),'adapt_templates');
		$adapt_templates->setChecked($action->getChangeRoleTemplates());
		$adapt_templates->setValue(1);
		$action_perm->addSubItem($adapt_templates);
		
		$role_filter = new ilTextInputGUI($this->getPluginObject()->txt('form_role_filter'), 'role_filter');
		ilLoggerFactory::getLogger('lfpm')->dump($action->getRoleFilter(),  ilLogLevel::DEBUG);
		$role_filter->setRequired(true);
		$role_filter->setMulti(true);
		$role_filter->setValue(array_shift($action->getRoleFilter()));
		$role_filter->setMultiValues($action->getRoleFilter());
		$action_perm->addSubItem($role_filter);
		
		
		// Avaliability settings
		$GLOBALS['lng']->loadLanguageModule('crs');
		
		$start = new ilDateTimeInputGUI($GLOBALS['lng']->txt('crs_timings_start'),'timing_start');
		$start->setShowTime(true);
		$start->setDate(new ilDateTime($action->getTimingStart(), IL_CAL_UNIX));
		
		ilLoggerFactory::getLogger('lfpm')->debug('Timing start: ' . $action->getTimingStart());
		
		$action_availability->addSubItem($start);
		
		$end = new ilDateTimeInputGUI($GLOBALS['lng']->txt('crs_timings_end'),'timing_end');
		$end->setShowTime(true);
		$end->setDate(new ilDateTime($action->getTimingEnd(),IL_CAL_UNIX));

		ilLoggerFactory::getLogger('lfpm')->debug('Timing end: ' . $action->getTimingEnd());

		$action_availability->addSubItem($end);
			
		$isv = new ilCheckboxInputGUI($GLOBALS['lng']->txt('crs_timings_visibility_short'),'visible');
		$isv->setInfo($GLOBALS['lng']->txt('crs_timings_visibility'));
		$isv->setValue(1);
		$isv->setChecked($action->getTimingVisibility() ? true : false);
		$action_availability->addSubItem($isv);
		
		$reset = new ilCheckboxInputGUI($this->getPluginObject()->txt('reset_timings'),'reset');
		$reset->setInfo($this->getPluginObject()->txt('reset_timings_info'));
		$reset->setValue(1);
		$reset->setChecked($action->resetTimingsEnabled());
		$action_availability->addSubItem($reset);

		$form->addItem($action_type);
		
		

		$form->addCommandButton('save', $GLOBALS['lng']->txt('save'));
		$form->addCommandButton('showAffected', $this->getPluginObject()->txt('btn_show_affected'));
		return $form;
		
	}
	
	protected function doSave()
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
			$action->setActionType($form->getInput('action_type'));
			$action->setTimingStart($form->getItemByPostVar('timing_start')->getDate()->get(IL_CAL_UNIX));
			$action->setTimingEnd($form->getItemByPostVar('timing_end')->getDate()->get(IL_CAL_UNIX));
			$action->setResetTimingsEnabled($form->getInput('reset'));
			
			ilLoggerFactory::getLogger('lfpm')->debug('Starting time is: ' . $form->getItemByPostVar('timing_start')->getDate()->get(IL_CAL_UNIX));
			ilLoggerFactory::getLogger('lfpm')->debug('Ending time is: ' . $form->getItemByPostVar('timing_end')->getDate()->get(IL_CAL_UNIX));
			
			
			$action->setTimingVisibility($form->getInput('visible'));
			
			ilPermissionManagerSettings::getInstance()->setLogLevel($form->getInput('log_level'));
			ilPermissionManagerSettings::getInstance()->setAction($action);
			ilPermissionManagerSettings::getInstance()->update();
			return true;
		}
		ilUtil::sendFailure($GLOBALS['lng']->txt('err_check_input'));
		$this->configure($form);
		return false;
		
	}

	protected function save()
	{
		if($this->doSave())
		{
			ilUtil::sendSuccess($GLOBALS['lng']->txt('settings_saved'),true);
			$GLOBALS['ilCtrl']->redirect($this,'configure');
			return true;
		}
	}
	
	/**
	 * Save settings
	 */
	protected function showAffected()
	{
		
		if($this->doSave())
		{
			$GLOBALS['ilCtrl']->redirect($this,'listAffected');
			return true;
		}
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
		
		$meminfo = '';
		if(function_exists('memory_get_peak_usage'))
		{
			$meminfo = ' Memory used: ';
			$meminfo .= ((int) (memory_get_peak_usage() / 1024 / 1024));
			$meminfo .= ' MB';
			
			ilUtil::sendInfo($meminfo);
		}
		
		
		$GLOBALS['tpl']->setContent($table->getHTML());
	}
	
	/**
	 * Execute action
	 */
	protected function performUpdate()
	{
		$action = ilPermissionManagerSettings::getInstance()->getAction();
		$info = $action->start();
		
		$meminfo = '';
		if(function_exists('memory_get_peak_usage'))
		{
			$meminfo = ' Memory used: ';
			$meminfo .= ((int) (memory_get_peak_usage() / 1024 / 1024)); 
			$meminfo .= ' MB';
		}
		
		ilUtil::sendSuccess($this->getPluginObject()->txt('executed_permission_update').$meminfo, true);
		$GLOBALS['ilCtrl']->redirect($this,'configure');
	}

}
?>