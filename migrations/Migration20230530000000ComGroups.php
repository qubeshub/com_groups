<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

use Hubzero\Content\Migration\Base;

// No direct access
defined('_HZEXEC_') or die();

/**
 * Migration script to add welcome message fields to group description block
 **/
class Migration20230530000000ComGroups extends Base
{	
	/**
	 * Up
	 **/
	public function up()
	{
        // Create focus areas object table
        if (!$this->db->tableExists('#__xgroups_groups'))
        {
            $query = "CREATE TABLE IF NOT EXISTS `#__xgroups_groups` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `child` int(11) unsigned NOT NULL DEFAULT '0',
                `parent` int(11) unsigned NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_child_parent` (`child`,`parent`)
              ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
            $this->db->setQuery($query);
			if ($this->db->query()) {
                $this->log("Successfully created jos_xgroups_groups table", "success");
            } else {
                $this->log("Unable to create jos_xgroups_groups table", "failure");
            }
        }
	}

	/**
	 * Down
	 **/
	public function down()
	{
        if ($this->db->tableExists('#__xgroups_groups'))
		{
            $query = "DROP TABLE `#__xgroups_groups`";
			$this->db->setQuery($query);
			if ($this->db->query()) {
                $this->log("Successfully dropped jos_xgroups_groups table", "success");
            } else {
                $this->log("Unable to drop jos_xgroups_groups table", "failure");
            }
		}
	}
}