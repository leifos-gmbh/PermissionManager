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
			self::ADV_TYPE_OUTSIDE_GROUPS => ilPermissionManagerAction::getInstance()->txt('adv_outside_groups'),
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
	
	private function walkThroughTree($a_node, $a_mode, &$info_by_type)
	{
		if($this->isHandled($a_node))
		{
			if($a_mode == self::MODE_SUMMARY)
			{
				$info_by_type[$a_node['type']]['num']++;
			}
			else
			{
				$this->updateNode($a_node);
			}
		}
		
		foreach($GLOBALS['tree']->getChilds($a_node['child']) as $child)
		{
			if($child['type'] == 'adm')
			{
				continue;
			}
			
			if(!$GLOBALS['objDefinition']->isContainer($child['type']))
			{
				if($this->isHandled($child))
				{
					if($a_mode == self::MODE_SUMMARY)
					{
						$info_by_type[$child['type']]['num']++;
					}
					else
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
	
	private function updateNode($a_node)
	{
		
	}
	
	private function isHandled($a_node)
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