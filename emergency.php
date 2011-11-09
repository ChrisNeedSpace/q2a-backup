<?php

class EmergencyRestore
{
var $dbhost = '127.0.0.1';
var $dbuser = 'masterqa';
var $dbpsw = 'admin';
var $dbname = 'qadb';

var $backupPath = "../../qa-content/backup/";
	
function doConnect()
{
	if(isset($_POST['emergency_host']))
		$this->dbhost = $_POST['emergency_host'];
	if(isset($_POST['emergency_user']))
		$this->dbuser = $_POST['emergency_user'];
	if(isset($_POST['emergency_pass']))
		$this->dbpsw = $_POST['emergency_pass'];
	if(isset($_POST['emergency_db']))
		$this->dbname = $_POST['emergency_db'];
	
	//Connects to mysql server
	$connect = @mysql_connect($this->dbhost,$this->dbuser,$this->dbpsw);
	if (!$connect) {
		$_POST['emergency_error_connect'] = "Could not connect to database/host.";
		return false;
	}
	else
	{
		$_POST['emergency_connected'] = "Connected successfully.";
		return true;
	}
}

function setUpImport($path, &$error)
{
	require_once('mysqldump.php');
	$dumper = new MySQLDump($this->dbname,'q2a.sql',false,false);
}

function launchFile(&$error)
{
	$path = $this->getImportFilePath();
	// read the file
	$file = null;
	
	if (!file_exists($path))
	{
		$error = "Import aborted.";
		return;
	}
	
	$compressed = $this->endsWith(strtolower($path), ".gz");
	if ($compressed)
		$file = gzopen($path, "r");
	else
		$file = fopen($path, "r");
	if(!$file) 
	{
		$error = "Error opening data file: ". $path;
		return;
	}
	$size = filesize($path);
	if(!$size)
	{
		$error = "File is empty: ". $path;
		return;
	}
	
	// debug
	// $file2 = fopen($path."a", "w");
	// fwrite($file2, $content);
	// fclose($file2);
	
	
	$lineNum = 0;
	$query = "";
	$line = "";
	$lineseparator = "\n";
	$lastIsComment = false;
	while ( ! feof( $file ) )
	{
		if ($compressed)
			$line = gzread($file, 1024);
		else
			$line = fgets( $file, 1024);
		$line = str_replace("\r","",$line);
		
		
		$lines = explode($lineseparator,$line);
		foreach($lines as $lineExpl)
		{
			$lineNum++;
			$lineExpl = trim($lineExpl," \t");
			// omit first line (problem was with byte order mark)
			if ($lineNum>1 && $lineExpl)
			{
				if (!$this->startsWith($lineExpl, "--") && !$lastIsComment)
					$query .= $lineExpl;
				else
					$lastIsComment = true;
			}
			if ($this->endsWith($query, ";"))
			{
				mysql_query($query);
				$query = "";
			}
		}

	}
	
	if ($compressed)
		gzclose($file);
	else
		fclose($file);
}

function getImportFilePath()
{
	if (file_exists($this->backupPath.'q2a.gz'))
		return $this->backupPath.'q2a.gz';
	else if (file_exists($this->backupPath.'q2a.sql'))
		return $this->backupPath.'q2a.sql';
}

function startsWith($string, $search)
{
	return (strncmp($string, $search, strlen($search)) == 0);
}

function endsWith($string, $search)
{
	$length = strlen($search);
	$start  = $length * -1; //negative
	return (substr($string, $start) === $search);
}
}
	$emer = new EmergencyRestore();
	$connected = false;
	if(isset($_POST['emergency_host']))
		$connected = $emer->doConnect();
	if ($connected)
	{
		$error = "";
		$emer->launchFile($error);
		$_POST['emergency_error'] = $error;
		
	}

?>
<html>
<body>




<h1>Q2A Emergency Restore Tool</h1>
<form action="./emergency.php" method="post" >
	<h4>1. Set up your database connection:</h4>
	<table style="" border="0">
	<?php
		echo '<tr>' .
				'<td style="width:150px;">Database host:</td>' .
				'<td><input type="text" name="emergency_host" value="'.(@$_POST['emergency_host'] ? $_POST['emergency_host'] : "127.0.0.1").'" /></td>' .
			'</tr>' .
			'<tr>' .
				'<td>User:</td>' .
				'<td><input type="text" name="emergency_user" value="'.(@$_POST['emergency_user'] ? $_POST['emergency_user'] : "masterqa").'" /></td>' .
			'</tr>' .
			'<tr>' .
				'<td>Password:</td>' .
				'<td><input type="text" name="emergency_pass" value="'.(@$_POST['emergency_pass'] ? $_POST['emergency_pass'] : "admin").'" /></td>' .
			'</tr>' .
			'<tr>' .
				'<td>Database:</td>' .
				'<td><input type="text" name="emergency_db" value="'.(@$_POST['emergency_db'] ? $_POST['emergency_db'] : "qadb").'" /></td>' .
			'</tr>' .
			'<tr><td colspan="2">' .
				'<h3 style="color:#f00;">'.@$_POST['emergency_error_connect'].'</h3>' .
				'<h3 style="color:#0a0;">'.@$_POST['emergency_connected'].'</h3>' .
			'</td></tr>';

	echo '<tr><td colspan="2"><h4>2. Upload your import file to "'.realpath($emer->backupPath).'" folder and name it "q2a.gz" or "q2a.sql".</h4></td></tr>';
	if ($emer->getImportFilePath())
		echo '<tr><td><h3 style="color:#0a0;">File found.</h3></td></tr>';
	else
		echo '<tr><td><h3 style="color:#f00;">File not found.</h3></td></tr>';
		
	echo "<tr><td><h4>3. Do the Import.</h4></td></tr>";
	
	if (isset($_POST['emergency_error']))
	{
		if (@$_POST['emergency_error'])
			echo '<tr><td><h3 style="color:#f00;">'.@$_POST['emergency_error'].'</h3></td></tr>';
		else
			echo '<tr><td><h3 style="color:#0a0;">Import done.</h3></td></tr>';
	}
	echo 	'<tr>' .
				'<td>&nbsp;</td>' .
				'<td><input type="submit" name="emergency_restore" value="Import file !" /></td>' .
			'</tr>' .
	'</table>' .
	'</form>';

	?>


<h3 style="color:#f00;"><?php echo @$_POST['emergency_errors']; ?></h3>


</body>
</html>