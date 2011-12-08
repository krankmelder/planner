/*
 * Roundcube Planner
 * @version @package_version@
 * @author Lazlo Westerhof
 */
$(document).ready(function() {
  var list = 'all';

  // add event listeners
  rcmail.addEventListener('plugin.plan_retrieve', function(response) {
    $('#planner_items').html(response);
  });
  rcmail.addEventListener('plugin.plan_reload', function(response) {
    rcmail.http_post('plugin.plan_retrieve', '_p=' + list);
  });

  // load plans
  rcmail.http_post('plugin.plan_retrieve', '_p=all');

  // listeners
  $('#planner_submit').click(function() {
    rcmail.http_post('plugin.plan_new', '_p=' + encodeURIComponent($('#planner_raw').val()));
    $('#planner_raw').val("");
  });
  // use .on() for jQuery 1.7+
  $("a.done").live("click", function(){
    rcmail.http_post('plugin.plan_done', '_id=' + $(this).parent().attr("id"));
    $(this).parent().remove();
  });
  $('a.star').live("click", function(){
    rcmail.http_post('plugin.plan_unstar', '_id=' + $(this).parent().attr("id"));
  });
  $('a.nostar').live("click", function(){
    rcmail.http_post('plugin.plan_star', '_id=' + $(this).parent().attr("id"));
  });
  $('a.delete').live("click", function(){
    rcmail.http_post('plugin.plan_delete', '_id=' + $(this).parent().attr("id"));
    $(this).parent().remove();
  });

  $('#all').click(function() {
    rcmail.http_post('plugin.plan_retrieve', '_p=all');
    list = 'all';
  });
  $('#starred').click(function() {
    rcmail.http_post('plugin.plan_retrieve', '_p=starred');
    list = 'starred';
  });
  $('#today').click(function() {
    rcmail.http_post('plugin.plan_retrieve', '_p=today');
    list = 'today';
  });
  $('#tomorrow').click(function() {
    rcmail.http_post('plugin.plan_retrieve', '_p=tomorrow');
    list = 'tomorrow';
  });
  $('#week').click(function() {
    rcmail.http_post('plugin.plan_retrieve', '_p=week');
    list = 'week';
  });
  $('#done').click(function() {
    rcmail.http_post('plugin.plan_retrieve', '_p=done');
    list = 'done';
  });
  
  // help
  $("#help").click(function () {
      $("#planner_help").slideToggle("slow");
  });
});
