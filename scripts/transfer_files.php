<?php

// Script

// FILL IN THE FIELDS WITH YOUR FTP INFORMATION.
$file_uploader = new FileUploader("<live domain name>", '<ftp user name>', '<ftp password>');
$file_uploader->update();

// Helper class.
class FileUploader {
	/**
	* INSTALLATION NOTE
	* 
	* Set the values of the following variables:
	*
	* $domain_root
	* $website_root (needs to be a subdirectory of ftp_root)
	* $images_prefix
	* $domain_name
	* $username
	* $password
	*/

	// Root folders.
	private $domain_root = '/Applications/MAMP/htdocs/dasparts/';            // ** localhost's website root folder.
	private $ftp_root = '/';										// ftp login's root folder.
	private $website_root = '/';									// live website's root folder relative to ftp login
	
	// Path segments.
	private $images_prefix = 'images/onlinestore/';							 // prefix from website's root to images folder
	private $exports_prefix = 'exports/NewParts/';
	private $imports_prefix = 'exports/NewParts/';

	// FTP login information.
	private $domain_name;
	private $username;
	private $password;

	function __construct($domain_name, $user_name, $password) {
		$this->domain_name = $domain_name;
		$this->user_name = $user_name;
		$this->password = $password;
	}


	function update() {
		echo("Updating.\n");
		// Try to upload images for all of the files in the exports folder.
		$exports_folder = $this->domain_root . $this->exports_prefix;
		foreach (glob($exports_folder . "*.csv") as $file) {
			$this->uploadImages($file);
		}
	}

	/**
	* Attempts to upload all of the files for the given CSV export file.
	*/
	function uploadImages($path_to_csv) {

		if (!file_exists($path_to_csv)) {
			echo("Cannot find file $path_to_csv\n");
			return;
		}

		// If there are already images queue for this CSV file, load paths from file.
		// Otherwise, extract the image paths from the CSV file.
		$images_file = $path_to_csv . '_img';
		$data = array();
		if (file_exists($images_file)) {
			$data = json_decode(file_get_contents($images_file));
		} else {
			$data = $this->getImagePaths($path_to_csv);
		}

		// Outpute array for logging/debugging purposes.
		var_dump($data);

		// Establish FTP connection.
		$conn_id       = ftp_connect($this->domain_name);
		$login_result  = ftp_login($conn_id, $this->user_name, $this->password);
		if ($login_result === false) {
			echo "Error establishing FTP connection.";
			return;
		}

		// Try to send files to remote server.
		$failures = $this->sendImageFiles($data, $conn_id);

		// Check for failures.
		if (empty($failures)) {
			// If all files uploaded, upload CSV file and delete local files.

			// Get filename.
			$file_name = substr($path_to_csv, strrpos($path_to_csv, '/') + 1); 
			$file_without_extension = substr($file_name, 0, strlen($file_name) - 4);

			// Make a filename that is unique on the target server.
			$imports_folder = $this->website_root . $this->imports_prefix;
			$file_list = ftp_nlist($conn_id, $imports_folder);
			$i = 0;
			while (in_array($file_name, $file_list)) {
				$file_name = $file_without_extension . $i . '.csv';
				$i++;
			}
			$remote_file = $imports_folder . $file_name;

			// Try to upload CSV file.
			if (ftp_put($conn_id, $remote_file, $path_to_csv, FTP_ASCII)) {
				echo "successfully uploaded $remote_file\n";
				// Delete local files.
				unlink($path_to_csv);
				if (file_exists($images_file)) {
					unlink($images_file);					
				}
			} else {
				echo "There was a problem while uploading $remote_file\n";
			}
		} else {
			// If there were failures, save them in a special file.
			file_put_contents($images_file, json_encode($failures));
		}

		ftp_close($conn_id);
	}

	/**
	* Reads a CSV file and extracts all entries under the headings 
	* "addition_images", "main_image", and "thumb_image".
	*
	* It is assume that the CSV file was made by RO CSVI, so that these entries
	* are file paths starting with "/images/".
	*/
	private function getImagePaths($path_to_csv) {
		// Open CSV file.
		$csv_file_handle = fopen($path_to_csv, 'r')
				or die('Error occurred when opening file ' . $path_to_csv );

		// Get indices of desired headings.
		$first_line = fgetcsv($csv_file_handle);
		$additional_images 	= array_search("additional_images", $first_line);
		$main_image 		= array_search('main_image', $first_line);
		$thumb_image		= array_search('thumb_image', $first_line);

		// Accumulate all paths into an array.
		$data = array();
		while ($rec = fgetcsv($csv_file_handle)) {
			array_push($data, $rec[$main_image]);
			array_push($data, $rec[$thumb_image]);
			// Paths for additional images are separated with a '|' character.
			$add = explode("|", $rec[$additional_images]);
			foreach ($add as $path) {
				array_push($data, $path);
			}
		}

		// Close CSV file.
		fclose($csv_file_handle);

		// Remove all blank paths.
		array_filter($data);
		return $data;
	}

	/**
	* Given an array of file paths and an FTP connection, attempts to upload
	* all of the files to the FTP server.
	*/
	private function sendImageFiles($file_paths, $conn_id) {

		// Record failures in an array.
		$failures = array();

		// Try to upload each file in $file_paths.
		foreach ($file_paths as $path) {
			// If field is blank, do nothing.
			if ($path == '') {
				continue;
			}

			// Build paths
			$file = $this->domain_root . $path;
			$remote_file = $this->ftp_root . $path;
			$remote_directory = substr($remote_file, 0, strrpos($remote_file, '/'));

			// Make sure directory exists first!
			if (ftp_nlist($conn_id, $remote_directory) == false) {
				echo("Folder not found " . $remote_directory . "\n");
				// If folder is not found, create one.
				$this->ensureDir($path, $conn_id);
			}
			// Try to upload image file.
			if (ftp_put($conn_id, $remote_file, $file, FTP_BINARY)) {
				echo "successfully uploaded $file\n";
			} else {
				echo "There was a problem while uploading $file\n";
				// If it failed for whatever random reason, add it to the array.
				array_push($failures, $path);
			}	
		}

		return $failures;
	}

	/**
	* Given a folder path and an FTP connection, attempts to make the path
	* on the FTP server.
	*/
	private function ensureDir($path, $conn_id) {
		$base_path = $this->ftp_root;

		// Include images_prefix in the base path if possible, because it saves up to 4 FTP operations.
		$pos = strpos($path, $this->images_prefix);
		if ($pos !== false) {
			$base_path = $base_path . $this->images_prefix;
			$path = substr($path, $pos + strlen($this->images_prefix));
		}
		$base_path = rtrim($base_path, '/');
		$dirs = explode( "/", $path);
		for ($i = 0; $i < count($dirs) - 1; $i++) {
			$base_path = $base_path . "/" . $dirs[$i];
			echo("Checking for folder " . $base_path . "\n");
			if (ftp_nlist($conn_id, $base_path) == false) {
				echo("Making folder " . $base_path . "...\n");
				ftp_mkdir($conn_id, $base_path);
				echo("Folder created.\n");
			}
		}
	}
}


?>