<?php
define('IN_CODE', true);
require_once('header.php');

if (!$User->type['Moderator'])
    libHTML::notice(l_t('Access denied'), l_t('Only moderators can create bot-only spectator games.'));
if ($Misc->Panic)
    libHTML::notice(l_t('Game creation disabled'), l_t('Game creation is temporarily disabled.'));

require_once(l_r('gamemaster/game.php'));
libHTML::starthtml();

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    try
    {
        libAuth::formToken_Valid();
        $game = libBotOnlyGames::create($_POST['name'] ?? null);
        libHTML::notice(l_t('Bot-only game created'),
            l_t('Watch the game: ').'<a href="game/?gameID='.(int)$game->id.'">'.
            htmlspecialchars($game->name, ENT_QUOTES, 'UTF-8').'</a>');
    }
    catch (Exception $error)
    {
        print '<div class="content"><p class="notice">'.
            htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8').'</p></div>';
    }
}

print '<div class="content"><h2>'.l_t('Start a Bot-Only Game').'</h2><p>'.
    l_t('Watch seven bots play Classic Diplomacy without messaging. These games are unranked, have three-day phases, and continue when only bots remain. Normal victory and draw rules still apply.').
    '</p><p>'.l_t('Your bot service must submit orders for all seven accounts. Creating a game does not start a bot service.').
    '</p><form method="post">'.libAuth::formTokenHTML().
    '<label>'.l_t('Game name: ').'<input name="name" maxlength="50" required></label> '.
    '<button type="submit">'.l_t('Create spectator game').'</button></form></div>';
libHTML::footer();
