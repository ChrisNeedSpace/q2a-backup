<?php

/*
	Question2Answer (c) 2011, Gideon Greenspan

	http://www.question2answer.org/

	
	File: qa-plugin/Kielce-backup/qa-backup.php
	Version: (see qa-plugin.php)
	Description: Module class for DB Backup plugin


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://www.question2answer.org/license.php
*/

class qa_backup {
	
	var $urltoroot;
	var $currentUrlDir;
	var $savedpath = "";
	var $error = "";
	var $msg = "";
	var $backupDirUrl  ="";
	var $fileCount = 0;
	var $listedFiles = "";
	var $backupDir = "";
	
	function load_module($directory, $urltoroot)
	{
		$this->urltoroot=$urltoroot;
		$this->currentUrlDir = $directory;
	}
	
	function option_default($option)
	{
		if ($option=='backup_file_max_size') {
			return 1048576;
		}
		if ($option=='backup_only_qa_tables_enable') {
			return true;
		}
		
	}
	
	function admin_form(&$qa_content)
	{
		require_once QA_INCLUDE_DIR.'qa-app-blobs.php';
				
		global $qa_root_url_relative, $QA_CONST_PATH_MAP;
		
		$this->backupDirUrl = $qa_root_url_relative . "qa-content/backup/";
		$this->backupDir = QA_BASE_DIR."qa-content/backup/";
		if (!is_dir($this->backupDir))
			mkdir($this->backupDir, 0755);

		if (qa_clicked('backup_send_upload_file')) {
			$this->saveChanges();
			if (isset($_FILES["backup_upload_file"]))
				$this->uploadFile();
		}

		if (qa_clicked('backup_export_button')) {
			$this->saveChanges();
			$this->doBackup();
			$this->msg = 'Database Backup done.';
		}

		if (qa_clicked('backup_delete_files')) {
			$this->saveChanges();
			$this->doDelete();
		}

		$this->listedFiles = $this->listFiles();

		if (qa_clicked('backup_import_button')) {
			$this->saveChanges();
			$this->doImport();
			$this->msg = 'Database Import done';
		}
		
		
		return array(
			 'ok' => $this->error ? '<span style="color:#f00;">ERROR: '.$this->error.'</span>' : ($this->msg ? $this->msg : null),
			
			'fields' => array(
				array(
					'label' => $this->listedFiles,
					'type' => 'static',
					'value' => '<input type="file" name="backup_upload_file" onmouseout="this.className=\'qa-form-tall-button qa-form-tall-button-0\';" onmouseover="this.className=\'qa-form-tall-hover qa-form-tall-hover-0\';" class="qa-form-tall-button qa-form-tall-button-0" /> '.
							   '<input type="submit" value="Upload to server" name="backup_send_upload_file" onmouseout="this.className=\'qa-form-tall-button qa-form-tall-button-0\';" onmouseover="this.className=\'qa-form-tall-hover qa-form-tall-hover-0\';" onclick="bck_t=\'\'" class="qa-form-tall-button qa-form-tall-button-0" /><br />'.
							   '<input type="submit" value="Delete all files" name="backup_delete_files" onmouseout="this.className=\'qa-form-tall-button qa-form-tall-button-0\';" onmouseover="this.className=\'qa-form-tall-hover qa-form-tall-hover-0\';" onclick="bck_t=\'delete all files from a backup folder\'" class="qa-form-tall-button qa-form-tall-button-0" />',
						
					'tags' => 'NAME="backup_importFile"',
				),
				array(
					'label' => 'Compress backups.',
					'type' => 'checkbox',
					'value' => qa_opt('backup_compress_enable'),
					'tags' => 'NAME="backup_compress_enable"',
				),
				array(
					'label' => 'Backup Q2A tables only - those with `'.QA_MYSQL_TABLE_PREFIX.'` prefix. <br />If you uncheck this option, you will backup whole batabase. <br />(Useful for more than one Q2A instance in one database).',
					'type' => 'checkbox',
					'value' => qa_opt('backup_only_qa_tables_enable'),
					'tags' => 'NAME="backup_only_qa_tables_enable"',
				),
				// Not yet implemented !
				// array(
					// 'id' => 'wysiwyg_editor_upload_max_size_display',
					// 'label' => 'Maximum single backup file size in MB:',
					// 'type' => 'number',
					// 'value' => $this->bytes_to_mega_html(qa_opt('backup_file_max_size')),
					// 'tags' => 'NAME="backup_file_max_size_field"',
				// ),
				array(
					'label' => '<span style="color:#f99; font-size:20px; text-align:center;">Caution! Use carefully. Risk of losing all your data.<br />'.
							   '<span style="font-size:15px;">(While restoring, it is always recommended to <a href="'.$this->urltoroot.'README.rst" target="backup_readme">restore in a safe way</a>).<br />In case of problems, see <a href="'.$this->urltoroot.'README.rst" target="backup_readme">TROUBLESHOOTING</a> section.</span></span>',
					'type' => 'custom',
					'tags' => 'NAME="backup_import_label"',
				),
			),
			
			'buttons' => array(
				array(
					'label' => 'Do the backup !',
					'tags' => 'NAME="backup_export_button" onmouseup="bck_t=\'\'"',
				),
				array(
					'label' => 'Import selected file !',
					'tags' => 'NAME="backup_import_button" onmouseup="bck_t=\'execute EVERYTHING that is in the selected file. If you import wrong file, you can have mess in your database or even data loss.\nIt is recommended to make a backup first.\n\nNOTE: Backup files done with this plugin delete previous data and then do the import\'"',
				),
			),
		);
	}
	
	function saveChanges()
	{
		qa_opt('backup_compress_enable',(bool)qa_post_text('backup_compress_enable'));
		qa_opt('backup_only_qa_tables_enable',(bool)qa_post_text('backup_only_qa_tables_enable'));
		//qa_opt('backup_file_max_size', max(1048576*0.1, 1048576*(float)qa_post_text('backup_file_max_size_field')));
	}
	
	function doBackup()
	{
		$path = QA_BASE_DIR."qa-content/backup";
		// create backup folder
		if (!is_dir($path))
			mkdir($path, 0755);
		if (!is_dir($this->getDir($path)))
		{
			$this->error = "No such directory: ". $this->getDir($path);
			return;
		}
		// create default index.php file to avoid that somebody see directory content.
		if (!file_exists($path."/index.php"))
		{
			$indexFile = @fopen($path."/index.php", "w");
			@fwrite($indexFile, '<?php header(\'Location: ../\'); ?>');
			@fclose($indexFile);
		}
		
		
		$ext = qa_opt('backup_compress_enable') ? '.gz' : '.sql';
		$path .= "/".$this->prefix()."-".date_format(new DateTime(), 'Y_m_d-H_i_s')."-".$this->suffix().$ext;
		
		$oldMaintenance = qa_opt('site_maintenance');
		qa_opt('site_maintenance', 1);
		$this->getDumper($path)->doDump();
		qa_opt('site_maintenance', $oldMaintenance);
	}
	
	function doImport()
	{
		if ($this->fileCount == 0)
		{
			$this->error = "No files found. Please, upload first.";
			return;
		}
			
		$fileName = qa_post_text('file_name_selected');
		if (!$fileName)
		{
			$this->error = "No files selected. Please, select a file.";
			return;
		}
		
		$path = QA_BASE_DIR."qa-content/backup" . "/" . $fileName;
		if (!file_exists($path))
		{
			$this->error = "File does not exist: ". $path;
			return;
		}
		
		$oldMaintenance = qa_opt('site_maintenance');
		qa_opt('site_maintenance', 1);
		$this->launchFile($path, $this->error);
		qa_opt('site_maintenance', $oldMaintenance);
	}
	
	function launchFile($path, &$error)
	{
		// read the file
		$file = null;
		
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
		$lineseparator = "\n";
		$lastIsComment = false;
		
		
		$newLnPos = true;
		$line = "";
		$query = "";
		while ( ! feof( $file ) )
		{
			$chunk = "";
			if ($compressed)
				$chunk = gzread($file, 1024);
			else
				$chunk = fgets( $file, 1024);
			$chunk = str_replace("\r","",$chunk);
			
			do
			{
				$newLnPos = strpos($chunk, "\n");
				if ($newLnPos === 0 || $newLnPos > 0)
				{
					$line .= substr($chunk, 0, $newLnPos);
					// omit first line (issue with byte order mark)
					if ($lineNum>0 && $line && !$this->startsWith($line, "--"))
					{
						$query .= $line . " ";
						if ($this->endsWith($line, ";"))
						{
							qa_db_query_raw($query); // execute query
							$query = "";
						}
					}
					$line = "";
					$lineNum++;
					
					$chunk = substr($chunk, $newLnPos+1);
				}
				else
				{
					$line .= $chunk;
				}
			} while($newLnPos === 0 || $newLnPos > 0);
		}
		if ($lineNum>0 && $line && !$this->startsWith($line, "--"))
		{
			$query .= $line;
		}
		if (strlen($query) > 0)
		{
			qa_db_query_raw($query); // execute query
			$query = "";
		}
		$line = "";
		
		
		
		if ($compressed)
			gzclose($file);
		else
			fclose($file);
	}
	
	function uploadFile()
	{
		if ($_FILES["backup_upload_file"]["size"] > 2*1024*1024) {
			$this->error = "File is too large (".$this->formatKB($_FILES["backup_upload_file"]["size"]).").";
			return;
		}
		if (!$_FILES["backup_upload_file"]["name"]) {
			$this->error = "No files selected. Please, select a file.<br />";
			return;
		}
		if ($_FILES["backup_upload_file"]["type"] != "text/plain" && $_FILES["backup_upload_file"]["type"] != "application/octet-stream") {
			$this->error = "Wrong file type: ".$_FILES["backup_upload_file"]["type"].". <br />You can only upload text or gz files!";
			return;
		}
		if ($_FILES["backup_upload_file"]["error"]) {
			$this->error = " code: ".$_FILES["backup_upload_file"]["error"]." - enexpected one...<br />";
			return;
		}
		
		
		$dir = $this->backupDir;
		
		
		if (file_exists($dir . $_FILES["backup_upload_file"]["name"]))
			@unlink($dir . $_FILES["backup_upload_file"]["name"]);
		if (file_exists($dir . $_FILES["backup_upload_file"]["name"]))
		{
			$this->error .= "Could not delete file ".$_FILES["backup_upload_file"]["name"] . ". ";
		}
		else
		{
			move_uploaded_file($_FILES["backup_upload_file"]["tmp_name"],
				$dir . $_FILES["backup_upload_file"]["name"]);
			$this->msg = "File uploaded to: <br />" . $dir . $_FILES["backup_upload_file"]["name"] . "<br />";
			$this->msg .= "(type: " . $_FILES["backup_upload_file"]["type"] . ", ";
			$this->msg .= "size: " . ceil(($_FILES["backup_upload_file"]["size"] / 1024)) . " KB)<br />";
		}
	}
	
	function doDelete()
	{
		$fileArr = $this->getFiles($this->backupDir);
		if (count($fileArr) == 0)
		{
			$this->msg = "There are no files to delete.";
			return;
		}
		for ($i=0; $i < count($fileArr); $i++)
		{
			if (file_exists($this->backupDir.$fileArr[$i]))
				@unlink($this->backupDir.$fileArr[$i]);
			if (file_exists($this->backupDir.$fileArr[$i]))
				$this->error .= $this->backupDir.$fileArr[$i] . "<br />";
		}
		if (strlen($this->error) > 0)
			$this->error = "Can't delete files (are they locked by another process?): <br />".$this->error;
		else
			$this->msg = 'Files deleted';
	}
	
	function getFiles($dirpath)
	{
		$myDirectory = opendir($dirpath);

		while($entryName = readdir($myDirectory))
			$dirArray[] = $entryName;
		
		closedir($myDirectory);
		sort($dirArray);

		$indexCount	= count($dirArray);
		
		$filesArr = array();
		for ($index=0; $index < $indexCount; $index++) 
		{
			if (substr("$dirArray[$index]", 0, 1) != "." // don't list hidden files
				&& !$this->endsWith($dirArray[$index], "index.php") // ignore index.php
				&& !is_dir($dirpath.$dirArray[$index])  // don't list directories
				)
			{ 
				$filesArr[] = $dirArray[$index];
			}
		}
		return $filesArr;
	}
	
	function getDumper($path)
	{
		require_once('mysqldump.php');
		$tabPrefix = qa_opt('backup_only_qa_tables_enable') ? QA_MYSQL_TABLE_PREFIX : "";
		return  new MySQLDump(QA_FINAL_MYSQL_DATABASE, $path, qa_opt('backup_compress_enable'), false, $tabPrefix); // db, filepath, compress, hex, table_prefix, bufferSizeKB
			// , qa_opt('backup_file_max_size')/1024.0
	}
	
	function listFiles()
	{
		$fileArr = $this->getFiles($this->backupDir);
		
		$strFiles = "";
		for ($i=0; $i < count($fileArr); $i++)
		{
			$strFiles .= "<input type=\"radio\" name=\"file_name_selected\" value=\"$fileArr[$i]\">".
							"<a href=\"".$this->backupDirUrl.$fileArr[$i]."\" target=\"_blank\">$fileArr[$i]</a> (".$this->formatKB(@filesize($this->backupDir.$fileArr[$i])).")".
						 "</input><br />";
		}
		$this->fileCount = count($fileArr);
		
		$res = "";
		if ($this->fileCount > 0)
		{
			$res .= '<div style="color:#000; font-size:12px; text-align:left; width:500px;">';
			$res .= 'Files in backup folder <span style="color:#f99">(download them and delete fast before someone sees your data!)</span>:<br />';
			$res .= $strFiles;
			$res .= "</div>";
		}
		return $res;
	}

	function formatKB($number)
	{
		if (!$number)
			return 0;
		$number = ceil($number/1024);
		return number_format($number, 0, ",", " ")." KB";
	}
	
	function getDir($path)
	{
		$res = "";
		$slash1 = strrpos($path, "\\");
		$slash2 = strrpos($path, "/");
		$maxSlash = -1;
		if ($slash1) $maxSlash = $slash1;
		if ($slash2 && $slash2>$maxSlash) $maxSlash = $slash2;
		if ($maxSlash != -1)
			$res = substr($path, 0, $maxSlash+1);
		return $res;
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
	
	function prefix()
	{
		$prefix = qa_opt('site_title');
		$prefix = strtolower(@ereg_replace("[^A-Za-z0-9]", "", $prefix));
		if (strlen($prefix) > 8)
			$prefix = substr($prefix, 0, 8);
		if (!$prefix)
			$prefix = "q2a";
		return $prefix;
	}
	
	function suffix()
	{
		return substr(md5(microtime()), 0, 5);
	}
	
	function bytes_to_mega_html($bytes)
	{
		return qa_html(number_format($bytes/1048576, 1));
	}
};
	

/*
	Omit PHP closing tag to help avoid accidental output
*/