<?php

namespace MarceliTo\Wiretap\Tests;

use MarceliTo\Wiretap\Wiretap;
use Orchestra\Testbench\TestCase;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WiretapExceptionTest extends TestCase
{
    private $wiretap;
    private $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'enabled' => true,
            'log_to_laravel' => false, // Disable Laravel logging for tests
            'webhook' => ['enabled' => false], // Disable webhooks for tests
            'exception_levels' => [
                'Illuminate\Validation\ValidationException' => 'info',
                'Illuminate\Auth\AuthenticationException' => 'warning',
                'Illuminate\Database\Eloquent\ModelNotFoundException' => 'info',
                'Symfony\Component\HttpKernel\Exception\NotFoundHttpException' => 'info',
                'Symfony\Component\HttpKernel\Exception\HttpException' => function ($exception) {
                    $statusCode = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500;
                    return $statusCode >= 500 ? 'error' : 'info';
                },
                'default' => 'error',
            ],
        ];

        $this->wiretap = new Wiretap($this->config);
    }

    public function testDetermineExceptionLevelForValidationException()
    {
        $exception = ValidationException::withMessages(['email' => 'The email field is required.']);
        $level = $this->callProtectedMethod($this->wiretap, 'determineExceptionLevel', [$exception]);

        $this->assertEquals('info', $level);
    }

    public function testDetermineExceptionLevelForAuthenticationException()
    {
        $exception = new AuthenticationException();
        $level = $this->callProtectedMethod($this->wiretap, 'determineExceptionLevel', [$exception]);

        $this->assertEquals('warning', $level);
    }

    public function testDetermineExceptionLevelForModelNotFoundException()
    {
        $exception = new ModelNotFoundException();
        $level = $this->callProtectedMethod($this->wiretap, 'determineExceptionLevel', [$exception]);

        $this->assertEquals('info', $level);
    }

    public function testDetermineExceptionLevelForNotFoundHttpException()
    {
        $exception = new NotFoundHttpException();
        $level = $this->callProtectedMethod($this->wiretap, 'determineExceptionLevel', [$exception]);

        $this->assertEquals('info', $level);
    }

    public function testDetermineExceptionLevelForHttpExceptionWithClosure()
    {
        // Test 500 error
        $serverError = new HttpException(500, 'Server Error');
        $level = $this->callProtectedMethod($this->wiretap, 'determineExceptionLevel', [$serverError]);
        $this->assertEquals('error', $level);

        // Test 400 error
        $clientError = new HttpException(400, 'Bad Request');
        $level = $this->callProtectedMethod($this->wiretap, 'determineExceptionLevel', [$clientError]);
        $this->assertEquals('info', $level);
    }

    public function testDetermineExceptionLevelForUnknownException()
    {
        $exception = new \RuntimeException('Unknown error');
        $level = $this->callProtectedMethod($this->wiretap, 'determineExceptionLevel', [$exception]);

        $this->assertEquals('error', $level);
    }

    public function testErrorIfMethodLogsWhenConditionIsTrue()
    {
        // We can't easily test the actual logging without mocking Laravel's Log facade
        // This test just ensures the method exists and doesn't throw errors
        $this->wiretap->errorIf(true, 'Test error message');
        $this->wiretap->errorIf(false, 'This should not log');

        // If we get here without exceptions, the method works
        $this->assertTrue(true);
    }

    public function testExceptionMethodWithSkipLevel()
    {
        // Configure exception to be skipped
        $configWithSkip = array_merge($this->config, [
            'exception_levels' => [
                'RuntimeException' => 'skip',
                'default' => 'error',
            ],
        ]);

        $wiretap = new Wiretap($configWithSkip);
        $exception = new \RuntimeException('This should be skipped');

        // This should not throw any errors and should return early
        $wiretap->exception($exception);

        $this->assertTrue(true);
    }

    /**
     * Helper method to call protected methods for testing
     */
    private function callProtectedMethod($object, $method, array $args = [])
    {
        $class = new \ReflectionClass($object);
        $method = $class->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}