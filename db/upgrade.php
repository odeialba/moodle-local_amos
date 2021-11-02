<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * AMOS upgrade steps.
 *
 * @package     local_amos
 * @category    upgrade
 * @copyright   2010 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Perform upgrade steps.
 *
 * @param int $oldversion Old version we are upgrading from.
 */
function xmldb_local_amos_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();
    $result = true;

    if ($oldversion < 2019020501) {
        // Add a new table 'amos_stats'.
        $table = new xmldb_table('amos_stats');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('branch', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('lang', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('numofstrings', XMLDB_TYPE_INTEGER, '20', null, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('branchlangcomp', XMLDB_INDEX_NOTUNIQUE, ['branch', 'lang', 'component']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2019020501, 'local', 'amos');
    }

    if ($oldversion < 2019020502) {
        // Make AMOS to regenerate all ZIP packs and gather all the stats.
        set_config('lastexportzip', 1, 'local_amos');
        upgrade_plugin_savepoint(true, 2019020502, 'local', 'amos');
    }

    if ($oldversion < 2019020602) {
        // Add index component (not unique) to the table amos_stats.
        $table = new xmldb_table('amos_stats');
        $index = new xmldb_index('component', XMLDB_INDEX_NOTUNIQUE, ['component']);

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2019020602, 'local', 'amos');
    }

    if ($oldversion < 2019040901) {
        $dbman->install_one_table_from_xmldb_file($CFG->dirroot.'/local/amos/db/install.xml', 'amos_app_strings');
        upgrade_plugin_savepoint(true, 2019040901, 'local', 'amos');
    }

    if ($oldversion < 2019040902) {
        // Create the new tables to store the English strings and their translations.

        if (!$dbman->table_exists(new xmldb_table('amos_strings'))) {
            $dbman->install_one_table_from_xmldb_file($CFG->dirroot.'/local/amos/db/install.xml', 'amos_strings');
        }

        if (!$dbman->table_exists(new xmldb_table('amos_translations'))) {
            $dbman->install_one_table_from_xmldb_file($CFG->dirroot.'/local/amos/db/install.xml', 'amos_translations');
        }

        upgrade_plugin_savepoint(true, 2019040902, 'local', 'amos');
    }

    if ($oldversion < 2020111300) {
        if (!$dbman->table_exists(new xmldb_table('amos_preferences'))) {
            $dbman->install_one_table_from_xmldb_file($CFG->dirroot.'/local/amos/db/install.xml', 'amos_preferences');
        }

        upgrade_plugin_savepoint(true, 2020111300, 'local', 'amos');
    }

    if ($oldversion < 2020111800) {
        // Set initial value for the local_amos/standardcomponents setting.
        set_config('standardcomponents', implode(PHP_EOL, [
            'antivirus_clamav 31',
            'assignfeedback_comments 23',
            'assignfeedback_editpdf 26',
            'assignfeedback_file 23',
            'assignfeedback_offline 24',
            'assignment_offline',
            'assignment_online',
            'assignment_upload',
            'assignment_uploadsingle',
            'assignsubmission_comments 23',
            'assignsubmission_file 23',
            'assignsubmission_onlinetext 23',
            'atto_accessibilitychecker 27',
            'atto_accessibilityhelper 27',
            'atto_align 27',
            'atto_backcolor 27',
            'atto_bold 27',
            'atto_charmap 27',
            'atto_clear 27',
            'atto_collapse 27',
            'atto_emojipicker 38',
            'atto_emoticon 27',
            'atto_equation 27',
            'atto_fontcolor 27',
            'atto_h5p 38',
            'atto_html 27',
            'atto_image 27',
            'atto_indent 27',
            'atto_italic 27',
            'atto_link 27',
            'atto_managefiles 27',
            'atto_media 27',
            'atto_noautolink 27',
            'atto_orderedlist 27',
            'atto_recordrtc 35',
            'atto_rtl 27',
            'atto_strike 27',
            'atto_subscript 27',
            'atto_superscript 27',
            'atto_table 27',
            'atto_title 27',
            'atto_underline 27',
            'atto_undo 27',
            'atto_unorderedlist 27',
            'auth_cas',
            'auth_db',
            'auth_email',
            'auth_fc -33',
            'auth_imap -33',
            'auth_ldap',
            'auth_lti 31',
            'auth_manual',
            'auth_mnet',
            'auth_nntp -33',
            'auth_nologin',
            'auth_none',
            'auth_oauth2 33',
            'auth_pam -33',
            'auth_pop3 -33',
            'auth_radius -31',
            'auth_shibboleth',
            'auth_webservice',
            'availability_completion 27',
            'availability_date 27',
            'availability_grade 27',
            'availability_group 27',
            'availability_grouping 27',
            'availability_profile 27',
            'block_activity_modules',
            'block_activity_results 29',
            'block_admin_bookmarks',
            'block_badges 25',
            'block_blog_menu',
            'block_blog_recent',
            'block_blog_tags',
            'block_calendar_month',
            'block_calendar_upcoming',
            'block_comments',
            'block_community -37',
            'block_completionstatus',
            'block_course_list',
            'block_course_overview -32',
            'block_course_summary',
            'block_feedback',
            'block_globalsearch 31',
            'block_glossary_random',
            'block_html',
            'block_login',
            'block_lp 31',
            'block_mentees',
            'block_messages -33',
            'block_mnet_hosts',
            'block_myoverview 33',
            'block_myprofile',
            'block_navigation',
            'block_news_items',
            'block_online_users',
            'block_participants -37',
            'block_private_files',
            'block_quiz_results',
            'block_recent_activity',
            'block_recentlyaccessedcourses 36',
            'block_recentlyaccesseditems 36',
            'block_rss_client',
            'block_search -21',
            'block_search_forums',
            'block_section_links',
            'block_selfcompletion',
            'block_settings',
            'block_site_main_menu',
            'block_social_activities',
            'block_starredcourses 36',
            'block_tag_flickr',
            'block_tag_youtube',
            'block_tags',
            'block_timeline 36',
            'booktool_exportimscp 23',
            'booktool_importhtml 23',
            'booktool_print 23',
            'cachelock_file 24',
            'cachestore_apcu 32',
            'cachestore_file 24',
            'cachestore_memcache 24 -35',
            'cachestore_memcached 24',
            'cachestore_mongodb 24',
            'cachestore_redis 32',
            'cachestore_session 24',
            'cachestore_static 24',
            'calendartype_gregorian 26',
            'contenttype_h5p 39',
            'core_access',
            'core_admin',
            'core_analytics 34',
            'core_antivirus 31',
            'core_auth',
            'core_availability 27',
            'core_backup',
            'core_badges 25',
            'core_block',
            'core_blog',
            'core_bulkusers',
            'core_cache 24',
            'core_calendar',
            'core_cohort',
            'core_comment 29',
            'core_competency 31',
            'core_completion',
            'core_condition -26',
            'core_contentbank 39',
            'core_countries',
            'core_course',
            'core_currencies',
            'core_customfield 37',
            'core_dbtransfer',
            'core_debug',
            'core_dock -25',
            'core_editor',
            'core_edufields',
            'core_enrol',
            'core_error',
            'core_favourites 36',
            'core_fileconverter 33',
            'core_filepicker',
            'core_files',
            'core_filters',
            'core_flashdetect -21',
            'core_fonts -25',
            'core_form',
            'core_grades',
            'core_grading 22',
            'core_group',
            'core_h5p 38',
            'core_help',
            'core_hub',
            'core_imscc',
            'core_install',
            'core_iso6392',
            'core_langconfig',
            'core_license',
            'core_mathslib 21',
            'core_media 23',
            'core_message',
            'core_mimetypes',
            'core_mnet',
            'core_my',
            'core_notes',
            'core_pagetype',
            'core_pix',
            'core_plagiarism',
            'core_plugin 21',
            'core_portfolio',
            'core_privacy 33',
            'core_publish -32',
            'core_question',
            'core_rating',
            'core_register -32',
            'core_repository',
            'core_role',
            'core_rss',
            'core_search',
            'core_simpletest -21',
            'core_table',
            'core_tag',
            'core_timezones',
            'core_user',
            'core_userkey',
            'core_webservice',
            'core_xapi 39',
            'core_xmldb -21',
            'coursereport_completion -21',
            'coursereport_log -21',
            'coursereport_outline -21',
            'coursereport_participation -21',
            'coursereport_progress -21',
            'coursereport_stats -21',
            'customfield_checkbox 37',
            'customfield_date 37',
            'customfield_select 37',
            'customfield_text 37',
            'customfield_textarea 37',
            'datafield_checkbox',
            'datafield_date',
            'datafield_file',
            'datafield_latlong',
            'datafield_menu',
            'datafield_multimenu',
            'datafield_number',
            'datafield_picture',
            'datafield_radiobutton',
            'datafield_text',
            'datafield_textarea',
            'datafield_url',
            'dataformat_csv 31',
            'dataformat_excel 31',
            'dataformat_html 31',
            'dataformat_json 31',
            'dataformat_ods 31',
            'dataformat_pdf 37',
            'datapreset_imagegallery',
            'editor_atto 27',
            'editor_textarea',
            'editor_tinymce',
            'enrol_authorize -25',
            'enrol_category',
            'enrol_cohort',
            'enrol_database',
            'enrol_flatfile',
            'enrol_guest',
            'enrol_imsenterprise',
            'enrol_ldap',
            'enrol_lti 31',
            'enrol_manual',
            'enrol_meta',
            'enrol_mnet',
            'enrol_paypal',
            'enrol_self',
            'fileconverter_googledrive 33',
            'fileconverter_unoconv 33',
            'filter_activitynames',
            'filter_algebra',
            'filter_censor',
            'filter_data 22',
            'filter_displayh5p 38',
            'filter_emailprotect',
            'filter_emoticon',
            'filter_glossary 22',
            'filter_mathjaxloader 27',
            'filter_mediaplugin',
            'filter_mod_data -21',
            'filter_mod_glossary -21',
            'filter_multilang',
            'filter_tex',
            'filter_tidy',
            'filter_urltolink',
            'format_scorm -25',
            'format_singleactivity 26',
            'format_social',
            'format_topics',
            'format_weeks',
            'forumreport_summary 38',
            'gradeexport_ods',
            'gradeexport_txt',
            'gradeexport_xls',
            'gradeexport_xml',
            'gradeimport_csv',
            'gradeimport_direct 28',
            'gradeimport_xml',
            'gradereport_grader',
            'gradereport_history 28',
            'gradereport_outcomes',
            'gradereport_overview',
            'gradereport_singleview 28',
            'gradereport_user',
            'gradingform_guide 23',
            'gradingform_rubric 22',
            'h5plib_v124 39',
            'local_qeupgradehelper 21 -21',
            'logstore_database 27',
            'logstore_legacy 27',
            'logstore_standard 27',
            'ltiservice_basicoutcomes 37',
            'ltiservice_gradebookservices 35',
            'ltiservice_memberships 30',
            'ltiservice_profile 28',
            'ltiservice_toolproxy 28',
            'ltiservice_toolsettings 28',
            'media_html5audio 32',
            'media_html5video 32',
            'media_swf 32',
            'media_videojs 32',
            'media_vimeo 32',
            'media_youtube 32',
            'message_airnotifier 27',
            'message_email',
            'message_jabber',
            'message_popup',
            'mlbackend_php 34',
            'mlbackend_python 34',
            'mnetservice_enrol',
            'mod_assign 23',
            'mod_assignment',
            'mod_book 23',
            'mod_chat',
            'mod_choice',
            'mod_data',
            'mod_feedback',
            'mod_folder',
            'mod_forum',
            'mod_glossary',
            'mod_h5pactivity 39',
            'mod_imscp',
            'mod_label',
            'mod_lesson',
            'mod_lti 22',
            'mod_page',
            'mod_quiz',
            'mod_resource',
            'mod_scorm',
            'mod_survey',
            'mod_url',
            'mod_wiki',
            'mod_workshop',
            'portfolio_boxnet',
            'portfolio_download',
            'portfolio_flickr',
            'portfolio_googledocs',
            'portfolio_mahara',
            'portfolio_picasa',
            'profilefield_checkbox',
            'profilefield_datetime',
            'profilefield_menu',
            'profilefield_text',
            'profilefield_textarea',
            'qbehaviour_adaptive 21',
            'qbehaviour_adaptivenopenalty 21',
            'qbehaviour_deferredcbm 21',
            'qbehaviour_deferredfeedback 21',
            'qbehaviour_immediatecbm 21',
            'qbehaviour_immediatefeedback 21',
            'qbehaviour_informationitem 21',
            'qbehaviour_interactive 21',
            'qbehaviour_interactivecountback 21',
            'qbehaviour_manualgraded 21',
            'qbehaviour_missing 21',
            'qformat_aiken',
            'qformat_blackboard -24',
            'qformat_blackboard_six',
            'qformat_examview',
            'qformat_gift',
            'qformat_learnwise -27',
            'qformat_missingword',
            'qformat_multianswer',
            'qformat_qti_two -21',
            'qformat_webct',
            'qformat_xhtml',
            'qformat_xml',
            'qtype_calculated',
            'qtype_calculatedmulti',
            'qtype_calculatedsimple',
            'qtype_ddimageortext 30',
            'qtype_ddmarker 30',
            'qtype_ddwtos 30',
            'qtype_description',
            'qtype_essay',
            'qtype_gapselect 30',
            'qtype_match',
            'qtype_missingtype',
            'qtype_multianswer',
            'qtype_multichoice',
            'qtype_numerical',
            'qtype_random',
            'qtype_randomsamatch',
            'qtype_shortanswer',
            'qtype_truefalse',
            'quiz_grading',
            'quiz_overview',
            'quiz_responses',
            'quiz_statistics',
            'quizaccess_delaybetweenattempts 22',
            'quizaccess_ipaddress 22',
            'quizaccess_numattempts 22',
            'quizaccess_offlineattempts 32',
            'quizaccess_openclosedate 22',
            'quizaccess_password 22',
            'quizaccess_safebrowser 22 -38',
            'quizaccess_seb 39',
            'quizaccess_securewindow 22',
            'quizaccess_timelimit 22',
            'report_backups',
            'report_capability -21',
            'report_competency 31',
            'report_completion 22',
            'report_configlog',
            'report_courseoverview',
            'report_customlang -21',
            'report_eventlist 27',
            'report_infectedfiles 310',
            'report_insights 34',
            'report_log',
            'report_loglive 22',
            'report_outline 22',
            'report_participation 22',
            'report_performance 25',
            'report_profiling -21',
            'report_progress 22',
            'report_questioninstances',
            'report_search 31 -31',
            'report_security',
            'report_spamcleaner -21',
            'report_stats',
            'report_status 39',
            'report_unittest -21',
            'report_unsuproles -21',
            'report_usersessions 29',
            'repository_alfresco -31',
            'repository_areafiles 26',
            'repository_boxnet',
            'repository_contentbank 39',
            'repository_coursefiles',
            'repository_dropbox',
            'repository_equella 23',
            'repository_filesystem',
            'repository_flickr',
            'repository_flickr_public',
            'repository_googledocs',
            'repository_local',
            'repository_merlot',
            'repository_nextcloud 36',
            'repository_onedrive 33',
            'repository_picasa',
            'repository_recent',
            'repository_s3',
            'repository_skydrive 26',
            'repository_upload',
            'repository_url',
            'repository_user',
            'repository_webdav',
            'repository_wikimedia',
            'repository_youtube',
            'scormreport_basic 22',
            'scormreport_graphs 23',
            'scormreport_interactions 22',
            'scormreport_objectives 26',
            'search_simpledb 35',
            'search_solr 31',
            'theme_afterburner 21 -26',
            'theme_anomaly -26',
            'theme_arialist -26',
            'theme_base -31',
            'theme_binarius -26',
            'theme_boost 32',
            'theme_bootstrapbase 25 -36',
            'theme_boxxie -26',
            'theme_brick -26',
            'theme_canvas -31',
            'theme_classic 37',
            'theme_clean 25 -36',
            'theme_formal_white -26',
            'theme_formfactor -26',
            'theme_fusion -26',
            'theme_leatherbound -26',
            'theme_magazine -26',
            'theme_more 27 -36',
            'theme_mymobile 22 -25',
            'theme_nimble -26',
            'theme_nonzero -26',
            'theme_overlay -26',
            'theme_serenity -26',
            'theme_sky_high -26',
            'theme_splash -26',
            'theme_standard -26',
            'theme_standardold -26',
            'tinymce_ctrlhelp 25',
            'tinymce_dragmath 24 -27',
            'tinymce_managefiles 26',
            'tinymce_moodleemoticon 24',
            'tinymce_moodleimage 24',
            'tinymce_moodlemedia 24',
            'tinymce_moodlenolink 24',
            'tinymce_pdw 26',
            'tinymce_spellchecker 24',
            'tinymce_wrap 26',
            'tool_analytics 34',
            'tool_assignmentupgrade 23 -35',
            'tool_availabilityconditions 27',
            'tool_behat 25',
            'tool_bloglevelupgrade 22 -23',
            'tool_capability 22',
            'tool_cohortroles 31',
            'tool_customlang 22',
            'tool_dataprivacy 33',
            'tool_dbtransfer 22',
            'tool_filetypes 29',
            'tool_generator 22',
            'tool_health 22',
            'tool_httpsreplace 34',
            'tool_innodb 22',
            'tool_installaddon 25',
            'tool_langimport 22',
            'tool_licensemanager 39',
            'tool_log 27',
            'tool_lp 31',
            'tool_lpimportcsv 32',
            'tool_lpmigrate 31',
            'tool_messageinbound 28',
            'tool_mobile 31',
            'tool_monitor 28',
            'tool_moodlenet 39',
            'tool_multilangupgrade 22',
            'tool_oauth2 33',
            'tool_phpunit 23',
            'tool_policy 33',
            'tool_profiling 22',
            'tool_qeupgradehelper 22 -26',
            'tool_recyclebin 31',
            'tool_replace 22',
            'tool_spamcleaner 22',
            'tool_task 27',
            'tool_templatelibrary 29',
            'tool_timezoneimport 22 -28',
            'tool_unittest 22 -23',
            'tool_unsuproles 22',
            'tool_uploadcourse 26',
            'tool_uploaduser 22',
            'tool_usertours 32',
            'tool_xmldb 22',
            'webservice_amf -30',
            'webservice_rest',
            'webservice_soap',
            'webservice_xmlrpc',
            'workshopallocation_manual',
            'workshopallocation_random',
            'workshopallocation_scheduled 23',
            'workshopeval_best',
            'workshopform_accumulative',
            'workshopform_comments',
            'workshopform_numerrors',
            'workshopform_rubric',
        ]), 'local_amos');

        upgrade_plugin_savepoint(true, 2020111800, 'local', 'amos');
    }

    if ($oldversion < 2021110200) {
        // Install amos_workplace_strings table from install.xml.
        if (!$dbman->table_exists(new xmldb_table('amos_workplace_strings'))) {
            $dbman->install_one_table_from_xmldb_file($CFG->dirroot.'/local/amos/db/install.xml', 'amos_workplace_strings');
        }

        require_once($CFG->dirroot.'/local/amos/db/workplace_string_ids.php');

        foreach ($workplacestrings as $component => $componentstrings) {
            foreach ($componentstrings as $componentstring) {
                $string = new stdClass();
                $string->component = $component;
                $string->stringid = $componentstring;
                $string->workplaceid = 'addon.' . $component . '.' . $componentstring;
                $DB->insert_record('amos_workplace_strings', $string);
            }
        }

        // Amos savepoint reached.
        upgrade_plugin_savepoint(true, 2021110200, 'local', 'amos');
    }

    return $result;
}
