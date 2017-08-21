<?php
require_once "../config.php";
require_once "peer_util.php";

use \Tsugi\Util\LTI;
use \Tsugi\Core\Cache;
use \Tsugi\Core\LTIX;

// Sanity checks
$LAUNCH = LTIX::requireData();

// Model
$p = $CFG->dbprefix;

if ( isset($_POST['json']) ) {
    $json = $_POST['json'];
    if ( get_magic_quotes_gpc() ) $json = stripslashes($json);
    $json = json_decode(upgradeSubmission($json));
    if ( $json === null ) {
        $_SESSION['error'] = "Bad JSON Syntax";
        header( 'Location: '.addSession('configure') ) ;
        return;
    }

    // Some sanity checking...
    if ( $json->totalpoints < 0 ) {
        $_SESSION['error'] = "totalpoints is required and must be >= 0";
        header( 'Location: '.addSession('configure') ) ;
        return;
    }

    if ( $json->rating > 0 and $json->peerpoints > 0 ) {
        $_SESSION['error'] = "You can include peerpoints or rating range but not both.";
        header( 'Location: '.addSession('configure') ) ;
        return;
    }

    if ( ( $json->instructorpoints + $json->peerpoints + 
        ($json->assesspoints*$json->minassess) ) != $json->totalpoints ) {
        $_SESSION['error'] = "instructorpoints + peerpoints + (assesspoints*minassess) != totalpoints ";
        header( 'Location: '.addSession('configure') ) ;
        return;
    }

    $json = json_encode($json);
    $stmt = $PDOX->queryReturnError(
        "INSERT INTO {$p}peer_assn
            (link_id, json, created_at, updated_at)
            VALUES ( :ID, :JSON, NOW(), NOW())
            ON DUPLICATE KEY UPDATE json = :JSON, updated_at = NOW()",
        array(
            ':JSON' => $json,
            ':ID' => $LINK->id)
        );
    Cache::clear("peer_assn");
    if ( $stmt->success ) {
        $_SESSION['success'] = 'Assignment updated';
        header( 'Location: '.addSession('index') ) ;
    } else {
        $_SESSION['error'] = $stmt->errorImplode;
        header( 'Location: '.addSession('configure') ) ;
    }
    return;
}

// Load up the assignment
$row = loadAssignment();
$json = "";
if ( $row !== false ) $json = $row['json'];

// Clean up the JSON for presentation
if ( strlen($json) < 1 ) $json = getDefaultJson();
$json = LTI::jsonIndent($json);

// View
$OUTPUT->header();
$OUTPUT->bodyStart();
$OUTPUT->topNav();
$OUTPUT->flashMessages();
if ( ! $USER->instructor ) die("Requires instructor role");

?>
<p>Be careful in making any changes if this assignment has submissions.</p>
<p>
The assignment is configured by carefully editing the json below without 
introducing syntax errors.  Someday this will have a slick configuration 
screen but for now we edit the json.  I borrowed this user interface from the early
days of Coursera configuration screens :).
See the instructions below for detail on how to configure the assignment
and the kinds of changes that are safe to make after the assignment starts.
</p>
<form method="post" style="margin-left:5%;">
<textarea name="json" rows="25" cols="80" style="width:95%" >
<?php echo($json); ?>
</textarea>
<p>
<input type="submit" value="Save">
<input type=submit name=doCancel onclick="location='<?php echo(addSession('index'));?>'; return false;" value="Cancel"></p>
</form>
<p><b>Configuration:</b></p>
<p>This tool can create a range of structured drop boxes using the values below.  
You can make fully peer-graded assignments, fully instructor-graded assignments, 
or assignments with a combination of peer-grading and instructor grading.
Ths configuration is a bit complex - with great flexibility comes subtle complexity. One day
I will turn this into a pretty UI to make it seem less complex.
</p>
<ul>
<li>The title, description and grading text will be displayed to the user.  These can be edited
at any time - even after the assignment has started.</li>
<li>The 'parts' are an array of parts, each item has a title and type.  The title text for a part can be 
edited at any time.  You should not change the number, type, or order of the parts once the assignment 
has started.  The type can be one of the following:
<ul>
<li>image - The user will be prompted for an uploaded image.  This image needs to be &lt; 1M in size
and must be a JPG or PNG.   These strict limitations are to insure that the database does not get too big
and that students don't upload viruses for the other students.</li>
<li>url - This is a url for the user to view.</li>
<li>code - This is a text area where students can paste in code.  There is an optional
<b>language</b> attribute that will enable syntax highlighting 
using the <a href="http://prismjs.com/" target="_blank">Prism</a> syntax highlighter. 
Available languages include:
markup, css, javascript, java, php, c, python, sql, or ruby.
</li>
</ul>
If you change the type or number of parts while an assessment is live things might 
actually fail (nasty error messages on student screens).  If you need to make such a 
change, it is probably better to start over with a 
new assignment.
</li>
<li>gallery - can be "off", "always" or "after".   This enables students to browse a gallery
of other student submissions.   You can indicate if a student can access the gallery 
before their subission or after their submission.
</li>
<li>galleryformat - can be "card" or "table".  The default is "card".
</li>
<li>totalpoints - this is the number of points for the assignment.   
The value for totalpoints must be the sum of instructorpoints, 
peerpoints, and assesspoints*minassess. 
The value totalpoints is required must be above 0.
</li>
<li>instructorpoints - this is the number of points that come from the instructor.  
Leave this as zero for a purely peer-graded assignment.
Set this to be the same as totalpoints (peerpoints and assesspoints set to zero)
to create a purely instructor graded drop box.
</li>
<li>rating - this indicates that the peers are rating the submission rather than 
grading the submission.  The number is the rating scale (1-10).  Ratings are not
part of the calculation.
</li>
<li>peerpoints - this is the maximum points that come from the other students assessments.
Each of the peer-graders
will assign a value up to this number.  
Currently the grading policy is to take the 
highest score from peers since this is really intended for pass/fail assignments and getting
feedback from peers rather than carefully crafted assignments with subtle differences in the scores.</li>
<li>minassess - this is the minimum number of peer assessments each student must do.  
If you set peerpoints to zero and minassess to something greater than zero, students will be able
to comment on their peer submissions but not submit any points</li>
<li>maxassess - this is the maximum number of peer assessments the student can do.</li>
<li>asssesspoints - this is the number of points students get for each peer assessment that they do.
If this is zero, students can do peer assessing/commenting but will get no points for their efforts.</li>
<li>flag - if this is true, students will be given the option to flag submissions and flag
comments on their submissions.  Setting this to false, turns off the flagging workflow.</li>
<li>resubmit - can be "off" or "always".   This enables students to delete and resubmit their
submission as long as the due date has not passed.
</li>

</ul>
<p>
You can change any of these last five point values while an assessment is running, but you should 
probably then use "Grade Maintenance" to regrade all assignments to make sure that grading is 
consistent across all students.  Changing 'maxassess' or 'minassess' will not delete any assessments
that have already been done - it simply changes the policy in terms of new assessments the students 
are allowed to do.
</p>
<p>
This code is open source and relatively easy to work with.   It is entirely possible to add new features
to this over time if there is interest.
</p>

<?php

$OUTPUT->footer();
