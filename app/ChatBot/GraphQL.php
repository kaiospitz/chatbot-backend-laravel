<?php

namespace App\ChatBot;

use Illuminate\Support\Facades\Http;

class GraphQL
{

    private $endpointURL = 'https://inbenta-graphql-swapi-prod.herokuapp.com/api';

    public $htmlListOfFilms = '';
    public $htmlListOfCharacters = '';

    public $hasFailed = false;
    public $errorCode = '';


    /**
     * Make the request to the GraphQL API
     */
    function fetch($payload)
    {
        $headers = [
            'Content-Type' => 'application/json'
        ];

        $response = Http::withHeaders($headers)->post($this->endpointURL, $payload);

        $statusCode = $response->status();
        $responseJSON = json_decode($response->getBody(), true);

        return [$statusCode, $responseJSON];
    }


    /**
     * Fetch a list of star wars films from the GraphQL API
     */
    function fetchStarWarsFilms()
    {

        $payload = [
            'query' => '{allFilms{films{title}}}'
        ];

        $response = $this->fetch($payload);

        $statusCode = $response[0];
        $responseJSON = $response[1];

        /**
         * Check for unexpected response codes
         */
        if ($statusCode !== 200) {
            $this->hasFailed = true;
            $this->errorCode = 'films_error';
        } else {

            /**
             * Check if all expected data are in our response
             */
            if (!isset($responseJSON['data']) || !isset($responseJSON['data']['allFilms']['films'])) {
                $this->hasFailed = true;
                $this->errorCode = 'bad_films_response';
                return;
            }


            /**
             * Successfull request, format the response to HTML
             */
            $output = "The <strong>force</strong> is in this movies:<ul>";

            $films = $responseJSON['data']['allFilms']['films'];

            if ($films) {
                foreach ($films as $film) {
                    if ($film['title']) {
                        $output .= '<li>' . $film['title'] . '</li>';
                    }
                }
            }

            $output .= '</ul>';

            $this->htmlListOfFilms = $output;
        }
    }



    /**
     * Fetch a list of star wars characters from the GraphQL API
     */
    function fetchStarWarsCharacters()
    {

        $payload = [
            'query' => '{allPeople(first: 10){people{name}}}'
        ];

        $response = $this->fetch($payload);

        $statusCode = $response[0];
        $responseJSON = $response[1];

        /**
         * Check for unexpected response codes
         */
        if ($statusCode !== 200) {
            $this->hasFailed = true;
            $this->errorCode = 'characters_error';
        } else {

            /**
             * Check if all expected data are in our response
             */
            if (!isset($responseJSON['data']) || !isset($responseJSON['data']['allPeople']['people'])) {
                $this->hasFailed = true;
                $this->errorCode = 'bad_characters_response';
                return;
            }


            /**
             * Successfull request, format the response to HTML
             */
            $output = "I haven't found any results, but here is a list of some Star Wars Characters:<ul>";

            $characters = $responseJSON['data']['allPeople']['people'];

            if ($characters) {
                foreach ($characters as $character) {
                    if ($character['name']) {
                        $output .= '<li>' . $character['name'] . '</li>';
                    }
                }
            }

            $output .= '</ul>';

            $this->htmlListOfCharacters = $output;
        }
    }
}
