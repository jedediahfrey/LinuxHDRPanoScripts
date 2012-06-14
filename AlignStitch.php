#!/usr/bin/env php
<?php
$verb=4;
$dry=true;
runCmd("ls");

die();
// Start building the command array
$cmds = array();
if ($argc == 1) {
	echo "Print Help\n";
	die;
}
// Create directories:
$cmds[] = "mkdir -p Completed";
$cmds[] = "mkdir -p Aligned";
$cmds[] = "mkdir -p Oriented";
// Pop off the name of the script
array_shift($argv);
// All HDR shots should have 3 bracketed (the way I do it)
if (count($argv) % 3 != 0) {
	die("Number of input pictures is not divsible by 3. Not all bracketed photos may be included.\n\n");
}
// Determine if we need to convert from RAW
$ext = strtolower(end(explode('.', $argv[0])));
$raw = $ext == "cr2" ? 1 : 0;
if ($raw) {
	$argv = convertRaw($argv);
}
function convertRaw(&$imgs, &$cmds) {
	// If converting from RAW needs to be done, do it.
	$cmds[] = "mkdir -p Converted";
	foreach ($imgs as $i => $img) {
		$cmds[] = sprintf("dcraw -w -c \"%s\" | convert - \"Converted/%s.tif\"", $file, $file);
		$imgs[$i] = "Converted/$file.tif";
	}
}
die;
// For each set of bracketed images.
for ($i = 0; $i < count($argv) / 3; $i++) {
	// Align each of the brackets with each other (not really needed if a tripod is used)
	$cmds[] = sprintf("align_image_stack -a Aligned/aligned_%d_ -m -d -i -C \"%s\" \"%s\" \"%s\"", $i, $argv[3 * $i], $argv[3 * $i + 1], $argv[3 * $i + 2]);
	// Copy the EXIF info & start building arrays with each of the exposures.
	for ($j = 0; $j <= 2; $j++) {
		$cmds[] = sprintf("exiftool -overwrite_original_in_place -tagsfromfile \"%s\" -exif:all \"Aligned/aligned_%d_000%d.tif\"", $argv[3 * $i + $j], $i, $j);
		$img[$j][] = sprintf("Aligned/aligned_%d_000%d.tif", $i, $j);
	}
}
// Create a panorama with the normally exposed photos.
// Then retty much this: http://wiki.panotools.org/Panorama_scripting_in_a_nutshell
$cmds[] = sprintf("%s -o automated.pto -f $(exiftool -FOV %s -s3 | cut -f 1 -d \" \") Aligned/aligned*_0000.tif", "/opt/bin/pto_gen", $argv[0]);
$cmds[] = "cpfind --multirow --celeste -o automated2.pto automated.pto";
$cmds[] = "cpclean -o automated3.pto automated2.pto";
$cmds[] = "autooptimiser -q -a -m -l -s -o automated4.pto automated3.pto";
$cmds[] = "pano_modify --canvas=AUTO --crop=AUTO -o automated5.pto automated4.pto";
// Not needed since we'll be making them separate.
//$cmds[]="pto2mk -o automated.mk -p Completed/automated_ automated5.pto";
//$cmds[]="make -f automated.mk all";
foreach ($img as $exposure => $i) {
	// Start stitching the panorama with each of the 3 brackets.
	$cmds[] = sprintf("nona -g -o Oriented/%s_ -m TIFF_m automated4.pto -z NONE \"%s\"", $exposure, implode("\" \"", $i));
	// Blend
	$cmds[] = sprintf("enblend -o Completed/%s.tif Oriented/%s_*tif", $exposure, $exposure);
	// Convert to JPG
	$cmds[] = sprintf("mogrify -format jpg Completed/%s.tif", $exposure, $exposure);
	// Copy the exif information over.
	$cmds[] = sprintf("exiftool -overwrite_original_in_place -all= -tagsfromfile %s -exif:all Completed/%d.tif", $argv[$exposure], $exposure);
	$cmds[] = sprintf("exiftool -overwrite_original_in_place -all= -tagsfromfile %s -exif:all Completed/%d.jpg", $argv[$exposure], $exposure);
}
// Debugging
print_r($cmds);
// Actually run all the scripts.
function runCmd($cmds) {
	global $verb, $dry;
	// If it's a single command turn it into an array for foreach.
	if (!is_array($cmds)) $cmds = array($cmds); 
	// For each command print and execute it.
	foreach ($cmds as $cmd) {
		// If the verbrosity is >2, dump out the command.
		if ($verb > 2) {
			echo $cmd . "\n";
		}
		// If it isn't a dry run.
		if (!$dry) {
			if ($verb > 3) {
				passthru($cmd);
			} else {
				exec($cmd);
			}
		}
	}
}
