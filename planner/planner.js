/*
 * Roundcube Planner
 * @version @package_version@
 * @author Lazlo Westerhof
 */
$(document).ready(function() {
  // add event listeners
  rcmail.addEventListener('plugin.planner_retrieve', function(response) {
    $('#planner_items').html(response);
  });

  // load planner items
  rcmail.http_post('plugin.planner_retrieve', '_p=all');

  // listeners
  $('#planner_submit').click(function() {
    if(rcmail.http_post('plugin.planner_new', '_p=' + encodeURIComponent($('#planner_raw').val()))) {
      $('#planner_raw').val("");
      rcmail.http_post('plugin.planner_retrieve', '_p=all');
    }
  });
  // use .on() for jQuery 1.7+
  $("a.done").live("click", function(){
    rcmail.http_post('plugin.planner_done', '_id=' + $(this).parent().attr("id"));
    $(this).parent().remove();
  });
  $('a.star').live("click", function(){
    rcmail.http_post('plugin.planner_unstar', '_id=' + $(this).parent().attr("id"));
  });
  $('a.nostar').live("click", function(){
    rcmail.http_post('plugin.planner_star', '_id=' + $(this).parent().attr("id"));
  });
  $('a.delete').live("click", function(){
    rcmail.http_post('plugin.planner_delete', '_id=' + $(this).parent().attr("id"));
    $(this).parent().remove();
  });

  $('#all').click(function() {
    rcmail.http_post('plugin.planner_retrieve', '_p=all');
  });
  $('#starred').click(function() {
    rcmail.http_post('plugin.planner_retrieve', '_p=starred');
  });
  $('#today').click(function() {
    rcmail.http_post('plugin.planner_retrieve', '_p=today');
  });
  $('#tomorrow').click(function() {
    rcmail.http_post('plugin.planner_retrieve', '_p=tomorrow');
  });
  $('#week').click(function() {
    rcmail.http_post('plugin.planner_retrieve', '_p=week');
  });
  $('#done').click(function() {
    rcmail.http_post('plugin.planner_retrieve', '_p=done');
  });
  
  // help
  $("#help").click(function () {
      $("#planner_help").slideToggle("slow");
  });
});
