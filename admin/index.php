<?php
require_once(dirname(dirname(__FILE__)).'/config.php');

require_once(dirname(dirname(__FILE__)).'/User.php');

if (!session_start())
{
	throw new Exception("Can't start session");
}

if (array_key_exists('impersonate', $_POST)) {
	$user = User::getUser($_POST['impersonate']);
	if ($user !== null) {
		$user->setSession(false); // always impersonate only for the browser session
		header('Location: '.UserConfig::$DEFAULTLOGINRETURN);
	}
	else
	{
		header('Location: #msg=cantimpersonate');
	}
}

require_once(UserConfig::$header);

?><h1>Users (<?php echo User::getTotalUsers()?>)<?php if (UserConfig::$enableInvitations) { ?> | <a href="invitations.php">Invitations</a><?php } ?></h1>
<div style="background: white; padding: 1em">
<h2>Active Users | <a href="registrations.php">Registered Users</a> | <a href="bymodule.php">Registrations By Module</a></h2>
<?php
$daily_active_users = User::getDailyActiveUsers();

$selectedactivityid = null;
$selectedactivity = null;
$activityuser = null;
$dates = array();
if (array_key_exists('activityid', $_REQUEST)) {
	$selectedactivityid = $_REQUEST['activityid'];
	$selectedactivity = UserConfig::$activities[$selectedactivityid];

	$dates = User::getDailyPointsByActivity($selectedactivityid);
} else {
	if (array_key_exists('userid', $_REQUEST)) {
		$activityuser = User::getUser($_REQUEST['userid']);
	}
	$daily_activity = User::getDailyActivityPoints($activityuser);
	foreach ($daily_active_users as $record) {
		if (!array_key_exists($record['date'], $dates)) {
			$dates[$record['date']] = array();
		}

		if (!array_key_exists('users', $dates[$record['date']])) {
			$dates[$record['date']]['users'] = 0;
		}

		$dates[$record['date']]['users'] += 1;
	}
	foreach ($daily_activity as $record) {
		if (!array_key_exists($record['date'], $dates)) {
			$dates[$record['date']] = array();
		}

		if (!array_key_exists('points', $dates[$record['date']])) {
			$dates[$record['date']]['points'] = 0;
		}
		$dates[$record['date']]['points'] += $record['total'] * UserConfig::$activities[$record['activity']][1];
	}
}

$total = 0;
?>
<script type='text/javascript' src='http://www.google.com/jsapi'></script>
<script type='text/javascript'>
google.load('visualization', '1', {'packages':['annotatedtimeline']});
google.setOnLoadCallback(function() {
	var data = new google.visualization.DataTable();
	data.addColumn('date', 'Date');
	<?php
	if (is_null($selectedactivity)) {
		if (is_null($activityuser)) {
		?>
		data.addColumn('number', 'Active Users');
		<?php
		}
		?>
		data.addColumn('number', 'Total Points');
	<?php
	} else {
	?>
		data.addColumn('number', 'Number of activities');
	<?php
	}
	?>

	var daily = [
<?php
		$first = true;

		foreach ($dates as $date => $record)
		{
			if (!$first) {
				?>,
				<?php
			}
			else
			{
				$first = false;
			}?>
	[new Date('<?php echo $date?>'),<?php
			if (is_null($selectedactivity)) {
				if (is_null($activityuser)) {
					echo $record['users']?>,<?php
				}
				
				if (array_key_exists('points', $record) && $record['points'] > 0) {
					echo $record['points'];
				} else {
					echo 0;
				}
			} else {
				echo $record ? $record : 0;
			}
			?>]
			<?php
		}
	?>
	];

	data.addRows(daily);

	var chart = new google.visualization.AnnotatedTimeLine(document.getElementById('chart_div'));
	chart.draw(data, {
		displayAnnotations: true,
		scaleColumns: [0, 1],
		scaleType: 'allmaximized'
	});
});
</script>
<div id='chart_div' style='width: 100%; height: 240px; margin-bottom: 1em'></div>

<?php 
if (!is_null($selectedactivity)) {
?>
<h2>Showing activity of type "<?php echo $selectedactivity[0] ?>" (<a href=".">reset</a>)</h2>
<?php
}

if (!is_null($activityuser)) {
?>
<h2>Showing activity for <?php echo $activityuser->getName()?> (<a href=".">reset</a>)</h2>
<?php
}
?>

<table cellpadding="5" cellspacing="0" border="1" width="100%">
<tr><th>Time</th>
<?php
if (is_null($selectedactivity)) {
?>
<th>Activity</th><th>Points</th>
<?php
}

if (is_null($activityuser)) {
?>
<th>User</th>
<?php
}?>
</tr>
<?php
$perpage = 20;
$pagenumber = 0;

if (array_key_exists('page', $_GET)) {
	$pagenumber = $_GET['page'];
}

// TODO get activities only for specific activity

if (!is_null($selectedactivity)) {
	$activities = User::getUsersByActivity($selectedactivityid, $pagenumber, $perpage);
} else if (is_null($activityuser)) {
	$activities = User::getUsersActivity(array_key_exists('all', $_REQUEST), $pagenumber, $perpage);
}
else
{
	$activities = $activityuser->getActivity(array_key_exists('all', $_REQUEST), $pagenumber, $perpage);
}
?>
<tr><td colspan="4">
<?php
if (count($activities) == $perpage) {
	?><a style="float: right" href="?page=<?php echo $pagenumber+1?>">next &gt;&gt;&gt;</a><?php
}
else
{
	?><span style="color: silver; float: right">next &gt;&gt;&gt;</span><?php
}

if ($pagenumber > 0) {
	?><a style="float: left" href="?page=<?php echo $pagenumber-1?>">&lt;&lt;&lt;prev</a><?php
}
else
{
	?><span style="color: silver; float: left">&lt;&lt;&lt;prev</span><?php
}
?>
<span style="float: left; margin: 0 2em">Page <?php echo $pagenumber+1?></span>
</td></tr>
<?php
$now = time();

foreach ($activities as $activity)
{
	$time = $activity['time'];
	$ago = intval(floor(($now - $time)/86400));

	$tz = date_default_timezone_get();

	$user = User::getUser($activity['user_id']);

	?><tr valign="top">
	<td align="right"><?php echo date('M j, h:iA', $time)?> (<?php if ($ago <= 5) {?><span style="color: #00<?php echo sprintf('%02s', dechex((4 - $ago) * 150 / 4 + 50))?>00; font-weight: bold"><?php }?><?php echo $ago?> day<?php echo $ago > 1 ? 's' : '' ?> ago<?php if ($ago <= 5) {?></span><?php }?>)</td>
	<?php
	if (is_null($selectedactivity)) {
	?>
	<td><a href="?activityid=<?php echo $activity['activity_id']?>"><?php $act = UserConfig::$activities[$activity['activity_id']]; echo $act[0] ?></a></td>
	<td><?php echo $act[1] ?></td>
	<?php
	}

	if (is_null($activityuser)) {
	?>
		<td><a href="?userid=<?php echo $user->getID()?>"><?php echo $user->getName();?></a></td>
	<?php
	}?>
</tr><?php
}

?>
<tr><td colspan="6">
<?php
if (count($activities) == $perpage) {
	?><a style="float: right" href="?page=<?php echo $pagenumber+1?>">next &gt;&gt;&gt;</a><?php
}
else
{
	?><span style="color: silver; float: right">next &gt;&gt;&gt;</span><?php
}

if ($pagenumber > 0) {
	?><a style="float: left" href="?page=<?php echo $pagenumber-1?>">&lt;&lt;&lt;prev</a><?php
}
else
{
	?><span style="color: silver; float: left">&lt;&lt;&lt;prev</span><?php
}
?>
<span style="float: left; margin-left: 2em">Page <?php echo $pagenumber+1?></span>

</td></tr>
</table>

</div><?php
require_once(UserConfig::$footer);