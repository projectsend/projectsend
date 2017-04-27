<?php
/**
 * Thumbnails generating functions.
 * Parts of this file are taken from another scripts from around the WWW.
 *
 * @package		ProjectSend
 * @subpackage	Thumbnails
 * 
 */

/** Get thumbnails options from the database */
require_once('../sys.includes.php');

/** If we set the quality via URI, ignore the default value that comes from the database */
if(!empty($_GET['ql'])) {
	$thumbnail_quality = $_GET['ql'];
}
else {
	$thumbnail_quality = THUMBS_QUALITY;
}

/**
 * Type is a required parameter that defines where the generated thumbnail
 * image will be saved.
 */
if(empty($_GET['type'])) {
	return false;
}
else {
	switch($_GET['type']) {
		case 'logo':
			$do_on_folder = THUMBS_FOLDER;
		break;
		case 'tlogo':
			$do_on_folder = LOGO_THUMB_FOLDER;
		break;
		case 'prev':
			$who = basename($_GET['who']);
			$thumb_name = $_GET['name'];
			$do_on_folder = '../upload/'.$who.'/thumbs/';
		break;
	}
}

/** Generate the thumbnail file name */
$pathinfo = pathinfo($_GET['src']);
$thumb_name = $pathinfo['filename'];
if(isset($_GET['w'])) { $thumb_name .= '-W'.$_GET['w']; }
if(isset($_GET['h'])) { $thumb_name .= '-H'.$_GET['h']; }
$thumb_name .= '.'.$pathinfo['extension'];

$destination = $do_on_folder.basename($thumb_name);

if (!file_exists($thumb_name)) {
	$extension = strtolower($pathinfo['extension']);
	/** Detect filetype and make a temp image */
	if ($extension == "gif") {
		$source = imagecreatefromgif($_GET['src']);
	}
	if ($extension == "jpeg" || $extension == "pjpeg" || $extension == "jpg" ) {
		$source = imagecreatefromjpeg($_GET['src']);
	}
	if ($extension == "png") {
		$source = imagecreatefrompng($_GET['src']);
	}

	$image_width = imagesx($source);
	$image_height = imagesy($source);
	$new_width = $_GET['w'];
	$new_height = $_GET['h'];
	
	if ((!isset($_GET['w'])) && (!isset($_GET['h']))) {
		$new_width = 100;
		$new_height = 100;
	}
	else if (($_GET['w']) && (!$_GET['h'])) {
		$new_width = $_GET['w'];
		$new_height = (((($_GET['w'] * 100) / $image_width) * $image_height) / 100);
	}
	else if ((!$_GET['w']) && ($_GET['h'])) {
		$new_width = (((($_GET['h'] * 100) / $image_height) * $image_width) / 100);
		$new_height = $_GET['h'];
	}
	
	/** Recreate the picture with the original colors and avoiding pixelation */
	$image = imagecreatetruecolor($new_width,$new_height);
	imagealphablending($image,false);
	imagesavealpha($image,true);
	imagecopyresampled($image,$source,0,0,0,0,$new_width,$new_height,$image_width,$image_height);


	/** Copy thumbnail to the corresponding folder */
	switch($extension) {
		case 'png':
			imagepng($image,$destination,3,NULL);
			break;
		case 'gif':
			imagegif($image);
			break;
		case 'jpg':
			imagejpeg($image,$destination,$thumbnail_quality);
			break;
		default:
			imagejpeg($image,$destination,$thumbnail_quality);
			break;
	}
	
}

switch($extension) {
	case 'png':
		$output = imagecreatefrompng($destination);
		 /** Setting alpha blending on */
		imagealphablending($output, false);
		 /** Save alphablending setting */
		imagesavealpha($output, true);
		break;
	case 'gif':
		$output = imagecreatefromgif($destination);
		break;
	case 'jpg':
		$output = imagecreatefromjpeg($destination);
		break;
	default:
		$output = imagecreatefromjpeg($destination);
		break;
}


/**
 * Check if any effect needs to be applied to the thumbnail
 */

/** Add Unsharp */
if ($_GET['sh']) { 
	if ($_GET['sh'] == 1) { $cant = 70; $radio = 0.5; $thres = 3; }
	else { $arraysh = explode("|", $_GET['sh']); $cant = $arraysh[0]; $radio = $arraysh[1]; $thres = $arraysh[2]; }
	UnsharpMask($output, $cant, $radio, $thres);
}

/** Rotate */
if($_GET['r']) { 
	$arrayr = explode("|", $_GET['r']);
	$grados = $arrayr[0];
	$back = '0x' . $arrayr[1];
	$rotate = imagerotate($output, $grados, $back);
	imagejpeg($rotate,NULL,$thumbnail_quality);
}

/** Add Blur */
if($_GET['bl']) {
	$blcant = $_GET['bl'];
	blur($output,$blcant);
}

/** Add Pixelate */
if($_GET['px']) {
	$pxcant = $_GET['px'];
	pixelate($output,$pxcant);
}

/** Add Scatter */
if($_GET['sc']) {
	scatter($output);
}

/** Make Duotone */
if($_GET['duo']) { 
	$arraynoi = explode("|", $_GET['duo']);
	$noir = $arraynoi[0];
	$noig = $arraynoi[1];
	$noib = $arraynoi[2];
	duotone($output,$noir,$noig,$noib);
}

/** Make Grayscale */
if($_GET['gr']) { greyscale($output); }

/**
 * Finally, save the file on the corresponding folder
 */
switch($extension) {
	case 'png':
		header("Content-Type: image/png");
		imagepng($output,NULL,0,PNG_NO_FILTER);
		break;
	case 'gif':
		header("Content-Type: image/gif");
		imagegif($output);
		break;
	case 'jpg':
		header("Content-Type: image/jpeg");
		imagejpeg($output,NULL,$thumbnail_quality);
		break;
	default:
		header("Content-Type: image/jpeg");
		imagejpeg($output,NULL,$thumbnail_quality);
		break;
}
/**
 * Thumbnail creation ends here
 */

/**
 * Define the functions that can be applied to the file:
 */

/** GRAYSCALE */
function greyscale($image)
{
    $imagex = imagesx($image);
    $imagey = imagesy($image);

    for ($x = 0; $x <$imagex; ++$x) {
        for ($y = 0; $y <$imagey; ++$y) {
            $rgb = imagecolorat($image, $x, $y);
            $red = ($rgb >> 16) & 255;
            $green = ($rgb >> 8) & 255;
            $blue = $rgb & 255;
            $grey = (int)(($red+$green+$blue)/3);
            $newcol = imagecolorallocate($image, $grey,$grey,$grey);
            imagesetpixel($image, $x, $y, $newcol);
        }
    }
} 

/** SCATTER */
function scatter($image)
{
    $imagex = imagesx($image);
    $imagey = imagesy($image);

    for ($x = 0; $x < $imagex; ++$x) {
        for ($y = 0; $y < $imagey; ++$y) {
            $distx = rand(-4, 4);
            $disty = rand(-4, 4);

            if ($x + $distx >= $imagex) continue;
            if ($x + $distx < 0) continue;
            if ($y + $disty >= $imagey) continue;
            if ($y + $disty < 0) continue;

            $oldcol = imagecolorat($image, $x, $y);
            $newcol = imagecolorat($image, $x + $distx, $y + $disty);
            imagesetpixel($image, $x, $y, $newcol);
            imagesetpixel($image, $x + $distx, $y + $disty, $oldcol);
        }
    }
} 

/** DUOTONE */
function duotone($image, $rplus, $gplus, $bplus)
{
    $imagex = imagesx($image);
    $imagey = imagesy($image);

    for ($x = 0; $x <$imagex; ++$x) {
        for ($y = 0; $y <$imagey; ++$y) {
            $rgb = imagecolorat($image, $x, $y);
            $red = ($rgb >> 16) & 0xFF;
            $green = ($rgb >> 8) & 0xFF;
            $blue = $rgb & 0xFF;
            $red = (int)(($red+$green+$blue)/3);
            $green = $red + $gplus;
            $blue = $red + $bplus;
            $red += $rplus;

            if ($red > 255) $red = 255;
            if ($green > 255) $green = 255;
            if ($blue > 255) $blue = 255;
            if ($red < 0) $red = 0;
            if ($green < 0) $green = 0;
            if ($blue < 0) $blue = 0;

            $newcol = imagecolorallocate ($image, $red,$green,$blue);
            imagesetpixel ($image, $x, $y, $newcol);
        }
    }
} 

/** BLUR */
function blur($image,$dist)
{
    $imagex = imagesx($image);
    $imagey = imagesy($image);

    for ($x = 0; $x < $imagex; ++$x) {
        for ($y = 0; $y < $imagey; ++$y) {
            $newr = 0;
            $newg = 0;
            $newb = 0;

            $colours = array();
            $thiscol = imagecolorat($image, $x, $y);

            for ($k = $x - $dist; $k <= $x + $dist; ++$k) {
                for ($l = $y - $dist; $l <= $y + $dist; ++$l) {
                    if ($k < 0) { $colours[] = $thiscol; continue; }
                    if ($k >= $imagex) { $colours[] = $thiscol; continue; }
                    if ($l < 0) { $colours[] = $thiscol; continue; }
                    if ($l >= $imagey) { $colours[] = $thiscol; continue; }
                    $colours[] = imagecolorat($image, $k, $l);
                }
            }

            foreach($colours as $colour) {
                $newr += ($colour >> 16) & 0xFF;
                $newg += ($colour >> 8) & 0xFF;
                $newb += $colour & 0xFF;
            }

            $numelements = count($colours);
            $newr /= $numelements;
            $newg /= $numelements;
            $newb /= $numelements;

            $newcol = imagecolorallocate($image, $newr, $newg, $newb);
            imagesetpixel($image, $x, $y, $newcol);
        }
    }
} 

/** PIXELATE */
function pixelate($image,$blocksize)
{
    $imagex = imagesx($image);
    $imagey = imagesy($image);

    for ($x = 0; $x < $imagex; $x += $blocksize) {
        for ($y = 0; $y < $imagey; $y += $blocksize) {
            // get the pixel colour at the top-left of the square
            $thiscol = imagecolorat($image, $x, $y);

            // set the new red, green, and blue values to 0
            $newr = 0;
            $newg = 0;
            $newb = 0;

            // create an empty array for the colours
            $colours = array();

            // cycle through each pixel in the block
            for ($k = $x; $k < $x + $blocksize; ++$k) {
                for ($l = $y; $l < $y + $blocksize; ++$l) {
                    // if we are outside the valid bounds of the image, use a safe colour
                    if ($k < 0) { $colours[] = $thiscol; continue; }
                    if ($k >= $imagex) { $colours[] = $thiscol; continue; }
                    if ($l < 0) { $colours[] = $thiscol; continue; }
                    if ($l >= $imagey) { $colours[] = $thiscol; continue; }

                    // if not outside the image bounds, get the colour at this pixel
                    $colours[] = imagecolorat($image, $k, $l);
                }
            }

            // cycle through all the colours we can use for sampling
            foreach($colours as $colour) {
                // add their red, green, and blue values to our master numbers
                $newr += ($colour >> 16) & 0xFF;
                $newg += ($colour >> 8) & 0xFF;
                $newb += $colour & 0xFF;
            }

            // now divide the master numbers by the number of valid samples to get an average
            $numelements = count($colours);
            $newr /= $numelements;
            $newg /= $numelements;
            $newb /= $numelements;

            // and use the new numbers as our colour
            $newcol = imagecolorallocate($image, $newr, $newg, $newb);
            imagefilledrectangle($image, $x, $y, $x + $blocksize - 1, $y + $blocksize - 1, $newcol);
        }
    }
} 

/** UNSHARP MASK */

/*
New: 
- In version 2.1 (February 26 2007) Tom Bishop has done some important speed enhancements.
- From version 2 (July 17 2006) the script uses the imageconvolution function in PHP 
version >= 5.1, which improves the performance considerably.


Unsharp masking is a traditional darkroom technique that has proven very suitable for 
digital imaging. The principle of unsharp masking is to create a blurred copy of the image
and compare it to the underlying original. The difference in colour values
between the two images is greatest for the pixels near sharp edges. When this 
difference is subtracted from the original image, the edges will be
accentuated. 

The Amount parameter simply says how much of the effect you want. 100 is 'normal'.
Radius is the radius of the blurring circle of the mask. 'Threshold' is the least
difference in colour values that is allowed between the original and the mask. In practice
this means that low-contrast areas of the picture are left unrendered whereas edges
are treated normally. This is good for pictures of e.g. skin or blue skies.

Any suggenstions for improvement of the algorithm, expecially regarding the speed
and the roundoff errors in the Gaussian blur process, are welcome.

*/

function UnsharpMask($img, $amount, $radius, $threshold)
{

////////////////////////////////////////////////////////////////////////////////////////////////  
////  
////                  Unsharp Mask for PHP - version 2.1.1  
////  
////    Unsharp mask algorithm by Torstein H�nsi 2003-07.  
////             thoensi_at_netcom_dot_no.  
////               Please leave this notice.  
////  
///////////////////////////////////////////////////////////////////////////////////////////////  



    // $img is an image that is already created within php using 
    // imgcreatetruecolor. No url! $img must be a truecolor image. 

    // Attempt to calibrate the parameters to Photoshop: 
    if ($amount > 500)    $amount = 500; 
    $amount = $amount * 0.016; 
    if ($radius > 50)    $radius = 50; 
    $radius = $radius * 2; 
    if ($threshold > 255)    $threshold = 255; 
     
    $radius = abs(round($radius));     // Only integers make sense. 
    if ($radius == 0) { 
        return $img; imagedestroy($img); break;        } 
    $w = imagesx($img); $h = imagesy($img); 
    $imgCanvas = imagecreatetruecolor($w, $h); 
    $imgBlur = imagecreatetruecolor($w, $h); 
     

    // Gaussian blur matrix: 
    //                         
    //    1    2    1         
    //    2    4    2         
    //    1    2    1         
    //                         
    ////////////////////////////////////////////////// 
         

    if (function_exists('imageconvolution')) { // PHP >= 5.1  
            $matrix = array(  
            array( 1, 2, 1 ),  
            array( 2, 4, 2 ),  
            array( 1, 2, 1 )  
        );  
        imagecopy ($imgBlur, $img, 0, 0, 0, 0, $w, $h); 
        imageconvolution($imgBlur, $matrix, 16, 0);  
    }  
    else {  

    // Move copies of the image around one pixel at the time and merge them with weight 
    // according to the matrix. The same matrix is simply repeated for higher radii. 
        for ($i = 0; $i < $radius; $i++)    { 
            imagecopy ($imgBlur, $img, 0, 0, 1, 0, $w - 1, $h); // left 
            imagecopymerge ($imgBlur, $img, 1, 0, 0, 0, $w, $h, 50); // right 
            imagecopymerge ($imgBlur, $img, 0, 0, 0, 0, $w, $h, 50); // center 
            imagecopy ($imgCanvas, $imgBlur, 0, 0, 0, 0, $w, $h); 

            imagecopymerge ($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 33.33333 ); // up 
            imagecopymerge ($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 25); // down 
        } 
    } 

    if($threshold>0){ 
        // Calculate the difference between the blurred pixels and the original 
        // and set the pixels 
        for ($x = 0; $x < $w-1; $x++)    { // each row
            for ($y = 0; $y < $h; $y++)    { // each pixel 
                     
                $rgbOrig = ImageColorAt($img, $x, $y); 
                $rOrig = (($rgbOrig >> 16) & 0xFF); 
                $gOrig = (($rgbOrig >> 8) & 0xFF); 
                $bOrig = ($rgbOrig & 0xFF); 
                 
                $rgbBlur = ImageColorAt($imgBlur, $x, $y); 
                 
                $rBlur = (($rgbBlur >> 16) & 0xFF); 
                $gBlur = (($rgbBlur >> 8) & 0xFF); 
                $bBlur = ($rgbBlur & 0xFF); 
                 
                // When the masked pixels differ less from the original 
                // than the threshold specifies, they are set to their original value. 
                $rNew = (abs($rOrig - $rBlur) >= $threshold)  
                    ? max(0, min(255, ($amount * ($rOrig - $rBlur)) + $rOrig))  
                    : $rOrig; 
                $gNew = (abs($gOrig - $gBlur) >= $threshold)  
                    ? max(0, min(255, ($amount * ($gOrig - $gBlur)) + $gOrig))  
                    : $gOrig; 
                $bNew = (abs($bOrig - $bBlur) >= $threshold)  
                    ? max(0, min(255, ($amount * ($bOrig - $bBlur)) + $bOrig))  
                    : $bOrig; 
                 
                 
                             
                if (($rOrig != $rNew) || ($gOrig != $gNew) || ($bOrig != $bNew)) { 
                        $pixCol = ImageColorAllocate($img, $rNew, $gNew, $bNew); 
                        ImageSetPixel($img, $x, $y, $pixCol); 
                    } 
            } 
        } 
    } 
    else{ 
        for ($x = 0; $x < $w; $x++)    { // each row 
            for ($y = 0; $y < $h; $y++)    { // each pixel 
                $rgbOrig = ImageColorAt($img, $x, $y); 
                $rOrig = (($rgbOrig >> 16) & 0xFF); 
                $gOrig = (($rgbOrig >> 8) & 0xFF); 
                $bOrig = ($rgbOrig & 0xFF); 
                 
                $rgbBlur = ImageColorAt($imgBlur, $x, $y); 
                 
                $rBlur = (($rgbBlur >> 16) & 0xFF); 
                $gBlur = (($rgbBlur >> 8) & 0xFF); 
                $bBlur = ($rgbBlur & 0xFF); 
                 
                $rNew = ($amount * ($rOrig - $rBlur)) + $rOrig; 
                    if($rNew>255){$rNew=255;} 
                    elseif($rNew<0){$rNew=0;} 
                $gNew = ($amount * ($gOrig - $gBlur)) + $gOrig; 
                    if($gNew>255){$gNew=255;} 
                    elseif($gNew<0){$gNew=0;} 
                $bNew = ($amount * ($bOrig - $bBlur)) + $bOrig; 
                    if($bNew>255){$bNew=255;} 
                    elseif($bNew<0){$bNew=0;} 
                $rgbNew = ($rNew << 16) + ($gNew <<8) + $bNew; 
                    ImageSetPixel($img, $x, $y, $rgbNew); 
            } 
        } 
    } 
    imagedestroy($imgCanvas); 
    imagedestroy($imgBlur); 
     
    return $img;
}
?>