# Twitch-Chat-Replay-PHP
Simple PHP library to fetch the Chat replay of a Twitch Video from Twitch Rechat servers to an array &amp; file


use the wrapper.php to instantiate the object.

the Twitch rechat servers are sending chunks of chat replays of 30 seconds. To fetch the chat replay of a VOD, we need to provide:

1. the id of the VOD.
2. the UNIX timestamp of the moment that we want to fetch the 30 seconds chunk of the chat.

for the 2nd, we can get this information from the Twitch API (which requires Twitch API key), or by sending a dummy timestamp to the Rechat server, and it will reply us back with the start/end timestamp of the given VOD. Sample answer:

{"errors":[{"status":400,"detail":"0 is not between 1488457807 and 1488460491"}]}

how to use the wrapper:

1. fill in according to the instructions the 4 elements of the $config array.
2. run it from cli by: php wrapper.php

hopefully, you will get the chat replay in an array (and in file if you configured so) that you can parse to get each chat entry.

