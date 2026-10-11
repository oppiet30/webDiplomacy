<?php
// Standalone orchestration tests; no production config, API keys, or database.
define('IN_CODE', true);
function l_t($message) { return $message; }
require __DIR__.'/../lib/botonlygames.php';
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
class FakeUser {
    public $type = array('Moderator'=>true);
    public $banned = false;
    function userIsTempBanned() { return $this->banned; }
}
class FakeDatabase {
    public $bots = array(12,13,14,15,16,17,18);
    public $writes = array();
    function sql_tabl($query) {
        check(strpos($query, "FIND_IN_SET('Bot', type)") !== false, 'Bot filter missing');
        check(strpos($query, "username NOT LIKE 'dipgpt%'") !== false, 'Full-press filter missing');
        check(strpos($query, 'points >= 5') !== false, 'Bet validation missing');
        return null;
    }
    function tabl_row($table) { return $this->bots ? array(array_shift($this->bots)) : false; }
    function escape($value) { return addslashes($value); }
    function sql_put($query) { $this->writes[] = $query; }
}
class processGame {
    public static $arguments;
    static function create(...$arguments) {
        self::$arguments = $arguments;
        return (object)array('id'=>42);
    }
}
class processMember {
    public static $members = array();
    public static $fail = false;
    static function create($user, $bet, $country) {
        if (self::$fail) throw new Exception('Simulated membership failure');
        self::$members[] = array($user, $bet, $country);
    }
}
class FakeRedis {
    public $hints = array();
    function append($key, $value) { $this->hints[] = array($key, $value); }
}
function resetFixtures() {
    global $User, $DB, $Redis;
    $User = new FakeUser(); $DB = new FakeDatabase(); $Redis = new FakeRedis();
    processGame::$arguments = null; processMember::$members = array(); processMember::$fail = false;
}
foreach (array('Diplomacy', 'Retreats', 'Builds') as $phase) {
    foreach (array('Mixed', 'MemberVsBots') as $type) {
        check(libBotOnlyGames::shouldCheckAutomaticDraw($type, $phase), 'Existing bot draw changed');
        check(!libBotOnlyGames::shouldCheckAutomaticDraw($type, $phase, 'Yes'), 'Opt-in draw exemption missing');
    }
    check(!libBotOnlyGames::shouldCheckAutomaticDraw('Members', $phase), 'Human game behavior changed');
}
foreach (array('Finished', 'Pre-game') as $phase)
    check(!libBotOnlyGames::shouldCheckAutomaticDraw('MemberVsBots', $phase), 'Inactive phase behavior changed');

resetFixtures();
$game = libBotOnlyGames::create(' Spectator test ');
check($game->id === 42, 'Game not returned');
check(processGame::$arguments[1] === 'Spectator test', 'Name not trimmed');
check(processGame::$arguments[0] === 1 && processGame::$arguments[4] === 'Unranked', 'Wrong game mode');
check(processGame::$arguments[11] === 'NoPress' && processGame::$arguments[16] === 'MemberVsBots'
    && processGame::$arguments[17] === true, 'Opt-in parameters incorrect');
check(array_column(processMember::$members, 0) === range(12,18), 'Wrong bot roster');
check(array_column(processMember::$members, 2) === range(1,7), 'Countries not uniquely assigned');
check($Redis->hints === array(array('processHint', ',42')), 'Processing not requested');

foreach (array('six bots','non-moderator','banned','empty name','array name','long name') as $scenario) {
    resetFixtures(); $name = 'Test';
    if ($scenario === 'six bots') array_pop($DB->bots);
    if ($scenario === 'non-moderator') $User->type['Moderator'] = false;
    if ($scenario === 'banned') $User->banned = true;
    if ($scenario === 'empty name') $name = ' ';
    if ($scenario === 'array name') $name = array('Test');
    if ($scenario === 'long name') $name = str_repeat('x',51);
    $failed = false;
    try { libBotOnlyGames::create($name); } catch (Exception $error) { $failed = true; }
    check($failed && processGame::$arguments === null && !$DB->writes && !$Redis->hints,
        'Invalid creation wrote data: '.$scenario);
}
resetFixtures(); processMember::$fail = true; $failed = false;
try { libBotOnlyGames::create('Test'); } catch (Exception $error) { $failed = true; }
check($failed && in_array('ROLLBACK TO SAVEPOINT botOnlyCreation', $DB->writes, true)
    && !$Redis->hints, 'Partial creation was not rolled back');
echo "Bot-only game tests passed\n";
