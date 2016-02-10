<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 HUBzero Foundation, LLC.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @author    Shawn Rice <zooley@purdue.edu>
 * @copyright Copyright 2005-2015 HUBzero Foundation, LLC.
 * @license   http://opensource.org/licenses/MIT MIT
 */

namespace Components\Groups\Admin\Controllers;

use Hubzero\Component\AdminController;
use Hubzero\User\Group;
use Components\Groups\Models\Role;
use Request;
use Config;
use Route;
use Lang;
use User;
use App;

require_once(dirname(dirname(__DIR__)) . DS . 'models' . DS . 'role.php');

/**
 * Groups controller class for managing membership roles
 */
class Roles extends AdminController
{
	/**
	 * Determine task and execute it
	 *
	 * @return  void
	 */
	public function execute()
	{
		if (!User::authorize('core.manage', $this->_option))
		{
			App::redirect(
				Route::url('index.php?option=' . $this->_option, false)
			);
			return;
		}

		$this->registerTask('add', 'edit');
		$this->registerTask('apply', 'save');

		parent::execute();
	}

	/**
	 * Displays a list of groups
	 *
	 * @return	void
	 */
	public function displayTask()
	{
		// Incoming
		$filters = array(
			'gid' => Request::getState(
				$this->_option . '.' . $this->_controller . '.gid',
				'gid',
				''
			),
			'search' => urldecode(Request::getState(
				$this->_option . '.' . $this->_controller . '.search',
				'search',
				''
			)),
			// Sorting options
			'sort' => Request::getState(
				$this->_option . '.' . $this->_controller . '.sort',
				'filter_order',
				'name'
			),
			'sort_Dir' => Request::getState(
				$this->_option . '.' . $this->_controller . '.sortdir',
				'filter_order_Dir',
				'ASC'
			),
			// Filters for returning results
			'limit' => Request::getState(
				$this->_option . '.' . $this->_controller . '.limit',
				'limit',
				Config::get('list_limit'),
				'int'
			),
			'start' => Request::getState(
				$this->_option . '.' . $this->_controller . '.limitstart',
				'limitstart',
				0,
				'int'
			)
		);

		// Ensure we have a group ID
		if (!$filters['gid'])
		{
			App::redirect(
				Route::url('index.php?option=' . $this->_option, false),
				Lang::txt('COM_GROUPS_MISSING_ID'),
				'error'
			);
			return;
		}

		// Load the group page
		$group = new Group();
		$group->read($filters['gid']);

		$filters['gidNumber'] = $group->get('gidNumber');

		$rows = Role::all()
			->whereEquals('gidNumber', $filters['gidNumber'])
			->ordered()
			->paginated()
			->rows();

		// Output the HTML
		$this->view
			->set('filters', $filters)
			->set('group', $group)
			->set('rows', $rows)
			->display();
	}

	/**
	 * Edit an entry
	 *
	 * @param   object  $model
	 * @return  void
	 */
	public function editTask($model=NULL)
	{
		Request::setVar('hidemainmenu', 1);

		// Load a tag object if one doesn't already exist
		if (!is_object($model))
		{
			// Incoming
			$id = Request::getVar('id', array(0));
			if (is_array($id) && !empty($id))
			{
				$id = $id[0];
			}

			$model = Role::oneOrNew(intval($id));
		}

		$gid = Request::getVar('gid', '');

		$group = new Group();
		$group->read($gid);

		// Output the HTML
		$this->view
			->set('model', $model)
			->set('group', $group)
			->setLayout('edit')
			->display();
	}

	/**
	 * Save an entry
	 *
	 * @return  void
	 */
	public function saveTask()
	{
		// Check for request forgeries
		Request::checkToken();

		$fields = Request::getVar('fields', array(), 'post');

		$row = Role::oneOrNew(intval($fields['id']))->set($fields);

		$permissions = new \Hubzero\Config\Registry($fields['permissions']);

		$row->set('permissions', $permissions->toString()); //json_encode($fields['permissions']));

		// Store new content
		if (!$row->save())
		{
			Notify::error($row->getError());
			return $this->editTask($row);
		}

		Notify::success(Lang::txt('COM_GROUPS_ROLE_SAVED'));

		// Redirect to main listing
		if ($this->getTask() == 'apply')
		{
			return $this->editTask($row);
		}

		$tmpl = Request::getVar('tmpl', '');

		App::redirect(
			Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller . '&gid=' . Request::getVar('gid', '') . ($tmpl ? '&tmpl=' . $tmpl : ''), false)
		);
	}

	/**
	 * Remove one or more entries
	 *
	 * @return  void
	 */
	public function removeTask()
	{
		// Check for request forgeries
		Request::checkToken();

		$gid = Request::getVar('gid', '');
		$ids = Request::getVar('id', array());
		$ids = (!is_array($ids) ? array($ids) : $ids);
		$tmpl = Request::getVar('tmpl', '');

		// Make sure we have an ID
		if (empty($ids))
		{
			App::redirect(
				Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller . '&gid=' . $gid . ($tmpl ? '&tmpl=' . $tmpl : ''), false),
				Lang::txt('COM_GROUPS_ERROR_NO_ITEMS_SELECTED'),
				'error'
			);
			return;
		}

		foreach ($ids as $id)
		{
			// Remove the entry
			$model = Role::oneOrFail(intval($id));
			$model->destroy();
		}

		App::redirect(
			Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller . '&gid=' . $gid . ($tmpl ? '&tmpl=' . $tmpl : ''), false),
			Lang::txt('COM_GROUPS_ROLE_REMOVED')
		);
	}

	/**
	 * Assign members to a role
	 *
	 * @return  void
	 */
	public function assignTask()
	{
		Request::setVar('hidemainmenu', 1);

		$ids = Request::getVar('id', array());
		$gid = Request::getVar('gid', '');

		if (!$gid)
		{
			App::redirect(
				Route::url('index.php?option=' . $this->_option, false),
				Lang::txt('COM_GROUPS_MISSING_ID'),
				'error'
			);
			return;
		}

		$group = new Group();
		$group->read($gid);

		$rows = Role::all()
			->whereEquals('gidNumber', $group->get('gidNumber'))
			->rows();

		// Output the HTML
		$this->view
			->set('rows', $rows)
			->set('group', $group)
			->set('ids', $ids)
			->setLayout('assign')
			->display();
	}

	/**
	 * Assign members to a role
	 *
	 * @return  void
	 */
	public function delegateTask()
	{
		Request::setVar('hidemainmenu', 1);

		$ids = Request::getVar('id', array());
		$gid = Request::getVar('gid', '');
		$roleid = Request::getInt('roleid', 0);

		if (!$gid)
		{
			App::redirect(
				Route::url('index.php?option=' . $this->_option, false),
				Lang::txt('COM_GROUPS_MISSING_ID'),
				'error'
			);
			return;
		}

		$group = new Group();
		$group->read($gid);

		foreach ($ids as $id)
		{
			$model = \Components\Groups\Models\Member\Role::blank();
			$model->set('roleid', $roleid);
			$model->set('uidNumber', (int)$id);
			$model->save();
		}

		$tmpl = Request::getVar('tmpl', '');

		App::redirect(
			Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller . '&gid=' . $gid . ($tmpl ? '&tmpl=' . $tmpl : ''), false)
		);
	}

	/**
	 * Cancel task
	 *
	 * @return  void
	 */
	public function cancelTask()
	{
		$gid  = Request::getVar('gid', '');
		$tmpl = Request::getVar('tmpl', '');
		App::redirect(
			Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller . '&gid=' . $gid . ($tmpl ? '&tmpl=' . $tmpl : ''), false)
		);
	}
}
