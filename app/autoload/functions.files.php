<?php
/**
 * Get all the file information knowing only the id
 * Used on the Download information page.
 *
 * @return array
 */
function get_file_by_id($id)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_FILES . " WHERE id=:id");
	$statement->bindParam(':id', $id, PDO::PARAM_INT);
	$statement->execute();
	$statement->setFetchMode(PDO::FETCH_ASSOC);

	while ( $row = $statement->fetch() ) {
		$information = array(
							'id'			=> html_output($row['id']),
							'title'			=> html_output($row['filename']),
							'original_url'	=> html_output($row['original_url']),
							'url'			=> html_output($row['url']),
						);
		if ( !empty( $information ) ) {
			return $information;
		}
		else {
			return false;
		}
	}
}

/**
 * Get the total count of downloads grouped by file
 * Data returned:
 * - Count anonymous downloads (Public downloads)
 * - Unique logged in clients downloads
 * - Total count
 */
function generate_downloads_count( $id = null )
{
	global $dbh;

	$data = array();

	$sql = "SELECT file_id, COUNT(*) as downloads, SUM( ISNULL(user_id) ) AS anonymous_users, COUNT(DISTINCT user_id) as unique_clients FROM " . TABLE_DOWNLOADS;
	if ( !empty( $id ) ) {
		$sql .= ' WHERE file_id = :id';
	}

	$sql .=  " GROUP BY file_id";

	$statement	= $dbh->prepare( $sql );

	if ( !empty( $id ) ) {
		$statement->bindValue(':id', $id, PDO::PARAM_INT);
	}

	$statement->execute();

	$statement->setFetchMode(PDO::FETCH_ASSOC);

	while ( $row = $statement->fetch() ) {
		$data[$row['file_id']] = array(
									'file_id'			=> html_output($row['file_id']),
									'total'				=> html_output($row['downloads']),
									'unique_clients'	=> html_output($row['unique_clients']),
									'anonymous_users'	=> html_output($row['anonymous_users']),
								);
	}

	return $data;
}

/**
 * Check if a file id exists on the database.
 * Used on the download information page.
 *
 * @return bool
 */
function download_information_exists($id)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT id FROM " . TABLE_DOWNLOADS . " WHERE file_id = :id");
	$statement->bindParam(':id', $id, PDO::PARAM_INT);
	$statement->execute();
	if ( $statement->rowCount() > 0 ) {
		return true;
	}
	else {
		return false;
	}
}


/**
 * Receives the size of a file in bytes, and formats it for readability.
 * Used on files listings (templates and the files manager).
 */
function format_file_size($file)
{
	if ($file < 1024) {
		 /** No digits so put a ? much better than just seeing Byte */
		$formatted = (ctype_digit($file))? $file . ' Byte' :  ' ? ' ;
	} elseif ($file < 1048576) {
		$formatted = round($file / 1024, 2) . ' KB';
	} elseif ($file < 1073741824) {
		$formatted = round($file / 1048576, 2) . ' MB';
	} elseif ($file < 1099511627776) {
		$formatted = round($file / 1073741824, 2) . ' GB';
	} elseif ($file < 1125899906842624) {
		$formatted = round($file / 1099511627776, 2) . ' TB';
	} elseif ($file < 1152921504606846976) {
		$formatted = round($file / 1125899906842624, 2) . ' PB';
	} elseif ($file < 1180591620717411303424) {
		$formatted = round($file / 1152921504606846976, 2) . ' EB';
	} elseif ($file < 1208925819614629174706176) {
		$formatted = round($file / 1180591620717411303424, 2) . ' ZB';
	} else {
		$formatted = round($file / 1208925819614629174706176, 2) . ' YB';
	}

	return $formatted;
}


/**
 * Since filesize() was giving trouble with files larger
 * than 2gb, I looked for a solution and found this great
 * function by Alessandro Marinuzzi from www.alecos.it on
 * http://stackoverflow.com/questions/5501451/php-x86-how-
 * to-get-filesize-of-2gb-file-without-external-program
 *
 * I changed the name of the function and split it in 2,
 * because I do not want to display it directly.
 */
function get_real_size($file)
{
	clearstatcache();
    if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
		if (class_exists("COM")) {
			$fsobj = new COM('Scripting.FileSystemObject');
			$f = $fsobj->GetFile(realpath($file));
			$ff = $f->Size;
		}
		else {
	        $ff = trim(exec("for %F in (\"" . escapeshellarg($file) . "\") do @echo %~zF"));
		}
    }
	elseif (PHP_OS == 'Darwin') {
		$ff = trim(shell_exec("stat -L -f %z " . escapeshellarg($file)));
    }
	elseif ((PHP_OS == 'Linux') || (PHP_OS == 'FreeBSD') || (PHP_OS == 'Unix') || (PHP_OS == 'SunOS')) {
		$ff = trim(shell_exec("stat -L -c%s " . escapeshellarg($file)));
    }
	else {
		$ff = filesize($file);
	}

	/** Fix for 0kb downloads by AlanReiblein */
	if (!ctype_digit($ff)) {
		 /* returned value not a number so try filesize() */
		$ff=filesize($file);
	}

	return $ff;
}

/**
 * Delete just one file.
 * Used on the files managment page.
 */
function delete_file_from_disk($filename)
{
	if ( file_exists( $filename ) ) {
		chmod($filename, 0777);
		unlink($filename);
	}
}

/**
 * Deletes all files and sub-folders of the selected directory.
 * Used when deleting a client.
 */
function delete_recursive($dir)
{
	if (is_dir($dir)) {
		if ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false ) {
				if( $file != "." && $file != ".." ) {
					if( is_dir( $dir . $file ) ) {
						delete_recursive( $dir . $file . "/" );
						rmdir( $dir . $file );
					}
					else {
						chmod($dir.$file, 0777);
						unlink($dir.$file);
					}
				}
		   }
		   closedir($dh);
		   rmdir($dir);
	   }
	}
}

/**
 * Try to recognize if a file is an image
 *
 * @todo Check the mime type also
 */
function file_is_image( $file )
{
	$is_image = false;
	$pathinfo = pathinfo( $file );
	$extension = strtolower( $pathinfo['extension'] );

	if ( file_exists( $file ) ) {
		/** Check the extension */
		$image_extensions = array('jpg', 'jpeg', 'jpe', 'png', 'gif');
		if ( in_array( $extension, $image_extensions ) ) {
			$is_image = true;
		}
	}

	return $is_image;
}

/**
 * Try to recognize if a file is a valid svg
 */
function file_is_svg( $file )
{
	if ( file_exists( $file ) ) {
        $svg_sanitizer = new Sanitizer();
        $source_file = file_get_contents($file);
        $sanitized_file = $svg_sanitizer->sanitize($source_file);
    }
    else {
        return false;
    }

	return $sanitized_file;
}

/**
 * Make a thumbnail with SimpleImage
 */
function make_thumbnail( $file, $type = 'thumbnail', $width = THUMBS_MAX_WIDTH, $height = THUMBS_MAX_HEIGHT, $quality = THUMBS_QUALITY )
{
	$thumbnail = array();

	if ( file_is_image( $file ) ) {
		/** Original extension */
		$pathinfo	= pathinfo( $file );
		$filename	= md5( $pathinfo['basename'] );
		$extension	= strtolower( $pathinfo['extension'] );
		$mime_type	= mime_content_type($file);

		$thumbnail_file = 'thumb_' . $filename . '_' . $width . 'x' . $height . '.' . $extension;

		$thumbnail['original']['url'] = $file;
		$thumbnail['thumbnail']['location'] = THUMBNAILS_FILES_DIR . '/' . $thumbnail_file;
		$thumbnail['thumbnail']['url'] = THUMBNAILS_FILES_URL . '/' . $thumbnail_file;

		if ( !file_exists( $thumbnail['thumbnail']['location'] ) ) {
			try {
				$image = new \claviska\SimpleImage();
				$image
					->fromFile($file)
					->autoOrient();

				switch ( $type ) {
					case 'proportional':
						$method = 'bestFit';
						break;
					case 'thumbnail':
					default:
						$method = 'thumbnail';
						break;
				}

				$image->$method($width, $height);

				$image
					->toFile($thumbnail['thumbnail']['location'], $mime_type, $quality);

			} catch(Exception $err) {
				$thumbnail['error'] = $err->getMessage();
			}
		}
	}

	return $thumbnail;
}

/**
 * Creates a standarized download link. Used on
 * each template.
 */
function make_download_link($file_info)
{
	$download_link = BASE_URI.'process.php?do=download&amp;id='.$file_info['id'];

	return $download_link;
}