<?php

function admin_rooms_title() {
  return _("Rooms");
}

function admin_rooms() {
  $rooms_source = sql_select("SELECT * FROM `Room` ORDER BY `Name`");
  $rooms = [];
  foreach ($rooms_source as $room) {
    $rooms[] = [
        'name' => Room_name_render($room),
        'from_pentabarf' => $room['FromPentabarf'] == 'Y' ? '&#10003;' : '',
        'public' => $room['show'] == 'Y' ? '&#10003;' : '',
        'actions' => table_buttons([
            button(page_link_to('admin_rooms') . '&show=edit&id=' . $room['RID'], _("edit"), 'btn-xs'),
            button(page_link_to('admin_rooms') . '&show=delete&id=' . $room['RID'], _("delete"), 'btn-xs') 
        ]),
        'comment' => $room['comment'],
        'Number' => $room['Number']
    ];
  }
  $room = null;
  
  if (isset($_REQUEST['show'])) {
    $msg = "";
    $name = "";
    $from_pentabarf = "";
    $public = 'Y';
    $number = "";
    
    $angeltypes_source = sql_select("SELECT * FROM `AngelTypes` ORDER BY `name`");
    $angeltypes = [];
    $angeltypes_count = [];
    foreach ($angeltypes_source as $angeltype) {
      $angeltypes[$angeltype['id']] = $angeltype['name'];
      $angeltypes_count[$angeltype['id']] = 0;
    }
    
    if (test_request_int('id')) {
      $room = Room($_REQUEST['id']);
      if ($room === false) {
        engelsystem_error("Unable to load room.");
      }
      if ($room == null) {
        redirect(page_link_to('admin_rooms'));
      }
      
      $room_id = $_REQUEST['id'];
      $name = $room['Name'];
      $from_pentabarf = $room['FromPentabarf'];
      $public = $room['show'];
      $number = $room['Number'];
      $comment = $room['comment'];
      
      $needed_angeltypes = sql_select("SELECT * FROM `NeededAngelTypes` WHERE `room_id`='" . sql_escape($room_id) . "'");
      foreach ($needed_angeltypes as $needed_angeltype) {
        $angeltypes_count[$needed_angeltype['angel_type_id']] = $needed_angeltype['count'];
      }
    }
    
    if ($_REQUEST['show'] == 'edit') {
      if (isset($_REQUEST['submit'])) {
        $valid = true;
        
        if (isset($_REQUEST['name']) && strlen(strip_request_item('name')) > 0) {
          $name = strip_request_item('name');
          if (isset($room) && sql_num_query("SELECT * FROM `Room` WHERE `Name`='" . sql_escape($name) . "' AND NOT `RID`=" . sql_escape($room_id)) > 0) {
            $valid = false;
            $msg .= error(_("This name is already in use."), true);
          }
        } else {
          $valid = false;
          $msg .= error(_("Please enter a name."), true);
        }
        
        if (isset($_REQUEST['from_pentabarf'])) {
          $from_pentabarf = 'Y';
        } else {
          $from_pentabarf = '';
        }
        
        if (isset($_REQUEST['public'])) {
          $public = 'Y';
        } else {
          $public = '';
        }
        
        if (isset($_REQUEST['number'])) {
          $number = strip_request_item('number');
        } else {
          $valid = false;
        }

        if (isset($_REQUEST['comment'])) {
          $comment = strip_request_item_nl('comment');
        }
        
        foreach ($angeltypes as $angeltype_id => $angeltype) {
          if (isset($_REQUEST['angeltype_count_' . $angeltype_id]) && preg_match("/^[0-9]{1,4}$/", $_REQUEST['angeltype_count_' . $angeltype_id])) {
            $angeltypes_count[$angeltype_id] = $_REQUEST['angeltype_count_' . $angeltype_id];
          } else {
            $valid = false;
            $msg .= error(sprintf(_("Please enter needed angels for type %s.", $angeltype)), true);
          }
        }
        
        if ($valid) {
          if (isset($room_id)) {
            sql_query("UPDATE `Room` SET `Name`='" . sql_escape($name) . "', `FromPentabarf`='" . sql_escape($from_pentabarf) . "', `show`='" . sql_escape($public) . "', `Number`='" . sql_escape($number) . "', `comment`='". sql_escape($comment) ."' WHERE `RID`='" . sql_escape($room_id) . "' LIMIT 1");
            engelsystem_log("Room updated: " . $name . ", pentabarf import: " . $from_pentabarf . ", public: " . $public . ", number: " . $number);
          } else {
            $room_id = Room_create($name, $from_pentabarf, $public, $number, $comment);
            if ($room_id === false) {
              engelsystem_error("Unable to create room.");
            }
            engelsystem_log("Room created: " . $name . ", pentabarf import: " . $from_pentabarf . ", public: " . $public . ", number: " . $number);
          }
          
          NeededAngelTypes_delete_by_room($room_id);
          $needed_angeltype_info = [];
          foreach ($angeltypes_count as $angeltype_id => $angeltype_count) {
            $angeltype = AngelType($angeltype_id);
            if ($angeltype != null) {
              NeededAngelType_add(null, $angeltype_id, $room_id, $angeltype_count);
              $needed_angeltype_info[] = $angeltype['name'] . ": " . $angeltype_count;
            }
          }
          
          engelsystem_log("Set needed angeltypes of room " . $name . " to: " . join(", ", $needed_angeltype_info));
          success(_("Room saved."));
          redirect(page_link_to("admin_rooms"));
        }
      }
      $angeltypes_count_form = [];
      foreach ($angeltypes as $angeltype_id => $angeltype) {
        $angeltypes_count_form[] = div('col-lg-4 col-md-6 col-xs-6', [
            form_spinner('angeltype_count_' . $angeltype_id, $angeltype, $angeltypes_count[$angeltype_id]) 
        ]);
      }
      
      return page_with_title(admin_rooms_title(), [
          buttons([
              button(page_link_to('admin_rooms'), _("back"), 'back') 
          ]),
          $msg,
          form([
              div('row', [
                  div('col-md-6', [
                      form_text('name', _("Name"), $name),
                      form_checkbox('from_pentabarf', _("Frab import"), $from_pentabarf),
                      form_checkbox('public', _("Public"), $public),
                      form_text('number', _("Room number"), $number),
                      form_text('comment', _("Comment"), $comment)
                  ]),
                  div('col-md-6', [
                      div('row', [
                          div('col-md-12', [
                              form_info(_("Needed angels:")) 
                          ]),
                          join($angeltypes_count_form) 
                      ]) 
                  ]) 
              ]),
              form_submit('submit', _("Save")) 
          ]) 
      ]);
    } elseif ($_REQUEST['show'] == 'delete') {
      if (isset($_REQUEST['ack'])) {
        if (! Room_delete($room_id)) {
          engelsystem_error("Unable to delete room.");
        }
        
        engelsystem_log("Room deleted: " . $name);
        success(sprintf(_("Room %s deleted."), $name));
        redirect(page_link_to('admin_rooms'));
      }
      
      return page_with_title(admin_rooms_title(), [
          buttons([
              button(page_link_to('admin_rooms'), _("back"), 'back') 
          ]),
          sprintf(_("Do you want to delete room %s?"), $name),
          buttons([
              button(page_link_to('admin_rooms') . '&show=delete&id=' . $room_id . '&ack', _("Delete"), 'delete') 
          ]) 
      ]);
    }
  }
  
  return page_with_title(admin_rooms_title(), [
      buttons([
          button(page_link_to('admin_rooms') . '&show=edit', _("add")) 
      ]),
      msg(),
      table([
          'name' => _("Name"),
          'from_pentabarf' => _("Frab import"),
          'public' => _("Public"),
          'actions' => "" 
      ], $rooms) 
  ]);
}
?>
