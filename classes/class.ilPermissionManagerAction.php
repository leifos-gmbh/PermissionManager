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

    /**
     * @var ilLogger
     */
    private $logger;

    /**
     * @var ilObjectDefinition
     */
    private $objDefinition;

    public function __construct()
    {
        global $DIC;

        $this->tree = $DIC->repositoryTree();
        $this->logger = ilLoggerFactory::getLogger('lfpm');
        $this->objDefinition = $DIC['objDefinition'];
    }

    public static function getAdvancedTypeFilterOptions() : array
    {
        return array(
            self::ADV_TYPE_NONE => ilPermissionManagerPlugin::getInstance()->txt('adv_not_filtered'),
            self::ADV_TYPE_IN_COURSES => ilPermissionManagerPlugin::getInstance()->txt('adv_in_courses'),
            self::ADV_TYPE_IN_GROUPS => ilPermissionManagerPlugin::getInstance()->txt('adv_in_groups'),
            self::ADV_TYPE_OUTSIDE_COURSES => ilPermissionManagerPlugin::getInstance()->txt('adv_outside_courses'),
            self::ADV_TYPE_OUTSIDE_GROUPS => ilPermissionManagerPlugin::getInstance()->txt('adv_outside_groups'),
            self::ADV_TYPE_OUTSIDE_COURSE_AND_GROUPS => ilPermissionManagerPlugin::getInstance()->txt('adv_outside_courses_groups')
        );
    }

    public static function getTemplateOptions() : array
    {
        global $DIC;

        $ilDB = $DIC->database();
        $lng = $DIC->language();

        $query = 'SELECT obj_id, title FROM object_data WHERE type = ' . $ilDB->quote('rolt', 'text') . ' ORDER BY title';
        $res = $ilDB->query($query);

        $options[0] = $lng->txt('select_one');
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            $options[$row->obj_id] = $row->title;
        }
        return $options;
    }

    public function setRepositoryNode(int $a_node)
    {
        $this->rep_node = $a_node;
    }

    public function setTemplate(int $a_template_id)
    {
        $this->template_id = $a_template_id;
    }

    public function setResetTimingsEnabled(int $a_status)
    {
        $this->reset_timings = $a_status;
    }

    public function __sleep() : array
    {
        return array('rep_node', 'type_filter', 'advanced_type_filter', 'template_id', 'action', 'action_type', 'change_role_templates', 'role_filter', 'timing_start', 'timing_end', 'timing_visibility', 'reset_timings');
    }

    /**
     * Magic
     */
    public function __wakeup()
    {
        global $DIC;

        $this->tree = $DIC->repositoryTree();
        $this->logger = ilLoggerFactory::getLogger('lfpm');
        $this->objDefinition = $DIC['objDefinition'];
    }

    public function doSummary() : array
    {
        $this->tree->useCache(false);

        $info_by_type = array();
        // initializte $info array();
        foreach ($this->getTypeFilter() as $type) {
            $info_by_type[$type]['num'] = 0;
        }

        $this->logger->dump($info_by_type, ilLogLevel::DEBUG);

        // walk through repository tree
        $this->walkThroughTree($this->tree->getNodeData($this->getRepositoryNode()), self::MODE_SUMMARY, $info_by_type);

        return $info_by_type;
    }

    public function getTypeFilter() : array
    {
        return $this->type_filter;
    }

    public function setTypeFilter(array $a_filter)
    {
        $this->type_filter = $a_filter;
    }

    private function walkThroughTree(array $a_node, int $a_mode, array &$info_by_type)
    {
        $is_handled_type = $this->isHandledType($a_node);
        if ($is_handled_type) {
            $info_by_type[$a_node['type']]['num']++;
            if ($a_mode == self::MODE_UPDATE) {
                $this->updateNode($a_node);
            }
        } elseif (
            $a_mode == self::MODE_UPDATE &&
            $this->objDefinition->isContainer($a_node['type'])
        ) {
            $this->updateContainer($a_node);
        }

        foreach ($this->tree->getChilds($a_node['child']) as $child) {
            if ($child['type'] == 'adm') {
                continue;
            }

            if (!$this->objDefinition->isContainer($child['type'])) {
                $is_handled_type = $this->isHandledType($child);
                if ($is_handled_type) {
                    $info_by_type[$child['type']]['num']++;
                    if ($a_mode == self::MODE_UPDATE) {
                        $this->updateNode($child);
                    }
                }
            }
            if ($this->objDefinition->isContainer($child['type'])) {
                $this->walkThroughTree($child, $a_mode, $info_by_type);
            }
        }
    }

    private function isHandledType(array $a_node) : bool
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

    public function getAdvancedTypeFilter() : int
    {
        return $this->advanced_type_filter;
    }

    public function setAdvancedTypeFilter(int $a_adv_filter)
    {
        $this->advanced_type_filter = $a_adv_filter;
    }


    private function updateNode(array $a_node)
    {
        $this->logger->debug('Update node of type: ' . $a_node['type'] . '(' . $a_node['title'] . ')');

        if ($this->getActionType() == self::ACTION_TYPE_AVAILABILITY) {
            $this->updateAvailability($a_node);
            return;
        }

        foreach ($this->applyRoleFilter($a_node) as $role) {
            $this->logger->dump($a_node, ilLogLevel::DEBUG);
            $this->logger->dump($role, ilLogLevel::DEBUG);
            $this->logger->debug('Applying new permission to role templates');
            if ($this->getChangeRoleTemplates() && ($role['parent'] == $a_node['child'])) {
                $this->logger->debug('Update local role_permissions');
                $this->updateTemplatePermissions($a_node, $role);
            }
            $this->logger->debug('Update object permissions');
            $this->updateObjectPermissions($a_node, $role);
        }
        return;
    }

    public function getActionType() : int
    {
        return $this->action_type;
    }

    public function setActionType(int $a_type)
    {
        $this->action_type = $a_type;
    }

    private function updateAvailability(array $node) : bool
    {
        $this->logger->dump($node);

        // creates default entry
        $item = ilObjectActivation::getItem($node['child']);

        $this->logger->dump($item);

        if ($this->resetTimingsEnabled() == true) {
            $activation = new ilObjectActivation();
            $activation->setTimingType(ilObjectActivation::TIMINGS_DEACTIVATED);
            $activation->update($node['child']);
            return true;
        }

        $activation = new ilObjectActivation();
        $activation->setTimingType(ilObjectActivation::TIMINGS_ACTIVATION);
        $activation->setTimingStart($this->getTimingStart());
        $activation->setTimingEnd($this->getTimingEnd());
        $activation->toggleVisible($this->getTimingVisibility());
        $activation->update($node['child']);
        return true;
    }

    public function resetTimingsEnabled() : bool
    {
        return ($this->reset_timings ?? false);
    }

    public function getTimingStart() : int
    {
        return ($this->timing_start ?? time());
    }

    public function setTimingStart(int $a_start)
    {
        $this->timing_start = $a_start;
    }

    public function getTimingEnd() : int
    {
        return ($this->timing_end ?? time());
    }

    public function setTimingEnd(int $a_end)
    {
        $this->timing_end = $a_end;
    }

    public function getTimingVisibility() : int
    {
        return ($this->timing_visibility ?? 0);
    }

    public function setTimingVisibility(int $a_stat)
    {
        $this->timing_visibility = $a_stat;
    }


    private function applyRoleFilter(array $a_node) : array
    {
        global $DIC;

        $rbacreview = $DIC->rbac()->review();

        $valid_roles = array();
        foreach ($rbacreview->getParentRoleIds($a_node['child'], $this->getChangeRoleTemplates()) as $role) {
            foreach ($this->getRoleFilter() as $filter) {
                $filter = trim($filter);
                $role_title = trim($role['title']);

                if (!strlen($filter)) {
                    $this->logger->debug('Empty filter given');
                    continue;
                }
                if (preg_match('/' . $filter . '/', $role_title) === 1) {
                    $this->logger->debug('Filter ' . $filter . ' matches ' . $role_title);
                    $valid_roles[] = $role;
                } else {
                    $this->logger->debug('Filter ' . $filter . ' does not match ' . $role_title);
                }
            }
        }
        return $valid_roles;
    }

    public function getChangeRoleTemplates() : bool
    {
        return $this->change_role_templates;
    }

    public function setChangeRoleTemplates(bool $a_stat)
    {
        $this->change_role_templates = $a_stat;
    }

    public function getRoleFilter() : array
    {
        return $this->role_filter;
    }

    public function setRoleFilter(array $a_filter)
    {
        $this->role_filter = $a_filter;
    }

    private function updateTemplatePermissions(array $node, array $role)
    {
        global $DIC;

        $rbacadmin = $DIC->rbac()->admin();

        if ($this->getAction() == self::ACTION_ADD) {
            $this->logger->debug('Action add permissions');
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
            $this->logger->debug('Action remove permissions');
            $rbacadmin->copyRolePermissionSubtract(
                $this->getTemplate(),
                ROLE_FOLDER_ID,
                $role['obj_id'],
                $role['parent']
            );
        }
    }

    public function getAction() : int
    {
        return $this->action;
    }

    public function setAction(int $a_action)
    {
        $this->action = $a_action;
    }

    public function getTemplate() : int
    {
        return $this->template_id;
    }

    private function updateObjectPermissions(array $node, array $role) : void
    {
        global $DIC;

        $rbacreview = $DIC->rbac()->review();
        $rbacadmin = $DIC->rbac()->admin();

        $operations = $rbacreview->getOperationsOfRole($this->getTemplate(), $node['type'], ROLE_FOLDER_ID);
        $this->logger->debug('Operations for type ' . $node['type']);
        $this->logger->dump($operations, ilLogLevel::DEBUG);

        $active = $rbacreview->getActiveOperationsOfRole($node['child'], $role['obj_id']);
        $this->logger->debug('Active operations for ' . $node['title']);
        $this->logger->dump($active, ilLogLevel::DEBUG);

        $new_permissions = array();
        if ($this->getAction() == self::ACTION_ADD) {
            $new_permissions = array_unique(array_merge($operations, $active));
        }
        if ($this->getAction() == self::ACTION_REMOVE) {
            $new_permissions = array_diff($active, $operations);
        }

        $this->logger->debug('New operations for ' . $node['title']);
        $this->logger->dump($new_permissions, ilLogLevel::DEBUG);

        $rbacadmin->revokePermission($node['child'], $role['obj_id']);
        $rbacadmin->grantPermission($role['obj_id'], (array) $new_permissions, $node['child']);
    }

    private function updateContainer(array $a_node)
    {
        return;

        if (!$this->getChangeRoleTemplates()) {
            $this->logger->debug('Update container of type: ' . $a_node['type'] . '(' . $a_node['title'] . ')');
            $this->logger->debug('No template updates required');
            return;
        }

        // get roles by filter
        foreach ($this->applyRoleFilter($a_node) as $role) {
            $this->logger->debug('Applying new permission to role templates');
        }
    }

    public function getRepositoryNode() : int
    {
        return $this->rep_node;
    }

    /**
     * Start permission manipulation
     */
    public function start() : array
    {
        $this->tree->useCache(false);
        $info_by_type = array();
        foreach ($this->getTypeFilter() as $type) {
            $info_by_type[$type]['num'] = 0;
        }
        $this->logger->dump($info_by_type, ilLogLevel::DEBUG);

        // walk through repository tree
        $this->walkThroughTree($this->tree->getNodeData($this->getRepositoryNode()), self::MODE_UPDATE, $info_by_type);

        return $info_by_type;
    }
}
