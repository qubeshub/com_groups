<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Groups\Models;

use Hubzero\Database\Relational;
use Components\Groups\Models\Orm\Field;

/**
 * Recently visited groups
 */
class Recent extends Relational
{
	/**
	 * The table namespace
	 *
	 * @var  string
	 */
	protected $namespace = 'xgroups';

	/**
	 * Default order by for model
	 *
	 * @var  string
	 */
	public $orderBy = 'created';

	/**
	 * Default order direction for select queries
	 *
	 * @var  string
	 */
	public $orderDir = 'desc';

	/**
	 * Fields and their validation criteria
	 *
	 * @var  array
	 */
	protected $rules = array(
		'user_id'  => 'nonzero|positive',
		'group_id' => 'nonzero|positive'
	);

	/**
	 * Automatic fields to populate every time a row is created
	 *
	 * @var  array
	 */
	public $initiate = array(
		'created'
	);

	/**
	 * Retrieves one row loaded by user ID and group ID combo
	 *
	 * @param   integer  $user_id
	 * @param   integer  $group_id
	 * @return  object
	 */
	public static function oneByUserAndGroup($user_id, $group_id)
	{
		return self::blank()
			->whereEquals('user_id', $user_id)
			->whereEquals('group_id', $group_id)
			->row();
	}

	/**
	 * Update or create and entry and set the timestamp to 'now'
	 *
	 * @param   integer  $user_id
	 * @param   integer  $group_id
	 * @return  void
	 */
	public static function hit($user_id, $group_id)
	{
		$recent = self::oneByUserAndGroup($user_id, $group_id);
		$recent->set('user_id', $user_id);
		$recent->set('group_id', $group_id);
		$recent->set('created', Date::of('now')->toSql());
		$recent->save();
	}

	/**
	 * Remove records by user ID and group ID combo
	 *
	 * @param   integer  $user_id
	 * @param	integer	 $group_id
	 * @return  boolean  False if error, True on success
	 */
	public static function deleteRecent($user_id, $group_id)
	{
		$row = self::all()
			->whereEquals('user_id', $user_id)
			->whereEquals('group_id', $group_id)
			->row();

		if (!$row->destroy())
		{
			return false;
		}

		return true;
	}

	/**
	 * Remove records by user ID and group ID combo
	 *
	 * @param	Object	 $group
	 * @return  boolean  False if error, True on success
	 */
	public static function memberCheckIn($user_id, $group_id)
	{
		$welcomeMessage = false;
		$showWelcomeMessage = Field::oneByName('show_welcome_message')->collectGroupAnswers($group_id);
		if ($showWelcomeMessage && count(self::oneByUserAndGroup($user_id, $group_id)->toArray()) == 0) {
			$welcomeMessage = Field::oneByName('welcome_message')->collectGroupAnswers($group_id);
		}
		self::hit($user_id, $group_id);

		return $welcomeMessage;
	}
}
