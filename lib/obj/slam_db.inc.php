<?php

class SLAMdb
{
	public $link = null;
	public $tables = array();

	private $config;
	private $required_fields = array('Serial','Identifier','Removed');
	private $pdo_options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_BOTH,
		PDO::ATTR_EMULATE_PREPARES => false,
	];

	public function __construct(&$config)
	{
		$this->config = $config;

		if( $error = ($this->Connect( $config->values['db_server'],$config->values['db_port'],$config->values['db_user'],$config->values['db_pass'],$config->values['db_name'],$config->values['db_charset'])) !== true)
			die('Database error: Could not connect: '.$error);
		
		if(!($this->tables = $this->GetTables()))
			die ('Database error: Could not get list of categories'.$this->errorState());

		if (!in_array($config->values['user_table'],$this->tables))
			die ("Database error: required user table \"{$config->values['user_table']}\" not found.");
		
		if (!in_array($config->values['category_table'],$this->tables))
			die ("Database error: required category table \"{$config->values['category_table']}\" not found.");
				
		if (!in_array($config->values['perms_table'],$this->tables))
			die ("Database error: required permissions table \"{$config->values['perms_table']}\" not found.");
		
		if (!in_array($config->values['projects_table'],$this->tables))
			die ("Database error: required projects table \"{$config->values['projects_table']}\" not found.");
			
		/* load category information from the SLAM_Categories table */
		$this->loadCategories($config);
		
		/* load project information */
		$this->loadProjects($config);
	}
	
	public	function Connect($server,$dbport,$dbuser,$dbpass,$dbname,$charset)
	{
		/*
			attempts to connect to a server and database
			returns true on success, false otherwise
		*/
		
		try{
			$this->link = new PDO("mysql:host={$server};port={$dbport};dbname={$dbname};charset={$charset}",$dbuser,$dbpass,$this->pdo_options);
		}catch (PDOException $e){
			return $e;
		}
		
		return true;
	}

	public function Quote($a)
	{
		/*
			recursively quote an array or a string
		*/
	
		if (!is_array($a)) {
			return substr($this->link->quote($a), 1, -1);
		}
		
		foreach($a as $k => $v) {
			$a[$k] = (is_array($v)) ? $this->Quote($v) : substr($this->link->quote($v), 1, -1);
		}
		
		return $a;
	}
	
	private	function loadCategories(&$config)
	{
		/* retrieve all the category info from the specified category table */

		if(($data = $this->Query("SELECT * FROM {$config->values['category_table']}")) === false)
			die('Fatal error: could not retrieve category information. Please contact your system administrator: '.$this->ErrorState());
		
		$results = $data->fetchAll();
		if (count($results) < 1)
			die('Fatal error: Your category database table contains no categories. Please add a category or contact your system administrator.');
		
		/* iterate through all of the categories */
		foreach( $results as $category )
		{
			$fields = array_keys($this->GetStructure($category['Name']));
			
			/* are there any required fields that are not found in the current category? */
			$diff = array_diff($this->required_fields,array_intersect($this->required_fields,$fields));
			
			if(count($diff)>0)
			{				
				foreach($diff as $error)
					$config->errors[] = "Table \"{$category['Name']}\" is missing required attribute \"$error\".";
			}
			elseif( strlen($category['Name']) == 0 )
			{
				$config->errors[] = "A table with no name has been encountered.";
				continue;
			}
			elseif( strlen($category['Prefix']) != 2 )
			{
				$config->errors[] = "Table \"{$category['Name']}\" has a prefix that is not two characters long.";
				continue;
			}
			else
			{				
				$config->categories[ $category['Name'] ]['prefix'] = $category['Prefix'];
				$config->categories[ $category['Name'] ]['field_order'] = explode(',',$category['Field Order']);
				$config->categories[ $category['Name'] ]['list_fields'] = explode(',',$category['List Fields']);
				$config->categories[ $category['Name'] ]['title_field'] = $category['Title Field'];
				$config->categories[ $category['Name'] ]['owner_field'] = $category['Owner Field'];
				$config->categories[ $category['Name'] ]['date_field'] = $category['Date Field'];
				$config->values['lettercodes'][ $category['Prefix'] ] = $category['Name'];
			}
		}
				
		// define the regex
		$config->values['identifier_regex'] = "/([A-Za-z][A-Za-z])(".implode('|',array_keys($config->values['lettercodes'])).")[_]?(\d+)/";		
	}
	
	private function loadProjects(&$config)
	{
		if(($results = $this->Query("SELECT * FROM {$config->values['projects_table']}")) === false)
			die('Fatal error: could not retrieve project information. Please contact your system administrator: '.$this->ErrrorState());
		
		foreach( $results as $project )
			$config->projects[] = $project['Name'];

		return;
	}
	
	public	function GetTables()
	{
		/*
			returns all tables in the current database
		*/
		
		if(!($results = $this->Query('SHOW TABLES')))
			return false;
		
		$return = array();
		foreach( $results as $row )
			$return[] = $row[0];
		
		return $return;
	}
	
	public	function GetStructure($t)
	{
		/*
			returns information about the structure of the specified table, otherwise returns false on failure
		*/
		
		if(!($results = $this->Query("SHOW FULL COLUMNS FROM `$t`")))
			return false;

		$fields = array();
		foreach( $results as $row )
		{
			$fields[$row['Field']]['name'] = $row['Field'];
			$fields[$row['Field']]['comment'] = $row['Comment'];
			$fields[$row['Field']]['default'] = $row['Default'];
			$fields[$row['Field']]['null'] = ($row['Null'] == 'YES') ? true : false;
			if (preg_match('/^(enum|set)\((.*)\)$/',$row['Type'],$m)>0)
			{
				$fields[$row['Field']]['type'] = $m[1];
				$fields[$row['Field']]['values'] = explode(',',str_replace('\'','',$m[2]));
			}
			elseif (preg_match('/^(\w+)\((\d+)\)$/',$row['Type'],$m)>0)
			{
				$fields[$row['Field']]['type'] = $m[1];
				$fields[$row['Field']]['size'] = $m[2];
			}
			else
				$fields[$row['Field']]['type'] = $row['Type'];
		}

		return $fields;
	}
		
	public	function GetRecords($q,$vert=true)
	{
		/*
			returns records specified in the query in one of two formats:
			vertical: numerical array of records => associative array of fields
			horizontal: associative array of fields => numerical array of records
		*/

		if(($results = $this->Query($q)) === false)
			return false;
			
		$return = array();
		while( $row = $results->fetch(PDO::FETCH_ASSOC) )
		{
			if ($vert)
				$return[] = $row;
			else
				foreach($row as $key => $value)
					$return[$key][] = $value;
		}
		return $return;
	}
	
	public	function Query($q){
		try {
			$response = $this->link->query($q);
		} catch (PDOException $e) {
			$this->config->errors[] = $e->getMessage();
			return false;
		}
		return $response;
	}
	
	public	function Disconnect(){
		$this->link = null;
	}
	
	public function ErrorState($i=2)
	{
		$a = $this->link->errorInfo();
		return $a[$i];
	}
}

?>
