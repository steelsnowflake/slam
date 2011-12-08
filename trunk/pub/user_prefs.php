<?php
	set_include_path(get_include_path().PATH_SEPARATOR.'../');
	require('../lib/slam_index.inc.php');
	
	$config	= new SLAMconfig();
	$db		= new SLAMdb($config);
	$user	= new SLAMuser($config,$db);
?>
<form name='userOptions' id='userOptions' action='' method='POST'>
	<div id='setUserOptions'>
		<input type='hidden' name='a' value='user' />
		<input type='hidden' name='user_action' value='set_preferences' />
		<table>
			<tr>
				<td style='text-align:right;font-weight:bold'>Username:</td>
				<td>
					<input readonly='readonly' type='text' size='20' value='<?php echo($user->values['username']) ?>' />
				</td>
			</tr>
			<tr>
				<td style='text-align:right;vertical-align:top;font-weight:bold'>Groups:</td>
				<td>
					<textarea readonly='readonly' cols='17' rows='4'>
<?php
	foreach ($user->values['groups'] as $group)
		echo $group."\n";
?>
					</textarea>
				</td>
			</tr>
			<tr>
				<td style='text-align:right;font-weight:bold'>Email:</td>
				<td>
					<input readonly='readonly' type='text' size='20' value='<?php echo($user->values['email']) ?>' id='user_email' />
				</td>
			</tr>
			<tr>
				<td colspan='2' style='text-align:left;padding-top:10px;font-weight:bold'>By default, new assets are:</td>
			</tr>
			<tr>
				<td colspan='1' style='text-align:right'>In project</td>
				<td style='text-align:left'>
<?php

	/* get the user's default project from his preferences */
	$current = empty($user->prefs['default_project']) ? 'Other' : $user->prefs['default_project'];
	
	/* fill out the project menu with options from the config */
	$options = array_combine($config->values['default_project'],$config->values['default_project']);
	
	$selected = (in_array($current,$options) || ($current == '')) ? $current : 'Other';
	$vis = (in_array($current,$options) || ($current == '')) ? "style='display:none'" : ''; // hide the other input text box if the option is in the menu

	/* make the "other" project input field */
	if($config->values['novel_projects'])
	{
		$options['Other'] = 'Other'; //Append "Other" option to menu
		$input = SLAM_makeInputHTML($v,10,10,"name='defaultProject' id='defaultProject' $vis",false);
	}
	else
		$input = SLAM_makeInputHTML($v,10,10,"name='defaultProject' id='defaultProject' $vis",true);

	echo SLAM_makeMenuHTML($selected,$options,"name='user_projectMenu' onChange=\"doUserPreferencesProjectMenu(this.options[this.selectedIndex].value, 'defaultProject')\"",false,false);
	echo $input;
?>
				</td>
			</tr>
			<tr>
				<td colspan='2' style='text-align:right'>Editable by
<?php
	if (empty($user->prefs['newEntry_EditPermissions']))
		$user->prefs['newEntry_EditPermissions'] = 0;

	$options = array('Just me'=>0,'Me and my groups'=>1,'Anyone'=>2);
	echo SLAM_makeMenuHTML($user->prefs['default_entryEditable'],$options,"name='defaultEditable'",false);
?>
				</td>
			</tr>
			<tr>
				<td colspan='2' style='text-align:right'>Readable by
<?php
	if (empty($user->prefs['newEntry_ReadPermissions']))
		$user->prefs['newEntry_ReadPermissions'] = 2;

	echo SLAM_makeMenuHTML($user->prefs['default_entryReadable'],$options,"name='defaultReadable'",false);
?>
				</td>
			</tr>
			<tr>
				<td colspan='2' style='text-align:right;padding-top:10px'>
					<input type='submit' value='Save' />
					<input type='button' value='Cancel' onClick="removeBodyId('userDiv')" />
				</td>
			</tr>
		</table>
	</div>
</form>