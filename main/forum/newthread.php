<?php
/* For licensing terms, see /license.txt */

/**
 * These files are a complete rework of the forum. The database structure is
 * based on phpBB but all the code is rewritten. A lot of new functionalities
 * are added:
 * - forum categories and forums can be sorted up or down, locked or made invisible
 * - consistent and integrated forum administration
 * - forum options:     are students allowed to edit their post?
 *                      moderation of posts (approval)
 *                      reply only forums (students cannot create new threads)
 *                      multiple forums per group
 * - sticky messages
 * - new view option: nested view
 * - quoting a message
 *
 * @Author Patrick Cool <patrick.cool@UGent.be>, Ghent University
 * @Copyright Ghent University
 * @Copyright Patrick Cool
 *
 * @package chamilo.forum
 */

use ChamiloSession as Session;

// Including the global initialization file.
require_once '../inc/global.inc.php';

// The section (tabs).
$this_section = SECTION_COURSES;

// Notification for unauthorized people.
api_protect_course_script(true);

$nameTools = get_lang('ToolForum');

/* Including necessary files */

require_once 'forumconfig.inc.php';
require_once 'forumfunction.inc.php';

// Are we in a lp ?
$origin = '';
if (isset($_GET['origin'])) {
    $origin =  Security::remove_XSS($_GET['origin']);
}

/* MAIN DISPLAY SECTION */
$current_forum = get_forum_information($_GET['forum']);
$current_forum_category = get_forumcategory_information($current_forum['forum_category']);

/* Breadcrumbs */

if (isset($_SESSION['gradebook'])){
    $gradebook = Security::remove_XSS($_SESSION['gradebook']);
}

if (!empty($gradebook) && $gradebook == 'view') {
    $interbreadcrumb[] = array (
        'url' => '../gradebook/'.Security::remove_XSS($_SESSION['gradebook_dest']),
        'name' => get_lang('ToolGradebook')
    );
}

if (!empty($_GET['gidReq'])) {
    $toolgroup = intval($_GET['gidReq']);
    Session::write('toolgroup',$toolgroup);
}

/* Is the user allowed here? */

// The user is not allowed here if:

// 1. the forumcategory or forum is invisible (visibility==0) and the user is not a course manager
if (!api_is_allowed_to_edit(false, true) &&
    (($current_forum_category['visibility'] && $current_forum_category['visibility'] == 0) || $current_forum['visibility'] == 0)
) {
    api_not_allowed();
}

// 2. the forumcategory or forum is locked (locked <>0) and the user is not a course manager
if (!api_is_allowed_to_edit(false, true) &&
    (($current_forum_category['visibility'] && $current_forum_category['locked'] <> 0) OR $current_forum['locked'] <> 0)
) {
    api_not_allowed();
}

// 3. new threads are not allowed and the user is not a course manager
if (!api_is_allowed_to_edit(false, true) &&
    $current_forum['allow_new_threads'] <> 1
) {
    api_not_allowed();
}
// 4. anonymous posts are not allowed and the user is not logged in
if (!$_user['user_id'] AND $current_forum['allow_anonymous'] <> 1) {
    api_not_allowed();
}

// 5. Check user access
if ($current_forum['forum_of_group'] != 0) {
    $show_forum = GroupManager::user_has_access(
        api_get_user_id(),
        $current_forum['forum_of_group'],
        GroupManager::GROUP_TOOL_FORUM
    );
    if (!$show_forum) {
        api_not_allowed();
    }
}

// 6. Invited users can't create new threads
if (api_is_invitee()) {
    api_not_allowed(true);
}

$groupId = api_get_group_id();
if (!empty($groupId)) {
    $group_properties = GroupManager :: get_group_properties($groupId);
    $interbreadcrumb[] = array('url' => '../group/group.php?'.api_get_cidreq(), 'name' => get_lang('Groups'));
    $interbreadcrumb[] = array('url' => '../group/group_space.php?'.api_get_cidreq(), 'name' => get_lang('GroupSpace').' '.$group_properties['name']);
    $interbreadcrumb[] = array('url' => 'viewforum.php?'.api_get_cidreq().'&forum='.Security::remove_XSS($_GET['forum']), 'name' => $current_forum['forum_title']);
    $interbreadcrumb[] = array('url' => 'newthread.php?'.api_get_cidreq().'&forum='.Security::remove_XSS($_GET['forum']),'name' => get_lang('NewTopic'));
} else {
    $interbreadcrumb[] = array('url' => 'index.php?'.api_get_cidreq(), 'name' => $nameTools);
    $interbreadcrumb[] = array('url' => 'viewforumcategory.php?'.api_get_cidreq().'&forumcategory='.$current_forum_category['cat_id'], 'name' => $current_forum_category['cat_title']);
    $interbreadcrumb[] = array('url' => 'viewforum.php?'.api_get_cidreq().'&forum='.Security::remove_XSS($_GET['forum']), 'name' => $current_forum['forum_title']);
    $interbreadcrumb[] = array('url' => '#', 'name' => get_lang('NewTopic'));
}

/* Resource Linker */

if (isset($_POST['add_resources']) AND $_POST['add_resources'] == get_lang('Resources')) {
    $_SESSION['formelements']	= $_POST;
    $_SESSION['origin']			= $_SERVER['REQUEST_URI'];
    $_SESSION['breadcrumbs']	= $interbreadcrumb;
    header('Location: ../resourcelinker/resourcelinker.php');
    exit;
}

/* Header */

$htmlHeadXtra[] = <<<JS
    <script>
    $(document).on('ready', function() {
        $('#reply-add-attachment').on('click', function(e) {
            e.preventDefault();

            var newInputFile = $('<input>', {
                type: 'file',
                name: 'user_upload[]'
            });

            $('[name="user_upload[]"]').parent().append(newInputFile);
        });
    });
    </script>
JS;

if ($origin == 'learnpath') {
    Display::display_reduced_header();
} else {
    Display :: display_header(null);
}
handle_forum_and_forumcategories();

// Action links
echo '<div class="actions">';
echo '<span style="float:right;">'.search_link().'</span>';
echo '<a href="viewforum.php?forum='.Security::remove_XSS($_GET['forum']).'&'.api_get_cidreq().'">'.
    Display::return_icon('back.png',get_lang('BackToForum'),'',ICON_SIZE_MEDIUM).'</a>';
echo '</div>';

// Set forum attachment data into $_SESSION
getAttachedFiles($current_forum['forum_id'], 0, 0);
$values = show_add_post_form(
    $current_forum,
    $forum_setting,
    'newthread',
    '',
    isset($_SESSION['formelements']) ? $_SESSION['formelements'] : null
);

if (!empty($values) && isset($values['SubmitPost'])) {
    // Add new thread in table forum_thread.
    store_thread($current_forum, $values);
}

if (isset($origin) && $origin != 'learnpath') {
    Display :: display_footer();
}
