<?php
namespace Tests\Unit\Services;

use App\Services\PlaidService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaidServiceTest extends TestCase
{
    private PlaidService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.plaid.client_id' => 'test_client_id',
            'services.plaid.secret' => 'test_secret',
            'services.plaid.env' => 'sandbox',
        ]);
        $this->service = new PlaidService();
    }

    public function test_create_link_token(): void
    {
        Http::fake([
            'https://sandbox.plaid.com/link/token/create' => Http::response([
                'link_token' => 'link-sandbox-abc123',
                'expiration' => '2026-05-01T00:00:00Z',
            ]),
        ]);

        $result = $this->service->createLinkToken('user-1');

        $this->assertEquals('link-sandbox-abc123', $result['link_token']);
        Http::assertSent(function ($request) {
            return $request['client_id'] === 'test_client_id'
                && $request['user']['client_user_id'] === 'user-1'
                && in_array('transactions', $request['products']);
        });
    }

    public function test_exchange_public_token(): void
    {
        Http::fake([
            'https://sandbox.plaid.com/item/public_token/exchange' => Http::response([
                'access_token' => 'access-sandbox-xyz789',
                'item_id' => 'item_abc123',
            ]),
        ]);

        $result = $this->service->exchangePublicToken('public-sandbox-token');

        $this->assertEquals('access-sandbox-xyz789', $result['access_token']);
        $this->assertEquals('item_abc123', $result['item_id']);
    }

    public function test_sync_transactions(): void
    {
        Http::fake([
            'https://sandbox.plaid.com/transactions/sync' => Http::response([
                'added' => [
                    [
                        'transaction_id' => 'txn_001',
                        'account_id' => 'acc_001',
                        'amount' => 12.50,
                        'date' => '2026-04-28',
                        'merchant_name' => 'Starbucks',
                        'name' => 'STARBUCKS COFFEE',
                        'personal_finance_category' => ['primary' => 'FOOD_AND_DRINK'],
                    ],
                ],
                'modified' => [],
                'removed' => [],
                'next_cursor' => 'cursor_abc',
                'has_more' => false,
            ]),
        ]);

        $result = $this->service->syncTransactions('access-token', null);

        $this->assertCount(1, $result['added']);
        $this->assertEquals('txn_001', $result['added'][0]['transaction_id']);
        $this->assertEquals('cursor_abc', $result['next_cursor']);
        $this->assertFalse($result['has_more']);
    }

    public function test_get_accounts(): void
    {
        Http::fake([
            'https://sandbox.plaid.com/accounts/get' => Http::response([
                'accounts' => [
                    [
                        'account_id' => 'acc_001',
                        'name' => 'Checking',
                        'type' => 'depository',
                        'subtype' => 'checking',
                        'balances' => ['current' => 1247.33, 'available' => 1200.00],
                    ],
                ],
            ]),
        ]);

        $result = $this->service->getAccounts('access-token');

        $this->assertCount(1, $result);
        $this->assertEquals('Checking', $result[0]['name']);
        $this->assertEquals(1247.33, $result[0]['balances']['current']);
    }
}
