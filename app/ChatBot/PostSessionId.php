<?php

namespace App\ChatBot;

use App\Models\Session;

class PostSessionId
{
    public $isValidSessionId = false;
    public $doesSessionExist = false;
    public $sessionId = '';
    public $sessionToken = '';

    function __construct($input)
    {
        $this->sessionId = $input;
        return $this;
    }

    /**
     * Returns sanitized string
     */
    function sanitize()
    {
        $this->sessionId = addslashes(strip_tags($this->sessionId));
        $this->sessionId = filter_var($this->sessionId, FILTER_UNSAFE_RAW);
        return $this;
    }


    /**
     * Validate session id
     */
    function validate()
    {
        $min_len = 1;
        $max_len = 50;

        if (!$this->sessionId || strlen($this->sessionId) < $min_len || strlen($this->sessionId) > $max_len) {
            $this->isValidSessionId = false;
        } else {
            $this->isValidSessionId = true;

            /**
             * Check if we know the sessionId
             */
            $select = Session::where('sessionId', '=', $this->sessionId)->where('sessionExpiresAt', '>', time())->select('sessionToken')->first();


            if ($select) {

                /**
                 * Session found, update variables
                 */
                $this->doesSessionExist = true;
                $this->sessionToken = $select->sessionToken;
            }

            return $this;
        }
    }
}
