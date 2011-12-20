/*
 * Roundcube Planner
 * @version @package_version@
 * @author Lazlo Westerhof
 */
$(document).ready(function () {
  // strict mode syntax
  "use strict";

  // settings
  var list = 'all', preview = true;

  // add event listeners
  rcmail.addEventListener('plugin.plan_retrieve', function (response) {
    $('#plans').html(response);
    $('#planner_raw').focus();
  });
  rcmail.addEventListener('plugin.plan_counts', function (response) {
    // set list counts
    var lists = ['all', 'starred', 'today', 'tomorrow', 'week'];
    $.each(lists, function (key, value) {
      $('#' + value + ' span.count').html(response[value]);
    });
  });
  rcmail.addEventListener('plugin.plan_reload', function () {
    rcmail.http_post('plugin.plan_retrieve', '_p=' + list);
    // set list counts
    rcmail.http_post('plugin.plan_counts', '');
  });
  rcmail.addEventListener('plugin.plan_preview', function (response) {
    if (preview) {
      $('#plan_preview').html(response);
      $('#plan_preview').show();
    }
  });
  rcmail.addEventListener('plugin.plan_edit', function (response) {
    $('#' + response.id + ' span.edit').replaceWith(response.html);
    $('#' + response.id + ' #plan_edit_raw').focus();
  });
  rcmail.addEventListener('plugin.plan_init', function (response) {
    // override settings
    list = response.default_list;
    preview = response.preview_plan;
    // load plans
    rcmail.http_post('plugin.plan_retrieve', '_p=' + list);
    // set list counts
    rcmail.http_post('plugin.plan_counts', '');
    $('#' + list).toggleClass("active");
  });

  // startup planner javascript
  rcmail.http_post('plugin.plan_init', '');

  // listeners
  // use .on() for jQuery 1.7+
  $('#planner_submit').click(function (e) {
    if ($('#planner_raw').val() !== "") {
      e.preventDefault();
      rcmail.http_post('plugin.plan_new', '_p=' + encodeURIComponent($('#planner_raw').val()));
      $('#planner_raw').val("");
      // increase listcount by 1
      var count = parseInt($('#' + list + ' span.count').text(), 10) + 1;
      $('#' + list + ' span.count').html(count);
      // remove preview
      $('#plan_preview').html("");
      $('#plan_preview').hide();
    }
  });
  $('#planner_raw').keypress(function (e) {
    if ($('#planner_raw').val() !== "") {
      var keycode = e.keyCode ? e.keyCode : e.which;
      if (keycode === '13') {
        e.preventDefault();
        rcmail.http_post('plugin.plan_new', '_p=' + encodeURIComponent($('#planner_raw').val()));
        $('#planner_raw').val("");
        // increase listcount by 1
        var count = parseInt($('#' + list + ' span.count').text(), 10) + 1;
        $('#' + list + ' span.count').html(count);
        // remove preview
        $('#plan_preview').html("");
        $('#plan_preview').hide();
      }
    }
  });
  $('#planner_raw').keyup(function () {
    if (preview && $('#planner_raw').val()) {
      // load preview
      rcmail.http_post('plugin.plan_preview', '_p=' + encodeURIComponent($('#planner_raw').val()));
    } else {
      // remove preview
      $('#plan_preview').html("");
      $('#plan_preview').hide();
    }
  });
  $('#planner_raw').focusout(function () {
    if (!$('#planner_raw').val()) {
      // remove preview
      $('#plan_preview').html("");
      $('#plan_preview').hide();
    }
  });

  // plan functions
  $("a.done").live("click", function () {
    rcmail.http_post('plugin.plan_done', '_id=' + $(this).parent().attr("id"));
    $(this).parent().remove();
    // set list counts
    rcmail.http_post('plugin.plan_counts', '');
  });
  $('a.star').live("click", function () {
    rcmail.http_post('plugin.plan_unstar', '_id=' + $(this).parent().attr("id"));
  });
  $('a.nostar').live("click", function () {
    rcmail.http_post('plugin.plan_star', '_id=' + $(this).parent().attr("id"));
  });
  $('a.delete').live("click", function () {
    rcmail.http_post('plugin.plan_delete', '_id=' + $(this).parent().attr("id"));
    $(this).parent().remove();
  });
  $('span.edit').live("click", function () {
    rcmail.http_post('plugin.plan_raw', '_id=' + $(this).parent().attr("id"));
  });

  // set active view
  function setActive(id) {
    $('#' + list).toggleClass("active");
    list = id;
    $('#' + id).toggleClass("active");
  }

  // list plans
  $('#all').click(function () {
    rcmail.http_post('plugin.plan_retrieve', '_p=all');
    setActive('all');
  });
  $('#starred').click(function () {
    rcmail.http_post('plugin.plan_retrieve', '_p=starred');
    setActive('starred');
  });
  $('#today').click(function () {
    rcmail.http_post('plugin.plan_retrieve', '_p=today');
    setActive('today');
  });
  $('#tomorrow').click(function () {
    rcmail.http_post('plugin.plan_retrieve', '_p=tomorrow');
    setActive('tomorrow');
  });
  $('#week').click(function () {
    rcmail.http_post('plugin.plan_retrieve', '_p=week');
    setActive('week');
  });
  $('#done').click(function () {
    rcmail.http_post('plugin.plan_retrieve', '_p=done');
    setActive('done');
  });

  // edit
  $('#planner_edit_save').live("click", function () {
    rcmail.http_post('plugin.plan_edit', '_id=' + $(this).parent().attr("id") + '&_p=' + encodeURIComponent($('#plan_edit_raw').val()));
  });
  $('#planner_edit_cancel').live("click", function () {
    rcmail.http_post('plugin.plan_retrieve', '_p=' + list);
  });

  // help
  $("#help").click(function () {
    $("#planner_help").slideToggle("slow");
    $(this).toggleClass("active");
  });
});
