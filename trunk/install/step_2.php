<?php
	require('lib/constants.inc.php');
	require('lib/actions.inc.php');
	require('lib/db_schemas.inc.php');
	
	$fail = array();
		
	# Read the default settings either from the previously-entered options, or from the default file
	if (file_exists('./step_2.ini'))
		$defaults = parse_ini_file('./step_2.ini');
	else
		$defaults = parse_ini_file('./defaults.ini');
	
	# save the previous page settings
	if ($_REQUEST['STEP'] == 1)
		if( ($ret = write_SLAM_options( './step_1.ini' )) != true )
			$fail[] = "Could not save your progress. Please contact your system administrator: $ret";
?>
<html>
	<head>
		<title>SLAM installer - Step 2</title>
		<link type='text/css' href='css/install.css' rel='stylesheet' />
		<script type='text/javascript' src='js/check.js'></script>
		<script type='text/javascript' src='../js/convert.js'></script>
	</head>
	<body><div id='container'>
		<div id='installerTitle'><span style='font-family:Impact'>SLAM</span> installer - Step 2</div>
		<div id='installerVer'>Version: <?php print($slam_version) ?></div>
<?php
	foreach( $fail as $text )
		print "<div class='fatalFail'>$text</div>\n";		
?>		
		<form name='foward' action='step_3.php' method='post'>
			<input type='hidden' name='STEP' value='2' />
			<table id='configTable'>
				<tr>
					 <td class='helpHeader' colspan="2">For assistance, please refer to the SLAM [<a href='http://code.google.com/p/slam-project/wiki/Installation' target='_new'>installation wiki</a>].</td>
				</tr>
				<tr>
					<td class='inputCategory' colspan='2'>Default Categories</td>
				</tr>
				<tr>
					 <td class='categoryInfo' colspan="2">The installer can set up some asset categories for you automatically. Select which categories you would like to install at this time:</td>
				</tr>
			</table>
			<table id='optionalCategoriesTable'>
				<tr>
					<td class='optionalCategoryBox' style="color: #045FB4;font-weight:bold">&nbsp;</td>
					<td class='optionalCategoryName' style="color: #045FB4;font-weight:bold">Name</td>
					<td class='optionalCategoryPrefix' style="color: #045FB4;font-weight:bold">Prefix</td>
					<td class='optionalCategoryDescription' style="color: #045FB4;font-weight:bold">Description</td>
				</tr>
			
<?php
	$table_names = array_keys($sql_create_optional);

	foreach( $table_names as $name )
	{
		$index = array_search( $name, $defaults['SLAM_OPTIONAL_TABLES'] );
		
		print "<tr>\n";
		print "<td class='optionalCategoryBox'><input type='checkbox' name='SLAM_OPTIONAL_TABLES[]' value='".base64_encode($name)."' ";
		if ($index !== false)
			print "checked='checked' ";
		print "/></td>\n";
		print "<td class='optionalCategoryName'>$name</td>\n";
		if( ($prefix = $defaults['SLAM_OPTIONAL_PREFIX'][$index]) == '')
			$prefix = $sql_create_optional[$name]['prefix'];
		print "<td class='optionalCategoryPrefix'><input type='text' size='2' name='SLAM_OPTIONAL_PREFIX[]' value='$prefix'/></td>\n";
		print "<td class='optionalCategoryDescription'>".$sql_create_optional[$name]['description']."</td>\n";
		print "</tr>\n";
	}
?>
			</table>
			<div class='actionButtons'>
				<input type='submit' class='submitButton' value='Save these settings and Continue' />
			</div>
		</form>
		<form name='back' action='step_1.php' method='post'>
			<div class='actionButtons'>
				<input type='submit' class="submitButton" value='Cancel these settings and Go Back' />
			</div>
		</form>
	</div></body>
</html>