<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright 2005-2019 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access
defined('_HZEXEC_') or die();

// push css
$this->css();
?>
<header id="content-header">
	<h2><?php echo $this->title; ?></h2>

	<div id="content-header-extra">
		<ul id="useroptions">
			<li class="last">
				<a class="icon-group btn" href="<?php echo Route::url('index.php?option='.$this->option.'&cn='.$this->group->get('cn')); ?>">
					<?php echo Lang::txt('COM_GROUPS_ACTION_BACK_TO_GROUP'); ?>
				</a>
			</li>
		</ul>
	</div><!-- / #content-header-extra -->
</header>

<section class="main section">
	<div class="section-inner">
		<?php
			foreach ($this->notifications as $notification)
			{
				echo "<p class=\"{$notification['type']}\">{$notification['message']}</p>";
			}
		?>
		<form action="<?php echo Route::url('index.php?option=' . $this->option); ?>" method="post" id="hubForm">
			<div class="explaination">
				<h3><?php echo Lang::txt('COM_GROUPS_INVITE_SIDEBAR_HELP_TITLE'); ?></h3>
				<p><?php echo Lang::txt('COM_GROUPS_INVITE_SIDEBAR_HELP_DESC'); ?></p>
				<p><img class="invite-example" src="<?php echo Request::base(true); ?>/core/components/com_groups/site/assets/img/invite_example.jpg" alt="Example Auto-Completer" width="100%" />
			</div>
			<fieldset>
				<legend><?php echo Lang::txt('COM_GROUPS_INVITE_SECTION_TITLE'); ?></legend>

		 		<p><?php echo Lang::txt('COM_GROUPS_INVITE_SECTION_DESC', $this->group->get('description')); ?></p>

				<label>
					<?php echo Lang::txt('COM_GROUPS_INVITE_LOGINS'); ?> <span class="required"><?php echo Lang::txt('COM_GROUPS_REQUIRED'); ?></span>
					<?php
						$mc = Event::trigger('hubzero.onGetMultiEntry', array(array('members', 'logins', 'acmembers', '', implode(', ', $this->invites))));
						if (count($mc) > 0) {
							echo $mc[0];
						} else { ?>
							<input type="text" name="logins" id="acmembers" value="<?php echo $this->escape(implode(', ', $this->invites)); ?>" size="35" />
						<?php } ?>
					<span class="hint"><?php echo Lang::txt('COM_GROUPS_INVITE_LOGINS_HINT'); ?></span>
				</label>
				<label for="msg">
					<?php echo Lang::txt('COM_GROUPS_INVITE_MESSAGE'); ?>
					<textarea name="msg" id="msg" rows="12" cols="50"><?php echo $this->escape(stripslashes($this->msg)); ?></textarea>
				</label>
			</fieldset>
			<div class="clear"></div>
			<input type="hidden" name="option" value="<?php echo $this->option; ?>" />
			<input type="hidden" name="controller" value="membership" />
			<input type="hidden" name="task" value="doinvite" />
			<input type="hidden" name="cn" value="<?php echo $this->group->get('cn'); ?>" />
			<input type="hidden" name="return" value="<?php echo $this->return; ?>" />
			<?php echo Html::input('token'); ?>
			<p class="submit">
				<input class="btn btn-success" type="submit" value="<?php echo Lang::txt('COM_GROUPS_INVITE_BTN_TEXT'); ?>" />
			</p>
		</form>
	</div>
</section><!-- / .main section -->
