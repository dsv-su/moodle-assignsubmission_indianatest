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
 * This file contains the definition for the library class for indianatest submission plugin
 *
 * This class provides all the functionality for the new assign module.
 *
 * @package assignsubmission_indianatest
 * @copyright 2018 Pavel Sokolov {@link pavel.m.sokolov@gmail.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * library class for indianatest submission plugin extending submission plugin base class
 *
 * @package assignsubmission_indianatest
 * @copyright 2018 Pavel Sokolov {@link pavel.m.sokolov@gmail.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_indianatest extends assign_submission_plugin {

    /**
     * Get the name of the Indiana Test submission plugin
     * @return string
     */
    public function get_name() {
        return get_string('indianatest', 'assignsubmission_indianatest');
    }


    /**
     * Get indianatest submission information from the database
     *
     * @param  int $submissionid
     * @return mixed
     */
    private function get_indianatest_submission($submissionid) {
        global $DB;

        return $DB->get_record('assignsubmission_indianatest', array('submission'=>$submissionid));
    }

    /**
     * Get the settings for indianatest submission plugin
     *
     * @param MoodleQuickForm $mform The form to add elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        global $CFG, $COURSE;

    }

    /**
     * Save the settings for indianatest submission plugin
     *
     * @param stdClass $data
     * @return bool
     */
    public function save_settings(stdClass $data) {

        return true;
    }

    /**
     * Add form elements for settings
     *
     * @param mixed $submission can be null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return true if elements were added to the form
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        $elements = array();

        $mform->addElement('static', 'description', '', 'Enter the unique Test ID printed on the Certificate');
        $mform->addElement('text', 'testid', get_string('testid', 'assignsubmission_indianatest'));
        $mform->setType('testid', PARAM_INT);

        $mform->addElement('static', 'description', '', 'Enter the email address to which the Certificate was sent');
        $mform->addElement('text', 'email', get_string('email', 'assignsubmission_indianatest'));
        $mform->setType('email', PARAM_TEXT);

        $mform->addElement('static', 'description', '', 'Alternatively you can enter the IP address from the Certificate');
        $mform->addElement('text', 'ipnumber', get_string('ipnumber', 'assignsubmission_indianatest'));
        $mform->setType('ipnumber', PARAM_TEXT);

        $submissionid = $submission ? $submission->id : 0;

        if ($submission) {
            $indianatestsubmission = $this->get_indianatest_submission($submission->id);
            if ($indianatestsubmission) {
                $data->testid = $indianatestsubmission->testid;
                $data->email = $indianatestsubmission->email;
                $data->ipnumber = $indianatestsubmission->ipnumber;
            }
        }

        return true;
    }

    /**
     * Save data to the database
     *
     * @param stdClass $submission
     * @param stdClass $data
     * @return bool
     */
    public function save(stdClass $submission, stdClass $data) {
        global $USER, $DB;


        $indianatestsubmission = $this->get_indianatest_submission($submission->id);

        // Check that all values are ok before submitting anything.
        if (empty($data->email) && empty($data->ipnumber)) {
            $this->set_error('You have to supply either email of IP address of the issued certificate');
            return false;
        }

        $params = array(
            'context' => context_module::instance($this->assignment->get_course_module()->id),
            'courseid' => $this->assignment->get_course()->id
        );
        if (!empty($submission->userid) && ($submission->userid != $USER->id)) {
            $params['relateduserid'] = $submission->userid;
        }

        $groupname = null;
        $groupid = 0;
        // Get the group name as other fields are not transcribed in the logs and this information is important.
        if (empty($submission->userid) && !empty($submission->groupid)) {
            $groupname = $DB->get_field('groups', 'name', array('id' => $submission->groupid), MUST_EXIST);
            $groupid = $submission->groupid;
        } else {
            $params['relateduserid'] = $submission->userid;
        }

        $params['other'] = array(
            'submissionid' => $submission->id,
            'submissionattempt' => $submission->attemptnumber,
            'submissionstatus' => $submission->status,
            'groupid' => $groupid,
            'groupname' => $groupname,
            'testid' => $data->testid
        );

        if ($indianatestsubmission) {
            if (
                $indianatestsubmission->testid <> $data->testid ||
                (!empty($data->email) && $indianatestsubmission->email <> $data->email) ||
                (!empty($data->ipnumber) && $indianatestsubmission->ipnumber <> $data->ipnumber)) {
                $indianatestsubmission->valid = 0;
            }
            $indianatestsubmission->testid = $data->testid;
            $indianatestsubmission->email = $data->email;
            $indianatestsubmission->ipnumber = $data->ipnumber;
            $params['objectid'] = $indianatestsubmission->id;
            $updatestatus = $DB->update_record('assignsubmission_indianatest', $indianatestsubmission);
            
            $event = \assignsubmission_indianatest\event\submission_updated::create($params);
            $event->set_assign($this->assignment);
            $event->trigger();
            return $updatestatus;
        } else {
            $indianatestsubmission = new stdClass();
            $indianatestsubmission->testid = $data->testid;
            $indianatestsubmission->email = $data->email;
            $indianatestsubmission->ipnumber = $data->ipnumber;

            $indianatestsubmission->submission = $submission->id;
            $indianatestsubmission->assignment = $this->assignment->get_instance()->id;
            $indianatestsubmission->id = $DB->insert_record('assignsubmission_indianatest', $indianatestsubmission);
            
            $params['objectid'] = $indianatestsubmission->id;
            $event = \assignsubmission_indianatest\event\submission_created::create($params);
            $event->set_assign($this->assignment);
            $event->trigger();
            return $indianatestsubmission->id > 0;
        }
    }

     /**
      * Display indianatest validation in the submission status table
      *
      * @param stdClass $submission
      * @param bool $showviewlink - If the summary has been truncated set this to true
      * @return string
      */
    public function view_summary(stdClass $submission, & $showviewlink) {
        global $CFG;

        $indianatestsubmission = $this->get_indianatest_submission($submission->id);

        // Always show the view link.
        $showviewlink = false;

        if ($indianatestsubmission) {
            return $this->output_test_info($indianatestsubmission);
        } else {
            return false;
        }
    }

    public function output_test_info(stdClass $indianatestsubmission) {
        $result = "Test ID: $indianatestsubmission->testid";

        if (!empty($indianatestsubmission->ipnumber)) {
            $result .= "<br>IP number: $indianatestsubmission->ipnumber";
        }

        if (!empty($indianatestsubmission->email)) {
            $result .= "<br>Email: $indianatestsubmission->email";
        }

        $test = $this->validate_test($indianatestsubmission);
        if ($test) {
            $date = date("Y-m-d H:i:s", $test->date);
            $result .= "<p><p>The Certificate is valid for $test->name. The plagiarism test was passed on $date and took $test->time minutes.<p>Level: $test->level";
        } else {
            $result .= '<p><p>The Certificate cannot not be validated. Either Test ID does not exist, or supplied data is incorrect.';
        }

        return $result;
    }

    public function validate_test(stdClass $indianatestsubmission) {
        global $CFG, $DB;

        require_once($CFG->dirroot.'/mod/assign/submission/indianatest/src/pgbrowser/pgbrowser.php');

        // Validate if testid is integer
        if (!is_numeric($indianatestsubmission->testid)) {
            return false;
        }

        // Validate that any of email or ip are added
        if (empty($indianatestsubmission->email) && empty($indianatestsubmission->ipnumber)) {
            return false;
        }

        if ($indianatestsubmission->valid
            && $indianatestsubmission->time
            && $indianatestsubmission->date
            && $indianatestsubmission->level
            && $indianatestsubmission->name) {
                return $indianatestsubmission;
        }

        // Open the website
        $url = 'https://www.indiana.edu/~academy/firstPrinciples/login.phtml?action=validate';
        $b = new PGBrowser();
        $page = $b->get($url);
        $form = $page->forms(1);
        $form->set('timeStamp', $indianatestsubmission->testid);
        $page = $form->submit();

        // Did the certificate exist?
        if (strpos($page->html, "No matches were found for Test ID")) {
            return false;
        }

        $validated = false;
        $validateddata = new stdClass();

        if (!empty($indianatestsubmission->email)) {
            $form = $page->forms(1);
            $form->set('email', $indianatestsubmission->email);
            $page = $form->submit();
            if (strpos($page->html, "The Certificate for e-mail") && strpos($page->html, "is valid for")) {
                $validated = true;
                $indianatestsubmission->name = $page->forms(1)->fields['userName'];
                $indianatestsubmission->time = $page->forms(1)->fields['elapsedTime'];
                $indianatestsubmission->date = strtotime($page->forms(1)->fields['whenTestPassed']);
                $indianatestsubmission->level = $page->forms(1)->fields['testLevel'];
            }
        }
        if (!empty($indianatestsubmission->ipnumber)) {
            $form = $page->forms(0);
            $form->set('ipNumber', $indianatestsubmission->ipnumber);
            $page = $form->submit();
            if (strpos($page->html, "The Certificate for IP number") && strpos($page->html, "is valid for")) {
                $validated = true;
                $indianatestsubmission->name = $page->forms(1)->fields['userName'];
                $indianatestsubmission->time = $page->forms(1)->fields['elapsedTime'];
                $indianatestsubmission->date = strtotime($page->forms(1)->fields['whenTestPassed']);
                $indianatestsubmission->level = $page->forms(1)->fields['testLevel'];
            }
        }

        if (!$validated) {
            return false;
        } else {
            $indianatestsubmission->valid = 1;
            $DB->update_record('assignsubmission_indianatest', $indianatestsubmission);
        }

        return $indianatestsubmission;
    }



    /**
     * Produce a list of files suitable for export that represent this submission.
     *
     * @param stdClass $submission - For this is the submission data
     * @param stdClass $user - This is the user record for this submission
     * @return array - return an array of files indexed by filename
     */
    public function get_files(stdClass $submission, stdClass $user) {
        global $DB;

        $files = array();
        $indianatestsubmission = $this->get_indianatest_submission($submission->id);

        // Note that this check is the same logic as the result from the is_empty function but we do
        // not call it directly because we already have the submission record.
        if ($indianatestsubmission && !empty($indianatestsubmission->testid)) {
            $finaltext = $this->output_test_info($indianatestsubmission);
            $formattedtext = format_text($finaltext, 1, array('context'=>$this->assignment->get_context()));
            $head = '<head><meta charset="UTF-8"></head>';
            $submissioncontent = '<!DOCTYPE html><html>' . $head . '<body>'. $formattedtext . '</body></html>';

            $filename = get_string('indianatestfilename', 'assignsubmission_indianatest');
            $files[$filename] = array($submissioncontent);
        }

        return $files;
    }

    /**
     * Display the saved text content from the editor in the view table
     *
     * @param stdClass $submission
     * @return string
     */
    public function view(stdClass $submission) {
        global $CFG;
        $result = '';
        return $result;
    }

    /**
     * Return true if this plugin can upgrade an old Moodle 2.2 assignment of this type and version.
     *
     * @param string $type old assignment subtype
     * @param int $version old assignment version
     * @return bool True if upgrade is possible
     */
    public function can_upgrade($type, $version) {
        return false;
    }


    /**
     * Formatting for log info
     *
     * @param stdClass $submission The new submission
     * @return string
     */
    public function format_for_log(stdClass $submission) {
        // Format the info for each submission plugin (will be logged).
        $indianatestsubmission = $this->get_indianatest_submission($submission->id);
        $indianatestloginfo = '';

        return $indianatestloginfo;
    }

    /**
     * The assignment has been deleted - cleanup
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        $DB->delete_records('assignsubmission_indianatest',
                            array('assignment'=>$this->assignment->get_instance()->id));

        return true;
    }

    /**
     * No testid is set for this plugin
     *
     * @param stdClass $submission
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        $indianatestsubmission = $this->get_indianatest_submission($submission->id);
        return empty($indianatestsubmission->testid);
    }

    /**
     * Determine if a submission is empty
     *
     * This is distinct from is_empty in that it is intended to be used to
     * determine if a submission made before saving is empty.
     *
     * @param stdClass $data The submission data
     * @return bool
     */
    public function submission_is_empty(stdClass $data) {
        return empty($data->testid);
    }

}


