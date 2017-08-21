<?php
require_once "../config.php";
require_once "peer_util.php";

use \Tsugi\Core\LTIX;

// Sanity checks
$LAUNCH = LTIX::requireData();
if ( ! $USER->instructor ) die("Instructor only");
if ( isset($_POST['doClear']) ) {
    session_unset();
    die('session unset');
}

$OUTPUT->header();
$OUTPUT->bodyStart();
$OUTPUT->topNav();
$OUTPUT->flashMessages();
$OUTPUT->welcomeUserCourse();

$OUTPUT->togglePre("Session data",$OUTPUT->safe_var_dump($_SESSION));

?>
<form method="post">
<input type="submit" name="doExit" onclick="location='<?php echo(addSession('index'));?>'; return false;" value="Exit">
<input type="submit" name="doClear" value="Clear Session (will log out out)">
</form>
<?php
flush();

$OUTPUT->footer();


