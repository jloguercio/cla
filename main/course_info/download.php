<?php
/* For licensing terms, see /license.txt */

/**
 * Download script for course info
 * @package chamilo.course_info
 */

require_once '../inc/global.inc.php';
$this_section = SECTION_COURSES;

if (isset($_GET['session']) && $_GET['session']) {
	$archive_path = api_get_path(SYS_ARCHIVE_PATH).'temp/';
	$_cid = true;
	$is_courseAdmin = true;
} else {
	$archive_path = api_get_path(SYS_ARCHIVE_PATH);
}

$archive_file = isset($_GET['archive']) ? $_GET['archive'] : null;
$archive_file = str_replace(array('..', '/', '\\'), '', $archive_file);

list($extension) = getextension($archive_file);

if (empty($extension) || !file_exists($archive_path.$archive_file)) {
	exit;
}

$extension = strtolower($extension);
$content_type = '';

if (in_array($extension, array('xml', 'csv')) && (api_is_platform_admin(true) || api_is_drh())) {
	$content_type = 'application/force-download';
} elseif ($extension == 'zip' && $_cid && (api_is_platform_admin(true) || $is_courseAdmin)) {
	$content_type = 'application/force-download';
}

if (empty($content_type)) {
	api_not_allowed(true);
}

if (Security::check_abs_path($archive_path.$archive_file, $archive_path)) {
    header('Expires: Wed, 01 Jan 1990 00:00:00 GMT');
    header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
    header('Cache-Control: public');
    header('Pragma: no-cache');
    header('Content-Type: '.$content_type);
    header('Content-Length: '.filesize($archive_path.$archive_file));
    header('Content-Disposition: attachment; filename='.$archive_file);
    readfile($archive_path.$archive_file);
    exit;
} else {
    api_not_allowed(true);
}
