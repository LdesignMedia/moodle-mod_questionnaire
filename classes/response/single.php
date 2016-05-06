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
 * This file contains the parent class for questionnaire question types.
 *
 * @author Mike Churchward
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questiontypes
 */

namespace mod_questionnaire\response;
defined('MOODLE_INTERNAL') || die();

use mod_questionnaire\db\bulk_sql_config;

/**
 * Class for single response types.
 *
 * @author Mike Churchward
 * @package responsetypes
 */

class single extends base {
    public function response_table() {
        return 'questionnaire_resp_single';
    }

    public function insert_response($rid, $val) {
        global $DB;
        if (!empty($val)) {
            foreach ($this->question->choices as $cid => $choice) {
                if (strpos($choice->content, '!other') === 0) {
                    $other = optional_param('q'.$this->question->id.'_'.$cid, null, PARAM_CLEAN);
                    if (!isset($other)) {
                        continue;
                    }
                    if (preg_match("/[^ \t\n]/", $other)) {
                        $record = new \stdClass();
                        $record->response_id = $rid;
                        $record->question_id = $this->question->id;
                        $record->choice_id = $cid;
                        $record->response = $other;
                        $resid = $DB->insert_record('questionnaire_response_other', $record);
                        $val = $cid;
                        break;
                    }
                }
            }
        }
        if (preg_match("/other_q([0-9]+)/", (isset($val) ? $val : ''), $regs)) {
            $cid = $regs[1];
            if (!isset($other)) {
                $other = optional_param('q'.$this->question->id.'_'.$cid, null, PARAM_CLEAN);
            }
            if (preg_match("/[^ \t\n]/", $other)) {
                $record = new \stdClass();
                $record->response_id = $rid;
                $record->question_id = $this->question->id;
                $record->choice_id = $cid;
                $record->response = $other;
                $resid = $DB->insert_record('questionnaire_response_other', $record);
                $val = $cid;
            }
        }
        $record = new \stdClass();
        $record->response_id = $rid;
        $record->question_id = $this->question->id;
        $record->choice_id = isset($val) ? $val : 0;
        if ($record->choice_id) {// If "no answer" then choice_id is empty (CONTRIB-846).
            return $DB->insert_record($this->response_table(), $record);
        } else {
            return false;
        }
    }

    protected function get_results($rids=false) {
        global $DB;

        $rsql = '';
        $params = array($this->question->id);
        if (!empty($rids)) {
            list($rsql, $rparams) = $DB->get_in_or_equal($rids);
            $params = array_merge($params, $rparams);
            $rsql = ' AND response_id ' . $rsql;
        }
        // Added qc.id to preserve original choices ordering.
        $sql = 'SELECT rt.id, qc.id as cid, qc.content ' .
               'FROM {questionnaire_quest_choice} qc, ' .
               '{'.$this->response_table().'} rt ' .
               'WHERE qc.question_id= ? AND qc.content NOT LIKE \'!other%\' AND ' .
                     'rt.question_id=qc.question_id AND rt.choice_id=qc.id' . $rsql . ' ' .
               'ORDER BY qc.id';

        $rows = $DB->get_records_sql($sql, $params);

        // Handle 'other...'.
        $sql = 'SELECT rt.id, rt.response, qc.content ' .
               'FROM {questionnaire_response_other} rt, ' .
                    '{questionnaire_quest_choice} qc ' .
               'WHERE rt.question_id= ? AND rt.choice_id=qc.id' . $rsql . ' ' .
               'ORDER BY qc.id';

        if ($recs = $DB->get_records_sql($sql, $params)) {
            $i = 1;
            foreach ($recs as $rec) {
                $rows['other'.$i] = new \stdClass();
                $rows['other'.$i]->content = $rec->content;
                $rows['other'.$i]->response = $rec->response;
                $i++;
            }
        }

        return $rows;
    }

    public function display_results($rids=false, $sort='') {
        $this->display_response_choice_results($this->get_results($rids), $rids, $sort);
    }

    /**
     * Configure bulk sql
     * @return bulk_sql_config
     */
    protected function bulk_sql_config() {
        return new bulk_sql_config('questionnaire_resp_single', 'qrs', true, false, false);
    }
}