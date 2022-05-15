<?php

namespace App\Http\Controllers;

use App\ChatBot\GraphQL;
use Illuminate\Http\Request;
use App\Models\Messages;
use Illuminate\Support\Facades\Validator;
use App\ChatBot\PostMessage;
use App\ChatBot\PostSessionId;
use App\ChatBot\InbentaAPI;
use App\Models\Message;

class MessagesController extends Controller
{

    public function store(Request $request)
    {
        $postData = $request->json()->all();

        $message = new PostMessage($postData['message']);

        /**
         * Trim, sanitize and validates the post message
         */
        $message->trim()->sanitize()->validate();


        /**
         * If message validation fails, end with error
         */
        if (!$message->isValidMessage) {
            return response()->json(['errorCode' => 'invalid_message'], 400);
        }

        /**
         * Initiate the Inbenta API    
         * On load = check if we have a valid accessToken;
         *           reauths if accessToken if stale  
         */
        $api = new InbentaAPI();


        /**
         * Catch any errors in the InbentaAPI init and auth
         * End with error response
         */
        if ($api->authHasFailed || $api->authErrorCode !== '') {
            return response()->json(['errorCode' => $api->authErrorCode], 400);
        }


        /**
         * At this point we are successfully authed to the API and have a valid message
         * Start process of obtaining a sessionId and sessionToken
         */
        $session = new PostSessionId($postData['sessionId']);


        /**
         * Trim, sanitize and validates the post sessionId
         */
        $session->sanitize()->validate();


        /**
         * If no session id provided or invalid session id;
         * Create new session
         */
        if (!$session->isValidSessionId || !$session->doesSessionExist) {
            $api->newSession();

            /**
             * Catch any errors in the InbentaAPI session creation
             * End with error response
             */
            if ($api->sessionHasFailed || $api->sessionErrorCode !== '') {
                return response()->json(['errorCode' => $api->sessionErrorCode], 400);
            }
        } else {

            /**
             * Valid session already exsits, update api variables
             */
            $api->setSessionId($session->sessionId);
            $api->setSessionToken($session->sessionToken);
        }

        /**
         * At this point we are successfully authed to the API,
         * have a valid message and a valid sessionId and sessionToken
         * Start the process of messaging the api and returning response to the user
         */


        /**
         * Logic: check if message contains the word "force"
         *        if true fetch a list of Star War films via GraphQL
         */
        if ($message->isForceMessage) {
            $graphQL = new GraphQL;

            /**
             * Fetch a list of star war fillms from the GraphQL API
             */
            $graphQL->fetchStarWarsFilms();

            /**
             * Catch any errors in the GraphQL request
             * End with error response
             */
            if ($graphQL->hasFailed || $graphQL->errorCode) {
                return response()->json(['errorCode' => $graphQL->errorCode], 400);
            }

            $api->chatResponseMessage = $graphQL->htmlListOfFilms;
            $api->isAListResponse = true;
        } else {
            $api->sendMessage($message->cleanValidatedMessage());

            /**
             * Catch any errors in the GraphQL request
             * End with error response
             */
            if ($api->chatResponseHasFailed || $api->chatResponseErrorCode || !$api->chatResponseMessage) {
                return response()->json(['errorCode' => $api->chatResponseErrorCode], 400);
            }
        }


        /**
         * Logic, if current message is isChatResponseA404
         * check if the last message was also a 404, if so return list of star war characters
         */
        if ($api->isChatResponseA404 === true) {
            $select = Message::where('sessionId', '=', $api->sessionId)->orderBy('id', 'desc')->select('wasMessage404')->skip(1)->first();

            if ($select) {
                if ($select->wasMessage404 === 1) {
                    $graphQL = new GraphQL;

                    /**
                     * Last message was also a 404; fetch characters list
                     */
                    $graphQL->fetchStarWarsCharacters();

                    /**
                     * Catch any errors in the GraphQL request
                     * End with error response
                     */
                    if ($graphQL->hasFailed || $graphQL->errorCode) {
                        return response()->json(['errorCode' => $graphQL->errorCode], 400);
                    }

                    $api->chatResponseMessage = $graphQL->htmlListOfCharacters;
                    $api->isAListResponse = true;
                }
            }
        }

        $response = [
            'sessionId' => $api->sessionId,
            'botResponse' => $api->chatResponseMessage,
            'listResponse' => $api->isAListResponse
        ];

        return response()->json($response, 200);
    }
}
