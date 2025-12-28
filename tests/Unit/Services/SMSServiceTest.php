<?php

namespace Tests\Unit\Services;

use App\Services\SMSService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SMSServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SMSService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SMSService();
    }

    /** @test */
    public function it_can_be_instantiated()
    {
        $this->assertInstanceOf(SMSService::class, $this->service);
    }

    /** @test */
    public function it_sends_sms_successfully()
    {
        // Set environment variables for testing
        config(['smsprima.base_url' => 'https://test.smsprima.com/api']);
        config(['smsprima.api_key' => 'test_api_key']);
        config(['smsprima.sender' => 'TEST']);
        config(['smsprima.type' => '1']);

        // Mock HTTP response
        Http::fake([
            'https://test.smsprima.com/api*' => Http::response([
                'status' => 'success',
                'message' => 'SMS sent successfully'
            ], 200)
        ]);

        $phone = '9876543210';
        $message = 'Test message';

        $response = $this->service->sendSMS($phone, $message);

        Http::assertSent(function ($request) use ($phone, $message) {
            return $request->url() === 'https://test.smsprima.com/api' &&
                   str_contains($request->url(), 'destination=' . $phone) &&
                   str_contains($request->url(), 'message=' . rawurlencode($message));
        });

        $this->assertIsString($response);
    }

    /** @test */
    public function it_handles_sms_failure()
    {
        // Set environment variables for testing
        config(['smsprima.base_url' => 'https://test.smsprima.com/api']);
        config(['smsprima.api_key' => 'test_api_key']);
        config(['smsprima.sender' => 'TEST']);
        config(['smsprima.type' => '1']);

        // Mock HTTP failure response
        Http::fake([
            'https://test.smsprima.com/api*' => Http::response([
                'status' => 'error',
                'message' => 'Failed to send SMS'
            ], 400)
        ]);

        $phone = '9876543210';
        $message = 'Test message';

        $response = $this->service->sendSMS($phone, $message);

        $this->assertIsString($response);
    }

    /** @test */
    public function it_encodes_message_correctly()
    {
        // Set environment variables for testing
        config(['smsprima.base_url' => 'https://test.smsprima.com/api']);
        config(['smsprima.api_key' => 'test_api_key']);
        config(['smsprima.sender' => 'TEST']);
        config(['smsprima.type' => '1']);

        // Mock HTTP response
        Http::fake([
            'https://test.smsprima.com/api*' => Http::response(['status' => 'success'], 200)
        ]);

        $phone = '9876543210';
        $message = 'Test message with spaces & symbols!';

        $this->service->sendSMS($phone, $message);

        Http::assertSent(function ($request) use ($message) {
            return str_contains($request->url(), 'message=' . rawurlencode($message));
        });
    }

    /** @test */
    public function it_includes_all_required_parameters()
    {
        // Set environment variables for testing
        config(['smsprima.base_url' => 'https://test.smsprima.com/api']);
        config(['smsprima.api_key' => 'test_api_key']);
        config(['smsprima.sender' => 'TEST']);
        config(['smsprima.type' => '1']);

        // Mock HTTP response
        Http::fake([
            'https://test.smsprima.com/api*' => Http::response(['status' => 'success'], 200)
        ]);

        $phone = '9876543210';
        $message = 'Test message';

        $this->service->sendSMS($phone, $message);

        Http::assertSent(function ($request) {
            $url = $request->url();
            return str_contains($url, 'apikey=test_api_key') &&
                   str_contains($url, 'sender=TEST') &&
                   str_contains($url, 'destination=9876543210') &&
                   str_contains($url, 'type=1') &&
                   str_contains($url, 'message=');
        });
    }

    /** @test */
    public function it_handles_network_timeout()
    {
        // Set environment variables for testing
        config(['smsprima.base_url' => 'https://test.smsprima.com/api']);
        config(['smsprima.api_key' => 'test_api_key']);
        config(['smsprima.sender' => 'TEST']);
        config(['smsprima.type' => '1']);

        // Mock HTTP timeout
        Http::fake([
            'https://test.smsprima.com/api*' => Http::timeout(1)->response(['status' => 'timeout'], 408)
        ]);

        $phone = '9876543210';
        $message = 'Test message';

        $response = $this->service->sendSMS($phone, $message);

        $this->assertIsString($response);
    }

    /** @test */
    public function it_validates_phone_number_format()
    {
        // Set environment variables for testing
        config(['smsprima.base_url' => 'https://test.smsprima.com/api']);
        config(['smsprima.api_key' => 'test_api_key']);
        config(['smsprima.sender' => 'TEST']);
        config(['smsprima.type' => '1']);

        // Mock HTTP response
        Http::fake([
            'https://test.smsprima.com/api*' => Http::response(['status' => 'success'], 200)
        ]);

        $phone = '9876543210'; // Valid Nepal phone number
        $message = 'Test message';

        $response = $this->service->sendSMS($phone, $message);

        $this->assertIsString($response);
        Http::assertSent(function ($request) use ($phone) {
            return str_contains($request->url(), 'destination=' . $phone);
        });
    }

    /** @test */
    public function it_handles_empty_message()
    {
        // Set environment variables for testing
        config(['smsprima.base_url' => 'https://test.smsprima.com/api']);
        config(['smsprima.api_key' => 'test_api_key']);
        config(['smsprima.sender' => 'TEST']);
        config(['smsprima.type' => '1']);

        // Mock HTTP response
        Http::fake([
            'https://test.smsprima.com/api*' => Http::response(['status' => 'success'], 200)
        ]);

        $phone = '9876543210';
        $message = '';

        $response = $this->service->sendSMS($phone, $message);

        $this->assertIsString($response);
    }

    /** @test */
    public function it_handles_long_message()
    {
        // Set environment variables for testing
        config(['smsprima.base_url' => 'https://test.smsprima.com/api']);
        config(['smsprima.api_key' => 'test_api_key']);
        config(['smsprima.sender' => 'TEST']);
        config(['smsprima.type' => '1']);

        // Mock HTTP response
        Http::fake([
            'https://test.smsprima.com/api*' => Http::response(['status' => 'success'], 200)
        ]);

        $phone = '9876543210';
        $message = str_repeat('This is a very long message. ', 20); // Long message

        $response = $this->service->sendSMS($phone, $message);

        $this->assertIsString($response);
    }
}