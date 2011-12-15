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

    // load configuration
    $this->load_config('config.inc.php.dist');
    $this->load_config('config.inc.php');
   
    // load localization
    $this->add_texts('localization/', true);
      
    if($this->rc->task == 'settings') {
      $this->add_hook('preferences_sections_list', array($this, 'preferences_section'));
      $this->add_hook('preferences_list', array($this, 'preferences_list'));
      $this->add_hook('preferences_save', array($this, 'preferences_save'));
    }
    elseif($this->rc->task == 'planner') {
      // register actions
      $this->register_action('plugin.planner', array($this, 'startup'));
      $this->register_action('plugin.plan_init', array($this, 'plan_init'));
      $this->register_action('plugin.plan_new', array($this, 'plan_new'));
      $this->register_action('plugin.plan_done', array($this, 'plan_done'));
      $this->register_action('plugin.plan_star', array($this, 'plan_star'));
      $this->register_action('plugin.plan_unstar', array($this, 'plan_unstar'));
      $this->register_action('plugin.plan_edit', array($this, 'plan_edit'));
      $this->register_action('plugin.plan_delete', array($this, 'plan_delete'));
      $this->register_action('plugin.plan_retrieve', array($this, 'plan_retrieve'));
      $this->register_action('plugin.plan_counts', array($this, 'plan_counts'));
      $this->register_action('plugin.plan_raw', array($this, 'plan_raw'));
      $this->register_action('plugin.plan_preview', array($this, 'plan_preview'));
    }
    
    // add planner button to taskbar
    $this->add_button(array(
      'name'    => 'planner',
      'class'   => 'button-planner',
      'label'   => 'planner.planner',
      'href'    => './?_task=planner&_action=plugin.planner',
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
    $this->rc->output->set_pagetitle($this->gettext('planner'));

    // include javascript
    $this->include_script('planner.js');

    // send output
    $this->rc->output->send('planner.planner');
  }
  
  /**
   * Send plugin confiuration and list count to client
   */
  function plan_init() {
    if (!empty($this->user)) {
      // send configuration to client
      $config = array();
      $config['default_list'] = (string)$this->rc->config->get('default_list', "all");
      $config['preview_plan'] = $this->rc->config->get('preview_plan', true);

      $this->rc->output->command('plugin.plan_init', $config);
    }
  }

  /**
   * Create new plan
   */
  function plan_new() {
    if (!empty($this->user)) {
      $raw = get_input_value('_p', RCUBE_INPUT_POST);
      $formatted = $this->rawToFormatted($raw);

      $datetime = null;
      // convert usertime to GMT
      if(!empty($formatted['datetime'])) {
        $datetime = date( 'Y-m-d H:i:s', $this->toGMT(strtotime($formatted['datetime'])));
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
        "UPDATE planner SET done=? WHERE id=? AND user_id=?",
        1, $id, $this->user
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
        "UPDATE planner SET starred=? WHERE id=? AND user_id=?",
        1, $id, $this->user
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
        "UPDATE planner SET starred=? WHERE id=? AND user_id=?",
        0, $id, $this->user
      );
      $this->rc->output->command('plugin.plan_reload', array());
    }
  }

  /**
   * Edit a plan
   */
  function plan_edit() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);
      $raw = get_input_value('_p', RCUBE_INPUT_POST);

      $formatted = $this->rawToFormatted($raw);
      
      $datetime = null;
      // convert usertime to GMT
      if(!empty($formatted['datetime'])) {
        $datetime = date( 'Y-m-d H:i:s', $this->toGMT(strtotime($formatted['datetime'])));
      }
      $query = $this->rc->db->query(
        "UPDATE planner SET datetime=?, text=? WHERE id=? AND user_id=?",
        $datetime,
        trim($formatted['text']),
        $id,
        $this->user
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
        "UPDATE planner SET deleted=? WHERE id=? AND user_id=?",
        1, $id, $this->user
      );
    }
  }

  /**
   * Retrieve plans and output as html
   */
  function plan_retrieve() {
    if (!empty($this->user)) {
      $done = false;
      
      // show todo's always when true or only in all, starred and done
      $todo = "";
      if($this->rc->config->get('list_todo_always')) {
        $todo = " OR datetime IS NULL";
      }
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
                                          WHERE user_id=? AND done =? AND deleted =? AND (DATE(datetime) = DATE(NOW())". $todo . ")
                                          ORDER BY `datetime` ASC",
                                          $this->rc->user->ID, 0, 0
                                         );
          break;
        // retrieve tomorrow's
        case "tomorrow":
          $result = $this->rc->db->query("SELECT * FROM planner
                                          WHERE user_id=? AND done =? AND deleted =? AND (TO_DAYS(datetime) = TO_DAYS(NOW())+1". $todo . ")
                                          ORDER BY `datetime` ASC",
                                          $this->rc->user->ID, 0, 0
                                         );
          break;
        // retrieve this week
        case "week":
          $result = $this->rc->db->query("SELECT * FROM planner
                                          WHERE user_id=? AND done =? AND deleted =? AND (WEEK(datetime) = WEEK(NOW())". $todo . ")
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
   * Retrieve the number of plans in each list
   */
  function plan_counts() {
    // count todo plans?
    $todo = "";
    if($this->rc->config->get('list_todo_always')) {
      $todo = " OR datetime IS NULL";
    }
  
    // retrieve all the list counts
    $counts= array();
    $all = $this->rc->db->query("SELECT id FROM planner
                                 WHERE user_id=? AND done =? AND deleted =?",
                                 $this->rc->user->ID, 0, 0
                                );
    $counts['all'] = $this->rc->db->num_rows($all);
    $starred = $this->rc->db->query("SELECT id FROM planner
                                     WHERE user_id=? AND done =? AND deleted =? AND starred =?",
                                     $this->rc->user->ID, 0, 0, 1
                                    );
    $counts['starred'] = $this->rc->db->num_rows($starred);
    $today = $this->rc->db->query("SELECT id FROM planner
                                   WHERE user_id=? AND done =? AND deleted =? AND (DATE(datetime) = DATE(NOW())". $todo . ")",
                                   $this->rc->user->ID, 0, 0
                                  );
    $counts['today'] = $this->rc->db->num_rows($today);
    $tomorrow = $this->rc->db->query("SELECT id FROM planner
                                      WHERE user_id=? AND done =? AND deleted =? AND (TO_DAYS(datetime) = TO_DAYS(NOW())+1". $todo . ")",
                                      $this->rc->user->ID, 0, 0
                                     );
    $counts['tomorrow'] = $this->rc->db->num_rows($tomorrow);
    $week = $this->rc->db->query("SELECT id FROM planner
                                  WHERE user_id=? AND done =? AND deleted =? AND (WEEK(datetime) = WEEK(NOW())". $todo . ")",
                                  $this->rc->user->ID, 0, 0
                                 );
    $counts['week'] = $this->rc->db->num_rows($week);      

    $this->rc->output->command('plugin.plan_counts', $counts);
  }

  /**
   * Retrieve a plan in raw format for editing
   */
  function plan_raw() {
    if (!empty($this->user)) {
      $id = get_input_value('_id', RCUBE_INPUT_POST);

      $result = $this->rc->db->query("SELECT * FROM planner
                                      WHERE id=? AND user_id=?",
                                      $id, $this->rc->user->ID, 0, 0
                                     );
                                     
      $plan = $this->rc->db->fetch_assoc($result);
      
      $raw = $plan['text'];
      if(!empty($plan['datetime'])) {
        $raw = date('d/m/Y H:i', $this->toUserTime(strtotime($plan['datetime']))) . " " . $plan['text'];
      }

      $html = html::tag('input', array('id' => 'plan_edit_raw', 'type' => 'text', 'value' => $raw));
      $html .= html::tag('input', array('id' => 'planner_edit_save', 'type' => 'submit', 'value' => 'Save'));
      $html .= html::tag('input', array('id' => 'planner_edit_cancel', 'type' => 'submit', 'value' => 'Cancel'));
      
      $response = array('id' => $id, 'html' => $html);
      $this->rc->output->command('plugin.plan_edit', $response);
    }
  }
  
  /**
   * Create a plan preview from raw input
   */
  function plan_preview() {
    if (!empty($this->user)) {
      $raw = get_input_value('_p', RCUBE_INPUT_POST);

      // format raw input
      $formatted = $this->rawToFormatted($raw);
      
      // build preview html
      // add preview badge
      $preview .= html::span(array('id' => 'badge'), $this->gettext('preview'));

      // preview with date/time
      if(!empty($formatted['datetime'])) {
        $timestamp = strtotime($formatted['datetime']);
        $preview .= html::span('date', date('d M', $timestamp));
        $preview .= html::span('time', date('H:i', $timestamp));
        $preview .= html::span('datetime', $formatted['text']);
      }
      // preview without date/time
      else {
        $preview .= html::span('nodate', $formatted['text']);
      } 
     
      $this->rc->output->command('plugin.plan_preview', $preview);
    }
  }

  /**
   * Handler for preferences_sections_list hook.
   * Adds Planner settings sections into preferences sections list.
   *
   * @param array Original parameters
   *
   * @return array Modified parameters
   */
  function preferences_section($p) {
    $p['list']['plannersettings'] = array(
      'id' => 'plannersettings', 'section' => $this->gettext('planner'),
    );

    return $p;
  }

  /**
   * Handler for preferences_list hook.
   * Adds options blocks into Planner settings sections in Preferences.
   *
   * @param array Original parameters
   *
   * @return array Modified parameters
   */
  function preferences_list($p) {
    if ($p['section'] == 'plannersettings') {
      $p['blocks']['planner']['name'] = $this->gettext('mainoptions');
   
      $default_list = $this->rc->config->get('default_list', "all");
      $field_id = 'rcmfd_default_list';
      $select = new html_select(array('name' => '_default_list', 'id' => $field_id));
      $select->add($this->gettext('all'), "all");
      $select->add($this->gettext('starred'), "starred");
      $select->add($this->gettext('today'), "today");
      $select->add($this->gettext('tomorrow'), "tomorrow");
      $select->add($this->gettext('week'), "week");
      $select->add($this->gettext('done'), "done");      
      $p['blocks']['planner']['options']['default_list'] = array(
        'title' => html::label($field_id, Q($this->gettext('default_list'))),
        'content' => $select->show($this->rc->config->get('default_list')),
      );
      
      $list_todo_always = $this->rc->config->get('list_todo_always', false);
      $field_id = 'rcmfd_list_todo_always';
      $checkbox = new html_checkbox(array('name' => '_list_todo_always', 'id' => $field_id, 'value' => 1));
      $p['blocks']['planner']['options']['list_todo_always'] = array(
        'title' => html::label($field_id, Q($this->gettext('list_todo_always'))),
        'content' => $checkbox->show($list_todo_always?1:0),
      );
      
      $preview = $this->rc->config->get('preview_plan', true);
      $field_id = 'rcmfd_preview_plan';
      $checkbox = new html_checkbox(array('name' => '_preview_plan', 'id' => $field_id, 'value' => 1));
      $p['blocks']['planner']['options']['preview_plan'] = array(
        'title' => html::label($field_id, Q($this->gettext('preview_plan'))),
        'content' => $checkbox->show($preview?1:0),
      );
    } 
    return $p;
  }

  /**
   * Handler for preferences_save hook.
   * Executed on Planner settings form submit.
   *
   * @param array Original parameters
   * @return array Modified parameters
   */
  function preferences_save($p) {
    if ($p['section'] == 'plannersettings') {
      $p['prefs']['default_list'] = get_input_value('_default_list', RCUBE_INPUT_POST);
      $p['prefs']['list_todo_always'] = get_input_value('_list_todo_always', RCUBE_INPUT_POST) ? true : false;
      $p['prefs']['preview_plan'] = get_input_value('_preview_plan', RCUBE_INPUT_POST) ? true : false;
    }
    
    return $p;
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
    // loop over all plans retrieved
    $plans = "";
    while ($result && ($plan = $this->rc->db->fetch_assoc($result))) {
      $timestamp = $this->toUserTime(strtotime($plan['datetime']));
      $html = "";
      // starred plan
      if($plan['starred']) {
        $html .= html::a(array('class' => 'star', 'title' => $this->getText('unmark')), "");
      }
      else {
        $html .= html::a(array('class' => 'nostar', 'title' => $this->getText('mark')), "");
      }
      // plan with date/time
      $content = "";
      if(!empty($plan['datetime'])) {
        $content .= html::span('date', date('d M', $timestamp));
        $content .= html::span('time', date('H:i', $timestamp));
        $content .= html::span('datetime', $plan['text']);
      }
      // plan without date/time
      else {
        $content .= html::span('nodate', $plan['text']);
      }
      $html .= html::span('edit', $content);
      // finished plan
      if($done) {
        $html .= html::a(array('class' => 'delete', 'title' => $this->getText('delete_plan')), "");
      }
      // not finished plan
      else {
        $html .= html::a(array('class' => 'done', 'title' => $this->getText('mark_done')), "");
      }    
      // highlight today's and starred plans
      if(date('Ymd', $timestamp) === date('Ymd') || $plan['starred']) {
       $plans .= html::tag('li', array('id' => $plan['id'], 'class' => 'highlight'), $html);
      }
      else {
       $plans .= html::tag('li', array('id' => $plan['id']), $html);
      }
    }
    $list = html::tag('ul', array(), $plans);

    return $list;
  }
  
  /**
   * Correct GMT timestamp with timezone to user timestamp
   *
   * @param  timestamp GMT timestamp 
   * @return int       User timestamp
   */
  private function toUserTime($timestamp) {  
    return ($timestamp + $this->getTimzoneOffset($timestamp));
  }
  
  /**
   * Correct user timestamp with timezone to GMT timestamp
   *
   * @param  timestamp User timestamp 
   * @return int       GMT timestamp
   */
  private function toGMT($timestamp) {   
    return ($timestamp - $this->getTimzoneOffset($timestamp));
  }
  
  /**
   * Get offset of user timezone with GMT
   *
   * @return int User timezone offset
   */
   private function getTimzoneOffset() {
  // get timezone provided by the user
  $timezone = 0;
    if ($this->rc->config->get('timezone') === "auto") {
      $timezone = isset($_SESSION['timezone']) ? $_SESSION['timezone'] : date('Z')/3600;
    } else {
      $timezone = $this->rc->config->get('timezone');
      if($this->rc->config->get('dst_active')) {
        $timezone++;
      }
    }
    // calculate timezone offset
    return ($timezone * 3600);
  }
}
?>
