This is an overview of the New Products Transfer system. 

Instructions for setting up and using the system are in the `instructions` folder.

### Disclaimer
This code has not been thoroughly tested and the "installation" requires a lot of manually placing files in your file systems and configuring the content. It does not work right out of the box.

The system has 4 parts:
  * The "Export New Products" RO CSVI template.
  * The "transfer_files.php" PHP script.
  * The "import_all.sh" bash script.
  * The cron jobs.


### Export New Products
The "Export New Products" template uses a custom override that keeps track of the last datetime the template was run. If a product is created after this datetime, it will(/should) be exported the next time the template is run.


### transfer_files.php
The "transfer_files.php" script is run by the local server at regular inteverals, and checks to see if it needs to process any RO CSVI export files for new products. If it does, it attempts to transfer all of the image files for the exported products, from the local server to a specified FTP server. Once the images for a particular CSV file are all uploaded, the script also uploads the file so that the live server can import the new products. If any of the images or the CSV file fail to upload (which can happen randomly for no reason) they are recorded on the local server, and re-attempted the next time the script is run.


### import_all.sh
The "import_all.sh" is a script for a Unix/Linus/OSX command line, is run by the live server at regular intervals, and checks if there are RO CSVI files it needs to import. If there are files to import, it attempts to import all of them, and deletes the file when it is finished importing.


### cron jobs
A "cron job" is just some task that an operating system does at a regular interval. This system has two cron jobs: running transfer_files.php, and running import_all.sh. Both of these jobs are run frequently to make sure that the new products are transfered to the live website as soon as possible.

