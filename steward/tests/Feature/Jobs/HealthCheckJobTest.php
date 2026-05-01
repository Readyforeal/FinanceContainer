<?php

namespace Tests\Feature\Jobs;

use App\Jobs\HealthCheckJob;
use App\Models\IncomeSource;
use App\Services\FinancialContextBuilder;
use App\Services\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class HealthCheckJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_health_check_analysis(): void
    {
        IncomeSource::factory()->create([
            'name' => 'Main Job',
            'amount' => 5000,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $expectedAnalysis = 'Financial health check: Your spending habits are generally positive. You show consistent savings discipline and essential spending is well-managed. Biblical stewardship principle: continue giving generously.';

        $mockOllama = Mockery::mock(OllamaService::class);
        $mockOllama->shouldReceive('chat')
            ->once()
            ->andReturn($expectedAnalysis);

        $mockContext = Mockery::mock(FinancialContextBuilder::class);
        $mockContext->shouldReceive('buildSystemPrompt')
            ->once()
            ->andReturn('You are StewardAI...');
        $mockContext->shouldReceive('buildDynamicContext')
            ->once()
            ->andReturn('Account balances: ...');

        $job = new HealthCheckJob();
        $result = $job->handle($mockOllama, $mockContext);

        $this->assertIsString($result);
        $this->assertStringContainsString($expectedAnalysis, $result);
    }
}
