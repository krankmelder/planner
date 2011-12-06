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
 * Plugin that adds a hybrid between a 
 * todo-listand a calendar to Roundcube.
 *
 * @author Lazlo Westerhof
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
    $this->register_action('plugin.planner_new', array($this, 'planner_new'));
    $this->register_action('plugin.planner_done', array($this, 'planner_done'));
    $this->register_action('plugin.planner_star', array($this, 'planner_star'));
    $this->register_action('plugin.planner_unstar', array($this, 'planner_unstar'));
    $this->register_action('plugin.planner_delete', array($this, 'planner_delete'));
    $this->register_action('plugin.planner_retrieve', array($this, 'planner_retrieve'));

    // register handlers
    $this->register_handler('plugin.all', array($this, '(10)'));
    $this->register_handler('plugin.starred', array($this, ''));
    $this->register_handler('plugin.today', array($this, '(8)'));
    $this->register_handler('plugin.tomorrow', array($this, ''));
    $this->register_handler('plugin.week', array($this, '(8)'));

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
   * Create new planner item
   */
  function planner_new() {
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
      $this->rc->db->insert_id('planner');
    }
  }

  /**
   * Mark planner item done
   */
  function planner_done() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);

      $query = $this->rc->db->query(
        "UPDATE planner SET done=? WHERE id=?",
        1, $id
      );
    }
  }

  /**
   * Mark planner item starred
   */
  function planner_star() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);

      $query = $this->rc->db->query(
        "UPDATE planner SET starred=? WHERE id=?",
        1, $id
      );
    }
  }

  /**
   * Unmark starred planner item
   */
  function planner_unstar() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);

      $query = $this->rc->db->query(
        "UPDATE planner SET starred=? WHERE id=?",
        0, $id
      );
    }
  }

  /**
   * Delete a planner item
   *
   */
  function planner_delete() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);

      $query = $this->rc->db->query(
        "UPDATE planner SET deleted=? WHERE id=?",
        1, $id
      );
    }
  }

  /**
   * Retrieve planner items and output as html
   */
  function planner_retrieve() {
    if (!empty($this->user)) {
      $done = false;
      switch(get_input_value('_p', RCUBE_INPUT_POST)) {
        // retrieve all
        case "all":
          $result = $this->rc->db->query("SELECT *, UNIX_TIMESTAMP(datetime) AS timestamp FROM planner
                                          WHERE user_id=? AND done =? AND deleted =?
                                          ORDER BY `datetime` ASC",
                                          $this->rc->user->ID, 0, 0
                                         );
          break;
        // retrieve starred
        case "starred":
          $result = $this->rc->db->query("SELECT *, UNIX_TIMESTAMP(datetime) AS timestamp FROM planner
                                          WHERE user_id=? AND done =? AND deleted =? AND starred =?
                                          ORDER BY `datetime` ASC",
                                          $this->rc->user->ID, 0, 0, 1
                                         );
          break;
        // retrieve today's
        case "today":
          $result = $this->rc->db->query("SELECT *, UNIX_TIMESTAMP(datetime) AS timestamp FROM planner
                                          WHERE user_id=? AND done =? AND deleted =? AND DATE(datetime) = DATE(NOW())
                                          ORDER BY `datetime` ASC",
                                          $this->rc->user->ID, 0, 0
                                         );
          break;
        // retrieve tomorrow's
        case "tomorrow":
          $result = $this->rc->db->query("SELECT *, UNIX_TIMESTAMP(datetime) AS timestamp FROM planner
                                          WHERE user_id=? AND done =? AND deleted =? AND TO_DAYS(datetime) = TO_DAYS(NOW())+1
                                          ORDER BY `datetime` ASC",
                                          $this->rc->user->ID, 0, 0
                                         );
          break;
        // retrieve this week
        case "week":
          $result = $this->rc->db->query("SELECT *, UNIX_TIMESTAMP(datetime) AS timestamp FROM planner
                                          WHERE user_id=? AND done =? AND deleted =? AND WEEK(datetime) = WEEK(NOW())
                                          ORDER BY `datetime` ASC",
                                          $this->rc->user->ID, 0, 0
                                         );
          break;
        // retrieve done
        case "done":
          $result = $this->rc->db->query("SELECT *, UNIX_TIMESTAMP(datetime) AS timestamp FROM planner
                                          WHERE user_id=? AND deleted =? AND done =?
                                          ORDER BY `datetime` ASC",
                                          $this->rc->user->ID, 0, 1
                                         );
          $done = true;
          break;
        // retrieve all
        default:
          $result = $this->rc->db->query("SELECT *, UNIX_TIMESTAMP(datetime) AS timestamp FROM planner
                                          WHERE user_id=? AND deleted =?
                                          ORDER BY `datetime` ASC",
                                          $this->rc->user->ID, 0
                                         );
          break;
      }
      // send planner items to client
      $this->rc->output->command('plugin.planner_retrieve', $this->html($result, $done));
    }
  }

  /**
   * Convert raw planner item to formatted item with seperated date, time and text.
   * Returns formatted array if it is an item with a datetime.
   * Returns false if it is a text-only item.
   *
   * @param  raw       Raw planner item
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
            $formatted['datetime'] = date('d-m-Y', mktime(0, 0, 0, date("m"), date("d")+$matches['1'], date("Y"))) . $this->matchTime($split['1']);
            $formatted['text'] = $split['2'];
        }
        else {
            $formatted['datetime'] = date('d-m-Y', mktime(0, 0, 0, date("m"), date("d")+$matches['1'], date("Y"))) . "08:00:00";
            $formatted['text'] = $split['1']. " " .$split['2'];
        }
        return $formatted;
    }
    // dd/mm/yyyy
    elseif(preg_match('/(0[1-9]|[12][0-9]|3[01])[\.\-\/](0[1-9]|1[012])[\.\-\/](20)\d\d/', $split['0'], $matches)) {
        if($this->matchTime($split['1'])) {
            $formatted['datetime'] = date('d-m-Y', mktime(0, 0, 0, $matches['2'], $matches['1'], $matches['3'])) . $this->matchTime($split['1']);
            $formatted['text'] = $split['2'];
        }
        else {
            $formatted['datetime'] = date('d-m-Y', mktime(0, 0, 0, $matches['2'], $matches['1'], $matches['3'])) . "08:00:00";
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
   * Convert raw a possible planner item time to formatted item time.
   * Defaults to 08:00 if no time could be matched.
   *
   * @param  raw       Possible raw planner itme time
   * @return string    Formatted planner item time
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
   * Convert planner items retrieved from database to formatted html.
   *
   * @param  result    Results from planner item retrieval from database
   * @param  done      Is planner item done?
   * @return string    Formatted planner as html
   */
  private function html($result, $done) {
    $html = "<ul>";
    // loop over all planner items retrieved
    while ($result && ($plan = $this->rc->db->fetch_assoc($result))) {
	  if(date('Ymd', $plan['timestamp']) === date('Ymd')) {
		 $html.= "<li id=\"" . $plan['id'] . "\" class=\"today\">";
	  }
	  else {
		 $html.= "<li id=\"" . $plan['id'] . "\">";
	  }
      // starred plan
      if($plan['starred']) {
          $html.= "<a class=\"star\"></a>";
      }
      else {
          $html.= "<a class=\"nostar\"></a>";
      }
      // planner item with date/time
      if(!empty($plan['datetime'])) {
          $html.= "<span class=\"date\">" . date('d M', $plan['timestamp']) . "</span>";
          $html.= "<span class=\"time\">" . date('H:i', $plan['timestamp']) . "</span>";
          $html.= "<span class=\"datetime\">" . $plan['text'] . "</span>";
      }
      // planner item without date/time
      else {
          $html.= "<span class=\"nodate\">" . $plan['text'] . "</span>";
      }
	// finished planner item
      if($done) {
        $html.= "<a class=\"delete\" href=\"#\"></a>";
      }
	  // not finished planner item
      else {
        $html.= "<a class=\"done\" href=\"#\"></a>";
      }
      $html.= "</li>";
    }
    $html .= "</ul>";

    return $html;
  }
}
?>
