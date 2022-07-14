<?php declare(strict_types=1);
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Permission manager configuration
 * @author Stefan Meyer <smeyer.ilias@gmx.de>
 * @ilCtrl_IsCalledBy ilPermissionManagerConfigGUI: ilObjComponentSettingsGUI
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
            $action = new ilPermissionManagerAction();
            $action->setRepositoryNode((int) $form->getInput('node'));
            $action->setTypeFilter($form->getInput('type_filter') ?? []);
            $action->setAdvancedTypeFilter((int) $form->getInput('adv_type_filter'));
            $action->setTemplate((int) $form->getInput('template'));
            $action->setChangeRoleTemplates((bool)$form->getInput('adapt_templates'));
            $action->setRoleFilter($form->getInput('role_filter') ?? []);
            $action->setAction((int) $form->getInput('action'));
            $action->setActionType((int) $form->getInput('action_type'));
            $action->setTimingStart(
                is_object($form->getItemByPostVar('timing_start')->getDate()) ?
                    (int) $form->getItemByPostVar('timing_start')->getDate()->get(IL_CAL_UNIX) :
                    0
            );
            $action->setTimingEnd(
                is_object($form->getItemByPostVar('timing_end')->getDate()) ?
                    (int) $form->getItemByPostVar('timing_end')->getDate()->get(IL_CAL_UNIX) :
                    0
            );
            $action->setResetTimingsEnabled((bool) ($form->getInput('reset') ?? false));

            $action->setTimingVisibility((bool) ($form->getInput('visible') ?? false));

            $this->settings->setLogLevel((int) $form->getInput('log_level'));
            $this->settings->setAction($action);
            $this->settings->update();
            return true;
        }
        $this->tpl->setOnScreenMessage('failure', $this->lng->txt('err_check_input'));
        $this->configure($form);
        return false;
    }

    protected function initConfigurationForm() : ilPropertyFormGUI
    {
        $action = $this->settings->getAction();

        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->getPluginObject()->txt('form_tab_settings'));

        // log level
        $this->lng->loadLanguageModule('log');
        $level = new ilSelectInputGUI($this->getPluginObject()->txt('form_tab_settings_loglevel'), 'log_level');
        $level->setOptions(ilLogLevel::getLevelOptions());
        $level->setValue((string) $this->settings->getLogLevel());
        $form->addItem($level);

        $rep_node = new ilNumberInputGUI($this->getPluginObject()->txt('form_rep_node'), 'node');
        $rep_node->setMinValue(1);
        $rep_node->setRequired(true);
        $rep_node->setSize(7);
        $rep_node->setValue((string) $action->getRepositoryNode());
        $rep_node->setInfo($this->getPluginObject()->txt('form_rep_node_info'));
        $form->addItem($rep_node);

        $type_filter = new ilCheckboxGroupInputGUI($this->getPluginObject()->txt('form_type_filter'), 'type_filter');
        $type_filter->setValue($action->getTypeFilter());
        $type_filter->setRequired(true);

        $options = array();
        foreach ($this->objDefinition->getAllRepositoryTypes() as $type_str) {
            if (
                $this->objDefinition->isSystemObject($type_str) ||
                !$this->objDefinition->isRBACObject($type_str)) {
                continue;
            }
            if ($this->objDefinition->isPlugin($type_str)) {
                $options[$type_str] = ilObjectPlugin::lookupTxtById($type_str, 'obj_' . $type_str);
            } else {
                $options[$type_str] = $this->lng->txt('objs_' . $type_str);
            }
        }
        asort($options);
        foreach ($options as $type_str => $translation) {
            $type_option = new ilCheckboxOption($translation, $type_str);
            $type_filter->addOption($type_option);
        }
        $form->addItem($type_filter);

        $adv_filter = new ilSelectInputGUI($this->getPluginObject()->txt('form_type_adv_filter'), 'adv_type_filter');
        $adv_filter->setValue((string)$action->getAdvancedTypeFilter());
        $adv_filter->setOptions(ilPermissionManagerAction::getAdvancedTypeFilterOptions());
        $adv_filter->setRequired(true);
        $form->addItem($adv_filter);

        $action_type = new ilRadioGroupInputGUI($this->getPluginObject()->txt('action_type'), 'action_type');
        $action_type->setValue((string) $action->getActionType());
        $action_type->setRequired(true);

        $action_perm = new ilRadioOption(
            $this->getPluginObject()->txt('action_type_adjust_perm'),
            (string) ilPermissionManagerAction::ACTION_TYPE_PERMISSIONS
        );
        $action_type->addOption($action_perm);

        $action_availability = new ilRadioOption(
            $this->getPluginObject()->txt('action_type_adjust_availability'),
            (string) ilPermissionManagerAction::ACTION_TYPE_AVAILABILITY
        );
        $action_type->addOption($action_availability);

        $templates = new ilSelectInputGUI($this->getPluginObject()->txt('form_rolt'), 'template');
        $templates->setValue((string) $action->getTemplate());
        $templates->setRequired(true);
        $templates->setOptions(ilPermissionManagerAction::getTemplateOptions());
        $action_perm->addSubItem($templates);

        $action_ar = new ilRadioGroupInputGUI($this->getPluginObject()->txt('form_action'), 'action');
        $action_ar->setValue((string) $action->getAction());
        $action_ar->setRequired(true);

        $options_add = new ilRadioOption(
            $this->getPluginObject()->txt('action_add'),
            (string) ilPermissionManagerAction::ACTION_ADD
        );
        $action_ar->addOption($options_add);

        $options_remove = new ilRadioOption(
            $this->getPluginObject()->txt('action_remove'),
            (string) ilPermissionManagerAction::ACTION_REMOVE
        );
        $action_ar->addOption($options_remove);
        $action_perm->addSubItem($action_ar);

        $adapt_templates = new ilCheckboxInputGUI(
            $this->getPluginObject()->txt('form_action_templates'),
            'adapt_templates'
        );
        $adapt_templates->setChecked($action->getChangeRoleTemplates());
        $adapt_templates->setValue('1');
        $action_perm->addSubItem($adapt_templates);

        $role_filter = new ilTextInputGUI($this->getPluginObject()->txt('form_role_filter'), 'role_filter');
        $this->logger->dump($action->getRoleFilter(), ilLogLevel::DEBUG);
        $role_filter->setRequired(true);
        $role_filter->setMulti(true);
        $filter_roles = $action->getRoleFilter();
        $role_filter->setValue(array_shift($filter_roles));
        $role_filter->setMultiValues($action->getRoleFilter());
        $action_perm->addSubItem($role_filter);

        // Avaliability settings
        $this->lng->loadLanguageModule('crs');

        $start = new ilDateTimeInputGUI($this->lng->txt('crs_timings_start'), 'timing_start');
        $start->setShowTime(true);
        $start->setDate(new ilDateTime($action->getTimingStart(), IL_CAL_UNIX));

        $this->logger->debug('Timing start: ' . $action->getTimingStart());

        $action_availability->addSubItem($start);

        $end = new ilDateTimeInputGUI($this->lng->txt('crs_timings_end'), 'timing_end');
        $end->setShowTime(true);
        $end->setDate(new ilDateTime($action->getTimingEnd(), IL_CAL_UNIX));

        $this->logger->debug('Timing end: ' . $action->getTimingEnd());

        $action_availability->addSubItem($end);

        $isv = new ilCheckboxInputGUI($this->lng->txt('crs_timings_visibility_short'), 'visible');
        $isv->setInfo($this->lng->txt('crs_timings_visibility'));
        $isv->setValue('1');
        $isv->setChecked($action->getTimingVisibility() ? true : false);
        $action_availability->addSubItem($isv);

        $reset = new ilCheckboxInputGUI($this->getPluginObject()->txt('reset_timings'), 'reset');
        $reset->setInfo($this->getPluginObject()->txt('reset_timings_info'));
        $reset->setValue('1');
        $reset->setChecked($action->resetTimingsEnabled());
        $action_availability->addSubItem($reset);

        $form->addItem($action_type);

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
