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
class Migration20220312000000ComGroups extends Base
{	
    public static $show_welcome_message = 'show_welcome_message';
    public static $welcome_message = 'welcome_message';
	/**
	 * Up
	 **/
	public function up()
	{
        if ($this->db->tableExists('#__xgroups_description_fields') &&
            $this->db->tableExists('#__xgroups_description_options') &&
            $this->db->tableExists('#__xgroups_description_answers'))		
        {
            $this->db->setQuery("SELECT id FROM `#__xgroups_description_fields` WHERE `name` = " . $this->db->quote(self::$show_welcome_message));
            if (!$this->db->loadResult()) {
                // Create show_welcome_message field
                $query = "INSERT INTO `jos_xgroups_description_fields` (`type`, `name`, `label`, `ordering`, `disabled`) VALUES 
                ('checkboxes', " . $this->db->quote(self::$show_welcome_message) . ", 'Welcome Message Settings', 3, 1);";

                $this->db->setQuery($query);
                $this->db->query();

                // Get new field id
                $this->db->setQuery("SELECT id FROM `#__xgroups_description_fields` WHERE `name` = " . $this->db->quote(self::$show_welcome_message));
                $field_id = $this->db->loadResult();
                if ($field_id) {
                    $this->log('Created field ' . self::$show_welcome_message, 'success');

                    // Add option for new field
                    // Just in case, remove possible existing rows
                    $this->db->setQuery("DELETE FROM `#__xgroups_description_options` WHERE `field_id` = $field_id");
                    $this->db->query();

                    // Add option for show_welcome_message
                    $query = "INSERT INTO `jos_xgroups_description_options` (`field_id`, `value`, `label`, `ordering`, `checked`, `dependents`) VALUES 
                    ($field_id, 1, 'Show welcome message to new members', 1, 0, " . $this->db->quote('["' . self::$welcome_message . '"]') . ");";
                    $this->db->setQuery($query);
                    if ($this->db->query()) {
                        $this->log('Created option for field ' . self::$show_welcome_message, 'success');
                    } else {
                        $this->log('Unable to create option for ' . self::$show_welcome_message . '. Aborting...', 'failure');
                        return;
                    }
                } else {
                    $this->log('Unable to create field ' . self::$show_welcome_message . '. Aborting...', 'error');
                    return;
                }
            } else {
                $this->log('Field ' . self::$show_welcome_message . ' already exists.', 'warning');
            }

            $this->db->setQuery("SELECT id FROM `#__xgroups_description_fields` WHERE `name` = " . $this->db->quote(self::$welcome_message));
            if (!$this->db->loadResult()) {
                // Create show_welcome_message field
                $query = "INSERT INTO `jos_xgroups_description_fields` (`type`, `name`, `label`, `ordering`, `disabled`) VALUES 
                ('textarea', " . $this->db->quote(self::$welcome_message) . ", 'Welcome message', 4, 1);";

                $this->db->setQuery($query);
                if ($this->db->query()) {
                    $this->log('Created field ' . self::$welcome_message, 'success');
                } else {
                    $this->log('Unable to create field ' . self::$welcome_message . '. Aborting...', 'error');
                    return;
                }
            } else {
                $this->log('Field ' . self::$welcome_message . ' already exists.', 'warning');
            }
		}
	}

	/**
	 * Down
	 **/
	public function down()
	{
        $fields = array(self::$show_welcome_message, self::$welcome_message);
        if ($this->db->tableExists('#__xgroups_description_fields') &&
            $this->db->tableExists('#__xgroups_description_options') &&
            $this->db->tableExists('#__xgroups_description_answers'))
		{
            foreach ($fields as $name) {
                $this->db->setQuery("SELECT id FROM `#__xgroups_description_fields` WHERE `name` = " . $this->db->quote($name));
                $field_id = $this->db->loadResult();
                if ($field_id) {
                    // Delete answers
                    $this->db->setQuery("DELETE FROM `#__xgroups_description_answers` WHERE `field_id` = " . $field_id);
                    if ($this->db->query()) {
                        $this->log("Successfully deleted answers for " . $name, 'success');
                    } else {
                        $this->log("Failed to delete answers for " . $name, 'warning');
                    }

                    // Delete options
                    if ($name == self::$show_welcome_message) {
                        $this->db->setQuery("DELETE FROM `#__xgroups_description_options` WHERE `field_id` = " . $field_id);
                        if ($this->db->query()) {
                            $this->log("Successfully deleted option for " . $name, 'success');
                        } else {
                            $this->log("Failed to delete option for " . $name, 'warning');
                        }
                    }

                    // Delete field
                    $this->db->setQuery("DELETE FROM `#__xgroups_description_fields` WHERE `id` = " . $field_id);
                    if ($this->db->query()) {
                        $this->log("Successfully deleted field " . $name, 'success');
                    } else {
                        $this->log("Failed to delete field " . $name, 'warning');
                    }
                } else {
                    $this->log('Field ' . $name . ' does not exist.');
                }
            }
        }
	}
}
