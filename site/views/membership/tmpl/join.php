<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright © 2026 Purdue University. All Rights Reserved.
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access
defined('_HZEXEC_') or die();

$base = 'index.php?option=' . $this->option . '&cn=' . $this->group->get('cn');

// Carry the (already validated) return URL through the posted join
$joinUrl = $base . '&task=join';
if (!empty($this->return))
{
	$joinUrl .= '&return=' . urlencode(base64_encode($this->return));
}

// Shown in a pop-up (the group templates load it with no_html=1)
$modal = Request::getInt('no_html', 0);
?>
<?php if ($modal) { ?>
<h2><?php echo $this->escape($this->title); ?></h2>
<?php } else { ?>
<header id="content-header">
	<h2><?php echo $this->escape($this->title); ?></h2>

	<div id="content-header-extra">
		<ul id="useroptions">
			<li class="last">
				<a class="group btn" href="<?php echo Route::url('index.php?option=' . $this->option); ?>">
					<?php echo Lang::txt('COM_GROUPS_ACTION_BACK_TO_ALL_GROUPS'); ?>
				</a>
			</li>
		</ul>
	</div><!-- / #content-header-extra -->
</header>
<?php } ?>

<section class="main section">
	<div class="section-inner">
		<p class="info"><?php echo Lang::txt('COM_GROUPS_JOIN_CONFIRM', $this->escape($this->group->get('description'))); ?></p>
		<p class="join-actions">
			<?php // Posts the page's shared action form (and its token), so it also works from the pop-up ?>
			<button type="submit" form="hz-post-action" formaction="<?php echo Route::url($joinUrl); ?>" class="btn btn-success">
				<?php echo Lang::txt('COM_GROUPS_TOOLBAR_JOIN'); ?>
			</button>
			<?php if ($modal) { ?>
				<button type="button" class="btn btn-secondary fancybox-close-action">
					<?php echo Lang::txt('JCANCEL'); ?>
				</button>
			<?php } else { ?>
				<a class="btn btn-secondary" href="<?php echo Route::url($base); ?>">
					<?php echo Lang::txt('JCANCEL'); ?>
				</a>
			<?php } ?>
		</p>
	</div>
</section>
