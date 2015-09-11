<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 HUBzero Foundation, LLC.
 *
 * This file is part of: The HUBzero(R) Platform for Scientific Collaboration
 *
 * The HUBzero(R) Platform for Scientific Collaboration (HUBzero) is free
 * software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * HUBzero is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @author    Christopher Smoak <csmoak@purdue.edu>
 * @copyright Copyright 2005-2015 HUBzero Foundation, LLC.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

namespace Components\Groups\Site\Controllers;

use Hubzero\User\Group;
use Components\Groups\Models\Page;
use Request;
use Route;
use User;
use Lang;
use App;

/**
 * Groups controller class
 */
class Categories extends Base
{
	/**
	 * Override Execute Method
	 *
	 * @return 	void
	 */
	public function execute()
	{
		//get the cname, active tab, and action for plugins
		$this->cn     = Request::getVar('cn', '');
		$this->active = Request::getVar('active', '');
		$this->action = Request::getVar('action', '');

		// Check if they're logged in
		if (User::isGuest())
		{
			$this->loginTask(Lang::txt('COM_GROUPS_ERROR_MUST_BE_LOGGED_IN'));
			return;
		}

		//check to make sure we have  cname
		if (!$this->cn)
		{
			$this->_errorHandler(400, Lang::txt('COM_GROUPS_ERROR_NO_ID'));
		}

		// Load the group page
		$this->group = Group::getInstance($this->cn);

		// Ensure we found the group info
		if (!$this->group || !$this->group->get('gidNumber'))
		{
			$this->_errorHandler(404, Lang::txt('COM_GROUPS_ERROR_NOT_FOUND'));
		}

		// Check authorization
		if ($this->_authorize() != 'manager' && !$this->_authorizedForTask('group.pages'))
		{
			$this->_errorHandler(403, Lang::txt('COM_GROUPS_ERROR_NOT_AUTH'));
		}

		//continue with parent execute method
		parent::execute();
	}

	/**
	 * Display Page Categories
	 *
	 * @return void
	 */
	public function displayTask()
	{
		App::redirect(Route::url('index.php?option=com_groups&cn='.$this->group->get('cn').'&controller=pages#categories'));
	}

	/**
	 * Add Page Category
	 *
	 * @return void
	 */
	public function addTask()
	{
		$this->editTask();
	}

	/**
	 * Edit Page Category
	 *
	 * @return void
	 */
	public function editTask()
	{
		//set to edit layout
		$this->view->setLayout('edit');

		// get request vars
		$categoryid = Request::getInt('categoryid', 0);

		// get the category object
		$this->view->category = new Page\Category($categoryid);

		// are we passing a category object
		if ($this->category)
		{
			$this->view->category = $this->category;
		}

		// build the title
		$this->_buildTitle();

		// build pathway
		$this->_buildPathway();

		// get view notifications
		$this->view->notifications = ($this->getNotifications()) ? $this->getNotifications() : array();
		$this->view->group = $this->group;

		//display layout
		$this->view->display();
	}

	/**
	 * Save Page Category
	 *
	 * @return void
	 */
	public function saveTask()
	{
		// get request vars
		$category = Request::getVar('category', array(), 'post');

		// add group id to category
		$category['gidNumber'] = $this->group->get('gidNumber');

		// load category object
		$this->category = new Page\Category($category['id']);

		// bind to our new results
		if (!$this->category->bind($category))
		{
			$this->setNotification($this->category->getError(), 'error');
			$this->editTask();
			return;
		}

		// Store new content
		if (!$this->category->store(true))
		{
			$this->setNotification($this->category->getError(), 'error');
			$this->editTask();
			return;
		}

		//inform user & redirect
		$this->setNotification(Lang::txt('COM_GROUPS_PAGES_CATEGORY_SAVED'), 'passed');
		App::redirect(Route::url('index.php?option=' . $this->_option . '&cn=' . $this->group->get('cn') . '&controller=pages#categories'));
	}

	/**
	 * Delete Page Category
	 *
	 * @return void
	 */
	public function deleteTask()
	{
		// get request vars
		$categoryid = Request::getInt('categoryid', 0);

		// load category object
		$category = new Page\Category($categoryid);

		// make sure this is our groups cat
		if ($category->get('gidNumber') != $this->group->get('gidNumber'))
		{
			$this->setNotification(Lang::txt('COM_GROUPS_PAGES_CATEGORY_DELETE_ERROR'), 'error');
			App::redirect(Route::url('index.php?option=' . $this->_option . '&cn=' . $this->group->get('cn') . '&controller=pages#categories'));
			return;
		}

		// delete row
		if (!$category->delete())
		{
			$this->setNotification($category->getError(), 'error');
			App::redirect(Route::url('index.php?option=' . $this->_option . '&cn=' . $this->group->get('cn') . '&controller=pages#categories'));
			return;
		}

		//inform user & redirect
		$this->setNotification(Lang::txt('COM_GROUPS_PAGES_CATEGORY_DELETED'), 'passed');
		App::redirect(Route::url('index.php?option=' . $this->_option . '&cn=' . $this->group->get('cn') . '&controller=pages#categories'));
	}
}