<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2011 Purdue University. All rights reserved.
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
 * @copyright Copyright 2005-2011 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

// define base link
$base = 'index.php?option=' . $this->option . '&controller=' . $this->controller . '&gid=' . $this->group->cn;

// create title
Toolbar::title($this->group->get('description') . ': ' . Lang::txt('COM_GROUPS_PAGES'), 'groups.png');

// create toolbar buttons
$canDo = \Components\Groups\Helpers\Permissions::getActions('group');
if ($canDo->get('core.create'))
{
	Toolbar::addNew();
}
if ($canDo->get('core.edit'))
{
	Toolbar::editList();
}
if ($canDo->get('core.delete'))
{
	Toolbar::deleteList('COM_GROUPS_PAGES_DELETE_CONFIRM', 'delete');
}
Toolbar::spacer();
Toolbar::custom('manage', 'config','config','COM_GROUPS_MANAGE',false);
Toolbar::spacer();
Toolbar::help('pages');

$this->css();

// include modal for raw version links
Html::behavior('modal', 'a.version, a.preview', array('handler' => 'iframe', 'fullScreen'=>true));
?>

<script type="text/javascript">
function submitbutton(pressbutton)
{
	submitform(pressbutton);
}
</script>

<?php
	require_once JPATH_COMPONENT_ADMINISTRATOR . DS . 'views' . DS . 'pages' . DS . 'tmpl' . DS . 'menu.php';
?>

<?php if ($this->needsAttention->count() > 0) : ?>
	<table class="adminlist attention">
		<thead>
		 	<tr>
				<th>(<?php echo $this->needsAttention->count(); ?>) <?php echo Lang::txt('COM_GROUPS_PAGES_NEEDING_ATTENTION'); ?></th>
				<th><?php echo Lang::txt('COM_GROUPS_PAGES_NEEDING_ATTENTION_VIEW'); ?></th>
				<th><?php echo Lang::txt('COM_GROUPS_PAGES_NEEDING_ATTENTION_CHECKS'); ?></th>
				<th><?php echo Lang::txt('COM_GROUPS_PAGES_NEEDING_ATTENTION_APPROVE'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($this->needsAttention as $needsAttention) : ?>
				<tr>
					<td>
						<?php echo $this->escape($needsAttention->get('title')); ?> <br />
						<span class="hint" tabindex="-1"><?php echo '/groups/' . $this->group->get('cn') . '/' . $this->escape($needsAttention->get('alias')); ?></span>
					</td>
					<td>
						<ol class="attention-view">
							<li class="raw">
								<a class="version" href="<?php echo $base; ?>&amp;task=raw&amp;pageid=<?php echo $needsAttention->get('id'); ?>" class="btn">
									<?php echo Lang::txt('COM_GROUPS_PAGES_NEEDING_ATTENTION_VIEW_RAW'); ?>
								</a>
							</li>
							<?php if ($needsAttention->version()->get('checked_errors') && $needsAttention->version()->get('scanned')) : ?>
								<li class="preview">
									<a class="preview" href="<?php echo $base; ?>&amp;task=preview&amp;pageid=<?php echo $needsAttention->get('id'); ?>" class="btn">
										<?php echo Lang::txt('COM_GROUPS_PAGES_NEEDING_ATTENTION_RENDER_PREVIEW'); ?>
									</a>
								</li>
							<?php else : ?>
								<li class="preview">
									<?php echo Lang::txt('COM_GROUPS_PAGES_NEEDING_ATTENTION_RENDER_PREVIEW_HINT'); ?>
								</li>
							<?php endif; ?>
							<li class="edit">
								<a href="<?php echo $base; ?>&amp;task=edit&amp;id[]=<?php echo $needsAttention->get('id'); ?>" class="btn">
									<?php echo Lang::txt('COM_GROUPS_PAGES_NEEDING_ATTENTION_EDIT'); ?>
								</a>
							</li>
						</ol>
					</td>
					<td>
						<ol class="attention-actions">
							<li class="<?php if ($needsAttention->version()->get('checked_errors')) { echo 'completed'; } ?>">
								<a href="<?php echo $base; ?>&amp;task=errors&amp;id=<?php echo $needsAttention->get('id'); ?>" class="btn">
									<?php echo Lang::txt('COM_GROUPS_PAGES_NEEDING_ATTENTION_CHECK_FOR_ERRORS'); ?>
								</a>
							</li>
							<li class="<?php if ($needsAttention->version()->get('scanned')) { echo 'completed'; } ?>">
								<a href="<?php echo $base; ?>&amp;task=scan&amp;id=<?php echo $needsAttention->get('id'); ?>" class="btn">
									<?php echo Lang::txt('COM_GROUPS_PAGES_NEEDING_ATTENTION_SCAN_CONTENT'); ?>
								</a>
							</li>

						</ol>
					</td>
					<td width="20%">
						<ol class="attention-actions">
							<?php if ($needsAttention->version()->get('checked_errors') && $needsAttention->version()->get('scanned')) : ?>
								<li class="approve">
									<a href="<?php echo $base; ?>&amp;task=approve&amp;id=<?php echo $needsAttention->get('id'); ?>" class="btn">
										<strong><?php echo Lang::txt('COM_GROUPS_PAGES_NEEDING_ATTENTION_APPROVE'); ?></strong>
									</a>
								</li>
							<?php else: ?>
								<span><em><?php echo Lang::txt('COM_GROUPS_PAGES_NEEDING_ATTENTION_APPROVE_HINT'); ?></em></span>
							<?php endif; ?>
						</ol>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<br />
<?php endif; ?>

<form action="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller . '&gid=' . $this->group->cn); ?>" name="adminForm" id="adminForm" method="post">
	<table class="adminlist">
		<thead>
		 	<tr>
				<th><input type="checkbox" name="toggle" value="" onclick="checkAll(<?php echo $this->pages->count();?>);" /></th>
				<th scope="col"><?php echo Lang::txt('COM_GROUPS_PAGES_TITLE'); ?></th>
				<th scope="col"><?php echo Lang::txt('COM_GROUPS_PAGES_STATE'); ?></th>
				<th scope="col"><?php echo Lang::txt('COM_GROUPS_PAGES_HOME'); ?></th>
				<th scope="col"><?php echo Lang::txt('COM_GROUPS_PAGES_VERSIONS'); ?></th>
			</tr>
		</thead>
		<tbody>
<?php if ($this->pages->count() > 0) : ?>
	<?php foreach ($this->pages as $k => $page) : ?>
			<tr>
				<td>
					<input type="checkbox" name="id[]" id="cb<?php echo $k;?>" value="<?php echo $page->get('id'); ?>" onclick="isChecked(this.checked);" />
				</td>
				<td>
					<?php echo $this->escape(stripslashes($page->get('title'))); ?> <br />
					<?php
						// add /groups/{{group_cname}}
						$segments = array('groups', $this->group->get('cn'));

						// get parent aliases
						$parents  = $page->getRecursiveParents($page);
						$segments = array_merge($segments, $parents->lists('alias'));

						// remove home page
						$search = array_search('overview', $segments);
						if ($search !== false)
						{
							unset($segments[$search]);
						}

						// add this page alias
						$segments[] = $page->get('alias');
					?>
					<span class="hint" tabindex="-1"><?php echo DS . implode(DS, $segments); ?></span>
				</td>
				<td>
					<?php
					switch ($page->get('state'))
					{
						case 0:
							echo  Lang::txt('COM_GROUPS_PAGES_STATE_UNPUBLISHED');
						break;
						case 1:
							echo  Lang::txt('COM_GROUPS_PAGES_STATE_PUBLISHED');
						break;
						case 2:
							echo Lang::txt('COM_GROUPS_PAGES_STATE_DELETED');
						break;
					}
					?>
				</td>
				<td>
					<?php
						if ($page->get('home'))
						{
							echo '<span class="home">'.Lang::txt('JYES').'</span>';
						}
					?>
				</td>
				<td><?php echo $page->versions()->count(); ?></td>
			</tr>
	<?php endforeach; ?>
<?php else : ?>
			<tr>
				<td colspan="6"><?php echo Lang::txt('COM_GROUPS_PAGES_NO_PAGES'); ?></td>
			</tr>
<?php endif; ?>
		</tbody>
	</table>

	<input type="hidden" name="option" value="<?php echo $this->option; ?>" />
	<input type="hidden" name="controller" value="<?php echo $this->controller; ?>">
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="boxchecked" value="0" />
	<?php echo Html::input('token'); ?>
</form>