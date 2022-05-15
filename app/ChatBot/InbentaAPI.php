<?php

namespace App\ChatBot;

use App\Models\ApiToken;
use App\Models\Session;
use App\Models\Message;
use Illuminate\Support\Facades\Http;


class InbentaAPI
{
    /**
     * API variables
     */
    private $apiKey = 'nyUl7wzXoKtgoHnd2fB0uRrAv0dDyLC+b4Y6xngpJDY=';
    private $apiSecret = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJwcm9qZWN0IjoieW9kYV9jaGF0Ym90X2VuIn0.anf_eerFhoNq6J8b36_qbD4VqngX79-yyBKWih_eA1-HyaMe2skiJXkRNpyWxpjmpySYWzPGncwvlwz5ZRE7eg';

    private $authURL = 'https://api.inbenta.io/v1/auth';
    private $apiURL = '';


    /**
     * Auth variables
     */
    private $accessToken = '';
    private $accessTokenExpiresAt = 0;

    public $authHasFailed = false;
    public $authErrorCode = '';


    /**
     * Session variables
     */
    public $sessionToken = '';
    public $sessionId = '';
    public $sessionExpiresAt = 0;

    public $sessionHasFailed = false;
    public $sessionErrorCode = '';

    /**
     * Message variables
     */
    public $userMessage = '';
    public $chatResponseJSON = '';
    public $chatResponseMessage = '';
    public $isChatResponseA404 = false;
    public $isAListResponse = false;

    public $chatResponseHasFailed = false;
    public $chatResponseErrorCode = '';


    function __construct()
    {
        /**
         * Check if we already have a valid token
         */
        $select = ApiToken::where('type', '=', 'accessToken')->where('expiresAt', '>', time())->select('value', 'apiURL')->first();

        /**
         * No active accessTokens; reauth
         */
        if (!$select) {
            $this->auth();
        } else {

            /**
             * Valid token found, update variables
             */
            $this->accessToken = $select->value;
            $this->apiURL = $select->apiURL;
        }
    }


    /**
     * Auth with the Inbent API
     */
    function auth()
    {
        $headers = [
            'x-inbenta-key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ];

        $payload = [
            'secret' => $this->apiSecret
        ];

        /**
         * Send a post to the auth endpoint
         */
        $response = Http::withHeaders($headers)->post($this->authURL, $payload);

        $statusCode = $response->status();
        $responseJSON = json_decode($response->getBody(), true);


        /**
         * Check for unexpected response codes
         */
        if ($statusCode !== 200) {
            $this->authHasFailed = true;
            $this->authErrorCode = 'auth_fail';
        } else {

            /**
             * Check if all expected data are in our response
             */
            if (!isset($responseJSON['accessToken']) || !isset($responseJSON['expiration']) || !isset($responseJSON['apis']['chatbot'])) {
                $this->authHasFailed = true;
                $this->authErrorCode = 'bad_auth_response';
            }

            /**
             * Successful authentication; update the variables
             */
            $this->accessToken = $responseJSON['accessToken'];
            $this->accessTokenExpiresAt = $responseJSON['expiration'];
            $this->apiURL = $responseJSON['apis']['chatbot'];

            /**
             * Insert model
             */
            $apiToken = new ApiToken();

            $apiToken->type = 'accessToken';
            $apiToken->value = $this->accessToken;
            $apiToken->apiURL = $this->apiURL;
            $apiToken->expiresAt = $this->accessTokenExpiresAt;

            $apiToken->save();
        }
    }


    /**
     * Create new session
     */
    function newSession()
    {
        $endpoint = '/v1/conversation';

        $headers = [
            'x-inbenta-key' => $this->apiKey,
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json'
        ];

        /**
         * Send a post to the conversation endpoint
         */
        $response = Http::withHeaders($headers)->post($this->apiURL . $endpoint);

        $statusCode = $response->status();
        $responseJSON = json_decode($response->getBody(), true);


        /**
         * Check for unexpected response codes
         */
        if ($statusCode !== 200) {
            $this->sessionHasFailed = true;
            $this->sessionErrorCode = 'session_fail';
        } else {

            /**
             * Check if all expected data are in our response
             */
            if (!isset($responseJSON['sessionToken']) || !isset($responseJSON['sessionId'])) {
                $this->sessionHasFailed = true;
                $this->sessionErrorCode = 'bad_session_response';
            }

            /**
             * Successful session creation; update the variables
             */
            $this->sessionToken = $responseJSON['sessionToken'];
            $this->sessionId = $responseJSON['sessionId'];
            $this->sessionExpiresAt = time() + (60 * 30); // 30 minutes;

            /**
             * Insert model
             */
            $session = new Session();

            $session->sessionToken = $this->sessionToken;
            $session->sessionId = $this->sessionId;
            $session->sessionExpiresAt = $this->sessionExpiresAt;

            $session->save();
        }
    }


    /**
     * Send chat message to api
     */
    function sendMessage($message)
    {
        $this->userMessage = $message;

        $endpoint = '/v1/conversation/message';

        $headers = [
            'x-inbenta-key' => $this->apiKey,
            'x-inbenta-session' => 'Bearer ' . $this->sessionToken,
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json'
        ];

        $payload = [
            'message' => $message
        ];

        /**
         * Send a post to the conversation/messation endpoint
         */
        $response = Http::withHeaders($headers)->post($this->apiURL . $endpoint, $payload);

        $statusCode = $response->status();
        $responseJSON = json_decode($response->getBody(), true);

        /**
         * Check for unexpected response codes
         */
        if ($statusCode !== 200) {
            $this->chatResponseHasFailed = true;
            $this->chatResponseErrorCode = 'message_fail';
        } else {

            /**
             * Check if all expected data are in our response
             */
            if (!isset($responseJSON['answers'])) {
                $this->chatResponseHasFailed = true;
                $this->chatResponseErrorCode = 'bad_message_response';
            } else {
                /**
                 * Successfull message, save variable
                 */
                $this->chatResponseJSON = $responseJSON;


                /**
                 * Proccess message
                 */
                $this->processMessage();
            }
        }
    }

    /**
     * Process message response from the API
     */
    function processMessage()
    {
        $this->chatResponseMessage = $this->chatResponseJSON['answers'][0]['message'];

        /**
         * Need to test the API more to see if it ever answers with more then one answer
         * the documentation did not say anything about it do, so for this project
         * I am going to assume that the API only returns one answer message for each request
         * Therefore I'm going to hard code it, if there were multiple messages we would iterate here
         */
        if ($this->chatResponseJSON['answers'][0]['flags']) {
            if ($this->chatResponseJSON['answers'][0]['flags'][0] === 'no-results') {
                $this->isChatResponseA404 = true;
            }
        }


        /**
         * Save chat message
         */
        $messageModel = new Message();

        $messageModel->sessionId = $this->sessionId;
        $messageModel->userMessage = $this->userMessage;
        $messageModel->botMessage = $this->chatResponseMessage;
        $messageModel->wasMessage404 = $this->isChatResponseA404;
        $messageModel->responseJSON = json_encode($this->chatResponseJSON);

        $messageModel->save();
    }

    /**
     * Set session id
     */
    function setSessionId($sessionId)
    {
        $this->sessionId = $sessionId;
    }

    /**
     * Set session token
     */
    function setSessionToken($sessionToken)
    {
        $this->sessionToken = $sessionToken;
    }
}
