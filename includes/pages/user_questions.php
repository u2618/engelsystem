<?php

function questions_title() {
  return _("Ask the Heaven");
}

function user_questions() {
  global $user;
  
  if (! isset($_REQUEST['action'])) {
    $open_questions = sql_select("SELECT * FROM `Questions` WHERE `AID` IS NULL AND `UID`='" . sql_escape($user['UID']) . "'");
    
    $answered_questions = sql_select("SELECT * FROM `Questions` WHERE NOT `AID` IS NULL AND (`showGlobal` = 1 OR `UID`='" . sql_escape($user['UID']) . "')");
    foreach ($answered_questions as &$question) {
      $answer_user_source = User($question['AID']);
      $question['answer_user'] = User_Nick_render($answer_user_source);
    }
    
    return Questions_view($open_questions, $answered_questions, page_link_to("user_questions") . '&action=ask');
  } else {
    switch ($_REQUEST['action']) {
      case 'ask':
        $question = strip_request_item_nl('question');
        if ($question != "") {
          $result = sql_query("INSERT INTO `Questions` SET `UID`='" . sql_escape($user['UID']) . "', `Question`='" . sql_escape($question) . "'");
          if ($result === false) {
            engelsystem_error(_("Unable to save question."));
          }
          success(_("You question was saved."));
          engelsystem_email("orga@siegen.zapf.in", "[ZaPF-Engelsystem] Neue Frage im Engelsystem", "Hey,\n\nim Engelsystem wurde eine neue Frage gestellt.\nDu kannst sie unter folgenden URL beantworten:\n\nhttps://engel.zapf.in/?p=admin_questions\n\nViele Grüße,\nDas Engelsystem");
          redirect(page_link_to("user_questions"));
        } else {
          return page_with_title(questions_title(), [
              error(_("Please enter a question!"), true) 
          ]);
        }
        break;
      case 'delete':
        if (isset($_REQUEST['id']) && preg_match("/^[0-9]{1,11}$/", $_REQUEST['id'])) {
          $question_id = $_REQUEST['id'];
        } else {
          return error(_("Incomplete call, missing Question ID."), true);
        }
        
        $question = sql_select("SELECT * FROM `Questions` WHERE `QID`='" . sql_escape($question_id) . "' LIMIT 1");
        if (count($question) > 0 && $question[0]['UID'] == $user['UID'] && $question[0]['showGlobal'] == 0) {
          sql_query("DELETE FROM `Questions` WHERE `QID`='" . sql_escape($question_id) . "' LIMIT 1");
          redirect(page_link_to("user_questions"));
        } else {
          return page_with_title(questions_title(), [
              error(_("No question found or permissions denied."), true) 
          ]);
        }
        break;
    }
  }
}
?>
