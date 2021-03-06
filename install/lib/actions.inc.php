<?php

function write_SLAM_options( $filename )
{
	#
	# writes all options starting with "SLAM_" to a specified filename
	#
	
	$output = '';
	foreach( $_REQUEST  as $key=>$value )
	{
		if( strpos($key, 'SLAM_') === 0 )
		{
			if( is_array($value) )
				foreach( $value as $v )
					$output .= "{$key}[]=\"$v\"\n";
			else
				$output .= "$key=\"$value\"\n";
		}
	}
	
	if( file_put_contents( $filename, $output ) === false)
		return "Could not write option file.";
	
	return true;
}

function get_SLAM_options()
{
	$ret = array();
	$ini = array();
	
	if( ($ini[] = parse_ini_file('./step_1.ini')) === false)
		$ret['1'] =  "Could not read option file from step 1";
	if( ($ini[] = parse_ini_file('./step_2.ini')) === false)
		$ret['2'] =  "Could not read option file from step 2";
	if( ($ini[] = parse_ini_file('./step_3.ini')) === false)
		$ret['3'] = "Could not read option file from step 3";
	if( ($ini[] = parse_ini_file('./step_4.ini')) === false)
		$ret['4'] = "Could not read option file from step 4";
	
	if( count($ret) > 0 )
		return $ret;
	
	return $ini;
}

function check_SLAM_options()
{
	$errors = array();

	if( count($ini = get_SLAM_options()) < 4)
	{
		$not_present = array_intersect( array_keys($ini), array('1','2','3','4') );
		foreach($not_present as $step)
			$errors["Step $step"] = "Not completed.";
		return $errors;
	}
	
	$options = array_merge($ini[0], $ini[1], $ini[2], $ini[3] );
	
	/* step 1 options */
	$path = rtrim( $options['SLAM_CONF_PATH'],DIRECTORY_SEPARATOR);
	if( ($ret = checkDirectoryIsRW( $path )) !== true )
		$errors['Step 1 A'] ="SLAM installation path error: $ret";
	if (strlen($options['SLAM_CONF_PREFIX']) != 2)
		$errors['Step 1 B'] = 'SLAM lab prefix must be precisely 2 characters';
	
	if( ($ret = checkDbOptions( $options['SLAM_DB_HOST'], $options['SLAM_DB_PORT'], $options['SLAM_DB_NAME'], $options['SLAM_DB_USER'], $options['SLAM_DB_PASS'], $options['SLAM_DB_CHARSET'] )) !== true )
		$errors['Step 1 C'] = "SLAM database connection error: {$ret[0]}";
	
	$arch_path = rtrim( base64_decode($options['SLAM_FILE_ARCH_DIR']),DIRECTORY_SEPARATOR);
	$temp_path = rtrim( base64_decode($options['SLAM_FILE_TEMP_DIR']),DIRECTORY_SEPARATOR);
	
	if( ($ret = checkFileOptions( $arch_path, $temp_path )) !== true )
		$errors['Step 1 D'] = "SLAM attached file settings: {$ret[0]} {$ret[1]}";
		
	/* step 4 options */
	if( $options['SLAM_ROOT_NAME'] == '')
		$errors['Step 4 A'] = "Must specify a root user.";
	if( $options['SLAM_ROOT_PASS_1'] != $options['SLAM_ROOT_PASS_2'] )
		$errors['Step 4 B'] = "Root user passwords do not match.";
	if( $options['SLAM_ROOT_PASS_1'] == '')
		$errors['Step 4 B'] = "Must specify a root password.";

	return $errors;
}

function write_SLAM_config( )
{
	/* from db_schemas.inc.php */
	global $sql_create_required;
	global $sql_create_optional;
	global $pdo_options;
	
	/* check for the presence of the template files */
	$fail = array();
	if( ($config_ini = file_get_contents( './configuration.ini' )) == false)
		$fail[] = "Could not read configuration file template.";
	if( ($prefs_ini = file_get_contents( './preferences.ini' )) == false)
		$fail[] = "Could not read preference file template.";
	if( count($fail) > 0 )
		return $fail;
	
	/* do a last check of the saved options before continuining */
	$fail = check_SLAM_options();
	if( count($fail) > 0 )
		return $fail;
	
	/* retrieve all the saved options */
	$ini = get_SLAM_options();
	$options = array_merge($ini[0], $ini[1], $ini[2], $ini[3] );
		
	/* try and connect to the database */
	$server = $options['SLAM_DB_HOST'];
	$port = $options['SLAM_DB_PORT'];
	$dbname = $options['SLAM_DB_NAME'];
	$charset = $options['SLAM_DB_CHARSET'];
	$dbuser = $options['SLAM_DB_USER'];
	$dbpass = $options['SLAM_DB_PASS'];
	
	
	try {
		$pdo = new PDO("mysql:host={$server};port={$port};dbname={$dbname};charset={$charset}",$dbuser,$dbpass,$pdo_options);
	} catch (PDOException $e) {
		return array("Could not connect to the database with the provided credentials:$e");
	}
	
	/* create the required tables */
	foreach( $sql_create_required as $table ){
		try {
			$pdo->query($table['sql']);
		} catch (PDOException $e) {
			return array($e);
		}	
	}
	
	/* step 1 options */
	$options['SLAM_CONF_PATH'] = rtrim($options['SLAM_CONF_PATH'],DIRECTORY_SEPARATOR);
	$options['SLAM_FILE_ARCH_DIR'] = rtrim($options['SLAM_FILE_ARCH_DIR'],DIRECTORY_SEPARATOR);
	$options['SLAM_FILE_TEMP_DIR'] = rtrim($options['SLAM_FILE_TEMP_DIR'],DIRECTORY_SEPARATOR);
	
	if( !is_dir($options['SLAM_FILE_ARCH_DIR']) )
		if( !mkdir($options['SLAM_FILE_ARCH_DIR']) )
			return( array("Could not create {$options['SLAM_FILE_ARCH_DIR']}."));
			
	if( !is_dir($options['SLAM_FILE_TEMP_DIR']) )
		if( !mkdir($options['SLAM_FILE_TEMP_DIR']) )
			return( array("Could not create {$options['SLAM_FILE_TEMP_DIR']}."));
			
	/* step 2 options */
	/* create the optional categories */
	foreach( $options['SLAM_OPTIONAL_INSTALL'] as $i )
	{
		$name = base64_decode($options['SLAM_OPTIONAL_TABLE'][$i]);
		$prefix = $options['SLAM_OPTIONAL_PREFIX'][$i];
		$sql = $sql_create_optional[ $name ]['sql'];
		
		try {
			$pdo->query($sql);
		} catch (PDOException $e) {
			return array($e);
		} 
		
		/* don't add the template category to the category list */
		if( $name == 'Template' )
			continue;
		
		/* add the categories to the category table */
		try {
			SLAM_write_to_table( $pdo, 'SLAM_Categories', array('Name'=>$name,'Prefix'=>$prefix) );
		} catch (PDOException $e) {
			return array($e);
		} 
	}
	
	/* step 3 options */
	if( $options['SLAM_CUSTOM_PROJECT'] != 'true' )
		$options['SLAM_CUSTOM_PROJECT'] = 'false';
	
	foreach( $options['SLAM_PROJECT_NAME'] as $name ){
		try {
			SLAM_write_to_table( $pdo, 'SLAM_Projects', array('Name'=>$name) );
		} catch (PDOException $e) {
			return array($e);
		}
	}
	
	/* step 4 options */
		
	/* make the superuser account */
	$salt = substr(str_shuffle(MD5(microtime())), 0, 8);
	$crypt = sha1($salt.$options['SLAM_ROOT_PASS_1']);
	try {
		SLAM_write_to_table( $pdo, 'SLAM_Researchers', array('username'=>$options['SLAM_ROOT_NAME'],'email'=>$options['SLAM_ROOT_EMAIL'],'crypt'=>$crypt,'salt'=>$salt,'superuser'=>'1') );
	} catch (PDOException $e) {
		return array($e);
	}
	
	/* create the other accounts */
	$errors = array();
	foreach( $options['SLAM_USERS'] as $index=>$name )
	{
		# prevent an all-whitespace user
		if( preg_replace('/\s+/','',$name) == '' )
			continue;
			
		$email = $options['SLAM_EMAILS'][ $index ];
		$salt = substr(str_shuffle(MD5(microtime())), 0, 8);
		$crypt = sha1($salt.$options['SLAM_PASSWORDS'][$index]);
		if( is_array($options["SLAM_USER_PROJECTS_{$index}"]) )
			$projects = implode(',',$options["SLAM_USER_PROJECTS_{$index}"]);
		else
			$projects = '';
		
		try {
			SLAM_write_to_table( $pdo, 'SLAM_Researchers', array('username'=>$name,'email'=>$email,'crypt'=>$crypt,'salt'=>$salt,'superuser'=>'0','projects'=>$projects) );
		} catch (PDOException $e) {
			return array($e);
		}
	}

	if( count($errors) > 0 )
		return $errors;
	
	/* all done with the database */
	$pdo = null;
	
	# write all of the simple replacements
	foreach( $options as $key=>$value )
	{
		$config_ini = str_replace( $key, $value, $config_ini );
		$prefs_ini = str_replace( $key, $value, $prefs_ini );
	}
	
	# get installation path
	$path = $options['SLAM_CONF_PATH'];

	if( file_put_contents( $path.DIRECTORY_SEPARATOR."configuration.ini", $config_ini) === false )
		return array("Could not write configuration file.");
	if( file_put_contents( $path.DIRECTORY_SEPARATOR."preferences.ini", $prefs_ini) === false)
		return array("Could not write preferences file.");
	
	if( !unlink('step_1.ini') || !unlink('step_2.ini') || !unlink('step_3.ini') || !unlink('step_4.ini') )
		return array("Could not remove step setup files.");
	
	return true;
}

function update_auto_defaults(&$defaults) {
	/*
		Appropriately sets any 'auto' values in default.ini values
	*/

	/* database defaults - AWS detection of env RDS */
	if($_SERVER['RDS_HOSTNAME']){
		$defaults['SLAM_DB_HOST'] = $_SERVER['RDS_HOSTNAME'];
		$defaults['SLAM_DB_PORT'] = $_SERVER['RDS_PORT'];
		$defaults['SLAM_DB_NAME'] = $_SERVER['RDS_DB_NAME'];
		$defaults['SLAM_DB_USER'] = $_SERVER['RDS_USERNAME'];
		$defaults['SLAM_DB_PASS'] = $_SERVER['RDS_PASSWORD'];
	}
	
	if ($defaults['SLAM_CONF_PATH'] == 'auto'){
		$defaults['SLAM_CONF_PATH'] = str_replace(DIRECTORY_SEPARATOR.'install','',dirname(realpath($_SERVER['SCRIPT_FILENAME'])));
	}
		
	if ($defaults['SLAM_CONF_HEADER'] == 'auto')
		$defaults['SLAM_CONF_HEADER'] = 'From: SLAM <'.$_SERVER['SERVER_ADMIN'].'>';
	
	if ($defaults['SLAM_FILE_ARCH_DIR'] == 'auto')
		$defaults['SLAM_FILE_ARCH_DIR'] = str_replace(DIRECTORY_SEPARATOR.'install',DIRECTORY_SEPARATOR.'slam_files',dirname(realpath($_SERVER['SCRIPT_FILENAME'])));
	if ($defaults['SLAM_FILE_TEMP_DIR'] == 'auto')
		$defaults['SLAM_FILE_TEMP_DIR'] = str_replace(DIRECTORY_SEPARATOR.'install',DIRECTORY_SEPARATOR.'slam_files'.DIRECTORY_SEPARATOR.'temp',dirname(realpath($_SERVER['SCRIPT_FILENAME'])));

}

?>
