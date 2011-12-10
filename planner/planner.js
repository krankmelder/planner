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
  rcmail.addEventListener('plugin.plan_edit', function(response) {
    $('#' + response.id + ' span.edit').replaceWith('<input id="plan_edit_raw" type="text" value="' + response.raw + '"/><input id="planner_edit_save" class="plan_submit" type="button" value="Save"><input id="planner_edit_cancel" class="plan_submit" type="button" value="Cancel">');
    $('#' + response.id + ' #plan_edit_raw').focus();
  });

  // load plans
  rcmail.http_post('plugin.plan_retrieve', '_p=all');
  $('#all').toggleClass("active");
  $('#planner_raw').focus();

  // listeners
  // use .on() for jQuery 1.7+
  $('#planner_submit').click(function() {
    rcmail.http_post('plugin.plan_new', '_p=' + encodeURIComponent($('#planner_raw').val()));
    $('#planner_raw').val("");
  });
  
  // plan functions
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
  $('span.edit').live("click", function(){ 
    rcmail.http_post('plugin.plan_raw', '_id=' + $(this).parent().attr("id"));
  });
  
  // list plans
  $('#all').click(function() {
    rcmail.http_post('plugin.plan_retrieve', '_p=all');
    setActive('all');
  });
  $('#starred').click(function() {
    rcmail.http_post('plugin.plan_retrieve', '_p=starred');
    setActive('starred');
  });
  $('#today').click(function() {
    rcmail.http_post('plugin.plan_retrieve', '_p=today');
    setActive('today');
  });
  $('#tomorrow').click(function() {
    rcmail.http_post('plugin.plan_retrieve', '_p=tomorrow');
    setActive('tomorrow');
  });
  $('#week').click(function() {
    rcmail.http_post('plugin.plan_retrieve', '_p=week');
    setActive('week');
  });
  $('#done').click(function() {
    rcmail.http_post('plugin.plan_retrieve', '_p=done');
    setActive('done');
  });
  
  // edit
  $('#planner_edit_save').live("click", function() {
    rcmail.http_post('plugin.plan_edit', '_id=' + $(this).parent().attr("id") +'&_p=' + encodeURIComponent($('#plan_edit_raw').val()));
  });
  $('#planner_edit_cancel').live("click", function() {
    rcmail.http_post('plugin.plan_retrieve', '_p=' + list);
  });
  
  // help
  $("#help").click(function () {
    $("#planner_help").slideToggle("slow");
    $(this).toggleClass("active");
  });
  
  function setActive(id) {
    $('#' + list).toggleClass("active");
    list = id;
    $('#' + id).toggleClass("active");
  }
});
