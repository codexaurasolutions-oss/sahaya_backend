<?php

namespace App\Services\Admin;

use App\Models\BankAccount;
use App\Models\Salary;
use App\Models\SalaryPayout;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RazorpayXService
{
    protected string $keyId;
    protected string $keySecret;
    protected string $accountNumber;
    protected string $baseUrl;

    public function __construct()
    {
        $this->keyId = (string) config('services.razorpay.key');
        $this->keySecret = (string) config('services.razorpay.secret');
        $this->accountNumber = (string) config('services.razorpayx.account_number');
        $this->baseUrl = rtrim((string) config('services.razorpayx.base_url', 'https://api.razorpay.com'), '/');
    }

    public function isConfigured(): bool
    {
        return $this->keyId !== '' && $this->keySecret !== '' && $this->accountNumber !== '';
    }

    protected function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->withBasicAuth($this->keyId, $this->keySecret)
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    protected function sanitizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    public function buildContactPayload(User $staff): array
    {
        $name = trim((string) ($staff->name ?: trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''))));

        return array_filter([
            'name' => $name !== '' ? $name : ('Staff ' . $staff->id),
            'email' => $staff->email ?: null,
            'contact' => $this->sanitizePhone($staff->phone_number),
            'type' => 'employee',
            'reference_id' => 'staff_' . $staff->id,
            'notes' => [
                'staff_id' => (string) $staff->id,
                'source' => 'sahayya_salary_payout',
            ],
        ], static fn ($value) => $value !== null && $value !== '');
    }

    public function createContact(User $staff): array
    {
        $response = $this->client()->post('/v1/contacts', $this->buildContactPayload($staff));

        if ($response->failed()) {
            return [
                'status' => false,
                'message' => 'Failed to create RazorpayX contact',
                'response' => $response->json() ?: $response->body(),
            ];
        }

        return [
            'status' => true,
            'data' => $response->json(),
        ];
    }

    public function buildFundAccountPayload(BankAccount $bankAccount, string $contactId): array
    {
        return [
            'contact_id' => $contactId,
            'account_type' => 'bank_account',
            'bank_account' => [
                'name' => $bankAccount->user?->name ?: $bankAccount->user?->first_name ?: 'Staff',
                'ifsc' => strtoupper(trim((string) $bankAccount->ifsc_code)),
                'account_number' => preg_replace('/\s+/', '', (string) $bankAccount->account_number),
            ],
        ];
    }

    public function createFundAccount(BankAccount $bankAccount, string $contactId): array
    {
        $response = $this->client()->post('/v1/fund_accounts', $this->buildFundAccountPayload($bankAccount, $contactId));

        if ($response->failed()) {
            return [
                'status' => false,
                'message' => 'Failed to create RazorpayX fund account',
                'response' => $response->json() ?: $response->body(),
            ];
        }

        return [
            'status' => true,
            'data' => $response->json(),
        ];
    }

    public function buildPayoutPayload(Salary $salary, BankAccount $bankAccount, string $fundAccountId, array $options = []): array
    {
        $amount = (float) $salary->net_salary;
        $referenceId = $options['reference_id'] ?? ('salary_' . $salary->id . '_' . Str::uuid()->toString());

        return [
            'account_number' => $this->accountNumber,
            'fund_account_id' => $fundAccountId,
            'amount' => (int) round($amount * 100),
            'currency' => $options['currency'] ?? 'INR',
            'mode' => $options['mode'] ?? 'bank_transfer',
            'purpose' => $options['purpose'] ?? 'salary',
            'queue_if_low_balance' => $options['queue_if_low_balance'] ?? true,
            'reference_id' => $referenceId,
            'narration' => $options['narration'] ?? ('Salary payout for salary #' . $salary->id),
            'notes' => array_merge([
                'salary_id' => (string) $salary->id,
                'staff_id' => (string) $salary->staff_id,
                'bank_account_id' => (string) $bankAccount->id,
            ], $options['notes'] ?? []),
        ];
    }

    public function createPayout(Salary $salary, BankAccount $bankAccount, string $fundAccountId, string $idempotencyKey, array $options = []): array
    {
        $payload = $this->buildPayoutPayload($salary, $bankAccount, $fundAccountId, $options);

        $response = $this->client()
            ->withHeaders([
                'X-Payout-Idempotency' => $idempotencyKey,
            ])
            ->post('/v1/payouts', $payload);

        if ($response->failed()) {
            return [
                'status' => false,
                'message' => 'Failed to create RazorpayX payout',
                'request' => $payload,
                'response' => $response->json() ?: $response->body(),
            ];
        }

        return [
            'status' => true,
            'request' => $payload,
            'data' => $response->json(),
        ];
    }
}
