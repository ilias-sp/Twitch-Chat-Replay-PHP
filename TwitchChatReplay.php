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

class TwitchChatReplay {

    private $VOD_start_timestamp;

    private $VOD_end_timestamp;

    private $VOD_current_timestamp;
    
    private $VOD_id;

    private $rechat_URL;

    private $write_to_file;

    private $file_name;

    private $twitch_api_client_id;

    private $chat_replay_buffer;

    private $chat_replay_chunk_seconds;

    private $current_iteration;

    private $expected_iterations;

    //----------------------------------------------------------------------------------------
    //
    //----------------------------------------------------------------------------------------
    public function __construct(array $config) {
        
        if (!isset($config['use_twitch_api']) || 
            !isset($config['VOD_id']) || 
            !isset($config['twitch_api_key']) || 
            !isset($config['write_to_file']) )
        {
            throw new \Exception('Mandatory parameters are missing.');
        }

        if (!in_array('curl', get_loaded_extensions())) 
        {
            throw new \Exception('Curl is needed for this library to work.');
        }

        //--initialization:

        $this->chat_replay_buffer = array();
        $this->chat_replay_chunk_seconds = 30;
        $this->current_iteration = 0;
        $this->expected_iterations = 0;

        $this->VOD_id = $config['VOD_id'];
        $this->file_name = $this->VOD_id . '.dat';
        $this->write_to_file = $config['write_to_file'];

        if ($config['use_twitch_api'] === TRUE)
        {
            $this->twitch_api_client_id = $config['twitch_api_key'];
            $this->_get_initial_timestamp_from_twitch_API();
        }
        else
        {
            $this->_get_initial_timestamp_from_rechat();
        }

    }
    //----------------------------------------------------------------------------------------
    //
    //----------------------------------------------------------------------------------------
    public function retrieve_replay()
    {
        $this->_log_it("INFO", __FUNCTION__ . ' - START.');

        $this->VOD_current_timestamp = $this->VOD_start_timestamp;

        $this->expected_iterations = round(($this->VOD_end_timestamp - $this->VOD_start_timestamp)/$this->chat_replay_chunk_seconds, 0);
    
        while ($this->VOD_current_timestamp < $this->VOD_end_timestamp)
        {
            $this->_log_it("INFO", __FUNCTION__ . ' - calling the rechat API, iteration #' . $this->current_iteration . '/' . $this->expected_iterations . ' for current_timestamp = ' . $this->VOD_current_timestamp);
            $this->rechat_URL = 'https://rechat.twitch.tv/rechat-messages?video_id=' . $this->VOD_id . '&start=' . $this->VOD_current_timestamp;
            
            $call_API = $this->_curl_caller($this->rechat_URL);

            if ($call_API[1]['http_code'] === 200 && 
            $call_API[1]['content_type'] ===  'application/json')
            {
                foreach(json_decode($call_API[0])->data as $chat_item)
                {
                    $this->chat_replay_buffer[] = $chat_item;
                }
            }

            $this->current_iteration++;
            $this->VOD_current_timestamp = $this->VOD_current_timestamp + $this->chat_replay_chunk_seconds;
            // sleep(1);
        }

        if ($this->write_to_file === TRUE && 
            count($this->chat_replay_buffer) > 0 )
        {
            if (file_put_contents($this->file_name, json_encode($this->chat_replay_buffer)) === FALSE)
            {
                throw new \Exception('Could not save the Chat replay array to file: ' . $this->file_name);
            }
        }

        $this->_log_it("INFO", __FUNCTION__ . ' - END.');
        return $this->chat_replay_buffer;
    }
    //----------------------------------------------------------------------------------------
    //
    //----------------------------------------------------------------------------------------
    private function _get_initial_timestamp_from_rechat()
    {
        $this->VOD_current_timestamp = 0;
        $this->rechat_URL = 'https://rechat.twitch.tv/rechat-messages?video_id=' . $this->VOD_id . '&start=' . $this->VOD_current_timestamp;

        $call_API = $this->_curl_caller($this->rechat_URL);

        if ($call_API[1]['http_code'] === 400 &&
            $call_API[1]['content_type'] === 'application/json' && 
            isset(json_decode($call_API[0])->errors) )
         {
                        
            $regex = '~^((0 is not between )([0-9]+)( and )([0-9]+))$~';
            $has_match = preg_match($regex, json_decode($call_API[0])->errors[0]->detail, $matches);
            if (count($matches) == 6)
            {
                $this->VOD_start_timestamp = $matches[3];
                $this->VOD_end_timestamp = $matches[5];
                $this->_log_it("INFO", __FUNCTION__ . ' - start timestamp of the VOD was found: ' . $this->VOD_start_timestamp);

                $this->_log_it('INFO', __FUNCTION__ . ' - start timestamp of the VOD set to: ' . $this->VOD_start_timestamp);
                $this->_log_it('INFO', __FUNCTION__ . ' - end timestamp of the VOD set to: ' . $this->VOD_end_timestamp);
                return TRUE;
            }
            else
            {
                throw new \Exception('Response from Rechat API was not as expected while trying to detect start timestamp of the VOD, response=|' . "\n\n" . print_r($call_API, true) . "\n\n");
            }
         }
         else
         {
             throw new \Exception('Response from Rechat API was not as expected while trying to detect start timestamp of the VOD, response=|' . "\n\n" . print_r($call_API, true) . "\n\n");
         }
         return FALSE;
    }
    //----------------------------------------------------------------------------------------
    //
    //----------------------------------------------------------------------------------------
    private function _get_initial_timestamp_from_twitch_API()
    {
        $url = 'https://api.twitch.tv/kraken/videos/' . $this->VOD_id . '?client_id=' . $this->twitch_api_client_id;

        $call_API = $this->_curl_caller($url);

        // $this->_log_it('DEBUG', __FUNCTION__ . ' - Response from Twitch API:' . "\n\n" . print_r($call_API, true));
        
        if ($call_API[1]['http_code'] === 200 &&
            $call_API[1]['content_type'] === 'application/json' &&
            isset(json_decode($call_API[0])->recorded_at) &&
            isset(json_decode($call_API[0])->length) )
        {
            $this->_log_it('INFO', __FUNCTION__ . ' - Response from Twitch API was as expected, will try to detect the start and end timestamp of the VOD from it.');
            
            $this->VOD_start_timestamp = strtotime(json_decode($call_API[0])->recorded_at);
            $this->VOD_end_timestamp = strtotime(json_decode($call_API[0])->recorded_at) + json_decode($call_API[0])->length;

            $this->_log_it('INFO', __FUNCTION__ . ' - start timestamp of the VOD set to: ' . $this->VOD_start_timestamp);
            $this->_log_it('INFO', __FUNCTION__ . ' - end timestamp of the VOD set to: ' . $this->VOD_end_timestamp);
            return TRUE;
        }
        else
        {
            throw new \Exception('Response from Twitch API was not as expected while trying to detect start and end timestamp of the VOD, response=|' . "\n\n" . print_r($call_API, true) . "\n\n");
        }
    }  
    //----------------------------------------------------------------------------------------
    //
    //----------------------------------------------------------------------------------------
    private function _curl_caller($url)
    {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $html_response = curl_exec($ch);

        $curl_transfer_result = curl_getinfo($ch);

        return array($html_response, $curl_transfer_result);
    }
    //----------------------------------------------------------------------------------------
    //
    //----------------------------------------------------------------------------------------
    private function _log_it($level, $message)
    {
        echo date('H:i:s d/m/Y') . ': ' . $level . ' - ' . $message . "\n";
    }
    //----------------------------------------------------------------------------------------
    //
    //----------------------------------------------------------------------------------------
}
