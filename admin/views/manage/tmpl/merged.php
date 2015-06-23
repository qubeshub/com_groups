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
 * @copyright Copyright 2005-2015 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

// No direct access
defined('_HZEXEC_') or die();

$this->css();

Toolbar::title(Lang::txt('COM_GROUPS'), 'groups.png');
Toolbar::custom('display','back','back','COM_GROUPS_BACK', false);

Html::behavior('tooltip');
?>
<script type="text/javascript">
function submitbutton(pressbutton)
{
	var form = document.getElementById('adminForm');
	if (pressbutton == 'cancel') {
		submitform(pressbutton);
		return;
	}
	// do field validation
	submitform(pressbutton);
}
</script>

<form action="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller); ?>" method="post" name="adminForm" id="adminForm">

	<?php if (!empty($this->success)) : ?>
		<table class="adminlist success">
			<thead>
			 	<tr>
					<th scope="col"><?php echo Lang::txt('COM_GROUPS_PULL_SUCCESS'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->success as $success) : ?>
					<tr>
						<td>
							<?php
								$group = \Hubzero\User\Group::getInstance($success['group']);
								echo '<strong>' . $group->get('description') . ' (' . $group->get('cn') . ')</strong>';
							?>
							<br />
							<br />
							<pre><?php echo $success['message']; ?></pre>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<br /><br />

	<?php if (!empty($this->failed)) : ?>
		<table class="adminlist failed">
			<thead>
				<tr>
					<th scope="col"><?php echo Lang::txt('COM_GROUPS_PULL_FAIL'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->failed as $failed) : ?>
					<tr>
						<td>
							<?php
								$group = \Hubzero\User\Group::getInstance($failed['group']);
								echo '<strong>' . $group->get('description') . ' (' . $group->get('cn') . ')</strong>';
							?>
							<br />
							<br />
							<pre><?php echo $failed['message']; ?></pre>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<input type="hidden" name="option" value="<?php echo $this->option ?>" />
	<input type="hidden" name="controller" value="<?php echo $this->controller; ?>">
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="boxchecked" value="0" />
	<?php echo Html::input('token'); ?>
</form>