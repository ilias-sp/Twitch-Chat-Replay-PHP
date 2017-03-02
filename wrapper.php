<?php

/*
 * 
 * Twitch-Chat-Replay-PHP: Simple PHP library to fetch the Chat replay of a VOD from Twitch servers in an array.
 * 
 * 
 * 
 * @package  Twitch-Chat-Replay-PHP
 * @author   Elias Spil.
 * @license  MIT License
 * @version  1.1
 * @link     https://github.com/ilias-sp/Twitch-Chat-Replay-PHP
 */
 
require_once('./TwitchChatReplay.php');



// init stuff:

// set to FALSE if you dont have Twitch API Key and you want to use the chat replay server to detect start and end timestamp of the VOD:
$config['use_twitch_api'] = FALSE; 
// keep the below key anyway, just set value to null if you dont want to use the Twitch API:
$config['twitch_api_key'] = '';
// define the Twitch VOD you want to retrieve its chat:
$config['VOD_id'] = 'v125820793';
// if you want the array to be stored in file, set to TRUE:
$config['write_to_file'] = TRUE;



// run:

$chatreplay = new TwitchChatReplay($config);

$chat_replay_array = $chatreplay->retrieve_replay();

// print results (will need to parse the array for further processing):
print_r($chat_replay_array);
