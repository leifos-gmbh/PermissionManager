<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilPermissionManagerAction
 * @author  Stefan Meyer <smeyer.ilias@gmx.de>
 */
class ilPermissionManagerAction
{
    const MODE_SUMMARY = 1;
    const MODE_UPDATE = 2;

    const ADV_TYPE_NONE = 0;
    const ADV_TYPE_IN_COURSES = 1;
    const ADV_TYPE_IN_GROUPS = 2;
    const ADV_TYPE_OUTSIDE_COURSES = 3;
    const ADV_TYPE_OUTSIDE_GROUPS = 4;
    const ADV_TYPE_OUTSIDE_COURSE_AND_GROUPS = 5;

    const ACTION_TYPE_PERMISSIONS = 0;
    const ACTION_TYPE_AVAILABILITY = 1;

    const ACTION_ADD = 1;
    const ACTION_REMOVE = 2;

    private $rep_node = 0;
    private $type_filter = [];
    private $advanced_type_filter = 0;
    private $template_id = 0;
    private $action_type = self::ACTION_TYPE_PERMISSIONS;
    private $action = 0;
    private $change_role_templates = false;
    private $role_filter = [];
    private $timing_start = 0;
    private $timing_end = 0;
    private $timing_visibility = 0;
    private $reset_timings = false;

    /**
     * @var ilTree
     */
    private $tree;

    public function __construct()
    {
        global $DIC;

        $this->tree = $DIC->repositoryTree();
    }

    public static function getAdvancedTypeFilterOptions()
    {
        return array(
            self::ADV_TYPE_NONE                      => ilPermissionManagerPlugin::getInstance()->txt('adv_not_filtered'),
            self::ADV_TYPE_IN_COURSES                => ilPermissionManagerPlugin::getInstance()->txt('adv_in_courses'),
            self::ADV_TYPE_IN_GROUPS                 => ilPermissionManagerPlugin::getInstance()->txt('adv_in_groups'),
            self::ADV_TYPE_OUTSIDE_COURSES           => ilPermissionManagerPlugin::getInstance()->txt('adv_outside_courses'),
            self::ADV_TYPE_OUTSIDE_GROUPS            => ilPermissionManagerPlugin::getInstance()->txt('adv_outside_groups'),
            self::ADV_TYPE_OUTSIDE_COURSE_AND_GROUPS => ilPermissionManagerPlugin::getInstance()->txt('adv_outside_courses_groups')
        );
    }

    public static function getTemplateOptions()
    {
        global $DIC;

        $ilDB = $DIC->database();
        $lng  = $DIC->language();

        $query = 'SELECT obj_id, title FROM object_data WHERE type = ' . $ilDB->quote('rolt', 'text') . ' ORDER BY title';
        $res   = $ilDB->query($query);

        $options[0] = $lng->txt('select_one');
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            $options[$row->obj_id] = $row->title;
        }
        return $options;
    }

    public function setRepositoryNode($a_node)
    {
        $this->rep_node = $a_node;
    }

    public function setTemplate($a_template_id)
    {
        $this->template_id = $a_template_id;
    }

    public function setResetTimingsEnabled($a_status)
    {
        $this->reset_timings = $a_status;
    }

    public function __sleep()
    {
        return array('rep_node', 'type_filter', 'advanced_type_filter', 'template_id', 'action', 'action_type', 'change_role_templates', 'role_filter', 'timing_start', 'timing_end', 'timing_visibility', 'reset_timings');
    }

    public function doSummary()
    {


        $GLOBALS['tree']->useCache(false);

        $info_by_type = array();
        // initializte $info array();
        foreach ($this->getTypeFilter() as $type) {
            $info_by_type[$type]['num'] = 0;
        }

        ilLoggerFactory::getLogger('lfpm')->dump($info_by_type, ilLogLevel::DEBUG);

        // walk through repository tree
        $this->walkThroughTree($GLOBALS['tree']->getNodeData($this->getRepositoryNode()), self::MODE_SUMMARY, $info_by_type);

        return $info_by_type;
    }

    public function getTypeFilter()
    {
        return $this->type_filter;
    }

    public function setTypeFilter($a_filter)
    {
        $this->type_filter = $a_filter;
    }

    private function walkThroughTree($a_node, $a_mode, &$info_by_type)
    {
        $is_handled_type = $this->isHandledType($a_node);
        if ($is_handled_type) {
            $info_by_type[$a_node['type']]['num']++;
            if ($a_mode == self::MODE_UPDATE) {
                $this->updateNode($a_node);
            }
        } elseif (
            $a_mode == self::MODE_UPDATE &&
            $GLOBALS['objDefinition']->isContainer($a_node['type'])
        ) {
            $this->updateContainer($a_node);
        }

        foreach ($GLOBALS['tree']->getChilds($a_node['child']) as $child) {
            if ($child['type'] == 'adm') {
                continue;
            }

            if (!$GLOBALS['objDefinition']->isContainer($child['type'])) {
                $is_handled_type = $this->isHandledType($child);
                if ($is_handled_type) {
                    $info_by_type[$child['type']]['num']++;
                    if ($a_mode == self::MODE_UPDATE) {
                        $this->updateNode($child);
                    }
                }
            }
            if ($GLOBALS['objDefinition']->isContainer($child['type'])) {
                $this->walkThroughTree($child, $a_mode, $info_by_type);
            }
        }
    }

    private function isHandledType($a_node) : bool
    {
        // check type
        if (!in_array($a_node['type'], $this->getTypeFilter())) {
            return false;
        }
        switch ($this->getAdvancedTypeFilter()) {
            case self::ADV_TYPE_IN_COURSES:
                if (!$this->tree->checkForParentType($a_node['child'], 'crs', true)) {
                    return false;
                }
                break;

            case self::ADV_TYPE_IN_GROUPS:
                if (!$this->tree->checkForParentType($a_node['child'], 'grp', true)) {
                    return false;
                }
                break;

            case self::ADV_TYPE_OUTSIDE_COURSES:
                if ($this->tree->checkForParentType($a_node['child'], 'crs', true)) {
                    return false;
                }
                break;

            case self::ADV_TYPE_OUTSIDE_GROUPS:
                if ($this->tree->checkForParentType($a_node['child'], 'grp', true)) {
                    return false;
                }
                break;

            case self::ADV_TYPE_OUTSIDE_COURSE_AND_GROUPS:
                if (
                    $this->tree->checkForParentType($a_node['child'], 'crs', true) ||
                    $this->tree->checkForParentType($a_node['child'], 'grp', true)
                ) {
                    return false;
                }
                break;
        }

        return true;
    }

    public function getAdvancedTypeFilter()
    {
        return $this->advanced_type_filter;
    }

    public function setAdvancedTypeFilter($a_adv_filter)
    {
        $this->advanced_type_filter = $a_adv_filter;
    }

    /**
     * Update node
     * @param type $a_node
     */
    private function updateNode(array $a_node)
    {
        ilLoggerFactory::getLogger('lfpm')->debug('Update node of type: ' . $a_node['type'] . '(' . $a_node['title'] . ')');

        if ($this->getActionType() == self::ACTION_TYPE_AVAILABILITY) {
            $this->updateAvailability($a_node);
            return;
        }

        foreach ($this->applyRoleFilter($a_node) as $role) {
            ilLoggerFactory::getLogger('lfpm')->dump($a_node, ilLogLevel::DEBUG);
            ilLoggerFactory::getLogger('lfpm')->dump($role, ilLogLevel::DEBUG);
            ilLoggerFactory::getLogger('lfpm')->debug('Applying new permission to role templates');
            if ($this->getChangeRoleTemplates() && ($role['parent'] == $a_node['child'])) {
                ilLoggerFactory::getLogger('lfpm')->debug('Update local role_permissions');
                $this->updateTemplatePermissions($a_node, $role);
            }
            ilLoggerFactory::getLogger('lfpm')->debug('Update object permissions');
            $this->updateObjectPermissions($a_node, $role);
        }
        return;
    }

    public function getActionType()
    {
        return $this->action_type;
    }

    public function setActionType($a_type)
    {
        $this->action_type = $a_type;
    }

    private function updateAvailability($node)
    {
        ilLoggerFactory::getLogger('lfpm')->dump($node);

        // creates default entry
        $item = ilObjectActivation::getItem($node['child']);

        ilLoggerFactory::getLogger('lfpm')->dump($item);
        /*
        if(
            $item['timing_type'] == ilObjectActivation::TIMINGS_ACTIVATION &&
            $item['timing_end'] < time()
        )
        {
            // do nothing
            ilLoggerFactory::getLogger('lfpm')->debug('Item access already exceeded. Aborting');
            return false;
        }
         *
         */

        if ($this->resetTimingsEnabled() == true) {
            include_once './Services/Object/classes/class.ilObjectActivation.php';
            $activation = new ilObjectActivation();
            $activation->setTimingType(ilObjectActivation::TIMINGS_DEACTIVATED);
            $activation->update($node['child']);
            return true;
        }

        include_once './Services/Object/classes/class.ilObjectActivation.php';
        $activation = new ilObjectActivation();
        $activation->setTimingType(ilObjectActivation::TIMINGS_ACTIVATION);
        $activation->setTimingStart($this->getTimingStart());
        $activation->setTimingEnd($this->getTimingEnd());
        $activation->toggleVisible($this->getTimingVisibility());
        $activation->update($node['child']);
        return true;
    }

    public function resetTimingsEnabled()
    {
        return $this->reset_timings;
    }

    public function getTimingStart()
    {
        return $this->timing_start ? $this->timing_start : time();
    }

    public function setTimingStart($a_start)
    {
        $this->timing_start = $a_start;
    }

    public function getTimingEnd()
    {
        return $this->timing_end ? $this->timing_end : time();
    }

    public function setTimingEnd($a_end)
    {
        $this->timing_end = $a_end;
    }

    public function getTimingVisibility()
    {
        return $this->timing_visibility;
    }

    public function setTimingVisibility($a_stat)
    {
        $this->timing_visibility = $a_stat;
    }

    /**
     * Apply role filter
     * @param array $a_node
     */
    private function applyRoleFilter(array $a_node)
    {
        global $DIC;

        $rbacreview = $DIC->rbac()->review();

        $valid_roles = array();
        foreach ($rbacreview->getParentRoleIds($a_node['child'], $this->getChangeRoleTemplates()) as $role) {
            #ilLoggerFactory::getLogger('lfpm')->dump($role, ilLogLevel::DEBUG);
            foreach ($this->getRoleFilter() as $filter) {
                $filter     = trim($filter);
                $role_title = trim($role['title']);

                if (!strlen($filter)) {
                    ilLoggerFactory::getLogger('lfpm')->debug('Empty filter given');
                    continue;
                }
                if (preg_match('/' . $filter . '/', $role_title) === 1) {
                    ilLoggerFactory::getLogger('lfpm')->debug('Filter ' . $filter . ' matches ' . $role_title);
                    $valid_roles[] = $role;
                } else {
                    ilLoggerFactory::getLogger('lfpm')->debug('Filter ' . $filter . ' does not match ' . $role_title);
                }
            }
        }
        return $valid_roles;
    }

    public function getChangeRoleTemplates()
    {
        return $this->change_role_templates;
    }

    public function setChangeRoleTemplates($a_stat)
    {
        $this->change_role_templates = $a_stat;
    }

    public function getRoleFilter()
    {
        return $this->role_filter;
    }

    public function setRoleFilter($a_filter)
    {
        $this->role_filter = $a_filter;
    }

    /**
     * Update template permissions
     * @param array $node
     * @param array $role
     */
    private function updateTemplatePermissions(array $node, array $role)
    {
        global $DIC;

        $rbacadmin = $DIC->rbac()->admin();

        if ($this->getAction() == self::ACTION_ADD) {
            ilLoggerFactory::getLogger('lfpm')->debug('Action add permissions');
            $rbacadmin->copyRolePermissionUnion(
                $this->getTemplate(),
                ROLE_FOLDER_ID,
                $role['obj_id'],
                $role['parent'],
                $role['obj_id'],
                $role['parent']
            );
        }
        if ($this->getAction() == self::ACTION_REMOVE) {
            ilLoggerFactory::getLogger('lfpm')->debug('Action remove permissions');
            $rbacadmin->copyRolePermissionSubtract(
                $this->getTemplate(),
                ROLE_FOLDER_ID,
                $role['obj_id'],
                $role['parent']
            );
        }
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setAction($a_action)
    {
        $this->action = $a_action;
    }

    public function getTemplate()
    {
        return $this->template_id;
    }

    /**
     * Update object permissions
     * @param array $node
     * @param array $role
     */
    private function updateObjectPermissions(array $node, array $role)
    {
        global $DIC;

        $rbacreview = $DIC->rbac()->review();
        $rbacadmin  = $DIC->rbac()->admin();

        $operations = $rbacreview->getOperationsOfRole($this->getTemplate(), $node['type'], ROLE_FOLDER_ID);
        ilLoggerFactory::getLogger('lfpm')->debug('Operations for type ' . $node['type']);
        ilLoggerFactory::getLogger('lfpm')->dump($operations, ilLogLevel::DEBUG);

        $active = $rbacreview->getActiveOperationsOfRole($node['child'], $role['obj_id']);
        ilLoggerFactory::getLogger('lfpm')->debug('Active operations for ' . $node['title']);
        ilLoggerFactory::getLogger('lfpm')->dump($active, ilLogLevel::DEBUG);

        if ($this->getAction() == self::ACTION_ADD) {
            $new_permissions = array_unique(array_merge($operations, $active));
        }
        if ($this->getAction() == self::ACTION_REMOVE) {
            $new_permissions = array_diff($active, $operations);
        }

        ilLoggerFactory::getLogger('lfpm')->debug('New operations for ' . $node['title']);
        ilLoggerFactory::getLogger('lfpm')->dump($new_permissions, ilLogLevel::DEBUG);

        $rbacadmin->revokePermission($node['child'], $role['obj_id']);
        $rbacadmin->grantPermission($role['obj_id'], (array) $new_permissions, $node['child']);
    }

    private function updateContainer(array $a_node)
    {
        return;

        if (!$this->getChangeRoleTemplates()) {
            ilLoggerFactory::getLogger('lfpm')->debug('Update container of type: ' . $a_node['type'] . '(' . $a_node['title'] . ')');
            ilLoggerFactory::getLogger('lfpm')->debug('No template updates required');
            return;
        }

        // get roles by filter
        foreach ($this->applyRoleFilter($a_node) as $role) {
            ilLoggerFactory::getLogger('lfpm')->debug('Applying new permission to role templates');
        }
    }

    public function getRepositoryNode()
    {
        return $this->rep_node;
    }

    /**
     * Start permission manipulation
     */
    public function start()
    {
        $GLOBALS['tree']->useCache(false);
        foreach ($this->getTypeFilter() as $type) {
            $info_by_type[$type]['num'] = 0;
        }
        ilLoggerFactory::getLogger('lfpm')->dump($info_by_type, ilLogLevel::DEBUG);

        // walk through repository tree
        $this->walkThroughTree($GLOBALS['tree']->getNodeData($this->getRepositoryNode()), self::MODE_UPDATE, $info_by_type);

        return $info_by_type;

    }
}

?>