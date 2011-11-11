<?php
/**
* Dump MySQL database
*
* @name    MySQLDump
* @author  Daniele Vigan - CreativeFactory.it <daniele.vigano@creativefactory.it>
* @upgrade_and_fixes Krzysztof Kielce
* @version 2.30 - 30/10/2011
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/

class MySQLDump {
	var $database = null;
	var $compress = false;
	var $hexValue = false;
	var $filename = null;
	var $file = null;
	var $isWritten = false;
	var $prefix = "";
	var $bufferSize = "";

	/**
	* Class constructor
	* @param string $db The database name
	* @param string $filepath The file where the dump will be written
	* @param boolean $compress It defines if the output file is compress (gzip) or not
	* @param boolean $hexValue It defines if the outup values are base-16 or not
	* @param boolean $prefix It defines if to dump only tables with a certain prefix
	* @param integer $bufferSizeKB Defines max size of a one file part
	*/
	function MYSQLDump($db = null, $filepath = 'dump.sql', $compress = false, $hexValue = false, $only_tables_with_prefix = "", $bufferSizeKB = 1024)
	{
		$this->compress = $compress;
		$this->prefix = $only_tables_with_prefix;
		$this->bufferSize = $bufferSizeKB * 1024;
		if ( !$this->setOutputFile($filepath) )
			return false;
		return $this->setDatabase($db);
	}

	/**
	* Sets the database to work on
	* @param string $db The database name
	*/
	function setDatabase($db){
		$this->database = $db;
		if ( !@mysql_select_db($this->database) )
			return false;
		return true;
  }

	/**
	* Returns the database where the class is working on
	* @return string
	*/
	function getDatabase(){
		return $this->database;
	}

	/**
	* Sets the output file type (It can be made only if the file hasn't been already written)
	* @param boolean $compress If it's true, the output file will be compressed
	*/
	function setCompress($compress){
		if ( $this->isWritten )
			return false;
		$this->compress = $compress;
		$this->openFile($this->filename);
		return true;
  }

	/**
	* Returns if the output file is or not compressed
	* @return boolean
	*/
	function getCompress(){
		return $this->compress;
	}

	/**
	* Sets the output file
	* @param string $filepath The file where the dump will be written
	*/
	function setOutputFile($filepath){
		if ( $this->isWritten )
			return false;
		$this->filename = $filepath;
		$this->file = $this->openFile($this->filename);
		return $this->file;
  }

	/**
	* Returns the output filename
	* @return string
	*/
	function getOutputFile(){
		return $this->filename;
	}

	/**
	* Writes to file the $table's structure
	* @param string $table The table name
	*/
	function getTableStructure($table){
		if ( !$this->setDatabase($this->database) )
			return false;
		// Structure Header
		$structure = "-- \n";
		$structure .= "-- Table structure for table `{$table}` \n";
		$structure .= "-- \n";
		// Dump Structure
		$structure .= 'DROP TABLE IF EXISTS `'.$table.'`;'."\n";
		
		$row2 = mysql_fetch_row(mysql_query('SHOW CREATE TABLE '.$table));
		$structure.= "\n".$row2[1].";\n";

		$structure .= "\n-- --------------------------------------------------------\n";
		$this->saveToFile($this->file,$structure);
	}

	/**
	* Writes to file the $table's data
	* @param string $table The table name
	* @param boolean $hexValue It defines if the output is base 16 or not
	*/
	function getTableData($table,$hexValue = true) {
		if ( !$this->setDatabase($this->database) )
			return false;
		// Header
		$data = "-- \n";
		$data .= "-- Dumping data for table `$table` \n";
		$data .= "-- \n";

		$records = mysql_query('SHOW FIELDS FROM `'.$table.'`');
		$num_fields = @mysql_num_rows($records);
		if ( $num_fields == 0 )
			return false;
		// Field names
		$selectStatement = "SELECT ";
		$insertStatement = "INSERT INTO `$table` (";
		$hexField = array();
		for ($x = 0; $x < $num_fields; $x++) {
			$record = @mysql_fetch_assoc($records);
			if ( ($hexValue) && ($this->isTextValue($record['Type'])) ) {
				$selectStatement .= 'HEX(`'.$record['Field'].'`)';
				$hexField [$x] = true;
			}
			else
				$selectStatement .= '`'.$record['Field'].'`';
			$insertStatement .= '`'.$record['Field'].'`';
			$insertStatement .= ", ";
			$selectStatement .= ", ";
		}
		$insertStatement = @substr($insertStatement,0,-2).') VALUES';
		$selectStatement = @substr($selectStatement,0,-2).' FROM `'.$table.'`';

		$records = @mysql_query($selectStatement);
		$num_rows = @mysql_num_rows($records);
		$num_fields = @mysql_num_fields($records);
		// Dump data
		if ( $num_rows > 0 ) {
			$data .= $insertStatement;
			for ($i = 0; $i < $num_rows; $i++) {
				$record = @mysql_fetch_assoc($records);
				$data .= ' (';
				for ($j = 0; $j < $num_fields; $j++) {
					$field_name = @mysql_field_name($records, $j);
					$field_meta = @mysql_fetch_field($records, $j);
					$field_type = "";
					$field_default = "";
					if ($field_meta)
					{
						$field_type = $field_meta->type;
						$field_default = $field_meta->type;
					}
					$testt = count($hexField);
					if ( count($hexField)>0 && $hexField[$j] && (@strlen($record[$field_name]) > 0) )
						$data .= "0x".$record[$field_name];
					else
					{
						$fieldValue = @str_replace('\"','"',@mysql_escape_string($record[$field_name]));
						if (@strlen($fieldValue) > 0)
							$fieldValue = "'".$fieldValue."'";
						else
						{
							if ($field_type == 'string')
								$fieldValue = "''";
							else
								$fieldValue = '\N';
						}
						$data .= $fieldValue;
					}
					$data .= ',';
				}
				$data = @substr($data,0,-1).")";
				$data .= ( $i < ($num_rows-1) ) ? ',' : ';';
				$data .= "\n";
				//if data in greather than 1MB, save
				if (strlen($data) > $this->bufferSize) {
					$this->saveToFile($this->file,$data);
					$data = '';
				}
			}
			$data .= "\n-- --------------------------------------------------------\n\n";
			$this->saveToFile($this->file,$data);
		}
	}

  /**
	* Writes to file all the selected database tables structure
	* @return boolean
	*/
	function getDatabaseStructure(){
		$records = @mysql_query('SHOW TABLES');
		if ( @mysql_num_rows($records) == 0 )
			return false;
		$structure = "";
		while ( $record = @mysql_fetch_row($records) ) {
			if (strlen($this->prefix)==0 || $this->startsWith($record[0], $this->prefix))
				$structure .= $this->getTableStructure($record[0]);
		}
		return true;
  }

	/**
	* Writes to file all the selected database tables data
	* @param boolean $hexValue It defines if the output is base-16 or not
	*/
	function getDatabaseData($hexValue = true){
		$records = @mysql_query('SHOW TABLES');
		if ( @mysql_num_rows($records) == 0 )
			return false;
		while ( $record = @mysql_fetch_row($records) ) {
			if (strlen($this->prefix)==0 || $this->startsWith($record[0], $this->prefix))
				$this->getTableData($record[0],$hexValue);
		}
  }

	/**
	* Writes to file the selected database dump
	*/
	function doDump() {
		@mysql_query('FLUSH TABLES WITH READ LOCK');
		try {
			@mysql_query('SET CHARSET UTF8');
			$this->saveToFile($this->file, pack("CCC",0xef,0xbb,0xbf));
			$this->saveToFile($this->file,"--\n-- SQLDump backup file. Created by Q2A Database Backup Plugin.\n");
			$this->saveToFile($this->file,"-- TABLE_PREFIX = `".$this->prefix."`\n--\n\n");
			$this->saveToFile($this->file,"SET NAMES UTF8;\n\n");
			$this->saveToFile($this->file,"SET FOREIGN_KEY_CHECKS = 0;\n\n");
			$this->getDatabaseStructure();
			$this->getDatabaseData($this->hexValue);
			$this->saveToFile($this->file,"SET FOREIGN_KEY_CHECKS = 1;\n\n". "-- ");
			$this->closeFile($this->file);
			@mysql_query('UNLOCK TABLES');
		}
		catch (Exception $e) {
			@mysql_query('UNLOCK TABLES');
			print $e->getMessage(). "<br />";
		}
		return true;
	}
	
  /**
	* @access private
	*/
	function isTextValue($field_type) {
		switch ($field_type) {
			case "tinytext":
			case "text":
			case "mediumtext":
			case "longtext":
			case "binary":
			case "varbinary":
			case "tinyblob":
			case "blob":
			case "mediumblob":
			case "longblob":
				return True;
				break;
			default:
				return False;
		}
	}
	
	/**
	* @access private
	*/
	function openFile($filename) {
		$file = false;
		if ( $this->compress )
			$file = @gzopen($filename, "w9");
		else
			$file = @fopen($filename, "w");
		return $file;
	}

  /**
	* @access private
	*/
	function saveToFile($file, $data) {
		if ( $this->compress )
			@gzwrite($file, $data);
		else
			@fwrite($file, $data);
		$this->isWritten = true;
	}

  /**
	* @access private
	*/
	function closeFile($file) {
		if ( $this->compress )
			@gzclose($file);
		else
			@fclose($file);
	}
	
  /**
	* @access private
	*/
	function startsWith($string, $search)
	{
		return (strncmp($string, $search, strlen($search)) == 0);
	}

  /**
	* @access private
	*/	
	function endsWith($string, $search)
	{
		$length = strlen($search);
		$start  = $length * -1; //negative
		return (substr($string, $start) === $search);
	}
}
?>