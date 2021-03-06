<?php
session_start();
date_default_timezone_set('America/New_York');

$website = 'https://armory/';


include('functions.php');


//==============SET TIMEOUT==============
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800) && isset($_SESSION['id'])) {
      // last request was more than 30 minutes ago
  session_unset();     // unset $_SESSION variable for the run-time 
  session_destroy();   // destroy session data in storage
  $message = "Logged out due to inactivity.";
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp

$todaystart = date('Y-m-d', time());
$todaystart = strtotime($todaystart . ' 00:00:00');



//==============CONNECT TO DATABASE==============

include "servers.php";

mysql_connect("$mysql_host", "$mysql_user", "$mysql_pass") or die("Cannot connect to database");
mysql_select_db("$mysql_db") or die("Cannot find database");



//==============CHECK FOR PAGE==============
if (isset($_GET['page'])) {
  $page = $_GET['page'];
} else {
  $page = 'home';
}



//==============TIME ARRAY==============
$dateunits = array (
  'year' => 31536000,
  'month' => 2592000,
  'week' => 604800,
  'day' => 86400,
  'hour' => 3600,
  'minute' => 60,
  'second' => 1
);



//==============UNICODE NUMBER ARRAY==============
$unicnumbers = array (
  0 => '&#9450;',
  1 => '&#9312;',
  2 => '&#9313;',
  3 => '&#9314;',
  4 => '&#9315;',
  5 => '&#9316;',
  6 => '&#9317;',
  7 => '&#9318;',
  8 => '&#9319;',
  9 => '&#9320;',
  10 => '&#9321;',
  11 => '&#9322;',
  12 => '&#9323;',
  13 => '&#9324;',
  14 => '&#9325;',
  15 => '&#9326;',
  16 => '&#9327;',
  17 => '&#9328;',
  18 => '&#9329;',
  19 => '&#9330;',
  20 => '&#9331:;',
  21 => '&#9331:;+',
);


//==============AUTHENTICATE LOGIN==============
if ( isset($_POST['login']) && $_POST['login'] == "y") { 
  if ($_POST['user'] && $_POST['pass']) {
    $ldap_conn = ldap_connect($ldap_server2, 389) or die ("Could not connect to LDAP server.");
    $user = strtolower($_POST['user']);
    $pass = $_POST['pass'];
    $ldaplogin = "armgroup\\$user";
    if ($ldap_conn) {
      if ($bind = ldap_bind($ldap_conn, $ldaplogin, $pass)) {
        $sql = "SELECT * FROM staff WHERE username='$user'";
        $result = mysql_query($sql);
        $count = mysql_num_rows($result);
        $message = 'Login Successful!';
        if ($count == 1) {
          $userdata = mysql_fetch_array($result);
          $_SESSION['id'] = $userdata['id'];
          $_SESSION['user'] = $userdata['username'];
          $_SESSION['fname'] = $userdata['fname'];
          $_SESSION['lname'] = $userdata['lname'];
          $_SESSION['email'] = $userdata['email'];
          if ($userdata['picture'] != "") {
            $_SESSION['picture'] = $userdata['picture'];
          } else {
            $_SESSION['picture'] = "img/no_pic1.png";
          }
          $_SESSION['security'] = $userdata['security'];
        } else {
          $fname = ucfirst(substr($user, 0, 1));
          $lname = ucfirst(substr($user, 1));
          $email = "$user@armgroup.net";
          $security = "comment article_pend profile";
          $sql = "INSERT INTO staff (username, fname, lname, email, security)  VALUES ('$user', '$fname', '$lname', '$email', '$security')";
          $userdata = mysql_query($sql);
          if ($userdata) {
            $userid = mysql_insert_id();
            $_SESSION['id'] = $userid;
            $_SESSION['user'] = $user;
            $_SESSION['fname'] = $fname;
            $_SESSION['lname'] = $lname;
            $_SESSION['security'] = $security;
            $message = "New user added. Please update your <a href='?page=profile&profileid=$userid'>profile</a>!";
          } else {
            $message = mysql_error();
          }
        }
        $page = 'home';
      } else {
        $page = "login";
        $message = $message . "Wrong username and/or password.<br /><br />";
      }
    } else {
      $message =  "LDAP did not connect.";
    } 

    /* $pass = sha1($user.mysql_real_escape_string(stripslashes($_POST['pass'])));
    $user = mysql_real_escape_string(stripslashes($_POST['user']));
    $sql = "SELECT * FROM staff WHERE username='$user' AND password='$pass'";
    $result = mysql_query($sql);
    $count = mysql_num_rows($result); */

  } else {
    $page = "login";
    $message = "You did not provide<br /> your username and/or  password.<br /><br />";
  }
} 

//==============LOGOUT SCRIPT==============
if (isset($_GET['logout'])) {                                                      //LOGOUT SCRIPT
  $_SESSION = array();
  session_destroy();
}

if ( $page == 'event' && isset($_GET['date']) ) {                            //EVENTS - REDIRECT IF ONLY 1 RESULT
  $date = strtotime($_GET['date'] . ' 00:00:00');
  $endofdate = $date + $dateunits['day'] - 1; 
  $fulldate = date('l, M. j, Y', strtotime($_GET['date']));
  $sql = "SELECT * FROM articles WHERE type = 'event' AND startdate BETWEEN '$date' AND '$endofdate'";
  $result = mysql_query($sql); 
  if (mysql_num_rows($result) == 1) {
    $row = mysql_fetch_array($result);
    $page = 'article';
    $_GET['articleid'] = $row['id'];
  }
}




//==============ADD OR EDIT STUFF==============
if (isset($_POST['submit'])) {                      
  $submit = $_POST['submit'];
  if (isset($_POST['type'])) {
    $type = $_POST['type'];


    function pendingResponse($id) {
      if ($_POST['submit'] == 'Approve') {
        $verb = "APPROVED";
        $line2 = "To view your $_POST[type] please follow this link: $website/index.php?page=article&articleid=$id"; 
      } elseif ($_POST['submit'] == 'Reject') {
        $verb = "REJECTED";
        $line2 = "If you have any questions, please direct them to $_SESSION[email].";
      }
      $to = $_POST['useremail'];
      $subject = "ARMory - Submission #$_POST[id] has been $verb";
      $body = "
        The following submission to ARMory was $verb by $_SESSION[fname] $_SESSION[lname].
        $line2


        Your submission:
            
            TITLE:
                    $_POST[title]

            BODY:
                    " . strip_tags($_POST['body'])
      ;
      $headers = 'From: helpdesk@armgroup.net' . "\r\n" .
        'Reply-To: ' . $_SESSION['email'] . "\r\n" .  
        'X-Mailer: PHP/' . phpversion();

      mail($to, $subject, $body, $headers);

    }



    switch ($type) {                                /////////////////
                                                   //    STAFF    //
      case 'employee':                            ///////////////// 
        $table = "staff";    
        if ( hasPermission('profile_pend') ) { $table = "staffPending"; $submit = 'add'; $message = $table; }

        if ($_POST['fname'] != ''  && $_POST['lname'] != '' && $_POST['birthday'] != '' && ($_POST['initials'] != '' || $_POST['noinitial'] == 'y') ) { //CHECK REQUIRED FIELDS
          extract($_POST, EXTR_OVERWRITE);
          $username = strtolower(substr($fname, 0, 1) . $lname);
          if ( isset($birthday) ) { $birthday = date('Y-m-d', strtotime($birthday)); } else { $birthday = NULL; }
          if ( isset($startday) ) { $startday = date('Y-m-d', strtotime($startday)); } else { $startday = NULL; }
          if ( isset($leaveday) && $leaveday != '' ) { $leaveday = date('Y-m-d', strtotime($leaveday)); } else { $leaveday = NULL; }
          $description = "";
          foreach ($_POST['description'] as $i) {
            if ($description != "") { $description .= ' -- '; }
            $description .= $i;
          }
          if ($email == false) {$email = $username . '@armgroup.net';}


          switch ($_POST['submit']) {

            case 'add':   ////////////////  ADD  ////
    
              $password = sha1($username . $lname . date("md", $birthday));
              $emp_sql = "INSERT INTO $table (username, password, fname, lname, initials, birthday, startday, location, title, email, phone, ext, cellphone, picture, description, about, security, updateid) VALUES ('$username', '$password', '$fname', '$lname', '$initials', '$birthday', '$startday', '$location', '$title', '$email', '$phone', '$ext', '$cellphone', '$picture', '$description', '$about', '1', '$profileid' )"; 
              break;

            case 'edit':   ///////////////  EDIT  ////

              if ($_GET['profileid']) {
                $profileid = $_GET['profileid'];
                if ( $_FILES['picture']['name'] != '' ) { 
                  $pic_data = imageUpload($_POST['user']);
                  $message .= $pic_data['message'];
                }
                $emp_sql = "UPDATE $table SET username='$username', fname='$fname', lname='$lname', initials='$initials', birthday='$birthday', showbday='$showbday', startday='$startday', location='$location', title='$title', email='$email', phone='$phone', ext='$ext', cellphone='$cellphone', description='$description', about='$about'";
                if ( $pic_data['success'] == TRUE && $pic_data['path'] != '' ) { 
                  $emp_sql .= ", picture='$pic_data[path]'"; 
                  $_SESSION['picture'] = $pic_data['path'];
                }
                if ( isset($leaveday) ) { $emp_sql .= ", leaveday='$leaveday'"; }
                $emp_sql .= " WHERE id=$profileid";
              } else {
                $message .= 'No profile provided.<br />';
              }
              break;


            default:  ////////////////  DEFAULT  ////

              $message .= "Uh oh! Something went wrong. <br /> Submit = " . $submit . ".<br />";
          }
            
          if (mysql_query($emp_sql)) { // IF SUCCESS
            if (isset($pic_data) && $pic_data['success'] == FALSE) {
              $message .= "There was a problem with the image. ";
            } else {
              if (!$_GET['profileid']) { $_GET['profileid'] = mysql_insert_id(); }
              $message .= "Profile " . $submit . "ed successfully!!!";
            }
          } else { 
            $message .= "There was a problem.<br />" . mysql_error();
          }
        } else { //IF REQUIRED FIELDS ARE EMPTY
          $_POST['action'] = 'edit'; 
          $message = "Please fill all required fields.";
        }

      break;
      case 'resources':
      case 'job':                           ////////////////////
      case 'event':                        //    ARTICLES    // 
      case 'article':                     ////////////////////
      case 'help':
      case 'workshare':

        $table = "articles";
       
          if ( hasPermission($type . "_pend") ) {$table = 'articlesPending'; $submit = "add"; }

            extract($_POST, EXTR_OVERWRITE);
            $userid = $_SESSION['id'];
            if ( isset($startdate) && isset($enddate) ) {
              $startdate = "$startdate $hour_startdate:$minute_startdate $ampm_startdate";
              $enddate = "$enddate $hour_enddate:$minute_enddate $ampm_enddate";
              $startdate = strtotime($startdate);
              $enddate = strtotime($enddate);
            } else {
              $startdate = NULL;
              $enddate = NULL;
            }
            $title = mysql_real_escape_string($title);
            $body = mysql_real_escape_string($body);
            $date = time();
            

            switch ($_POST['submit']) {

              case 'add':   ///////////////  ADD  ////
               
                if (isset($_POST['title']) && isset($_POST['body']) && hasPermission($type)) {
                  if (($type != 'job' || ($type == 'job' && $_POST['location'] != '')) && ($type != 'resources' || ($type == 'resources' && $_POST['category'] != '' && $_POST['subcat'] != ''))) {
                    $art_sql = "INSERT INTO $table (title, body, userid, date, startdate, enddate, location, type, del, category, subcat) 
                      VALUES ('$title', '$body', '$userid', '$date', '$startdate', '$enddate', '$location', '$type', 'n', '$category', '$subcat')";
                    $_GET['articleid'] = mysql_insert_id();
                  } else {
                    $message = "Please fill all fields.";
                  }
                } else { 
                  $message = "Please fill all fields.";
                }
                $verb = "added";
                break;

                
              case 'edit':  //////////////  EDIT  ////

                if (isset($_POST['title']) && isset($_POST['body']) && hasPermission($type)) {
                  if (($type != 'job' || ($type == 'job' && $_POST['location'] != '')) && ($type != 'resources' || ($type == 'resources' && $_POST['category'] != '' && $_POST['subcat'] != ''))) {
                    if ($_GET['articleid']) {
                      $articleid = $_GET['articleid'];
                      $art_sql = "UPDATE articles SET title='$title', body='$body', startdate='$startdate', enddate='$enddate', type='$type', category='$category', subcat='$subcat' WHERE id=$articleid";
                    }
                  } else {
                    $message = "Please fill all fields.";
                  }
                } else { 
                  $message = "Please fill all fields.";
                }
                $verb = "edited";
                break;


              case 'Delete':  //////////////  DELETE  ////

                if ($_GET['articleid']) {
                  $art_sql = "UPDATE articles SET del = 'y' WHERE id = $_GET[articleid]";
                } else {
                  $message = "Unknown article . . .";
                }
                if ($type == "job") {
                  $page = "careerops";
                } else {
                  $page = "home";
                }
                $verb = "deleted";
                break;


              case 'Approve':   //////////////  APPROVE  ////

                if (isset($_POST['title']) && isset($_POST['body']) && hasPermission($type)) {
                  $sql = "UPDATE articlesPending SET del = 'y' WHERE id = $_POST[id]";
                  if (mysql_query($sql)) {
                    $art_sql = "INSERT INTO $table (title, body, userid, date, startdate, enddate, location, type, del) VALUES ('$title', '$body', '$penduserid', '$date', '$startdate', '$enddate', '$location', '$type', 'n')";
                    $_GET['articleid'] = mysql_insert_id();
                    pendingResponse($_GET['articleid']);
                  }
                } else { 
                  $message = "Please fill all fields.";
                }
                $verb = "approved";
                break;


              case 'Reject':  //////////////////  REJECT  ////

                pendingResponse($_POST['id']);
                $art_sql = "UPDATE articlesPending SET del = 'y' WHERE id = $_POST[id]";
                $verb = "rejected";
                break;

              
              default:        //////////////////  DEFAULT  ////
             
                $message .= 'No profile provided.<br />';
                $message .= "Submit is not set. <br /> Submit = " . $submit . ".<br />";
              }
            
            
            if (mysql_query($art_sql)) { // IF SUCCESS
              if (!$_GET['articleid']) { $_GET[articleid] = mysql_insert_id(); }
              $message .= ucfirst($type) . " " . $verb . " successfully!!!";
              updateSubscribers($_GET['articleid']);
            } else { 
              if (!isset($message)) {
                $message = "There was a problem.<br />" . mysql_error() . ".";
              }
            }
    }
  }
}



//==============  COMMENT PROCESSING  ===============
if (isset($_POST['submit']) && $_POST['submit'] == 'Comment' && isset($_GET['articleid'])) {
  $required = array('comment');
  $_POST['date'] = time();
  $post = validate($_POST, $required);
  if ($post) {
    $sql = "INSERT INTO comments (userid, parentid, text, date) VALUES ('$_SESSION[id]', '$_GET[articleid]', '$post[comment]', '$post[date]')";
    $result = mysql_query($sql);
    if ($result) {
      $message = "Comment submitted";
      updateSubscribers($_GET['articleid']);
    } else {
      $message = "Message failed to submit.";
      $message .= mysql_error();
    }
  } else {
    $message .= "Please enter text to submit a comment";
  }
}



//================= SUBSCRIBE ===================
if (isset($_POST['submit']) && $_POST['submit'] == 'Subscribe' && isset($_GET['articleid']) && isset($_SESSION['id'])) {

  $sql = "UPDATE staff SET subscriptions = CONCAT(subscriptions, '($_GET[articleid])') WHERE id = '$_SESSION[id]'";
  $result = mysql_query($sql);
  if ($result) {
    $message = "You are now subscribed to this article." . mysql_error();
  } else {
    $message = "Subscription failed. <br />" . mysql_error();
  }

} elseif (isset($_POST['submit']) && $_POST['submit'] == 'Unsubscribe' && isset($_GET['articleid']) && isset($_SESSION['id'])) {

  $sql = "UPDATE staff SET subscriptions = REPLACE(subscriptions, '($_GET[articleid])', '') WHERE id = '$_SESSION[id]'";
  $result = mysql_query($sql);
  if ($result) {
    $message = "You are now unsubscribed from this article." . mysql_error();
  } else {
    $message = "Unsubscription failed. <br />" . mysql_error();
  }

}



//============== EMAIL FEEDBACK AND TICKETS ===============
if (isset($_SESSION['security'])) {
  if (isset($_POST['submit']) && $_POST['submit'] == "Send") { 
    if (isset($_POST['subject']) && isset($_POST['body'])) {
      if ($_POST['type'] == 'feedback') {
        $subject = "ARMory - Feedback - $_POST[subject]";
      } elseif ($_POST['type'] == 'ticket') {
        $subject = $_POST['subject'];
      }
      
      $to = "helpdesk@armgroup.net";
      $body = "SUBMITTED BY: $_SESSION[fname] $_SESSION[lname], $_SESSION[email] \r\n\r\n $_POST[body]";
      if ($_POST['type'] == 'ticket') { 
        $body .= "


          Sent from ARMory
        ";
      }
      $headers = 'From: ' . $_SESSION['email'] . "\r\n" . 
        'Reply-To: ' . $_SESSION['email'] . "\r\n" .
        'X-Mailer: PHP/' . phpversion();

      mail ($to, $subject, $body, $headers);
      $message = ucfirst($_POST['type']) . " submitted successfully!";


    } else {
      $page = "email";
      $_GET['type'] = $_POST['type'];
      $message = "Please fill out all fields.";

    }
  }
}




//==============USED FOR SAMPLE CONTENT==============
$_GET['sample'] = 'y';

?>
