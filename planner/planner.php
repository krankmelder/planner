<?php
/*
 +-------------------------------------------------------------------------+
 | Roundcube Planner plugin                                                |
 | @version @package_version@                                              |
 |                                                                         |
 | Copyright (C) 2011, Lazlo Westerhof.                                    |
 |                                                                         |
 | This program is free software; you can redistribute it and/or modify    |
 | it under the terms of the GNU General Public License version 2          |
 | as published by the Free Software Foundation.                           |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 |                                                                         |
 | You should have received a copy of the GNU General Public License along |
 | with this program; if not, write to the Free Software Foundation, Inc., |
 | 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.             |
 |                                                                         |
 +-------------------------------------------------------------------------+
 | Author: Lazlo Westerhof <roundcube@lazlo.me>                            |
 +-------------------------------------------------------------------------+
*/

/**
 * Roundcube Planner plugin
 *
 * Planner is a task-management plugin for Roundcube.
 * A hybrid between a todo-list and a calendar.
 */
class planner extends rcube_plugin
{
  public $task = '?(?!login|logout).*';

  private $rc;
  private $user;

  function init() {
    $this->rc = rcmail::get_instance();
    $this->user = $this->rc->user->ID;

    // load localization
    $this->add_texts('localization/', true);

    // register actions
    $this->register_action('plugin.planner', array($this, 'startup'));
    $this->register_action('plugin.plan_new', array($this, 'plan_new'));
    $this->register_action('plugin.plan_done', array($this, 'plan_done'));
    $this->register_action('plugin.plan_star', array($this, 'plan_star'));
    $this->register_action('plugin.plan_unstar', array($this, 'plan_unstar'));
    $this->register_action('plugin.plan_delete', array($this, 'plan_delete'));
    $this->register_action('plugin.plan_retrieve', array($this, 'plan_retrieve'));

    // add planner button to taskbar
    $this->add_button(array(
      'name'    => 'planner',
      'class'   => 'button-planner',
      'label'   => 'planner.planner',
      'href'    => './?_task=dummy&_action=plugin.planner',
      'id'      => 'planner_button'
      ), 'taskbar');
      
    // include stylesheet
    $skin = $this->rc->config->get('skin');
    if(!file_exists($this->home . '/skins/' . $skin . '/planner.css')) {
      $skin = "default";
    }
    $this->include_stylesheet('skins/' . $skin . '/planner.css');
  }

  /**
   * Startup planner, set pagetitle, include javascript and send output.
   */
  function startup() {
    // set pagetitle
    $this->rc->output->set_pagetitle($this->getText('planner'));

    // include javascript
    $this->include_script('planner.js');

    // send output
    $this->rc->output->send('planner.planner');
  }
   
  /**
   * Create new plan
   */
  function plan_new() {
    if (!empty($this->user)) {
      $raw = get_input_value('_p', RCUBE_INPUT_POST);
      $formatted = $this->rawToFormatted($raw);

      $datetime = null;
      if(!empty($formatted['datetime'])) {
        $datetime = date( 'Y-m-d H:i:s', strtotime($formatted['datetime']));
      }

      $query = $this->rc->db->query(
        "INSERT INTO planner
        (user_id, datetime, text)
        VALUES (?, ?, ?)",
        $this->user,
        $datetime,
        trim($formatted['text'])
      );
      $this->rc->output->command('plugin.plan_reload', array());
    }
  }

  /**
   * Mark plan done
   */
  function plan_done() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);

      $query = $this->rc->db->query(
        "UPDATE planner SET done=? WHERE id=?",
        1, $id
      );
    }
  }

  /**
   * Mark plan starred
   */
  function plan_star() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);

      $query = $this->rc->db->query(
        "UPDATE planner SET starred=? WHERE id=?",
        1, $id
      );
      $this->rc->output->command('plugin.plan_reload', array());
    }
  }

  /**
   * Unmark starred plan
   */
  function plan_unstar() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);

      $query = $this->rc->db->query(
        "UPDATE planner SET starred=? WHERE id=?",
        0, $id
      );
      $this->rc->output->command('plugin.plan_reload', array());
    }
  }

  /**
   * Delete a plan
   */
  function plan_delete() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);

      $query = $this->rc->db->query(
        "UPDATE planner SET deleted=? WHERE id=?",
        1, $id
      );
    }
  }

  /**
   * Retrieve plans and output as html
   */
  function plan_retrieve() {
    if (!empty($this->user)) {
      $done = false;
      switch(get_input_value('_p', RCUBE_INPUT_POST)) {
        // retrieve all
        case "all":
          $result = $this->rc->db->query("SELECT * FROM planner
                                          WHERE user_id=? AND done =? AND deleted =?
                                          ORDER BY `datetime` ASC",
                                          $this->rc->user->ID, 0, 0
                                         );
          break;
        // retrieve starred
        case "starred":
          $result = $this->rc->db->query("SELECT * FROM planner
                                          WHERE user_id=? AND done =? AND deleted =? AND starred =?
                                          ORDER BY `datetime` ASC",
                                          $this->rc->user->ID, 0, 0, 1
                                         );
          break;
        // retrieve today's
        case "today":
          $result = $this->rc->db->query("SELECT * FROM planner
                                          WHERE user_id=? AND done =? AND deleted =? AND DATE(datetime) = DATE(NOW())
                                          ORDER BY `datetime` ASC",
                                          $this->rc->user->ID, 0, 0
                                         );
          break;
        // retrieve tomorrow's
        case "tomorrow":
          $result = $this->rc->db->query("SELECT * FROM planner
                                          WHERE user_id=? AND done =? AND deleted =? AND TO_DAYS(datetime) = TO_DAYS(NOW())+1
                                          ORDER BY `datetime` ASC",
                                          $this->rc->user->ID, 0, 0
                                         );
          break;
        // retrieve this week
        case "week":
          $result = $this->rc->db->query("SELECT * FROM planner
                                          WHERE user_id=? AND done =? AND deleted =? AND WEEK(datetime) = WEEK(NOW())
                                          ORDER BY `datetime` ASC",
                                          $this->rc->user->ID, 0, 0
                                         );
          break;
        // retrieve done
        case "done":
          $result = $this->rc->db->query("SELECT * FROM planner
                                          WHERE user_id=? AND deleted =? AND done =?
                                          ORDER BY `datetime` ASC",
                                          $this->rc->user->ID, 0, 1
                                         );
          $done = true;
          break;
        // retrieve all
        default:
          $result = $this->rc->db->query("SELECT * FROM planner
                                          WHERE user_id=? AND deleted =?
                                          ORDER BY `datetime` ASC",
                                          $this->rc->user->ID, 0
                                         );
          break;
      }
      // send plans to client
      $this->rc->output->command('plugin.plan_retrieve', $this->html($result, $done));
    }
  }

  /**
   * Convert raw plan to formatted item with seperated date, time and text.
   * Returns formatted array if it is an item with a datetime.
   * Returns false if it is a text-only item.
   *
   * @param  raw       Raw plan
   * @return array     Formatted item with seperated date/time
   */
  private function rawToFormatted($raw) {
	$raw = trim($raw);
    $split = preg_split("/[\s]+/", $raw, 3);
    // today
    if("today" == $split['0']) {
        if($this->matchTime($split['1'])) {
            $formatted['datetime'] = date('Y-m-d') . " " . $this->matchTime($split['1']);
            $formatted['text'] = $split['2'];
        }
        else {
            $formatted['datetime'] = date('Y-m-d') . "08:00:00";
            $formatted['text'] = $split['1']. " " .$split['2'];
        }
        return $formatted;
    }
    // tomorrow
    elseif("tomorrow" == $split['0']) {
        if($this->matchTime($split['1'])) {
            $formatted['datetime'] = date('Y-m-d', mktime(0, 0, 0, date("m"), date("d")+1, date("Y"))) . $this->matchTime($split['1']);
            $formatted['text'] = $split['2'];
        }
        else {
            $formatted['datetime'] = date('Y-m-d', mktime(0, 0, 0, date("m"), date("d")+1, date("Y"))) . "08:00:00";
            $formatted['text'] = $split['1']. " " .$split['2'];
        }
        return $formatted;
    }
    // +5
    elseif(preg_match('/\+(([0-9][0-9])|([0-9]))/', $split['0'], $matches)) {
        if($this->matchTime($split['1'])) {
            $formatted['datetime'] = date('Y-m-d', mktime(0, 0, 0, date("m"), date("d")+$matches['1'], date("Y"))) . $this->matchTime($split['1']);
            $formatted['text'] = $split['2'];
        }
        else {
            $formatted['datetime'] = date('Y-m-d', mktime(0, 0, 0, date("m"), date("d")+$matches['1'], date("Y"))) . "08:00:00";
            $formatted['text'] = $split['1']. " " .$split['2'];
        }
        return $formatted;
    }
    // dd/mm/yyyy
    elseif(preg_match('/(0[1-9]|[12][0-9]|3[01])[\.\-\/](0[1-9]|1[012])[\.\-\/]((20)[0-9][0-9])/', $split['0'], $matches)) {
        if($this->matchTime($split['1'])) {
            $formatted['datetime'] = date('Y-m-d', mktime(0, 0, 0, $matches['2'], $matches['1'], $matches['3'])) . $this->matchTime($split['1']);
            $formatted['text'] = $split['2'];
        }
        else {
            $formatted['datetime'] = date('Y-m-d', mktime(0, 0, 0, $matches['2'], $matches['1'], $matches['3'])) . "08:00:00";
            $formatted['text'] = $split['1']. " " .$split['2'];
        }
        return $formatted;
	}
    // dd/mm
    elseif(preg_match('/(0[1-9]|[12][0-9]|3[01])[\.\-\/](0[1-9]|1[012])/', $split['0'], $matches)) {
        if($this->matchTime($split['1'])) {
            $formatted['datetime'] = date('Y-m-d', mktime(0, 0, 0, $matches['2'], $matches['1'], date('Y'))) . $this->matchTime($split['1']);
            $formatted['text'] = $split['2'];
        }
        else {
            $formatted['datetime'] = date('Y-m-d', mktime(0, 0, 0, $matches['2'], $matches['1'], date('Y'))) . "08:00:00";
            $formatted['text'] = $split['1']. " " .$split['2'];
        }
        return $formatted;
	}
    else {
        $formatted['text'] = $raw;
        return $formatted;
    }
    return false;
  }

  /**
   * Convert raw a possible plan time to formatted item time.
   * Defaults to 08:00 if no time could be matched.
   *
   * @param  raw       Possible raw planner itme time
   * @return string    Formatted plan time
   */
  private function matchTime($raw) {
    // match hh:mm
    if(preg_match('/(([0-1][0-9])|([2][0-3])):([0-5][0-9])/', $raw, $matches)) {
      return $matches[0] . ":00";
    }
    // match time 12h
    elseif(preg_match('/(([0-1][0-9])|([2][0-3]))h/', $raw, $matches)) {
      return $matches[1].":00:00";
    }
    // no time?
    else {
      return false;
    }
  }

  /**
   * Convert plans retrieved from database to formatted html.
   *
   * @param  result    Results from plan retrieval from database
   * @param  done      Is plan done?
   * @return string    Formatted planner as html
   */
  private function html($result, $done) {
    $html = "<ul>";
    // loop over all plans retrieved
    while ($result && ($plan = $this->rc->db->fetch_assoc($result))) {
	  $timestamp = strtotime($plan['datetime']);
	  if(date('Ymd', $timestamp) === date('Ymd')) {
		 $html.= "<li id=\"" . $plan['id'] . "\" class=\"today\">";
	  }
	  else {
		 $html.= "<li id=\"" . $plan['id'] . "\">";
	  }
      // starred plan
      if($plan['starred']) {
          $html.= "<a class=\"star\" title=\"" . $this->getText('unmark') . "\"></a>";
      }
      else {
          $html.= "<a class=\"nostar\" title=\"" . $this->getText('mark') . "\"></a>";
      }
      // plan with date/time
      if(!empty($plan['datetime'])) {
          $html.= "<span class=\"date\">" . date('d M', $timestamp) . "</span>";
          $html.= "<span class=\"time\">" . date('H:i', $timestamp) . "</span>";
          $html.= "<span class=\"datetime\">" . $plan['text'] . "</span>";
      }
      // plan without date/time
      else {
          $html.= "<span class=\"nodate\">" . $plan['text'] . "</span>";
      }
	// finished plan
      if($done) {
        $html.= "<a class=\"delete\" href=\"#\" title=\"" . $this->getText('delete') . "\"></a>";
      }
	  // not finished plan
      else {
        $html.= "<a class=\"done\" href=\"#\" title=\"" . $this->getText('done') . "\"></a>";
      }
      $html.= "</li>";
    }
    $html .= "</ul>";

    return $html;
  }
}
?>
