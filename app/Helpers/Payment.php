<?php

namespace App\Helpers;

class Payment
{
    private $apiUrl;

    public function __construct()
    {
        $this->apiUrl = 'https://c578.coresuz.ru'; // Your payment API URL
    }

    private function sendRequest(array $data): array
    {
        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_POST, true);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'cURL error: ' . $error];
        }

        return json_decode($response, true);
    }

    public function createInvoice(int $amount, string $currency, string $paymentMethod, string $description, string $card): array
    {
        return $this->sendRequest([
            'method' => 'createInvoice',
            'amount' => $amount,
            'currency' => $currency,
            'payment_method' => $paymentMethod,
            'description' => $description,
            'card' => $card
        ]);
    }

    public function checkInvoice(string $apiKey): array
    {
        return $this->sendRequest([
            'method' => 'checkInvoice',
            'api_key' => $apiKey
        ]);
    }

    public function markPaid(int $amount, string $card): array
    {
        return $this->sendRequest([
            'method' => 'markPaid',
            'amount' => $amount,
            'card' => $card
        ]);
    }

    public function markSuccess(string $apiKey): array
    {
        return $this->sendRequest([
            'method' => 'markSuccess',
            'api_key' => $apiKey
        ]);
    }
}