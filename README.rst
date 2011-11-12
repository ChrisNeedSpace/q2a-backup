=============================
Question2Answer Database Backup 0.5c
=============================
-----------
Description
-----------
This is a plugin for **Question2Answer** that provides database backup and restore functionality.

--------
Features
--------
- full database or particular Q2A instance export (via admin panel)
- database restore tool (via admin panel)
- emergency restore tool


------------
Installation
------------
#. Install Question2Answer_
#. Get the source code for this plugin from github_, either using git_, or downloading directly:

   - To download using git, install git and then type 
     ``git clone git@github.com:KrzysztofKielce/q2a-backup.git backup``
     at the command prompt (on Linux, Windows is a bit different)
   - To download directly, go to the `project page`_ and click **Download**

#. Navigate to your site, go to **Admin > Plugins** on your q2a install and go to section '**Database Backup**', then '**Do the backup!**' or '**Import whole database!**'.

.. _Question2Answer: http://www.question2answer.org/install.php
.. _git: http://git-scm.com/
.. _github:
.. _project page: https://github.com/KrzysztofKielce/q2a-backup

------------------------------
HOW TO - RESTORE IN A SAFE WAY
------------------------------
  To avoid data loss, it is always recommended to make restore in a safe way. Here are the steps:

#. Get a backup file on your local disk and manually change table prefixes in that file (e.g. to `qb_`).
   - especially in lines defining database structure:

     DROP TABLE IF EXISTS `qa_xxxxx`;

     CREATE TABLE `qa_xxxxx` (

     CONSTRAINT `qa_xxxxx_ibfk_1` FOREIGN KEY (`xxxx`) REFERENCES `qa_xxxxxx`

   - but not inside data lines :

     ('nav_qa_is_home','0'), **- DO NOT CHANGE**

     ('nav_qa_not_home','0'), **- DO NOT CHANGE**.

#. Upload the backup file onto the server via the plugin form (**Admin -> Plugins**, section **Database Backup**).

    [Uploaded file should appear on the list. If not, try uploading the file manually via FTP client, to path "Q2A_ROOT_DIR/qa-content/backup/"].

#. Import database via plugin form.

#. Edit your qa-config.php file and change your table prefix to that you have changed in the backup file (e.g. `qb_`).
#. Browse your website and test!
#. If all's OK, you can import (2.) normal backup file and revert the changes in qa-config.php (4.) to your old table prefix and finally do the import (3.).

---------------
TROUBLESHOOTING
---------------
#.
 - Problem: All data is gone.
 - Solution: If you didn't do a backup, you can have a real problem. If you have the backup file: 

  + Try uploading it by phpMyAdmin or other panel.
  + Or if you have an access to the command line on your server, you could also import file via mysql utility: 
    On your server open a command line (Windows: Ctrl+R -> cmd) or terminal (Linux: Ctl + Alt + T).
    Execute a command: **path to mysql_bin_folder**/mysql -u **username_from_config** -p **database_name** --password=**password_from_config** < **import_file_path**
    e.g. "c:/wamp/bin/mysql/mysql5.5.8/bin/mysql" -u "qasuperuser" -p "qadatabase" --password="admin" < "qa/qa-content/backup/q2afile.sql"
  + Otherwise, please use my emergency restore tool at: '**/qa/qa-plugin/Kielce-backup/emergency.php**' .
#. 
 - Problem: After import: special characters are not displayed correctly.
 - Solution: The problem is in character encoding. The default character encoding for tables is UTF8. For this reason the best is to use import files that have the same encoding (utf8). So first, you can check it in some text editor such as Notepad++. Second, please ensure that there is a line at the beginning of the imported file: SET NAMES UTF8;

#. 
 - Problem: Import - first line issue.
 - Solution: Please ensure that first line of imported file is a comment '--'. In the first line there are some chars that indicate byte order for utf8 file. For some reason these bytes are interpreted as normal text in my parser, so first line must be ignored.
#.
 - Problem: File doesn't upload.
 - Solution: Try uploading file manually, by some FTP client, e.g. FileZilla (to path "Q2A_ROOT_DIR/qa-content/backup/"). The possible reasons of problem are: too big size (files over 2 MB can be problematic); wrong file type - executables are not available to be uploaded.

----------
Disclaimer
----------
This is **beta** code.  It is probably okay for production environments, but may not work exactly as expected.  Refunds will not be given.  If it breaks, you get to keep both parts.

-------
Release
-------
All code herein is Copylefted_.

.. _Copylefted: http://en.wikipedia.org/wiki/Copyleft

---------
About q2A
---------
Question2Answer is a free and open source platform for Q&A sites. For more information, visit:

http://www.question2answer.org/
