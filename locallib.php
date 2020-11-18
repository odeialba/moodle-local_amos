<?php

// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AMOS local library
 *
 * @package   amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/mlanglib.php');

define('AMOS_USER_MAINTAINER',  0);
define('AMOS_USER_CONTRIBUTOR', 1);

/**
 * Represents a collection commits to be shown at the AMOS Log page
 */
class local_amos_log implements renderable {

    const LIMITCOMMITS = 1000;

    /** @var array of commit records to be displayed in the log */
    public $commits = array();

    /** @var int number of found commits */
    public $numofcommits = null;

    /** @var int number of filtered strings modified by filtered commits */
    public $numofstrings = null;

    /**
     * Fetches the required commits from the repository
     *
     * @param array $filter allows to filter commits
     */
    public function __construct(array $filter = array()) {
        global $DB;

        // we can not use limits inside subquery so firstly let us get commits we are interested in
        $params     = array();
        $where      = array();
        $getsql     = "SELECT id";
        $countsql   = "SELECT COUNT(*)";
        $sql        = "  FROM {amos_commits}";

        if (!empty($filter['userid'])) {
            $where['userid'] = "userid = ?";
            $params[] = $filter['userid'];
        }

        if (!empty($filter['userinfo'])) {
            $where['userinfo'] = $DB->sql_like('userinfo', '?', false, false);
            $params[] = '%'.$DB->sql_like_escape($filter['userinfo']).'%';
        }

        if (!empty($where['userinfo']) and !empty($where['userid'])) {
            $where['user'] = '(' . $where['userid'] . ') OR (' . $where['userinfo'] . ')';
            unset($where['userinfo']);
            unset($where['userid']);
        }

        if (!empty($filter['committedafter'])) {
            $where['committedafter'] = 'timecommitted >= ?';
            $params[] = $filter['committedafter'];
        }

        if (!empty($filter['committedbefore'])) {
            $where['committedbefore'] = 'timecommitted < ?';
            $params[] = $filter['committedbefore'];
        }

        if (!empty($filter['source'])) {
            $where['source'] = 'source = ?';
            $params[] = $filter['source'];
        }

        if (!empty($filter['commitmsg'])) {
            $where['commitmsg'] = $DB->sql_like('commitmsg', '?', false, false);
            $params[] = '%'.$DB->sql_like_escape($filter['commitmsg']).'%';
        }

        if (!empty($filter['commithash'])) {
            $where['commithash'] = $DB->sql_like('commithash', '?', false, false);
            $params[] = $DB->sql_like_escape($filter['commithash']).'%';
        }

        if ($where) {
            $where = '(' . implode(') AND (', $where) . ')';
            $sql .= " WHERE $where";
        }

        $ordersql = " ORDER BY timecommitted DESC, id DESC";

        $this->numofcommits = $DB->count_records_sql($countsql.$sql, $params);

        $commitids = $DB->get_records_sql($getsql.$sql.$ordersql, $params, 0, self::LIMITCOMMITS);

        if (empty($commitids)) {
            // nothing to load
            return;
        }
        // now get all repository records modified by these commits
        // and optionally filter them if requested

        $params = array();
        list($csql, $params) = $DB->get_in_or_equal(array_keys($commitids));

        if (!empty($filter['branch'])) {
            list($branchsql, $branchparams) = $DB->get_in_or_equal(array_keys($filter['branch']));
        } else {
            $branchsql = '';
        }

        if (!empty($filter['lang'])) {
            list($langsql, $langparams) = $DB->get_in_or_equal($filter['lang']);
        } else {
            $langsql = '';
        }

        if (!empty($filter['component'])) {
            list($componentsql, $componentparams) = $DB->get_in_or_equal($filter['component']);
        } else {
            $componentsql = '';
        }

        $countsql   = "SELECT COUNT(r.id)";
        $getsql     = "SELECT r.id, c.source, c.timecommitted, c.commitmsg, c.commithash, c.userid, c.userinfo,
                              r.commitid, r.branch, r.lang, r.component, r.stringid, t.text, r.timemodified, r.deleted";
        $sql        = "  FROM {amos_commits} c
                         JOIN {amos_repository} r ON (c.id = r.commitid)
                         JOIN {amos_texts} t ON (r.textid = t.id)
                        WHERE c.id $csql";

        if ($branchsql) {
            $sql .= " AND r.branch $branchsql";
            $params = array_merge($params, $branchparams);
        }

        if ($langsql) {
            $sql .= " AND r.lang $langsql";
            $params = array_merge($params, $langparams);
        }

        if ($componentsql) {
            $sql .= " AND r.component $componentsql";
            $params = array_merge($params, $componentparams);
        }

        if (!empty($filter['stringid'])) {
            $sql .= " AND r.stringid = ?";
            $params[] = $filter['stringid'];
        }

        $ordersql = " ORDER BY c.timecommitted DESC, c.id DESC, r.branch DESC, r.lang, r.component, r.stringid";

        $this->numofstrings = $DB->count_records_sql($countsql.$sql, $params);

        $rs = $DB->get_recordset_sql($getsql.$sql.$ordersql, $params);

        $numofcommits = 0;

        foreach ($rs as $r) {
            if (!isset($this->commits[$r->commitid])) {
                if ($numofcommits == self::LIMITCOMMITS) {
                    // we already have enough
                    break;
                }
                $commit = new stdclass();
                $commit->id = $r->commitid;
                $commit->source = $r->source;
                $commit->timecommitted = $r->timecommitted;
                $commit->commitmsg = $r->commitmsg;
                $commit->commithash = $r->commithash;
                $commit->userid = $r->userid;
                $commit->userinfo = $r->userinfo;
                $commit->strings = array();
                $this->commits[$r->commitid] = $commit;
                $numofcommits++;
            }
            $string = new stdclass();
            $string->branch = mlang_version::by_code($r->branch)->label;
            $string->component = $r->component;
            $string->lang = $r->lang;
            $string->stringid = $r->stringid;
            $string->deleted = $r->deleted;
            $this->commits[$r->commitid]->strings[] = $string;
        }
        $rs->close();
    }
}

/**
 * Represents data to be displayed at http://download.moodle.org/langpack/x.x/ index page
 */
class local_amos_index_tablehtml implements renderable {

    /** @var mlang_version */
    public $version = null;

    /** @var array */
    public $langpacks = array();

    /** @var int */
    public $timemodified;

    /** @var number of strings in the English official language pack */
    public $totalenglish = 0;

    /** @var number of available lang packs (without English) */
    public $numoflangpacks = 0;

    /** @var number of lang packs having more that xx% of the string translated */
    public $percents = array();

    /** @var array */
    protected $packinfo = array();

    /**
     * Initialize data
     *
     * @param mlang_version $version we are generating page for
     * @param array $packinfo data structure prepared by cli/export-zip.php
     */
    public function __construct(mlang_version $version, array $packinfo) {

        $this->version  = $version;
        $this->packinfo = fullclone($packinfo);
        $this->timemodified = time();
        $this->percents = array('0' => 0, '40' => 0, '60' => 0, '80' => 0); // percents => number of langpacks
        // get the number of strings for standard plugins
        // only the standard plugins are taken into statistics calculation
        $standard = local_amos_standard_plugins();
        $english = array(); // holds the number of English strings per component

        foreach ($standard[$this->version->dir] as $componentname => $unused) {
            $component = mlang_component::from_snapshot($componentname, 'en', $this->version);
            $english[$componentname] = $component->get_number_of_strings(true);
            $this->totalenglish += $english[$componentname];
            $component->clear();
        }

        foreach ($this->packinfo as $langcode => $info) {
            if ($langcode !== 'en') {
                $this->numoflangpacks++;
            }
            $langpack = new stdclass();
            $langpack->langname = $info['langname'];
            $langpack->filename = $langcode.'.zip';
            $langpack->filesize = $info['filesize'];
            $langpack->modified = $info['modified'];
            if (!empty($info['parent'])) {
                $langpack->parent = $info['parent'];
            } else {
                $langpack->parent = 'en';
            }
            // calculate the translation statistics
            if ($langpack->parent == 'en') {
                $langpack->totaltranslated = 0;
                foreach ($info['numofstrings'] as $component => $translated) {
                    if (isset($standard[$this->version->dir][$component])) {
                        $langpack->totaltranslated += min($translated, $english[$component]);
                    }
                }
                if ($this->totalenglish == 0) {
                    $langpack->ratio = null;
                } else {
                    $langpack->ratio = $langpack->totaltranslated / $this->totalenglish;
                    if ($langpack->ratio > 0.8) {
                        $this->percents['80']++;
                    } elseif ($langpack->ratio > 0.6) {
                        $this->percents['60']++;
                    } elseif ($langpack->ratio > 0.4) {
                        $this->percents['40']++;
                    } else {
                        $this->percents['0']++;
                    }
                }
            } else {
                $langpack->totaltranslated = 0;
                foreach ($info['numofstrings'] as $component => $translated) {
                    $langpack->totaltranslated += $translated;
                }
                $langpack->ratio = null;
            }
            $this->langpacks[$langcode] = $langpack;
        }
    }
}

/**
 * Renderable stash
 */
class local_amos_stash implements renderable {

    /** @var int identifier in the table of stashes */
    public $id;
    /** @var string title of the stash */
    public $name;
    /** @var int timestamp of when the stash was created */
    public $timecreated;
    /** @var stdClass the owner of the stash */
    public $owner;
    /** @var array of language names */
    public $languages = array();
    /** @var array of component names */
    public $components = array();
    /** @var int number of stashed strings */
    public $strings = 0;
    /** @var bool is autosave stash */
    public $isautosave;

    /** @var array of stdClasses representing stash actions */
    protected $actions = array();

    /**
     * Factory method using an instance if {@link mlang_stash} as a data source
     *
     * @param mlang_stash $stash
     * @param stdClass $owner owner user data
     * @return local_amos_stash new instance
     */
    public static function instance_from_mlang_stash(mlang_stash $stash, stdClass $owner) {

        if ($stash->ownerid != $owner->id) {
            throw new coding_exception('Stash owner mismatch');
        }

        $new                = new local_amos_stash();
        $new->id            = $stash->id;
        $new->name          = $stash->name;
        $new->timecreated   = $stash->timecreated;

        $stage = new mlang_stage();
        $stash->apply($stage);
        list($new->strings, $new->languages, $new->components) = mlang_stage::analyze($stage);
        $stage->clear();
        unset($stage);

        $new->components    = explode('/', trim($new->components, '/'));
        $new->languages     = explode('/', trim($new->languages, '/'));

        $new->owner         = $owner;

        if ($stash->hash === 'xxxxautosaveuser'.$new->owner->id) {
            $new->isautosave = true;
        } else {
            $new->isautosave = false;
        }

        return $new;
    }

    /**
     * Factory method using plain database record from amos_stashes table as a source
     *
     * @param stdClass $record stash record from amos_stashes table
     * @param stdClass $owner owner user data
     * @return local_amos_stash new instance
     */
    public static function instance_from_record(stdClass $record, stdClass $owner) {

        if ($record->ownerid != $owner->id) {
            throw new coding_exception('Stash owner mismatch');
        }

        $new                = new local_amos_stash();
        $new->id            = $record->id;
        $new->name          = $record->name;
        $new->timecreated   = $record->timecreated;
        $new->strings       = $record->strings;
        $new->components    = explode('/', trim($record->components, '/'));
        $new->languages     = explode('/', trim($record->languages, '/'));
        $new->owner         = $owner;

        if ($record->hash === 'xxxxautosaveuser'.$new->owner->id) {
            $new->isautosave = true;
        } else {
            $new->isautosave = false;
        }

        return $new;
    }

    /**
     * Constructor is not public, use one of factory methods above
     */
    protected function __construct() {
        // does nothing
    }

    /**
     * Register a new action that can be done with the stash
     *
     * @param string $id action identifier
     * @param moodle_url $url action handler
     * @param string $label action name
     */
    public function add_action($id, moodle_url $url, $label) {

        $action             = new stdClass();
        $action->id         = $id;
        $action->url        = $url;
        $action->label      = $label;
        $this->actions[]    = $action;
    }

    /**
     * Get the list of actions attached to this stash
     *
     * @return array of stdClasses with $url and $label properties
     */
    public function get_actions() {
        return $this->actions;
    }
}

/**
 * Represents renderable contribution infor
 */
class local_amos_contribution implements renderable {

    const STATE_NEW         = 0;
    const STATE_REVIEW      = 10;
    const STATE_REJECTED    = 20;
    const STATE_ACCEPTED    = 30;

    /** @var stdClass */
    public $info;
    /** @var stdClass */
    public $author;
    /** @var stdClss */
    public $assignee;
    /** @var string */
    public $language;
    /** @var string */
    public $components;
    /** @var int number of strings */
    public $strings;
    /** @var int number of strings after rebase */
    public $stringsreb;

    public function __construct(stdClass $info, stdClass $author=null, stdClass $assignee=null) {
        global $DB;

        $this->info = $info;

        if (empty($author)) {
            $this->author = $DB->get_record('user', array('id' => $info->authorid));
        } else {
            $this->author = $author;
        }

        if (empty($assignee) and !empty($info->assignee)) {
            $this->assignee = $DB->get_record('user', array('id' => $info->assignee));
        } else {
            $this->assignee = $assignee;
        }
    }
}

/**
 * Returns the list of standard components
 *
 * @return array (string)version => (string)legacyname => (string)frankenstylename
 */
function local_amos_standard_plugins() {
    global $CFG;
    static $list = null;

    if (is_null($list)) {
        $xml  = simplexml_load_file($CFG->dirroot.'/local/amos/plugins.xml');
        foreach ($xml->moodle as $moodle) {
            $version = (string)$moodle['version'];
            $list[$version] = array();
            $list[$version]['moodle'] = 'core';
            foreach ($moodle->plugins as $plugins) {
                $type = (string)$plugins['type'];
                foreach ($plugins as $plugin) {
                    $name = (string)$plugin;
                    if ($type == 'core' or $type == 'mod') {
                        $list[$version][$name] = "{$type}_{$name}";
                    } else {
                        $list[$version]["{$type}_{$name}"] = "{$type}_{$name}";
                    }
                }
            }
        }
    }

    return $list;
}

/**
 * Returns the list of app components
 *
 * @return array (string)frankenstylename
 */
function local_amos_app_plugins() {
    global $DB;

    static $list = null;

    if (is_null($list)) {
        $components = $DB->get_fieldset_select('amos_app_strings', 'DISTINCT component', "");
        $list = array_combine($components, $components);
        $list['local_moodlemobileapp'] = 'local_moodlemobileapp';
    }

    return $list;
}

/**
 * Returns the list of app components
 *
 * @return array (string)component/(string)stringid => (string)appid
 */
function local_amos_applist_strings() {
    global $DB;

    static $applist = null;

    if (is_null($applist)) {
        // get the app strings
        $applist = array();
        $rs = $DB->get_records('amos_app_strings');
        foreach ($rs as $s) {
            $applist[$s->component.'/'.$s->stringid] = $s->appid;
        }
    }



    return $applist;
}

/**
 * Returns the options used for {@link importfile_form.php}
 *
 * @return array
 */
function local_amos_importfile_options() {

    $options = array();

    $options['versions'] = array();
    $options['versioncurrent'] = null;
    foreach (mlang_version::list_all() as $version) {
        if ($version->translatable) {
            $options['versions'][$version->code] = $version->label;
        }
    }
    $options['versioncurrent'] = mlang_version::latest_version()->code;
    $options['languages'] = array_merge(array('' => get_string('choosedots')), mlang_tools::list_languages(false));
    $currentlanguage = current_language();
    if ($currentlanguage === 'en') {
        $currentlanguage = 'en_fix';
    }
    $options['languagecurrent'] = $currentlanguage;

    return $options;
}

/**
 * Returns the options used for {@link execute_form.php}
 *
 * @return array
 */
function local_amos_execute_options() {

    $options = array();

    $options['versions'] = array();
    $options['versioncurrent'] = null;
    $latestversioncode = mlang_version::latest_version()->code;
    foreach (mlang_version::list_all() as $version) {
        if ($version->translatable) {
            $options['versions'][$version->code] = $version->label;
            if ($version->code == $latestversioncode) {
                $options['versioncurrent'] = $version->code;
            }
        }
    }

    return $options;
}

/**
 * Returns an array of the changes from $old text to the $new one
 *
 * This is just slightly customized version of Paul's Simple Diff Algorithm.
 * Given two arrays of chunks (words), the function returns an array of the changes
 * leading from $old to $new.
 *
 * @author Paul Butler
 * @copyright (C) Paul Butler 2007 <http://www.paulbutler.org/>
 * @license May be used and distributed under the zlib/libpng license
 * @link https://github.com/paulgb/simplediff
 * @version 26f97a48598d7b306ae9
 * @param array $old array of words
 * @param array $new array of words
 * @return array
 */
function local_amos_simplediff(array $old, array $new) {

    $maxlen = 0;

    foreach ($old as $oindex => $ovalue) {
        $nkeys = array_keys($new, $ovalue);
        foreach ($nkeys as $nindex){
            $matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ?
                $matrix[$oindex - 1][$nindex - 1] + 1 : 1;
            if ($matrix[$oindex][$nindex] > $maxlen) {
                $maxlen = $matrix[$oindex][$nindex];
                $omax   = $oindex + 1 - $maxlen;
                $nmax   = $nindex + 1 - $maxlen;
            }
        }
    }
    if ($maxlen == 0) {
        return array(array('d' => $old, 'i' => $new));
    }
    return array_merge(
        local_amos_simplediff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
        array_slice($new, $nmax, $maxlen),
        local_amos_simplediff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen)));
}
