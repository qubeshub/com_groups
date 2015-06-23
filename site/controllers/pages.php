<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 Purdue University. All rights reserved.
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
 * @copyright Copyright 2005-2015 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

namespace Components\Groups\Site\Controllers;

use Hubzero\User\Group;
use Components\Groups\Models\Page;
use Components\Groups\Models\Module;
use Components\Groups\Helpers;
use Request;
use Route;
use User;
use Date;
use Lang;
use App;

/**
 * Groups controller class
 */
class Pages extends Base
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
	 * Display Group Pages
	 *
	 * @return 	void
	 */
	public function displayTask()
	{
		// check in for user
		Helpers\Pages::checkinForUser();

		// get group pages
		$pageArchive = new Page\Archive();
		$this->view->pages = $pageArchive->pages('tree', array(
			'gidNumber' => $this->group->get('gidNumber'),
			'state'     => array(0,1),
			'orderby'   => 'lft ASC'
		));

		// get page categories
		$categoryArchive = new Page\Category\Archive();
		$this->view->categories = $categoryArchive->categories('list', array(
			'gidNumber' => $this->group->get('gidNumber'),
			'orderby'   => 'title'
		));

		// get modules archive
		$moduleArchive = new Module\Archive();
		$this->view->modules = $moduleArchive->modules('list', array(
			'gidNumber' => $this->group->get('gidNumber'),
			'state'     => array(0,1),
			'orderby'   => 'position ASC, ordering ASC'
		));

		// get request vars
		$this->view->search = Request::getWord('search', '');
		$this->view->filter = Request::getInt('filer', 0);

		//build pathway
		$this->_buildPathway();

		//build title
		$this->_buildTitle();

		//set view vars
		$this->view->title  = Lang::txt('COM_GROUPS_PAGES_MANAGE') . ': ' . $this->group->get('description');

		// get view notifications
		$this->view->notifications = ($this->getNotifications()) ? $this->getNotifications() : array();
		$this->view->group         = $this->group;
		$this->view->config        = $this->config;

		//display
		$this->view->setLayout('manager');
		$this->view->display();
	}


	/**
	 * Add Group Page
	 *
	 * @return 	void
	 */
	public function addTask()
	{
		$this->editTask();
	}


	/**
	 * Edit Group Page
	 *
	 * @return 	void
	 */
	public function editTask()
	{
		//set to edit layout
		$this->view->setLayout('edit');

		//get request vars
		$pageid = Request::getInt('pageid', 0,'get');

		// load page object
		$this->view->page    = new Page($pageid);
		$this->view->version = $this->view->page->version();

		//are we adding or editing
		$new = ($this->view->page->get('id') === null && $pageid == null) ? true : false;

		// make sure page exists
		if (!$this->view->page->exists() && !$new)
		{
			App::abort(404, Lang::txt('COM_GROUPS_PAGES_PAGE_NOT_FOUND'));
		}

		// make sure page belongs to group - if editing
		if (!$this->view->page->belongsToGroup($this->group) && !$new)
		{
			App::abort(403, Lang::txt('COM_GROUPS_PAGES_PAGE_NOT_AUTH'));
		}

		// are we passing in a page from someplace else
		if ($this->page)
		{
			$this->view->page = $this->page;
		}
		if ($this->version)
		{
			$this->view->version = $this->version;
		}

		// checkout page
		Helpers\Pages::checkout($this->view->page->get('id'));

		// get a list of all pages for page ordering
		$pageArchive = Page\Archive::getInstance();
		$this->view->pages = $pageArchive->pages('list', array(
			'gidNumber' => $this->group->get('gidNumber'),
			'state'     => array(0,1),
			'orderby'   => 'lft'
		));

		// get page categories
		$categoryArchive = new Page\Category\Archive();
		$this->view->categories = $categoryArchive->categories('list', array(
			'gidNumber' => $this->group->get('gidNumber'),
			'orderby'   => 'title'
		));

		// get stylesheets for editor
		$this->view->stylesheets = Helpers\View::getPageCss($this->group);

		// get page templates
		$this->view->pageTemplates = Helpers\View::getPageTemplates($this->group);

		// build the title
		$this->_buildTitle();

		// build pathway
		$this->_buildPathway();

		// get view notifications
		$this->view->notifications = ($this->getNotifications()) ? $this->getNotifications() : array();
		$this->view->group         = $this->group;
		$this->view->config        = $this->config;

		//display layout
		$this->view->display();
	}

	/**
	 * Apply group page changes
	 *
	 * @return 	void
	 */
	public function applyTask()
	{
		// send to save task
		$this->saveTask(true);
	}

	/**
	 * Save group page
	 *
	 * @return 	void
	 */
	public function saveTask($apply = false)
	{
		// Get the page vars being posted
		$page    = Request::getVar('page', array(), 'post');
		$version = Request::getVar('pageversion', array(), 'post', 'none', JREQUEST_ALLOWRAW);

		// are we updating or creating a new page
		$task = ($page['id']) ? 'update' : 'create';

		// load page and version objects
		$this->page    = new Page($page['id']);
		$this->version = new Page\Version();

		// bind new page properties
		if (!$this->page->bind($page))
		{
			$this->setNotification($this->page->getError(), 'error');
			$this->editTask();
			return;
		}

		// bind new page version properties
		if (!$this->version->bind($version))
		{
			$this->setNotification($this->version->getError(), 'error');
			$this->editTask();
			return;
		}

		// make sure page belongs to group
		if ($task == 'update' && !$this->page->belongsToGroup($this->group))
		{
			App::abort(403, Lang::txt('COM_GROUPS_PAGES_PAGE_NOT_AUTH'));
		}

		// set page vars
		$this->page->set('gidNumber', $this->group->get('gidNumber'));

		// only get unique alias if not home page
		if ($this->page->get('home') == 0)
		{
			$this->page->set('alias', $this->page->uniqueAlias());
		}

		// update our depth
		$parent = $this->page->getParent();
		$depth  = ($parent->get('id')) ? $parent->get('depth') + 1 : 0;
		$this->page->set('depth', $depth);

		// make sure we can create both the page and version
		if (!$this->page->check() || !$this->version->check())
		{
			$error = ($this->page->getError()) ? $this->page->getError() : $this->version->getError();
			$this->setNotification($error, 'error');
			$this->editTask();
			return;
		}

		// our start should be our left (order) or the parents right - 1
		$start = $this->page->get('left');
		if (!$start)
		{
			$start = $parent->get('rgt') - 1;
		}

		// update current rights
		$sql = "UPDATE `#__xgroups_pages` SET rgt=rgt+2 WHERE rgt>".($start-1)." AND gidNumber=1053;";
		$this->database->setQuery($sql);
		$this->database->query();

		// update current lefts
		$sql2 = "UPDATE `#__xgroups_pages` SET lft=lft+2 WHERE lft>".($start-1)." AND gidNumber=1053;";
		$this->database->setQuery($sql2);
		$this->database->query();

		// set this pages left & right
		$this->page->set('lft', $start);
		$this->page->set('rgt', $start+1);

		// save page settings
		if (!$this->page->store(true))
		{
			$this->setNotification($this->page->getError(), 'error');
			$this->editTask();
			return;
		}

		if (!is_object($this->group->params))
		{
			$this->group->params = new \Hubzero\Config\Registry($this->group->params);
		}
		$this->version->set('page_trusted', $this->group->params->get('page_trusted', 0));

		// get currrent version #
		$currentVersionNumber = ($this->page->version()) ? $this->page->version()->get('version') : 0;

		// did the module content change?
		$contentChanged = false;
		$oldContent = ($this->page->version()) ? trim($this->page->version()->get('content')) : '';
		$newContent = (isset($version['content'])) ? trim($version['content']) : '';

		if (!$this->version->get('page_trusted', 0))
		{
			$newContent = Page\Version::purify($newContent, $this->group->isSuperGroup());
		}

		// is the new and old content different?
		if ($oldContent != $newContent)
		{
			$contentChanged = true;
		}

		// set page version vars
		$this->version->set('pageid', $this->page->get('id'));
		$this->version->set('version', $currentVersionNumber + 1);
		$this->version->set('created', Date::toSql());
		$this->version->set('created_by', User::get('id'));
		$this->version->set('approved', 1);
		$this->version->set('approved_on', Date::toSql());
		$this->version->set('approved_by', User::get('id'));

		// if we have php or script tags we must get page approved by admin
		// check the $newContent var since its already been purified
		// and has has php/script tags removed if not super group
		if (strpos($newContent, '<?') !== false ||
			strpos($newContent, '<?php') !== false ||
			strpos($newContent, '<script') !== false)
		{
			$this->version->set('approved', 0);
			$this->version->set('approved_on', NULL);
			$this->version->set('approved_by', NULL);
		}

		// only create a new version and send approve notif if content has changed
		if ($contentChanged)
		{
			// check version again (because were not on store() method)
			if (!$this->version->check())
			{
				$this->setNotification($this->version->getError(), 'error');
				$this->editTask();
				return;
			}

			// save version settings
			// dont run check on version store, skips onContentBeforeSave in Html format hadler
			if (!$this->version->store(false, $this->group->isSuperGroup()))
			{
				$this->setNotification($this->version->getError(), 'error');
				$this->editTask();
				return;
			}

			// send to approvers
			if ($this->version->get('approved', 0) == 0)
			{
				Helpers\Pages::sendApproveNotification('page', $this->page);
			}
		}

		// check page back in
		Helpers\Pages::checkin($this->page->get('id'));

		// redirect to return url
		if ($return = Request::getVar('return', '','post'))
		{
			$this->setNotification(Lang::txt('COM_GROUPS_PAGES_PAGE_SAVED', $task), 'passed');
			App::redirect(base64_decode($return));
			return;
		}

		// are we applying or saving?
		if ($apply)
		{
			$notification = Lang::txt('COM_GROUPS_PAGES_PAGE_SAVED_AND_LINK', $task, $this->page->url());
			$redirect = Route::url('index.php?option=' . $this->_option . '&cn=' . $this->group->get('cn') . '&controller=pages&task=edit&pageid=' . $this->page->get('id'));
		}
		else
		{
			$notification = Lang::txt('COM_GROUPS_PAGES_PAGE_SAVED', $task);
			$redirect = Route::url('index.php?option=com_groups&cn=' . $this->group->get('cn') . '&controller=pages');
		}

		// Push success message and redirect
		$this->setNotification($notification, 'passed');
		App::redirect($redirect);
	}

	/**
	 * Display page versions page
	 *
	 * @return 	void
	 */
	public function versionsTask()
	{
		include_once(PATH_CORE . DS . 'components' . DS . 'com_wiki' . DS . 'helpers' . DS . 'differenceengine.php');

		//set to edit layout
		$this->view->setLayout('versions');

		//get request vars
		$pageid = Request::getInt('pageid', 0,'get');

		// load page object
		$this->view->page = new Page($pageid);

		// make sure page exists
		if (!$this->view->page->exists())
		{
			App::abort(404, Lang::txt('COM_GROUPS_PAGES_PAGE_NOT_FOUND'));
		}

		// make sure page belongs to group - if editing
		if (!$this->view->page->belongsToGroup($this->group))
		{
			App::abort(403, Lang::txt('COM_GROUPS_PAGES_PAGE_NOT_AUTH'));
		}

		// build the title
		$this->_buildTitle();

		// build pathway
		$this->_buildPathway();

		// get view notifications
		$this->view->notifications = ($this->getNotifications()) ? $this->getNotifications() : array();
		$this->view->group         = $this->group;

		//display layout
		$this->view->display();
	}

	/**
	 * Publish Group Page
	 *
	 * @return 	void
	 */
	public function publishTask()
	{
		$this->setStateTask(1, 'published');
	}


	/**
	 * Unpublish Group Page
	 *
	 * @return 	void
	 */
	public function unpublishTask()
	{
		$this->setStateTask(0, 'unpubished');
	}


	/**
	 * Delete Group Page
	 *
	 * @return 	void
	 */
	public function deleteTask()
	{
		$this->setStateTask(2, 'deleted');
	}


	/**
	 * Set page state
	 *
	 * @return 	void
	 */
	public function setStateTask($state = 1, $status = 'published')
	{
		//get request vars
		$pageid = Request::getInt('pageid', 0, 'get');

		// load page model
		$page = new \Components\Groups\Models\Page($pageid);

		// make sure its out page
		if (!$page->belongsToGroup($this->group))
		{
			App::abort(403, Lang::txt('COM_GROUPS_PAGES_PAGE_NOT_AUTH'));
		}

		// make sure state is a valid state
		if (!in_array($state, array(0, 1, 2)))
		{
			$state = 1;
		}

		// set the page state
		$page->set('state', $state);

		// make sure the home page cant be deleted
		if ($page->get('home') == 1 && $page->get('state') != 1)
		{
			$page->set('state', 1);
		}

		// save
		if (!$page->store(false))
		{
			$this->setNotification($page->getError(), 'error');
			$this->displayTask();
			return;
		}

		// get page children
		$children = $page->getChildren();

		// if we are publishing/unpublishing
		if ($state == 0 || $state == 1)
		{
			// lets mark each child the same as parent
			foreach ($children as $child)
			{
				$child->set('state', $state);
				$child->store(false);
			}
		}

		// if deleting lets set the first childs parent
		// to be the deleted pages parents
		else if ($state == 2)
		{
			// update the first childs parent
			if ($firstChild = $children->first())
			{
				$firstChild->set('parent', $page->get('parent'));
				$firstChild->store(false);
			}

			// adjust depth foreach child
			// the proper depth is needed when viewing pages
			foreach ($children as $child)
			{
				$child->set('depth', $child->get('depth') - 1);
				$child->store(false);
			}
		}

		//inform user & redirect
		$this->setNotification(Lang::txt('COM_GROUPS_PAGES_PAGE_STATUS_CHANGE', $status), 'passed');
		App::redirect(Route::url('index.php?option=' . $this->_option . '&cn=' . $this->group->get('cn') . '&controller=pages'));
		if ($return = Request::getVar('return', '','get'))
		{
			App::redirect(base64_decode($return));
		}
	}


	/**
	 * Reorder Pages Task
	 *
	 * @return 	void
	 */
	public function reorderTask()
	{
		//get the request vars
		$pagesOrder = Request::getVar('order', array(), 'post');

		// update each page accordingly
		foreach ($pagesOrder as $pageOrder)
		{
			// must have id
			// dont add home page
			if (!$pageOrder['item_id'])
			{
				continue;
			}

			// update the pages parent, depth, left, right, and alias
			$page = new Page($pageOrder['item_id']);
			$page->set('parent', $pageOrder['parent_id']);
			$page->set('depth', ($pageOrder['depth'] - 1));
			$page->set('lft', $pageOrder['left']);
			$page->set('rgt', $pageOrder['right']);
			$page->set('alias', $page->uniqueAlias());
			$page->store(false);
		}

		//we successfully reordered
		echo json_encode(array('reordered'=>true));
	}


	/**
	 * Set Group Home Page
	 *
	 * @return 	void
	 */
	public function setHomeTask()
	{
		// get request vars
		$pageid = Request::getInt('pageid', 0, 'get');

		// load page model
		$page = new Page($pageid);

		// make sure its out page
		if (!$page->belongsToGroup($this->group))
		{
			App::abort(403, Lang::txt('COM_GROUPS_PAGES_PAGE_NOT_AUTH'));
		}

		// make sure we have an approved version
		$version = $page->approvedVersion();
		if ($version === null)
		{
			$this->setNotification(Lang::txt('COM_GROUPS_PAGES_PAGE_HOME_ERROR', $page->get('title')), 'error');
			App::redirect(Route::url('index.php?option=' . $this->_option . '&cn=' . $this->group->get('cn') . '&controller=pages'));
			return;
		}

		// remove any current home page
		$pageArchive = Page\Archive::getInstance();
		$pageArchive->reset('home', 0, array(
			'gidNumber' => $this->group->get('gidNumber')
		));

		// toggle home state
		$home = 1;
		if ($page->get('home') == 1)
		{
			$home = 0;
		}
		$page->set('home', $home);

		// store new group home page
		if (!$page->store())
		{
			$this->setNotification($page->getError(), 'error');
			return $this->displayTask();
		}

		// inform user
		$this->setNotification(Lang::txt('COM_GROUPS_PAGES_PAGE_HOME_SET', $page->get('title')), 'passed');

		// redirect
		App::redirect(Route::url('index.php?option=' . $this->_option . '&cn=' . $this->group->get('cn') . '&controller=pages'));
		if ($return = Request::getVar('return', '','get'))
		{
			App::redirect(base64_decode($return));
		}
	}

	/**
	 * Preview Group Page
	 *
	 * @return 	void
	 */
	public function previewTask()
	{
		// get reqest vars
		$pageid  = Request::getInt('pageid', 0, 'get');
		$version = Request::getInt('version', 0, 'get');

		// page object
		$page = new Page($pageid);

		// render preview
		echo Helpers\Pages::generatePreview($page, $version);
		exit();
	}


	/**
	 * Output raw content
	 *
	 * @param     $escape    Escape outputted content
	 * @return    string     HTML content
	 */
	public function rawTask($escape = true)
	{
		// get reqest vars
		$pageid  = Request::getInt('pageid', 0, 'get');
		$version = Request::getInt('version', 1, 'get');

		// page object
		$page = new Page($pageid);

		// make sure page belongs to this group
		if (!$page->belongsToGroup($this->group))
		{
			App::abort(403, Lang::txt('COM_GROUPS_PAGES_PAGE_NOT_AUTH'));
		}

		// load page version
		$pageVersion = $page->version($version);

		// do we have a page version
		if ($pageVersion === null)
		{
			App::abort(404, Lang::txt('COM_GROUPS_PAGES_PAGE_VERSION_NOT_FOUND'));
		}

		// output page version
		if ($escape)
		{
			echo '<pre>' . utf8_decode($this->view->escape($pageVersion->get('content'))) . '</pre>';
		}
		else
		{
			echo utf8_decode($pageVersion->get('content'));
		}
		exit();
	}

	/**
	 * Restore Page Version
	 *
	 * @return [type] [description]
	 */
	public function restoreTask()
	{
		// get reqest vars
		$pageid  = Request::getInt('pageid', 0, 'get');
		$version = Request::getInt('version', null, 'get');

		// page object
		$page = new Page($pageid);

		// make sure page belongs to this group
		if (!$page->belongsToGroup($this->group))
		{
			App::abort(403, Lang::txt('COM_GROUPS_PAGES_PAGE_NOT_AUTH'));
		}

		// load page version
		$pageVersion = $page->version($version);

		// do we have a page version
		if ($pageVersion === null)
		{
			App::abort(404, Lang::txt('COM_GROUPS_PAGES_PAGE_VERSION_NOT_FOUND'));
		}

		// get the current version for this page
		$currentVersionNumber = $page->version('current')->get('version');

		// duplicate page version unsetting the id & updating version #
		$newVersion = clone $pageVersion;
		$newVersion->set('id', null);
		$newVersion->set('version', $currentVersionNumber + 1);

		// attempt to save new version
		if (!$newVersion->store(false, $this->group->isSuperGroup()))
		{
			$this->setNotification($newVersion->getError(), 'error');
			$this->versionsTask();
			return;
		}

		// redirect
		App::redirect(
			Route::url('index.php?option=' . $this->_option . '&cn=' . $this->group->get('cn') . '&controller=pages&task=versions&pageid=' . $page->get('id')),
			Lang::txt('COM_GROUPS_PAGES_PAGE_VERSION_RESTORED', $page->get('title'), $version, Date::of($pageVersion->get('created'))->format('M d, Y @ g:ia')),
			'passed'
		);
	}
}