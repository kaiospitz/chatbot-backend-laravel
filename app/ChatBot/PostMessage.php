<?php

namespace App\ChatBot;


class PostMessage
{
    public $isValidMessage = false;
    public $isForceMessage = false;

    function __construct($input)
    {
        $this->str = $input;
    }

    /**
     * Trims chat message
     */
    function trim()
    {
        $this->str = trim($this->str);
        return $this;
    }

    /**
     * Returns sanitized string
     */
    function sanitize()
    {
        $this->str = addslashes(strip_tags($this->str));
        $this->str = filter_var($this->str, FILTER_UNSAFE_RAW);
        return $this;
    }


    /**
     * Validates chat message
     */
    function validate()
    {
        $min_len = 1;
        $max_len = 256;

        if (!$this->str || strlen($this->str) < $min_len || strlen($this->str) > $max_len) {
            $this->isValidMessage = false;
        } else {
            $this->isValidMessage = true;

            if (strpos($this->str, 'force') !== false) {
                $this->isForceMessage = true;
            }
        }

        return $this;
    }

    /**
     * Returns the trimmed, sanitized and validated message
     */
    function cleanValidatedMessage()
    {
        return $this->str;
    }
}
