<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilPermissionManagerAction
 *
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
	
	const ACTION_ADD = 1;
	const ACTION_REMOVE = 2;
	
	private $rep_node = 0;
	private $type_filter = array();
	private $advanced_type_filter = 0;
	private $template_id = 0;
	private $action = 0;
	private $change_role_templates = false;
	private $role_filter = array();
	
	
	
	public function setRepositoryNode($a_node)
	{
		$this->rep_node = $a_node;
	}
	
	public function getRepositoryNode()
	{
		return $this->rep_node;
	}
	
	public function setTypeFilter($a_filter)
	{
		$this->type_filter = $a_filter;
	}
	
	public function getTypeFilter()
	{
		return $this->type_filter;
	}
	
	public function setAdvancedTypeFilter($a_adv_filter)
	{
		$this->advanced_type_filter = $a_adv_filter;
	}
	
	public function getAdvancedTypeFilter()
	{
		return $this->advanced_type_filter;
	}
	
	public function setAction($a_action)
	{
		$this->action = $a_action;
	}
	
	public function getAction()
	{
		return $this->action;
	}
	
	public function setTemplate($a_template_id)
	{
		$this->template_id = $a_template_id;
	}
	
	public function getTemplate()
	{
		return $this->template_id;
	}
	
	public function setChangeRoleTemplates($a_stat)
	{
		$this->change_role_templates = $a_stat;
	}
	
	public function getChangeRoleTemplates()
	{
		return $this->change_role_templates;
	}
	
	public function setRoleFilter($a_filter)
	{
		$this->role_filter = $a_filter;
	}
	
	public function getRoleFilter()
	{
		return $this->role_filter;
	}
	
	public static function getAdvancedTypeFilterOptions()
	{
		return array(
			self::ADV_TYPE_NONE => $GLOBALS['lng']->txt('select_one'),
			self::ADV_TYPE_IN_COURSES => ilPermissionManagerPlugin::getInstance()->txt('adv_in_courses'),
			self::ADV_TYPE_IN_GROUPS => ilPermissionManagerPlugin::getInstance()->txt('adv_in_groups'),
			self::ADV_TYPE_OUTSIDE_COURSES => ilPermissionManagerPlugin::getInstance()->txt('adv_outside_courses'),
			self::ADV_TYPE_OUTSIDE_GROUPS => ilPermissionManagerPlugin::getInstance()->txt('adv_outside_groups'),
			self::ADV_TYPE_OUTSIDE_COURSE_AND_GROUPS => ilPermissionManagerPlugin::getInstance()->txt('adv_outside_courses_groups')
		);
	}
	
	public static function getTemplateOptions()
	{
		global $ilDB;
		
		$query = 'SELECT obj_id, title FROM object_data WHERE type = '.$ilDB->quote('rolt','text').' ORDER BY title';
		$res = $ilDB->query($query);
		
		$options[0] =  $GLOBALS['lng']->txt('select_one');
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$options[$row->obj_id] = $row->title; 
		}
		return $options;
	}
	
	public function __sleep()
	{
		return array('rep_node', 'type_filter', 'advanced_type_filter', 'template_id', 'action', 'change_role_templates', 'role_filter');
	}
	
	public function doSummary()
	{
		$GLOBALS['tree']->useCache(false);
		
		$info_by_type = array();
		// initializte $info array();
		foreach($this->getTypeFilter() as $type)
		{
			$info_by_type[$type]['num'] = 0;
		}
		ilLoggerFactory::getLogger('lfpm')->dump($info_by_type, ilLogLevel::DEBUG);
		
		// walk through repository tree
		$this->walkThroughTree($GLOBALS['tree']->getNodeData($this->getRepositoryNode()), self::MODE_SUMMARY, $info_by_type);
		
		return $info_by_type;
	}
	
	/**
	 * Start permission manipulation
	 */
	public function start()
	{
		$GLOBALS['tree']->useCache(false);
		foreach($this->getTypeFilter() as $type)
		{
			$info_by_type[$type]['num'] = 0;
		}
		ilLoggerFactory::getLogger('lfpm')->dump($info_by_type, ilLogLevel::DEBUG);
		
		// walk through repository tree
		$this->walkThroughTree($GLOBALS['tree']->getNodeData($this->getRepositoryNode()), self::MODE_UPDATE, $info_by_type);
		
		return $info_by_type;
		
	}
	
	private function walkThroughTree($a_node, $a_mode, &$info_by_type)
	{
		$is_handled_type = $this->isHandledType($a_node);
		if($is_handled_type)
		{
			$info_by_type[$a_node['type']]['num']++;
			if($a_mode == self::MODE_UPDATE)
			{
				$this->updateNode($a_node);
			}
		}
		elseif(
			$a_mode == self::MODE_UPDATE &&
			$GLOBALS['objDefinition']->isContainer($a_node['type'])
		)
		{
			$this->updateContainer($a_node);
		}
		
		foreach($GLOBALS['tree']->getChilds($a_node['child']) as $child)
		{
			if($child['type'] == 'adm')
			{
				continue;
			}

			if(!$GLOBALS['objDefinition']->isContainer($child['type']))
			{
				$is_handled_type = $this->isHandledType($child);
				if($is_handled_type)
				{
					$info_by_type[$a_node['type']]['num']++;
					if($a_mode == self::MODE_UPDATE)
					{
						$this->updateNode($child);
					}
				}
			}
			if($GLOBALS['objDefinition']->isContainer($child['type']))
			{
				$this->walkThroughTree($child, $a_mode, $info_by_type);
			}
		}
	}
	
	/**
	 * Update node
	 * @param type $a_node
	 */
	private function updateNode(array $a_node)
	{
		ilLoggerFactory::getLogger('lfpm')->debug('Update node of type: ' . $a_node['type']. '('.$a_node['title'].')');
		
		foreach($this->applyRoleFilter($a_node) as $role)
		{
			ilLoggerFactory::getLogger('lfpm')->dump($a_node,  ilLogLevel::DEBUG);
			ilLoggerFactory::getLogger('lfpm')->dump($role,  ilLogLevel::DEBUG);
			ilLoggerFactory::getLogger('lfpm')->debug('Applying new permission to role templates');
			if($this->getChangeRoleTemplates() && ($role['parent'] == $a_node['child']))
			{
				ilLoggerFactory::getLogger('lfpm')->info('Update local role_permissions');
				$this->updateTemplatePermissions($a_node, $role);
			}
			ilLoggerFactory::getLogger('lfpm')->info('Update object permissions');
			$this->updateObjectPermissions($a_node, $role);
		}
		return;
	}
	
	/**
	 * Update template permissions
	 * @param array $node
	 * @param array $role
	 */
	private function updateTemplatePermissions(array $node, array $role)
	{
		global $rbacadmin;
		
		if($this->getAction() == self::ACTION_ADD)
		{
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
		if($this->getAction() == self::ACTION_REMOVE)
		{
			ilLoggerFactory::getLogger('lfpm')->debug('Action remove permissions');
			$rbacadmin->copyRolePermissionSubtract(
				$this->getTemplate(),
				ROLE_FOLDER_ID,
				$role['obj_id'],
				$role['parent']
			);
		}
	}
	
	/**
	 * Update object permissions
	 * @param array $node
	 * @param array $role
	 */
	private function updateObjectPermissions(array $node, array $role)
	{
		global $rbacreview, $rbacadmin;
		
		$operations = $rbacreview->getOperationsOfRole($this->getTemplate(),$node['type'],ROLE_FOLDER_ID);
		ilLoggerFactory::getLogger('lfpm')->debug('Operations for type '.$node['type']);
		ilLoggerFactory::getLogger('lfpm')->dump($operations, ilLogLevel::DEBUG);
		
		$active = $rbacreview->getActiveOperationsOfRole($node['child'], $role['obj_id']);
		ilLoggerFactory::getLogger('lfpm')->debug('Active operations for '.$node['title']);
		ilLoggerFactory::getLogger('lfpm')->dump($active, ilLogLevel::DEBUG);
		
		if($this->getAction() == self::ACTION_ADD)
		{
			$new_permissions = array_unique(array_merge($operations, $active));
		}
		if($this->getAction() == self::ACTION_REMOVE)
		{
			$new_permissions = array_diff($active, $operations);
		}
		
		ilLoggerFactory::getLogger('lfpm')->debug('New operations for '.$node['title']);
		ilLoggerFactory::getLogger('lfpm')->dump($new_permissions, ilLogLevel::DEBUG);
		
		$rbacadmin->revokePermission($node['child'], $role['obj_id']);
		$rbacadmin->grantPermission($role['obj_id'], (array) $new_permissions, $node['child']);
	}
	
	private function updateContainer(array $a_node)
	{
		return;
		
		if(!$this->getChangeRoleTemplates())
		{
			ilLoggerFactory::getLogger('lfpm')->debug('Update container of type: '. $a_node['type']. '('.$a_node['title'].')');
			ilLoggerFactory::getLogger('lfpm')->debug('No template updates required');
			return;
		}
		
		// get roles by filter
		foreach($this->applyRoleFilter($a_node) as $role)
		{
			ilLoggerFactory::getLogger('lfpm')->debug('Applying new permission to role templates');
		}
	}
	
	/**
	 * Apply role filter
	 * @param array $a_node
	 */
	private function applyRoleFilter(array $a_node)
	{
		global $rbacreview;
		
		$valid_roles = array();
		foreach($rbacreview->getParentRoleIds($a_node['child'], $this->getChangeRoleTemplates()) as $role)
		{
			#ilLoggerFactory::getLogger('lfpm')->dump($role, ilLogLevel::DEBUG);
			foreach($this->getRoleFilter() as $filter)
			{
				$filter = trim($filter);
				$role_title = trim($role['title']);
			
				if(!strlen($filter))
				{
					ilLoggerFactory::getLogger('lfpm')->debug('Empty filter given');
					continue;
				}
				if(preg_match('/'.$filter.'/', $role_title) === 1)
				{
					ilLoggerFactory::getLogger('lfpm')->info('Filter '. $filter . ' matches '. $role_title);
					$valid_roles[] = $role;
				}
				else
				{
					ilLoggerFactory::getLogger('lfpm')->info('Filter '. $filter . ' does not match '. $role_title);
				}
			}
		}
		return $valid_roles;
	}
	
	private function isHandledType($a_node)
	{
		// check type
		if(!in_array($a_node['type'], $this->getTypeFilter()))
		{
			return false;
		}
		switch($this->getAdvancedTypeFilter())
		{
			case self::ADV_TYPE_IN_COURSES:
				if(!$GLOBALS['tree']->checkForParentType($a_node['child'], 'crs', true))
				{
					return false;
				}
				break;
				
			case self::ADV_TYPE_IN_GROUPS:
				if(!$GLOBALS['tree']->checkForParentType($a_node['child'], 'grp', true))
				{
					return false;
				}
				break;
				
			case self::ADV_TYPE_OUTSIDE_COURSES:
				if($GLOBALS['tree']->checkForParentType($a_node['child'], 'crs', true))
				{
					return false;
				}
				break;
				
			case self::ADV_TYPE_OUTSIDE_GROUPS:
				if($GLOBALS['tree']->checkForParentType($a_node['child'], 'grp', true))
				{
					return false;
				}
				break;
				

			case self::ADV_TYPE_OUTSIDE_COURSE_AND_GROUPS:
				if(
					$GLOBALS['tree']->checkForParentType($a_node['child'], 'crs', true) or
					$GLOBALS['tree']->checkForParentType($a_node['child'], 'grp', true)
				)
				{
					return false;
				}
				break;
		}

		return true;
	}
}
?>