<?php

/**
 * Sets all of Moodle's advanced features and
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/moodle/config.php');
require_once(__DIR__ . '/moodle/lib/adminlib.php');

$settings = array(
    'debugdisplay', 'enablenotes', 'enableblogs', 'enablebadges', 'enableoutcomes',
    'enableportfolios', 'enablerssfeeds', 'enablecompletion', 'enablecourserequests',
    'enableavailability', 'enableplagiarism', 'enablegroupmembersonly', 'enablegravatar',
    'enablesafebrowserintegration', 'usecomments', 'dndallowtextandlinks', 'gradepublishing'
);

// Enable all of Moodle's features that adds functionality to the default settings.
foreach ($settings as $setting) {
    set_config($setting, 1);
}

// Update admin user info in case we want to log in to set cache stores other configs...
$user = $DB->get_record('user', array('username' => 'admin'));
$user->email = 'moodle@moodlemoodle.com';
$user->firstname = 'Admin';
$user->lastname = 'User';
$user->city = 'Perth';
$user->country = 'AU';
$DB->update_record('user', $user);

// Disable email message processor.
$DB->set_field('message_processors', 'enabled', '0', array('name' => 'email'));

// Course list when not logged in and enrolled courses when logged as a usual configuration.
$frontpage = new admin_setting_courselist_frontpage(false);
$frontpage->write_setting(array(FRONTPAGEALLCOURSELIST));
$frontpagelogged = new admin_setting_courselist_frontpage(true);
$frontpagelogged->write_setting(array(FRONTPAGEENROLLEDCOURSELIST));

echo "Moodle site configuration finished successfully.\n";
