<?php

namespace App\Helpers;

use InvalidArgumentException;
use RuntimeException;

class Fragment
{
    private const BASE_URL = 'https://api.fragment-api.com/v1';
    private const CONNECT_TIMEOUT = 20;
    private const TIMEOUT = 60;

    /**
     * Authenticates a user with the Fragment API.
     *
     * @param string $apiKey API key from fragment-api.com/dashboard
     * @param string $phoneNumber Telegram phone number (e.g., +998331234567)
     * @param array $mnemonics TON wallet backup 24 words (e.g., ["noble", "crack", ...])
     * @return array API response
     * @throws InvalidArgumentException If inputs are invalid
     * @throws RuntimeException If the API request fails
     */
    public static function auth(string $apiKey, string $phoneNumber, array $mnemonics): array
    {
        if (empty($apiKey)) {
            throw new InvalidArgumentException('API key cannot be empty.');
        }
        if (!preg_match('/^\+\d{10,15}$/', $phoneNumber)) {
            throw new InvalidArgumentException('Invalid phone number format.');
        }
        if (count($mnemonics) !== 24 || !array_is_list($mnemonics) || !empty(array_filter($mnemonics, fn($word) => !is_string($word) || empty($word)))) {
            throw new InvalidArgumentException('Mnemonics must be an array of 24 non-empty strings.');
        }

        $url = self::BASE_URL . '/auth/authenticate/';
        $payload = json_encode([
            'api_key' => $apiKey,
            'phone_number' => $phoneNumber,
            'mnemonics' => $mnemonics,
        ]);

        $response = self::makeCurlRequest($url, 'POST', $payload);
        return self::handleResponse($response);
    }

    /**
     * Get User Info by Telegram username
     *
     * @param string $username Telegram username (e.g., @username)
     * @param string $authToken JWT token for authentication
     * @return array Decoded API response
     * @throws InvalidArgumentException If inputs are invalid
     * @throws RuntimeException If the API request fails
     */
    public static function getUserInfo(string $username, string $authToken): array
    {
        if (!preg_match('/^[a-zA-Z0-9_]{5,32}$/', $username)) {
            throw new InvalidArgumentException('Invalid Telegram username format.');
        }
        if (empty(trim($authToken))) {
            throw new InvalidArgumentException('Auth token cannot be empty.');
        }

        $url = self::BASE_URL . "/misc/user/{$username}/";
        $response = self::makeCurlRequest($url, 'GET', null, $authToken);
        return self::handleResponse($response);
    }

    /**
     * Retrieves the wallet balance.
     *
     * @param string $authToken JWT token from auth method
     * @return array API response
     * @throws InvalidArgumentException If auth token is invalid
     * @throws RuntimeException If the API request fails
     */
    public static function walletBalance(string $authToken): array
    {
        if (empty($authToken)) {
            throw new InvalidArgumentException('Auth token cannot be empty.');
        }

        $url = self::BASE_URL . '/misc/wallet/';
        $response = self::makeCurlRequest($url, 'GET', null, $authToken);
        return self::handleResponse($response);
    }

    /**
     * Purchases stars for a Telegram username.
     *
     * @param string $username Telegram username (e.g., @username)
     * @param int $quantity Number of stars to purchase
     * @param string $authToken JWT token
     * @return array API response
     * @throws InvalidArgumentException If inputs are invalid
     * @throws RuntimeException If the API request fails
     */
    public static function buyStars(string $username, int $quantity, string $authToken): array
    {
        if (!preg_match('/^[a-zA-Z0-9_]{5,32}$/', $username)) {
            throw new InvalidArgumentException('Invalid Telegram username format - ' . $username);
        }
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }
        if (empty($authToken)) {
            throw new InvalidArgumentException('Auth token cannot be empty.');
        }

        $url = self::BASE_URL . '/order/stars/';
        $payload = json_encode([
            'username' => $username,
            'quantity' => $quantity,
            'show_sender' => false,
        ]);

        $response = self::makeCurlRequest($url, 'POST', $payload, $authToken);
        return self::handleResponse($response);
    }

    /**
     * Purchases a Telegram Premium subscription for a username.
     *
     * @param string $username Telegram username (e.g., @username)
     * @param int $months Duration of the premium subscription (3, 6, or 12 months)
     * @param string $authToken JWT token for authentication
     * @return array Decoded API response
     * @throws InvalidArgumentException If inputs are invalid
     * @throws RuntimeException If the API request fails
     */
    public static function buyPremium(string $username, int $months, string $authToken): array
    {
        if (!preg_match('/^[a-zA-Z0-9_]{5,32}$/', $username)) {
            throw new InvalidArgumentException('Invalid Telegram username format.');
        }
        if (!in_array($months, [3, 6, 12], true)) {
            throw new InvalidArgumentException('Subscription duration must be 3, 6, or 12 months.');
        }

        if (empty(trim($authToken))) {
            throw new InvalidArgumentException('Auth token cannot be empty.');
        }

        $url = self::BASE_URL . '/order/premium/';
        $payload = json_encode([
            'username' => $username,
            'months' => $months,
            'show_sender' => false,
        ]);

        $response = self::makeCurlRequest($url, 'POST', $payload, $authToken);
        return self::handleResponse($response);
    }

    /**
     * Makes a cURL request to the specified URL.
     *
     * @param string $url API endpoint
     * @param string $method HTTP method (GET, POST)
     * @param string|null $payload JSON payload
     * @param string|null $authToken JWT token
     * @return string Raw response
     * @throws RuntimeException On cURL error
     */
    private static function makeCurlRequest(string $url, string $method, ?string $payload = null, ?string $authToken = null): string
    {
        $curl = curl_init();
        $headers = ['Accept: application/json'];
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
        }
        if ($authToken) {
            $headers[] = 'Authorization: JWT ' . $authToken;
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($curl);
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException('cURL error: ' . $error);
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode >= 400) {
            throw new RuntimeException('API request failed with HTTP code ' . $httpCode . ' and response: ' . $response);
        }

        return $response;
    }

    /**
     * Decodes and validates the API response.
     *
     * @param string $response Raw JSON response
     * @return array Decoded response
     * @throws RuntimeException If response is invalid
     */
    private static function handleResponse(string $response): array
    {
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON response: ' . json_last_error_msg());
        }
        return $decoded;
    }
}
