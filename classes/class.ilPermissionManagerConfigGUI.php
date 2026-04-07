<?php

/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

declare(strict_types=1);

/**
 * @ilCtrl_IsCalledBy ilPermissionManagerConfigGUI : ilObjComponentSettingsGUI
 */
class ilPermissionManagerConfigGUI extends ilPluginConfigGUI
{
    private ilObjectDefinition $objDefinition;
    private ilLanguage $lng;
    private ilCtrl $ctrl;
    private ilGlobalTemplateInterface $tpl;
    private ilLogger $logger;
    private ilTabsGUI $tabs;

    private ilPermissionManagerPlugin $plugin;
    private ilPermissionManagerSettings $settings;

    public function __construct()
    {
        global $DIC;

        $this->objDefinition = $DIC['objDefinition'];
        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->tabs = $DIC->tabs();
        $this->logger = $DIC->logger()->lfpm();

        $this->plugin = ilPermissionManagerPlugin::getInstance();
        $this->settings = ilPermissionManagerSettings::getInstance();
    }

    protected function save() : bool
    {
        if ($this->doSave()) {
            $this->tpl->setOnScreenMessage('success', $this->lng->txt('settings_saved'), true);
            $this->ctrl->redirect($this, 'configure');
            return true;
        }
        return false;
    }

    protected function doSave() : bool
    {
        $this->logger->debug('Saving confguration options...');
        $form = $this->initConfigurationForm();
        if ($form->checkInput()) {
            $is_whole_tree = $form->getInput('radio_area') === 'radio_whole_tree';
            $action = new ilPermissionManagerAction();
            $action->setRepositoryNode($is_whole_tree ? 0 : (int) $form->getInput('node'));
            $action->setTypeFilter($form->getInput('type_filter') ?? []);
            $action->setAdvancedTypeFilter((int) $form->getInput('adv_type_filter'));

            $action->setActionType((int) $form->getInput('action_type'));

            $action->setTemplate((int) $form->getInput('template'));
            $action->setChangeRoleTemplates((bool)$form->getInput('adapt_templates'));
            $action->setRoleFilter($form->getInput('role_filter') ?? []);
            $action->setAction((int) $form->getInput('action'));

            $action->setEndtimeSelected((bool) $form->getInput('availability_end_time'));
            $action->setStarttimeSelected((bool) $form->getInput('availability_start_time'));
            $action->setVisibilitySelected((bool) $form->getInput('visibility'));
            $action->setStarttimeSelectedOpiton(
                $action->getStarttimeSelected()
                    ? $form->getInput('adjustment_start_time') ?? ''
                    : ''
            );
            $action->setEndtimeSelectedOpiton(
                $action->getEndtimeSelected()
                    ? $form->getInput('adjustment_end_time') ?? ''
                    : ''
            );
            $action->setVisibilitySelectedOpiton(
                $action->getVisibilitySelected()
                    ? $form->getInput('adjustment_visibility') ?? ''
                    : ''
            );

            $action->setTimingStart(
                is_object($form->getItemByPostVar('timing_start')->getDate()) && $action->getStarttimeSelectedOpiton() === 'adjustment_start_time_option_force'
                    ? (int) $form->getItemByPostVar('timing_start')->getDate()->get(IL_CAL_UNIX)
                    : 0
            );
            $action->setTimingEnd(
                is_object($form->getItemByPostVar('timing_end')->getDate()) && $action->getEndtimeSelectedOpiton() === 'adjustment_end_time_option_force'
                    ? (int) $form->getItemByPostVar('timing_end')->getDate()->get(IL_CAL_UNIX)
                    : 0
            );
            $action->setResetStartTimeEnabled($action->getStarttimeSelectedOpiton() === 'adjustment_start_time_option_remove');
            $action->setResetEndTimeEnabled($action->getEndtimeSelectedOpiton() === 'adjustment_end_time_option_remove');

            $action->setForceVisibilityEnabled((($form->getInput('reset') ?? false)) && $action->getVisibilitySelectedOpiton() === 'adjustment_visibility_option_force');;
            $action->setRemoveVisibilityEnabled((($form->getInput('visible') ?? false)) && $action->getVisibilitySelectedOpiton() === 'adjustment_visibility_option_remove');;;

            $this->settings->setLogLevel((int) $form->getInput('log_level'));
            $this->settings->setAction($action);
            $this->settings->update();
            return true;
        }
        $form->setValuesByPost();
        $this->tpl->setOnScreenMessage('failure', $this->lng->txt('err_check_input'));
        $this->configure($form);
        return false;
    }

    protected function initConfigurationForm() : ilPropertyFormGUI
    {
        $action = $this->settings->getAction();
        $this->lng->loadLanguageModule('crs');

        # LOG LEVEL
        $section_logging = new ilFormSectionHeaderGUI();
        $section_logging->setTitle($this->getPluginObject()->txt('section_protocolling'));
        $this->lng->loadLanguageModule('log');
        $level = new ilSelectInputGUI($this->getPluginObject()->txt('form_tab_settings_loglevel'), 'log_level');
        $level->setOptions(ilLogLevel::getLevelOptions());
        $level->setValue((string) $this->settings->getLogLevel());

        # MAGAZINE SELECTION
        $section_usage_in_magazine = new ilFormSectionHeaderGUI();
        $section_usage_in_magazine->setTitle($this->getPluginObject()->txt('section_usage_in_magazine'));
        $rep_node = new ilNumberInputGUI($this->getPluginObject()->txt('form_rep_node'), 'node');
        $rep_node->setMinValue(1);
        $rep_node->setRequired(true);
        $rep_node->setSize(7);
        $rep_node->setValue((string) $action->getRepositoryNode());
        $rep_node->setInfo($this->getPluginObject()->txt('form_rep_node_info'));
        $radio_option_whole_tree = new ilRadioOption($this->getPluginObject()->txt('radio_area_option_whole_tree'), 'radio_whole_tree');
        $radio_option_subtree = new ilRadioOption($this->getPluginObject()->txt('radio_area_option_subtree'), 'radio_subtree');
        $radio_option_subtree->addSubItem($rep_node);
        $radio_area = new ilRadioGroupInputGUI($this->getPluginObject()->txt('radio_area'), 'radio_area');
        $radio_area->addOption($radio_option_whole_tree);
        $radio_area->addOption($radio_option_subtree);
        $radio_area->setValue($action->getRepositoryNode() > 0 ? 'radio_subtree' : 'radio_whole_tree');

        # ACTIONS
        $section_object_type_restriction = new ilFormSectionHeaderGUI();
        $section_object_type_restriction->setTitle($this->getPluginObject()->txt('section_object_type_restriction'));
        $options = [];
        foreach ($this->objDefinition->getAllRepositoryTypes() as $type_str) {
            if ($this->objDefinition->isSystemObject($type_str) || !$this->objDefinition->isRBACObject($type_str)) {
                continue;
            }
            $options[$type_str] = $this->objDefinition->isPlugin($type_str)
                ? ilObjectPlugin::lookupTxtById($type_str, 'obj_' . $type_str)
                : $this->lng->txt('objs_' . $type_str);
        }
        asort($options);
        $type_filter = new ilCheckboxGroupInputGUI($this->getPluginObject()->txt('form_type_filter'), 'type_filter');
        $type_filter->setValue($action->getTypeFilter());
        $type_filter->setRequired(true);
        foreach ($options as $type_str => $translation) {
            $type_option = new ilCheckboxOption($translation, $type_str);
            $type_filter->addOption($type_option);
        }
        $adv_filter = new ilSelectInputGUI($this->getPluginObject()->txt('form_type_adv_filter'), 'adv_type_filter');
        $adv_filter->setValue((string)$action->getAdvancedTypeFilter());
        $adv_filter->setOptions(ilPermissionManagerAction::getAdvancedTypeFilterOptions());
        $adv_filter->setRequired(true);

        # ADJUSTMENTS
        $section_adjustments = new ilFormSectionHeaderGUI();
        $section_adjustments->setTitle($this->getPluginObject()->txt('section_adjustments'));
        $options_add = new ilRadioOption($this->getPluginObject()->txt('action_add'), (string) ilPermissionManagerAction::ACTION_ADD);
        $options_remove = new ilRadioOption($this->getPluginObject()->txt('action_remove'), (string) ilPermissionManagerAction::ACTION_REMOVE);
        $action_ar = new ilRadioGroupInputGUI($this->getPluginObject()->txt('form_action'), 'action');
        $action_ar->setValue((string) $action->getAction());
        $action_ar->addOption($options_add);
        $action_ar->addOption($options_remove);

        $adapt_templates = new ilCheckboxInputGUI($this->getPluginObject()->txt('form_action_templates'), 'adapt_templates');
        $adapt_templates->setChecked($action->getChangeRoleTemplates());
        $adapt_templates->setValue('1');

        $filter_roles = $action->getRoleFilter();
        $role_filter = new ilTextInputGUI($this->getPluginObject()->txt('form_role_filter'), 'role_filter');
        $role_filter->setRequired(true);
        $role_filter->setMulti(true);
        $role_filter->setValue(array_shift($filter_roles));
        $role_filter->setMultiValues($action->getRoleFilter());
        $role_filter->setInfo($this->getPluginObject()->txt('form_role_filter_info'));
        $this->logger->dump($action->getRoleFilter(), ilLogLevel::DEBUG);

        $templates = new ilSelectInputGUI($this->getPluginObject()->txt('form_rolt'), 'template');
        $templates->setInfo($this->getPluginObject()->txt('form_rolt_byline'));
        $templates->setValue((string) $action->getTemplate());
        $templates->setRequired(true);
        $templates->setOptions(ilPermissionManagerAction::getTemplateOptions());

        $action_perm = new ilRadioOption($this->getPluginObject()->txt('radio_action_option_adjust_perm'), (string) ilPermissionManagerAction::ACTION_TYPE_PERMISSIONS);
        $action_perm->addSubItem($templates);
        $action_perm->addSubItem($action_ar);
        $action_perm->addSubItem($adapt_templates);
        $action_perm->addSubItem($role_filter);

        $start = new ilDateTimeInputGUI($this->lng->txt('crs_timings_start'), 'timing_start');
        $start->setShowTime(true);
        $start->setDate(new ilDateTime($action->getTimingStart(), IL_CAL_UNIX));
        $radio_adjustment_start_time_option_remove = new ilRadioOption($this->getPluginObject()->txt('radio_adjustment_start_time_option_remove'), 'adjustment_start_time_option_remove');
        $radio_adjustment_start_time_option_force = new ilRadioOption($this->getPluginObject()->txt('radio_adjustment_start_time_option_force'), 'adjustment_start_time_option_force');
        $radio_adjustment_start_time_option_force->addSubItem($start);
        $radio_adjustment_start_time = new ilRadioGroupInputGUI($this->getPluginObject()->txt('radio_adjustment_start_time'), 'adjustment_start_time');
        $radio_adjustment_start_time->addOption($radio_adjustment_start_time_option_remove);
        $radio_adjustment_start_time->addOption($radio_adjustment_start_time_option_force);
        $radio_adjustment_start_time->setRequired(true);
        $radio_adjustment_start_time->setValue($action->getStarttimeSelectedOpiton());
        $this->logger->debug('Timing start: ' . $action->getTimingStart());
        $checkbox_start_time = new ilCheckboxInputGUI($this->getPluginObject()->txt('checkbox_start_time'), 'availability_start_time');
        $checkbox_start_time->addSubItem($radio_adjustment_start_time);
        $checkbox_start_time->setChecked($action->getStarttimeSelected());

        $end = new ilDateTimeInputGUI($this->lng->txt('crs_timings_end'), 'timing_end');
        $end->setShowTime(true);
        $end->setDate(new ilDateTime($action->getTimingEnd(), IL_CAL_UNIX));
        $radio_adjustment_end_time_option_remove = new ilRadioOption($this->getPluginObject()->txt('radio_adjustment_end_time_option_remove'), 'adjustment_end_time_option_remove');
        $radio_adjustment_end_time_option_force = new ilRadioOption($this->getPluginObject()->txt('radio_adjustment_end_time_option_force'), 'adjustment_end_time_option_force');;
        $radio_adjustment_end_time_option_force->addSubItem($end);
        $radio_adjustment_end_time = new ilRadioGroupInputGUI($this->getPluginObject()->txt('radio_adjustment_end_time'), 'adjustment_end_time');
        $radio_adjustment_end_time->addOption($radio_adjustment_end_time_option_remove);
        $radio_adjustment_end_time->addOption($radio_adjustment_end_time_option_force);
        $radio_adjustment_end_time->setRequired(true);
        $radio_adjustment_end_time->setValue($action->getEndtimeSelectedOpiton());
        $this->logger->debug('Timing end: ' . $action->getTimingEnd());
        $checkbox_end_time = new ilCheckboxInputGUI($this->getPluginObject()->txt('checkbox_end_time'), 'availability_end_time');
        $checkbox_end_time->addSubItem($radio_adjustment_end_time);
        $checkbox_end_time->setChecked($action->getEndtimeSelected());

        $radio_adjustment_visibility_option_remove = new ilRadioOption($this->getPluginObject()->txt('radio_adjustment_visibility_option_remove'), 'reset');
        $radio_adjustment_visibility_option_force = new ilRadioOption($this->getPluginObject()->txt('radio_adjustment_visibility_option_force'), 'visible');
        $radio_adjustment_visibility = new ilRadioGroupInputGUI($this->getPluginObject()->txt('radio_adjustment_visibility'), 'adjustment_visibility');
        $radio_adjustment_visibility->addOption($radio_adjustment_visibility_option_remove);
        $radio_adjustment_visibility->addOption($radio_adjustment_visibility_option_force);
        $radio_adjustment_visibility->setRequired(true);
        $radio_adjustment_visibility->setValue($action->getVisibilitySelectedOpiton());
        $checkbox_visibility = new ilCheckboxInputGUI($this->getPluginObject()->txt('checkbox_visibility'), 'visibility');
        $checkbox_visibility->addSubItem($radio_adjustment_visibility);
        $checkbox_visibility->setChecked($action->getVisibilitySelected());

        $action_availability = new ilRadioOption(
            $this->getPluginObject()->txt('radio_action_option_adjust_availability'),
            (string) ilPermissionManagerAction::ACTION_TYPE_AVAILABILITY
        );
        $action_availability->addSubItem($checkbox_start_time);
        $action_availability->addSubItem($checkbox_end_time);
        $action_availability->addSubItem($checkbox_visibility);

        $action_type = new ilRadioGroupInputGUI($this->getPluginObject()->txt('radio_action'), 'action_type');
        $action_type->setValue((string) $action->getActionType());
        $action_type->setRequired(true);
        $action_type->addOption($action_perm);
        $action_type->addOption($action_availability);

        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->getPluginObject()->txt('form_tab_settings'));
        $form->addItem($section_usage_in_magazine);
        $form->addItem($radio_area);
        $form->addItem($section_object_type_restriction);
        $form->addItem($type_filter);
        $form->addItem($adv_filter);
        $form->addItem($section_adjustments);
        $form->addItem($action_type);
        $form->addItem($section_logging);
        $form->addItem($level);
        $form->addCommandButton('save', $this->lng->txt('save'));
        $form->addCommandButton('showAffected', $this->getPluginObject()->txt('btn_show_affected'));
        return $form;
    }

    protected function configure(?ilPropertyFormGUI $form = null) : void
    {
        $this->tabs->activateTab('configure');

        if (!$form instanceof ilPropertyFormGUI) {
            $form = $this->initConfigurationForm();
        }
        $this->tpl->setContent($form->getHTML());
    }

    /**
     * Save settings
     */
    protected function showAffected() : bool
    {
        if ($this->doSave()) {
            $this->ctrl->redirect($this, 'listAffected');
            return true;
        }
        return false;
    }

    /**
     * List affected objects by configuration
     */
    protected function listAffected() : void
    {
        $this->tabs->activateTab('configure');

        $table = new ilPermissionManagerSummaryTableGUI($this, 'listAffected');
        $table->setAction($this->settings->getAction());
        $table->setSettings($this->settings);
        $table->init();
        $table->parse();

        $meminfo = '';
        if (function_exists('memory_get_peak_usage')) {
            $meminfo = ' Memory used: ';
            $meminfo .= ((int) (memory_get_peak_usage() / 1024 / 1024));
            $meminfo .= ' MB';

            $this->tpl->setOnScreenMessage('info', $meminfo);
        }

        $this->tpl->setContent($table->getHTML());
    }


    protected function performUpdate() : void
    {
        $action = $this->settings->getAction();
        $info = $action->start();

        $meminfo = '';
        if (function_exists('memory_get_peak_usage')) {
            $meminfo = ' Memory used: ';
            $meminfo .= ((int) (memory_get_peak_usage() / 1024 / 1024));
            $meminfo .= ' MB';
        }

        $this->tpl->setOnScreenMessage(
            'success',
            $this->getPluginObject()->txt('executed_permission_update') . $meminfo,
            true
        );
        $this->ctrl->redirect($this, 'configure');
    }

    public function performCommand(string $cmd) : void
    {
        $this->tabs->addTab(
            'configure',
            $this->plugin->txt('tab_configure'),
            $this->ctrl->getLinkTarget($this, 'configure')
        );

        switch ($cmd) {
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
}
