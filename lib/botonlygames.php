<?php
defined('IN_CODE') or die('This script can not be run by itself.');

/** Creation and the single draw-rule exception for bot-only spectator games. */
class libBotOnlyGames
{
    public static function shouldCheckAutomaticDraw($playerTypes, $phase, $allowAllBots = 'No')
    {
        return $allowAllBots !== 'Yes' && $playerTypes !== 'Members'
            && $phase !== 'Finished' && $phase !== 'Pre-game';
    }

    public static function create($name)
    {
        global $User, $DB, $Game, $Redis;
        if (!$User->type['Moderator']) throw new Exception(l_t('Only moderators can create bot-only games.'));
        if ($User->userIsTempBanned()) throw new Exception(l_t('You are temporarily blocked from creating games.'));
        if (!is_string($name) || trim($name) === '' || strlen(trim($name)) > 50)
            throw new Exception(l_t('Enter a game name between 1 and 50 bytes long.'));

        // Validate the complete roster before writing any game or member records.
        $bots = array();
        $table = $DB->sql_tabl("SELECT id FROM wD_Users WHERE FIND_IN_SET('Bot', type) > 0
            AND username NOT LIKE 'dipgpt%' AND points >= 5 ORDER BY id LIMIT 7 FOR UPDATE");
        while ($row = $DB->tabl_row($table)) $bots[] = (int)$row[0];
        if (count($bots) !== 7)
            throw new Exception(l_t('Seven no-press bot accounts with at least five points each are required.'));

        $DB->sql_put('SAVEPOINT botOnlyCreation');
        try
        {
            $Game = processGame::create(1, $DB->escape(trim($name)), '', 5, 'Unranked',
                3*24*60, -1, 3*24*60, -1, 60, 'No', 'NoPress', 'Normal',
                'draw-votes-public', 0, 4, 'MemberVsBots', true);
            foreach ($bots as $index => $botID) processMember::create($botID, 5, $index + 1);
            $DB->sql_put('UPDATE wD_Games SET processTime = '.time().' WHERE id = '.$Game->id);
            $DB->sql_put('RELEASE SAVEPOINT botOnlyCreation');
        }
        catch (Exception $error)
        {
            $DB->sql_put('ROLLBACK TO SAVEPOINT botOnlyCreation');
            $DB->sql_put('RELEASE SAVEPOINT botOnlyCreation');
            throw $error;
        }
        $Redis->append('processHint', ','.$Game->id);
        return $Game;
    }
}
