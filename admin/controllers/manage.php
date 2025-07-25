<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Groups\Admin\Controllers;

use Hubzero\Component\AdminController;
use Hubzero\Config\Registry;
use Hubzero\User\Group;
use Components\Groups\Helpers\Permissions;
use Components\Groups\Models\Page;
use Components\Groups\Models\Log;
use Components\Groups\Helpers\Gitlab;
use Components\Groups\Models\Orm\Field;
use Filesystem;
use Request;
use Config;
use Nofity;
use Event;
use Route;
use Lang;
use User;
use Date;
use App;

include_once dirname(dirname(__DIR__)) . '/models/orm/field.php';

/**
 * Groups controller class for managing membership and group info
 */
class Manage extends AdminController
{
	/**
	 * Execute a task
	 *
	 * @return  void
	 */
	public function execute()
	{
		$this->registerTask('add', 'edit');
		$this->registerTask('apply', 'save');

		$this->registerTask('publish', 'state');
		$this->registerTask('unpublish', 'state');
		$this->registerTask('archive', 'state');

		parent::execute();
	}

	/**
	 * Displays a list of groups
	 *
	 * @return  void
	 */
	public function displayTask()
	{
		$this->view->filters = array(
			// Filters for getting a result count
			'limit'      => 'all',
			'fields'     => array('COUNT(*)'),
			'authorized' => 'admin',
			// Incoming
			'type'       => array(Request::getState(
				$this->_option . '.browse.type',
				'type',
				'all'
			)),
			'search'     => urldecode(Request::getState(
				$this->_option . '.browse.search',
				'search',
				''
			)),
			'discoverability' => Request::getState(
				$this->_option . '.browse.discoverability',
				'discoverability',
				''
			),
			'policy'     => Request::getState(
				$this->_option . '.browse.policy',
				'policy',
				''
			),
			'state'     => Request::getState(
				$this->_option . '.browse.state',
				'state',
				-1
			),
			'sort'       => Request::getState(
				$this->_option . '.browse.sort',
				'filter_order',
				'cn'
			),
			'sort_Dir'   => Request::getState(
				$this->_option . '.browse.sortdir',
				'filter_order_Dir',
				'ASC'
			),
			'approved'   => Request::getString('approved'),
			//'published'  => Request::getInt('published', 1),
			'created'    => Request::getString('created', '')
		);
		$this->view->filters['sortby'] = $this->view->filters['sort'] . ' ' . $this->view->filters['sort_Dir'];

		$canDo = \Components\Groups\Helpers\Permissions::getActions('group');
		if (!$canDo->get('core.admin'))
		{
			if ($this->view->filters['type'][0] == 'system' || $this->view->filters['type'][0] == "0" || $this->view->filters['type'][0] == null)
			{
				$this->view->filters['type'] = array('all');
			}

			if ($this->view->filters['type'][0] == 'all')
			{
				$this->view->filters['type'] = array(
					//0,  No system groups
					1,  // hub
					2,  // project
					3   // super
				);
			}
		}

		if ($this->view->filters['state'] >= 0)
		{
			$this->view->filters['published'] = $this->view->filters['state'];
		}

		// Get a record count
		$this->view->total = Group::find($this->view->filters);

		// Filters for returning results
		$this->view->filters['limit']  = Request::getState(
			$this->_option . '.browse.limit',
			'limit',
			Config::get('list_limit'),
			'int'
		);
		$this->view->filters['start']  = Request::getState(
			$this->_option . '.browse.limitstart',
			'limitstart',
			0,
			'int'
		);
		// In case limit has been changed, adjust limitstart accordingly
		$this->view->filters['start']  = ($this->view->filters['limit'] != 0 ? (floor($this->view->filters['start'] / $this->view->filters['limit']) * $this->view->filters['limit']) : 0);
		$this->view->filters['fields'] = array('cn', 'description', 'published', 'gidNumber', 'type');

		// Get a list of all groups
		$this->view->rows = array();
		if ($this->view->total > 0)
		{
			if ($rows = Group::find($this->view->filters))
			{
				$this->view->rows = $rows;
			}
		}

		// Set any errors
		foreach ($this->getErrors() as $error)
		{
			$this->view->setError($error);
		}

		// pass config to view
		$this->view->config = $this->config;

		// Output the HTML
		$this->view->display();
	}

	/**
	 * Displays an edit form
	 *
	 * @return  void
	 */
	public function editTask()
	{
		Request::setVar('hidemainmenu', 1);

		// Incoming
		$id = Request::getArray('id', array());

		// Get the single ID we're working with
		if (is_array($id))
		{
			$id = (!empty($id)) ? $id[0] : 0;
		}

		// determine task
		$task = (!$id ? 'create' : 'edit');

		$group = new Group();
		$group->read($id);

		// Make sure we are authorized
		if (!$this->authorize($task, $group))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Get custom fields and their values
		$customFields = Field::all()
			->order('ordering', 'asc')
			->rows();

		$customAnswers = array();
		foreach ($customFields as $field)
		{
			$fieldName = $field->get('name');
			$customAnswers[$fieldName] = $field->collectGroupAnswers($group->get('gidNumber'));
		}

		// Output the HTML
		$this->view
			->setErrors($this->getErrors())
			->setLayout('edit')
			->set('group', $group)
			->set('customFields', $customFields)
			->set('customAnswers', $customAnswers)
			->display();
	}

	/**
	 * Recursive array_map
	 *
	 * @param   string  $func  Function to map
	 * @param   array   $arr   Array to process
	 * @return  array
	 */
	protected function _multiArrayMap($func, $arr)
	{
		$newArr = array();

		foreach ($arr as $key => $value)
		{
			$newArr[$key] = (is_array($value) ? $this->_multiArrayMap($func, $value) : $func($value));
		}

		return $newArr;
	}

	/**
	 * Saves changes to a group or saves a new entry if creating
	 *
	 * @return  void
	 */
	public function saveTask()
	{
		// Check for request forgeries
		Request::checkToken();

		// Incoming
		$g = Request::getArray('group', array(), 'post');
		$g = $this->_multiArrayMap('trim', $g);

		$customFields = Field::all()->rows();
		$customFieldForm = Request::getArray('customfields', array());

		// Instantiate a Group object
		$group = new Group();

		// Is this a new entry or updating?
		$isNew = false;
		if (!$g['gidNumber'])
		{
			$isNew = true;

			// Set the task - if anything fails and we re-enter edit mode
			// we need to know if we were creating new or editing existing
			//$this->_task = 'new';
			$before = new Group();
		}
		else
		{
			//$this->_task = 'edit';

			// Load the group
			$group->read($g['gidNumber']);
			$before = clone($group);
		}

		$task = ($this->_task == 'edit') ? 'edit' : 'create';

		if (!$this->authorize($task, $group))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Check for any missing info
		if (!$g['cn'])
		{
			$this->setError(Lang::txt('COM_GROUPS_ERROR_MISSING_INFORMATION') . ': ' . Lang::txt('COM_GROUPS_CN'));
		}
		if (!$g['description'])
		{
			//$this->setError(Lang::txt('COM_GROUPS_ERROR_MISSING_INFORMATION') . ': ' . Lang::txt('COM_GROUPS_TITLE'));
			$g['description'] = $g['cn'];
		}

		// Push back into edit mode if any errors
		if ($this->getError())
		{
			foreach ($g as $k => $v)
			{
				$group->set($k, $v);
			}
			// Output the HTML
			$this->view
				->setErrors($this->getErrors())
				->setLayout('edit')
				->set('group', $group)
				->set('customFields', $customFields)
				->set('customAnswers', $customFieldForm)
				->display();
			return;
		}

		$g['cn'] = strtolower($g['cn']);

		// Ensure the data passed is valid
		if (!$this->_validCn($g['cn'], true))
		{
			$this->setError(Lang::txt('COM_GROUPS_ERROR_INVALID_ID'));
		}

		//only check if cn exists if we are creating or have changed the cn
		if ($this->_task == 'new' || $group->get('cn') != $g['cn'])
		{
			if (Group::exists($g['cn'], true))
			{
				$this->setError(Lang::txt('COM_GROUPS_ERROR_GROUP_ALREADY_EXIST'));
			}
		}

		foreach ($customFields as $field)
		{
			$field->setFormAnswers($customFieldForm);
			if (!$field->validate())
			{
				$this->setError($field->getError());
			}
		}

		// Push back into edit mode if any errors
		if ($this->getError())
		{
			foreach ($g as $k => $v)
			{
				$group->set($k, $v);
			}
			// Output the HTML
			$this->view
				->setErrors($this->getErrors())
				->setLayout('edit')
				->set('group', $group)
				->set('customFields', $customFields)
				->set('customAnswers', $customFieldForm)
				->display();
			return;
		}

		// group params
		$gparams = new Registry($group->get('params'));
		$gparams->merge(new Registry($g['params']));

		// set membership control param
		$membership_control = (isset($g['params']['membership_control'])) ? 1 : 0;
		$gparams->set('membership_control', $membership_control);
		$params = $gparams->toString();

		// Set the group changes and save
		$group->set('cn', $g['cn']);
		$group->set('type', $g['type']);
		$group->set('approved', $g['approved']);
		$group->set('published', $g['published']);
		if ($isNew)
		{
			$group->create();

			$group->set('published', 1);
			$group->set('approved', 1);
			$group->set('created', Date::toSql());
			$group->set('created_by', User::get('id'));

			$group->add('managers', array(User::get('id')));
			$group->add('members', array(User::get('id')));
		}
		$group->set('description', $g['description']);
		$group->set('discoverability', $g['discoverability']);
		$group->set('join_policy', $g['join_policy']);
		if (isset($g['public_desc']))
		{
			$group->set('public_desc', $g['public_desc']);
		}
		if (isset($g['private_desc']))
		{
			$group->set('private_desc', $g['private_desc']);
		}
		$group->set('restrict_msg', $g['restrict_msg']);
		$group->set('logo', $g['logo']);
		$group->set('plugins', $g['plugins']);
		$group->set('discussion_email_autosubscribe', isset($g['discussion_email_autosubscribe']) ? $g['discussion_email_autosubscribe'] : '');
		$group->set('params', $params);
		$group->update();

		if (isset($customFields))
		{
			foreach ($customFields as $field)
			{
				$field->saveGroupAnswers($group->get('gidNumber'));
			}
		}

		// create home page
		if ($isNew)
		{
			// create page
			$page = new Page(array(
				'gidNumber' => $group->get('gidNumber'),
				'parent'    => 0,
				'lft'       => 1,
				'rgt'       => 2,
				'depth'     => 0,
				'alias'     => 'overview',
				'title'     => 'Overview',
				'state'     => 1,
				'privacy'   => 'default',
				'home'      => 1
			));
			$page->store(false);

			// create page version
			$version = new Page\Version(array(
				'pageid'     => $page->get('id'),
				'version'    => 1,
				'content'    => "<!-- {FORMAT:HTML} -->\n<p>[[Group.DefaultHomePage()]]</p>",
				'created'    => Date::of('now')->toSql(),
				'created_by' => User::get('id'),
				'approved'   => 1
			));
			$version->store(false);
		}

		// Get plugins
		Event::trigger('groups.onGroupAfterSave', array($before, $group));

		// log edit
		Log::log(array(
			'gidNumber' => $group->get('gidNumber'),
			'action'    => 'group_edited',
			'comments'  => 'edited by administrator'
		));

		// handle special groups
		if ($group->isSuperGroup())
		{
			$this->_handleSuperGroup($group);

			// git lab stuff
			$this->_handSuperGroupGitlab($group);
		}

		// Output messsage and redirect
		Notify::success(Lang::txt('COM_GROUPS_SAVED'));

		if ($this->getTask() == 'apply')
		{
			App::redirect(
				Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller . '&task=edit&id=' . $group->get('gidNumber'), false)
			);
			return;
		}

		$this->cancelTask();
	}

	/**
	 * Generate default template files for special groups
	 *
	 * @param   object  $group  \Hubzero\User\Group
	 * @return  void
	 */
	private function _handleSuperGroup($group)
	{
		// get the upload path for groups
		$uploadPath = PATH_APP . DS . trim($this->config->get('uploadpath', '/site/groups'), DS) . DS . $group->get('gidNumber');

		// get the source path
		$srcTplPath = null;

		$db = \App::get('db');
		$query = $db->getQuery();
		$query->select('s.id, s.home, s.template, s.params, e.protected');
		$query->from('#__template_styles', 's');
		$query->where('s.client_id', '=', 0);
		$query->where('e.enabled', '=', 1);
		$query->where('s.home', '=', 1);
		$query->leftJoin('#__extensions as e', 'e.element', 's.template');
		$query->where('e.type', '=', 'template');
		$query->where('e.client_id', '=', 's.client_id');
		$db->setQuery($query);
		$template = $db->loadObject();
		if ($template)
		{
			foreach (array(PATH_APP, PATH_CORE) as $path)
			{
				if (is_dir($path . DS . 'templates' . DS . $template->template . DS . 'super'))
				{
					$srcTplPath = $path . DS . 'templates' . DS . $template->template . DS . 'super';
					break;
				}
			}
		}

		$srcPath = dirname(dirname(__DIR__)) . DS . 'super' . DS . 'default' . DS . '.';

		// create group folder if one doesnt exist
		if (!is_dir($uploadPath))
		{
			if (!Filesystem::makeDirectory($uploadPath))
			{
				Notify::error(Lang::txt('COM_GROUPS_SUPER_UNABLE_TO_CREATE'));
			}
		}

		// make sure folder is writable
		if (!is_writable($uploadPath))
		{
			Notify::error(Lang::txt('COM_GROUPS_SUPER_FOLDER_NOT_WRITABLE', $uploadpath));
			return;
		}

		// We need to handle templates a little differently
		if ($srcTplPath)
		{
			$uploadTplPath = $uploadPath . DS . 'template';
			shell_exec("cp -rf $srcTplPath $uploadTplPath 2>&1");
		}

		// copy over default template recursively
		// must have  /. at the end of source path to get all items in that directory
		// also doesnt overwrite already existing files/folders
		shell_exec("cp -rn $srcPath $uploadPath 2>&1");

		// make sure files are group read and writable
		// make sure files are all group owned properly
		shell_exec("chmod -R 2770 $uploadPath 2>&1");
		shell_exec("chgrp -R " . escapeshellcmd($this->config->get('super_group_file_owner', 'access-content')) . " " . $uploadPath . " 2>&1");

		// get all current users granted permissionss
		$this->database->setQuery("SHOW GRANTS FOR CURRENT_USER();");
		$grants = $this->database->loadColumn();

		// look at all current users granted permissions
		$canCreateSuperGroupDB = false;

		if (count($grants) > 0)
		{
			foreach ($grants as $grant)
			{
				if (preg_match('/sg\\\\_%/', $grant))
				{
					$canCreateSuperGroupDB = true;
				}
			} //end foreach
		} //end if

		// create super group DB if doesnt already exist
		if ($canCreateSuperGroupDB)
		{
			$this->database->setQuery("CREATE DATABASE IF NOT EXISTS `sg_{$group->get('cn')}`;");
			if (!$this->database->query())
			{
				Notify::error(Lang::txt('COM_GROUPS_SUPER_UNABLE_TO_CREATE_DB'));
			}
		}
		else
		{
			Notify::warning(Lang::txt('COM_GROUPS_SUPER_UNABLE_TO_CREATE_DB_PERMISSIONS'));
		}

		// check to see if we have a super group db config
		$supergroupDbConfigFile = DS . 'etc' . DS . 'supergroup.conf';
		if (!file_exists($supergroupDbConfigFile))
		{
			Notify::warning(Lang::txt('COM_GROUPS_SUPER_UNABLE_TO_LOAD_CONFIG'));
		}
		else
		{
			// get hub super group database config file
			$supergroupDbConfig = include $supergroupDbConfigFile;

			// define username, password, and database to be written in config
			$username = (isset($supergroupDbConfig['username'])) ? $supergroupDbConfig['username'] : '';
			$password = (isset($supergroupDbConfig['password'])) ? $supergroupDbConfig['password'] : '';
			$database = 'sg_' . $group->get('cn');

			//write db config in super group
			$dbConfigFile     = $uploadPath . DS . 'config' . DS . 'db.php';
			$dbConfigContents = "<?php\n\treturn array(\n\t\t'host'     => 'localhost',\n\t\t'port'     => '',\n\t\t'user' => '{$username}',\n\t\t'password' => '{$password}',\n\t\t'database' => '{$database}',\n\t\t'prefix'   => ''\n\t);";

			// write db config file
			if (!file_exists($dbConfigFile))
			{
				if (!file_put_contents($dbConfigFile, $dbConfigContents))
				{
					Notify::error(Lang::txt('COM_GROUPS_SUPER_UNABLE_TO_WRITE_CONFIG'));
				}
			}
		}

		// log super group change
		Log::log(array(
			'gidNumber' => $group->get('gidNumber'),
			'action'    => 'super_group_created',
			'comments'  => ''
		));
	}

	/**
	 * Create necessary super groups files
	 *
	 * @param   object  $group
	 * @return  void
	 */
	private function _handSuperGroupGitlab($group)
	{
		$client = new Gitlab();
		if (!$client->validate()) {
			return;
		}

		// make sure this is production hub
		$environment = strtolower(Config::get('application_env', 'development'));
		if (substr($environment, 0, 10) != 'production')
		{
			return;
		}

		// build group & project names
		$host        = explode('.', $_SERVER['HTTP_HOST']);
		$groupName   = 'hub-' . strtolower($host[0]);
		$projectName = 'sg_' . $group->get('cn');

		// Search for group in Gitlab
		$gitLabGroup = $client->groups($groupName);

		// create group if doesnt exist
		if (array_key_exists('message', $gitLabGroup)) {
			Notify::error("Error requesting groups: " . $gitLabGroup['message']);
			return;
		}
		elseif (empty($gitLabGroup))
		{
			$gitLabGroup = $client->createGroup(array(
				'name' => $groupName,
				'path' => $groupName
			));
			// Possible error check here
		}
		elseif (count($gitLabGroup) > 1)
		{  // If search returns more than one match, return with error.
			Notify::error(Lang::txt('COM_GROUPS_GITLAB_GET_GROUPS_MORE_THAN_ONE' . $groupName));
			return;
		}
		elseif (count($gitLabGroup) == 1)
		{
			// Grab first element of array
			$gitLabGroup = $gitLabGroup[0];
		}
		else
		{
			Notify::error(Lang::txt('COM_GROUPS_GITLAB_GET_GROUPS_UNKNOWN_ERROR'));
			return;
		}

		//Search for project in Gitlab
		$gitLabProject = $client->projects($projectName);

		// create project if doesnt exist
		if (array_key_exists('message', $gitLabProject))
		{
			Notify::error("Error requesting projects: " . $gitLabProject['message']);
			return;
		}
		elseif (empty($gitLabProject))
		{
			$gitLabProject = $client->createProject(array(
				'namespace_id'           => $gitLabGroup['id'],
				'name'                   => $projectName,
				'description'            => $group->get('description'),
				'issues_enabled'         => true,
				'merge_requests_enabled' => true,
				'wiki_enabled'           => true,
				'snippets_enabled'       => true,
			));
			// Possible error check here
		}
		elseif ($gitLabProject)
		{  // search result must match hub group name to gitlab project name exactly or create new gitlab project
			foreach ($gitLabProject as $glproj)
			{
				if ($glproj['name'] == $projectName)
				{
					$gitLabProject = $glproj;
					break;
				}
				else
				{
					$gitLabProject = null;
				}
			}
			// create project if doesnt exist
			if (empty($gitLabProject))
			{
				$gitLabProject = $client->createProject(array(
					'namespace_id'           => $gitLabGroup['id'],
					'name'                   => $projectName,
					'description'            => $group->get('description'),
					'issues_enabled'         => true,
					'merge_requests_enabled' => true,
					'wiki_enabled'           => true,
					'snippets_enabled'       => true,
				));
				// Possible error checking here
			}
		}
		else
		{
			Notify::error(Lang::txt('COM_GROUPS_GITLAB_GET_PROJECTS_UNKNOWN_ERROR'));
			return;
		}

		// path to group folder
		$uploadPath = PATH_APP . DS . trim($this->config->get('uploadpath', '/site/groups'), DS) . DS . $group->get('gidNumber');

		// build author info for making first commit
		$authorInfo = '"' . Config::get('sitename') . ' Groups <groups@' . $_SERVER['HTTP_HOST'] . '>"';

		// check to see if we already have git repo
		// only run gitlab setup once.
		if (is_dir($uploadPath . DS . '.git'))
		{
			return;
		}

		// url
		$url_bits = parse_url($gitLabProject['http_url_to_repo']);
		$gitLabUrl = $url_bits["scheme"] . '://oauth2:' . $client->get('token') . '@' . $url_bits["host"] . $url_bits["path"];

		// build command to run via shell
		// this will init the git repo, make the initial commit and push to the repo management machine
		$cmd  = 'sh ' . dirname(dirname(__DIR__)) . DS . 'admin' . DS . 'assets' . DS . 'scripts' . DS . 'gitlab_setup.sh ';
		$cmd .= $uploadPath  . ' ' . $authorInfo . ' ' . $gitLabUrl . ' 2>&1';

		// execute command
		$output = shell_exec($cmd);
		// Possible error checking here if git push fails (likely will have failed before this)

		// protect master branch
		// allows only admins to accept Merge Requests
		$protected = $client->protectBranch(array(
			'id'     => $gitLabProject['id'],
			'branch' => 'master'
		));
		if (array_key_exists('message', $protected))
		{
			Notify::error("Error on branch protection: " . $protected['message']);
			return;
		}
	}

	/**
	 * Fetch from Gitlab
	 *
	 * @return  void
	 */
	public function updateTask()
	{
		// Check for request forgeries
		Request::checkToken();

		// Incoming
		$ids = Request::getArray('id', array());

		// Get the single ID we're working with
		if (!is_array($ids))
		{
			$ids = array($ids);
		}

		// empty list?
		if (empty($ids))
		{
			return $this->cancelTask();
		}

		// Get GitLab client
		$client = new Gitlab();
		if (!$client->validate()) {
			return;
		}

		// vars to hold results of pull
		$success = array();
		$failed  = array();

		// loop through each group and pull code from repos
		foreach ($ids as $id)
		{
			// Load the group page
			$group = Group::getInstance($id);

			// Ensure we found the group info
			if (!$group)
			{
				continue;
			}

			// make sure its a super group
			if (!$group->isSuperGroup())
			{
				$failed[] = array('group' => $group->get('cn'), 'message' => Lang::txt('COM_GROUPS_GITLAB_NOT_SUPER_GROUP'));
				continue;
			}

			// path to group folder
			$uploadPath = PATH_APP . DS . trim($this->config->get('uploadpath', '/site/groups'), DS) . DS . $group->get('gidNumber');

			// make sure we have an upload path
			if (!is_dir($uploadPath))
			{
				if (!Filesystem::makeDirectory($uploadPath))
				{
					$failed[] = array('group' => $group->get('cn'), 'message' => Lang::txt('COM_GROUPS_GITLAB_UPLOAD_PATH_DOESNT_EXIST'));
					continue;
				}
			}

			// make sure we have a git repo
			if (!is_dir($uploadPath . DS . '.git'))
			{
				// only do stage setup on stage
				$environment = strtolower(Config::get('application_env', 'development'));
				if (substr($environment, 0, 7) != 'staging')
				{
					$failed[] = array('group' => $group->get('cn'), 'message' => Lang::txt('COM_GROUPS_GITLAB_NOT_MANAGED_BY_GIT'));
					continue;
				}

				// build group & project names
				$host        = explode('.', $_SERVER['HTTP_HOST']);
				$tld         = array_pop($host);
				$groupName   = 'hub-' . strtolower(end($host));
				$projectName = 'sg_' . $group->get('cn');

				// get gitlab config
				$gitlabUrl = $this->config->get('super_gitlab_url', '');
				$gitlabKey = $this->config->get('super_gitlab_key', '');

				// instantiate new gitlab client
				$gitlabGroup   = $client->group($groupName);
				$gitlabProject = $client->project($projectName);

				// if we didnt get both a matching project & group continue
				if (!$gitlabGroup || !$gitlabProject)
				{
					$failed[] = array('group' => $group->get('cn'), 'message' => Lang::txt('COM_GROUPS_GITLAB_NOT_MANAGED_BY_GIT'));
					continue;
				}

				// url
				$url_bits = parse_url($gitLabProject['http_url_to_repo']);
				$gitLabUrl = $url_bits["scheme"] . '://oauth2:' . $gitlabKey . '@' . $url_bits["host"] . $url_bits["path"];

				// setup stage environment
				$cmd  = 'sh ' . dirname(dirname(__DIR__)). DS . 'admin' . DS . 'assets' . DS . 'scripts' . DS . 'gitlab_setup_stage.sh ';
				$cmd .= str_replace('/' . $group->get('gidNumber'), '', $uploadPath) . ' ' . $group->get('gidNumber') . ' ' . $group->get('cn') . ' ' . $gitLabUrl . ' 2>&1';

				// execute command
				$output = shell_exec($cmd);
			}

			// build command to run via shell
			$cmd = "cd {$uploadPath} && ";

			if (!isset($user))
			{
				$user = Component::params('com_update')->get('system_user', 'hubadmin');
			}

			// Check to make sure using correct token
			$output = shell_exec($cmd . "git remote -v");
			$url_bits = parse_url(explode("\t", explode(" ", $output)[1])[1]);
			$gitLabKey = $client->get('token');
			if ($url_bits["pass"] !== $gitLabKey) {
				$gitLabUrl = $url_bits["scheme"] . '://' . $url_bits["user"] . ':' . $gitLabKey . '@' . $url_bits["host"] . $url_bits["path"];
				$rcmd  = 'sh ' . dirname(dirname(__DIR__)) . DS . 'admin' . DS . 'assets' . DS . 'scripts' . DS . 'gitlab_reset_remote.sh ';
				$rcmd .= $uploadPath  . ' ' . $gitLabUrl . ' 2>&1';
				$output = shell_exec($rcmd);
			}

			// The tasks and command to be performed
			$task = 'group';
			$museCmd = 'update';

			// Run as (hubadmin)
			$sudo =  '/usr/bin/sudo -u ' . $user . ' ';

			// Determines the path to muse and run the group update muse command
			$cmd .= $sudo . PATH_CORE . '/bin/muse' . ' ' . $task . ' ' . $museCmd . ' --format=json';

			// Execute and format the output
			$output = shell_exec($cmd);
			$output = json_decode($output);

			// did we succeed
			if ($output == '' || json_last_error() == JSON_ERROR_NONE)
			{
				// code is up to date
				$output = ($output == '') ? array(Lang::txt('COM_GROUPS_FETCH_CODE_UP_TO_DATE')) : $output;

				// add success message
				$success[] = array('group' => $group->get('cn'), 'message' => $output);
			}
			else
			{
				// add failed message
				$failed[] = array('group' => $group->get('cn'), 'message' => $output);
			}
		}

		// display view
		$this->view
			->set('success', $success)
			->set('failed', $failed)
			->set('config', $this->config)
			->setLayout('fetched')
			->display();
	}

	/**
	 * Merge From from Gitlab
	 *
	 * @return  void
	 */
	public function doUpdateTask()
	{
		// Check for request forgeries
		Request::checkToken();

		// Incoming
		$ids = Request::getArray('id', array());

		// Get the single ID we're working with
		if (!is_array($ids))
		{
			$ids = array($ids);
		}

		// empty list?
		if (empty($ids))
		{
			Notify::warning(Lang::txt('There are no eligible merge requests.'));
			return $this->cancelTask();
		}

		// vars to hold results of pull
		$success = array();
		$failed  = array();

		// loop through each group and pull code from repos
		foreach ($ids as $id)
		{
			// Load the group page
			$group = Group::getInstance($id);

			// Ensure we found the group info
			if (!$group)
			{
				continue;
			}

			// make sure its a super group
			if (!$group->isSuperGroup())
			{
				$failed[] = array(
					'group'   => $group->get('cn'),
					'message' => Lang::txt('COM_GROUPS_GITLAB_NOT_SUPER_GROUP')
				);
				continue;
			}

			// path to group folder
			$uploadPath = PATH_APP . DS . trim($this->config->get('uploadpath', '/site/groups'), DS) . DS . $group->get('gidNumber');

			// make sure we have an upload path
			if (!is_dir($uploadPath))
			{
				if (!Filesystem::makeDirectory($uploadPath))
				{
					$failed[] = array(
						'group'   => $group->get('cn'),
						'message' => Lang::txt('COM_GROUPS_GITLAB_UPLOAD_PATH_DOESNT_EXIST')
					);
					continue;
				}
			}

			// build command to run via shell
			$cmd = "cd {$uploadPath} && ";

			if (!isset($user))
			{
				$user = Component::params('com_update')->get('system_user', 'hubadmin');
			}

			// The tasks and command to be perofmred
			$task = 'group';
			$museCmd = 'update';

			// Run as (hubadmin)
			$sudo =  '/usr/bin/sudo -u ' . $user . ' ';

			// Determines the path to muse and run the group update muse command
			$cmd .= $sudo . PATH_CORE . '/bin/muse' . ' ' . $task . ' ' . $museCmd . ' -f --no-colors';

			// this will run a "git pull --rebase origin master"
			$output = shell_exec($cmd);

			if (strpos($output, 'ineligble') === false)
			{
				$museCmd = 'migrate';
				$cmd = "cd {$uploadPath} && ";
				$cmd .= $sudo . PATH_CORE . '/bin/muse' . ' ' . $task . ' ' . $museCmd . ' -f --no-colors';

				$output .= shell_exec($cmd);
			}
			else
			{
				// Error message - refusing to run migrations due to failed update
			}

			// did we succeed
			if (preg_match("/Updating the repository.../uis", $output))
			{
				// add success message
				$success[] = array(
					'group'   => $group->get('cn'),
					'message' => $output
				);
			}
			else
			{
				// add failed message
				$failed[] = array(
					'group'   => $group->get('cn'),
					'message' => $output
				);
			}
		}

		// display view
		$this->view
			->setLayout('merged')
			->set('success', $success)
			->set('failed', $failed)
			->set('config', $this->config)
			->display();
	}

	/**
	 * Removes a group and all associated information
	 *
	 * @return  void
	 */
	public function deleteTask()
	{
		// Check for request forgeries
		Request::checkToken();

		if (!User::authorise('core.delete', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Incoming
		$ids = Request::getArray('id', array());

		// Get the single ID we're working with
		if (!is_array($ids))
		{
			$ids = array($ids);
		}

		// Do we have any IDs?
		if (!empty($ids))
		{
			foreach ($ids as $id)
			{
				// Load the group page
				$group = Group::getInstance($id);

				// Ensure we found the group info
				if (!$group)
				{
					continue;
				}
				if (!$this->authorize('delete', $group))
				{
					continue;
				}

				// Get number of group members
				$groupusers    = $group->get('members');
				$groupmanagers = $group->get('managers');
				$members = array_merge($groupusers, $groupmanagers);

				// Start log
				$log  = Lang::txt('COM_GROUPS_SUBJECT_GROUP_DELETED');
				$log .= Lang::txt('COM_GROUPS_TITLE') . ': ' . $group->get('description') . "\n";
				$log .= Lang::txt('COM_GROUPS_ID') . ': ' . $group->get('cn') . "\n";
				$log .= Lang::txt('COM_GROUPS_PUBLIC_TEXT') . ': ' . stripslashes($group->get('public_desc')) . "\n";
				$log .= Lang::txt('COM_GROUPS_PRIVATE_TEXT') . ': ' . stripslashes($group->get('private_desc')) . "\n";
				$log .= Lang::txt('COM_GROUPS_RESTRICTED_MESSAGE') . ': ' . stripslashes($group->get('restrict_msg')) . "\n";

				// Log ids of group members
				if ($groupusers)
				{
					$log .= Lang::txt('COM_GROUPS_MEMBERS') . ': ';
					foreach ($groupusers as $gu)
					{
						$log .= $gu . ' ';
					}
					$log .=  "\n";
				}
				$log .= Lang::txt('COM_GROUPS_MANAGERS') . ': ';
				foreach ($groupmanagers as $gm)
				{
					$log .= $gm . ' ';
				}
				$log .= "\n";

				// Trigger the functions that delete associated content
				// Should return logs of what was deleted
				$logs = Event::trigger('groups.onGroupDelete', array($group));
				if (count($logs) > 0)
				{
					$log .= implode('', $logs);
				}

				// Delete group
				if (!$group->delete())
				{
					App::abort(500, 'Unable to delete group');
					return;
				}

				// log publishing
				Log::log(array(
					'gidNumber' => $group->get('gidNumber'),
					'action'    => 'group_deleted',
					'comments'  => $log
				));
			}

			Notify::success(Lang::txt('COM_GROUPS_REMOVED'));
		}

		// Redirect back to the groups page
		$this->cancelTask();
	}

	/**
	 * Change the state of one or more groups
	 *
	 * @return  void
	 */
	public function stateTask()
	{
		// Check for request forgeries
		Request::checkToken(['get', 'post']);

		if (!User::authorise('core.manage', $this->_option)
		 && !User::authorise('core.admin', $this->_option)
		 && !User::authorise('core.edit', $this->_option)
		 && !User::authorise('core.edit.state', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Incoming
		$ids = Request::getArray('id', array());
		$ids = (!is_array($ids) ? array($ids) : $ids);

		// Do we have any IDs?
		if (!empty($ids))
		{
			switch ($this->getTask())
			{
				case 'publish':
					$state = 1;
					$action = 'published';
				break;

				case 'archive':
					$state = 2;
					$action = 'archived';
				break;

				case 'unpublish':
				default:
					$state = 0;
					$action = 'unpublished';
				break;
			}

			$success = 0;

			//foreach group id passed in
			foreach ($ids as $id)
			{
				// Load the group page
				$group = new Group();
				$group->read($id);

				// Ensure we found the group info
				if (!$group)
				{
					continue;
				}

				// Group already has the desired state
				if ($group->get('published') == $state)
				{
					continue;
				}

				$before = clone $group;

				// Set the group to be archived
				$group->set('published', $state);
				$group->update();

				// log publishing
				Log::log(array(
					'gidNumber' => $group->get('gidNumber'),
					'action'    => 'group_' . $action,
					'comments'  => $action . ' by administrator'
				));

				// Get plugins
				Event::trigger('groups.onGroupAfterSave', array($before, $group));

				$success++;
			}

			// Output messsage and redirect
			if ($success)
			{
				Notify::success(Lang::txt('COM_GROUPS_SUCCESS_' . strtoupper($action), $success));
			}
		}

		$this->cancelTask();
	}

	/**
	 * Approve a group
	 *
	 * @return  void
	 */
	public function approveTask()
	{
		// Check for request forgeries
		Request::checkToken(['get', 'post']);

		if (!User::authorise('core.manage', $this->_option)
		 && !User::authorise('core.admin', $this->_option)
		 && !User::authorise('core.edit', $this->_option)
		 && !User::authorise('core.edit.state', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Incoming
		$ids = Request::getArray('id', array());

		// Get the single ID we're working with
		if (!is_array($ids))
		{
			$ids = array($ids);
		}

		$i = 0;

		// Do we have any IDs?
		if (!empty($ids))
		{
			// foreach group id passed in
			foreach ($ids as $id)
			{
				// Load the group page
				$group = new Group();
				$group->read($id);

				// Ensure we found the group info
				if (!$group)
				{
					continue;
				}

				// Set the group to be published and update
				$group->set('approved', 1);
				$group->update();

				// log publishing
				Log::log(array(
					'gidNumber' => $group->get('gidNumber'),
					'action'    => 'group_approved',
					'comments'  => 'approved by administrator'
				));

				$i++;
			}

			if ($i)
			{
				Notify::success(Lang::txt('COM_GROUPS_APPROVED'));
			}
		}

		// Output messsage and redirect
		$this->cancelTask();
	}

	/**
	 * Unapprove a group
	 *
	 * @return  void
	 */
	public function unapproveTask()
	{
		// Check for request forgeries
		Request::checkToken(['get', 'post']);

		if (!User::authorise('core.manage', $this->_option)
		 && !User::authorise('core.admin', $this->_option)
		 && !User::authorise('core.edit', $this->_option)
		 && !User::authorise('core.edit.state', $this->_option))
		{
			App::abort(403, Lang::txt('JERROR_ALERTNOAUTHOR'));
		}

		// Incoming
		$ids = Request::getArray('id', array());

		// Get the single ID we're working with
		if (!is_array($ids))
		{
			$ids = array($ids);
		}

		$i = 0;

		// Do we have any IDs?
		if (!empty($ids))
		{
			// foreach group id passed in
			foreach ($ids as $id)
			{
				// Load the group page
				$group = new Group();
				$group->read($id);

				// Ensure we found the group info
				if (!$group)
				{
					continue;
				}

				// Set the group to be published and update
				$group->set('approved', 0);
				$group->update();

				// log publishing
				Log::log(array(
					'gidNumber' => $group->get('gidNumber'),
					'action'    => 'group_unapproved',
					'comments'  => 'unapproved by administrator'
				));

				$i++;
			}

			if ($i)
			{
				Notify::success(Lang::txt('COM_GROUPS_UNAPPROVED'));
			}
		}

		// Output messsage and redirect
		$this->cancelTask();
	}

	/**
	 * Check if a group alias is valid
	 *
	 * @param   integer  $cname        Group alias
	 * @param   boolean  $allowDashes  Allow dashes in cn
	 * @return  boolean  True if valid, false if not
	 */
	private function _validCn($cn, $allowDashes = false)
	{
		$regex = '/^[0-9a-zA-Z]+[_0-9a-zA-Z]*$/i';
		if ($allowDashes)
		{
			$regex = '/^[0-9a-zA-Z]+[-_0-9a-zA-Z]*$/i';
		}

		if (\Hubzero\Utility\Validate::reserved('group', $cn))
		{
			return false;
		}

		if (preg_match($regex, $cn))
		{
			if (is_numeric($cn) && intval($cn) == $cn && $cn >= 0)
			{
				return false;
			}
			else
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Authorization check
	 * Checks if the group is a system group and the user has super admin access
	 *
	 * @param   object   $group  \Hubzero\User\Group
	 * @return  boolean  True if authorized, false if not.
	 */
	protected function authorize($task, $group=null)
	{
		// get users actions
		$canDo = Permissions::getActions('group');

		// build task name
		$taskName = 'core.' . $task;

		// can user perform task
		if (!$canDo->get($taskName) || (!$canDo->get('core.admin') && $task == 'edit' && $group->get('type') == 0))
		{
			// No access
			return false;
		}

		return true;
	}
}
