<?php

namespace App\Service;

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service for Stripe Checkout Session management.
 * 
 * Setup:
 *   1. Install stripe-php: composer require stripe/stripe-php
 *   2. Add to .env:
 *      STRIPE_SECRET_KEY=sk_test_...
 *      STRIPE_PUBLISHABLE_KEY=pk_test_...
 *   3. Test cards: https://stripe.com/docs/testing
 *      - Success: 4242 4242 4242 4242 | any future date | any 3-digit CVC
 *      - Decline: 4000 0000 0000 0002
 */
class StripePaymentService
{
    private string $secretKey;
    private string $publishableKey;

    public function __construct(string $secretKey = '', string $publishableKey = '')
    {
        // Log the injected values for debugging
        error_log('StripePaymentService constructor: secretKey length=' . strlen($secretKey) . ', publishableKey length=' . strlen($publishableKey));
        
        // Use injected parameters first, then fallback to environment
        $this->secretKey = $secretKey 
            ?: (getenv('STRIPE_SECRET_KEY') ?: ($_ENV['STRIPE_SECRET_KEY'] ?? ''));
        $this->publishableKey = $publishableKey 
            ?: (getenv('STRIPE_PUBLISHABLE_KEY') ?: ($_ENV['STRIPE_PUBLISHABLE_KEY'] ?? ''));
        
        error_log('StripePaymentService initialized: secretKey exists=' . !empty($this->secretKey) . ', publishableKey exists=' . !empty($this->publishableKey));
        error_log('StripePaymentService secretKey=' . substr($this->secretKey, 0, 20) . '...');
        
        if ($this->secretKey) {
            Stripe::setApiKey($this->secretKey);
            error_log('Stripe API key set');
        } else {
            error_log('WARNING: Stripe API key not set!');
        }
    }

    /**
     * Creates a Stripe Checkout Session.
     *
     * @param string $className Product name shown on Stripe's hosted page
     * @param int $amountCents Amount in the smallest currency unit (e.g., cents for EUR)
     * @param string $currency ISO currency code, e.g. "eur", "usd"
     * @param int $bookingId Embedded in the success URL so we know which booking to confirm
     * @param string $successUrl Full success redirect URL (or use default)
     * @param string $cancelUrl Full cancel redirect URL (or use default)
     * @return Session|null The Stripe Session object (use $session->url to get checkout URL)
     */
    public function createCheckoutSession(
        string $className,
        int $amountCents,
        string $currency,
        int $bookingId,
        string $successUrl = null,
        string $cancelUrl = null
    ): ?Session {
        try {
            if (!$this->isConfigured()) {
                error_log('Stripe is not configured. Secret key: ' . substr($this->secretKey, 0, 10) . '...');
                throw new \RuntimeException('Stripe is not configured. Set STRIPE_SECRET_KEY in .env');
            }

            $successUrl = $successUrl ?? 'http://localhost/payment/success?session_id={CHECKOUT_SESSION_ID}&booking_id=' . $bookingId;
            $cancelUrl = $cancelUrl ?? 'http://localhost/payment/cancel';

            error_log('Creating Stripe session for: ' . $className . ' - Amount: ' . $amountCents . ' cents - Currency: ' . $currency);

            $session = Session::create([
                'payment_method_types' => ['card'],
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => $currency,
                            'unit_amount' => $amountCents,
                            'product_data' => [
                                'name' => $className,
                            ],
                        ],
                        'quantity' => 1,
                    ],
                ],
            ]);

            error_log('Stripe session created: ' . $session->id);
            return $session;
        } catch (ApiErrorException $e) {
            error_log('Stripe API Error: ' . $e->getMessage());
            error_log('Stripe Error Details: ' . json_encode($e->getJsonBody()));
            return null;
        } catch (\Exception $e) {
            error_log('Stripe Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Verifies that a Stripe Checkout Session resulted in a completed payment.
     *
     * @param string $sessionId The Stripe session ID from the success URL
     * @return bool True if payment_status is 'paid'
     */
    public function verifySessionPaid(string $sessionId): bool
    {
        try {
            if (!$this->isConfigured()) {
                throw new \RuntimeException('Stripe is not configured');
            }

            $session = Session::retrieve($sessionId);
            return $session->payment_status === 'paid';
        } catch (ApiErrorException $e) {
            error_log('Stripe verification failed: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log('Stripe verification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets a Stripe Session by ID to inspect details.
     *
     * @param string $sessionId The Stripe session ID
     * @return Session|null
     */
    public function getSession(string $sessionId): ?Session
    {
        try {
            if (!$this->isConfigured()) {
                return null;
            }
            return Session::retrieve($sessionId);
        } catch (\Exception $e) {
            error_log('Failed to retrieve session: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Returns true if Stripe is properly configured.
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->secretKey) && $this->secretKey !== 'sk_test_YOUR_KEY_HERE';
    }

    /**
     * Gets the publishable key for client-side integration.
     *
     * @return string
     */
    public function getPublishableKey(): string
    {
        return $this->publishableKey;
    }

    /**
     * Converts TND (Tunisian Dinar) amount to EUR cents for Stripe.
     * Note: Stripe does not natively support TND.
     * Using approximate rate 1 TND ≈ 0.30 EUR. Replace with a live rate in production.
     * Minimum is 50 cents (Stripe's minimum charge).
     *
     * @param float $amountTnd Amount in TND
     * @return int Amount in EUR cents
     */
    public static function tndToEurCents(float $amountTnd): int
    {
        $eurAmount = $amountTnd * 0.30;
        return max(50, (int)round($eurAmount * 100));
    }

    /**
     * Converts any amount to the smallest currency unit (cents for most currencies).
     *
     * @param float $amount The amount
     * @param string $currency The currency code (eur, usd, etc.)
     * @return int The amount in the smallest unit
     */
    public static function convertToSmallestUnit(float $amount, string $currency = 'eur'): int
    {
        // Most currencies use 2 decimal places, some use 3 or 0
        $decimalPlaces = match(strtolower($currency)) {
            'jpy', 'krw' => 0,  // No decimal places
            'bhd', 'jod', 'kwd', 'omr', 'tnd' => 3,  // 3 decimal places
            default => 2,  // Standard: 2 decimal places
        };

        $factor = pow(10, $decimalPlaces);
        return (int)round($amount * $factor);
    }
}
