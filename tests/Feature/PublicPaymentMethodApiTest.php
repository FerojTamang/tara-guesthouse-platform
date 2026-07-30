<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\PublicPaymentMethodController;
use App\Core\Request;
use App\Core\Router;
use App\Core\ViewRenderer;
use App\Models\PaymentMethod;
use App\Repositories\PaymentMethodCatalogRepository;
use App\Services\PublicPaymentMethodService;
use App\Services\PublicPaymentMethodServiceProvider;
use DateTimeImmutable;
use JsonException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicPaymentMethodApiTest extends TestCase
{
    private PaymentMethodCatalogRepository $repository;

    private Router $router;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(PaymentMethodCatalogRepository::class);
        $controller = new PublicPaymentMethodController(
            new PublicPaymentMethodServiceProvider(
                fn (): PublicPaymentMethodService => new PublicPaymentMethodService(
                    $this->repository,
                ),
            ),
        );
        $this->router = new Router();
        $registrar = require dirname(__DIR__, 2) . '/routes/api.php';
        $registrar(
            $this->router,
            new ViewRenderer(dirname(__DIR__, 2) . '/resources/views'),
            $controller,
        );
    }

    /** @throws JsonException */
    public function testItReturnsOnlyActiveConfiguredMethodsUsingThePublicContract(): void
    {
        $this->repository->method('findAllActive')->willReturn([
            self::method(
                4,
                'Bank Transfer',
                'Transfer the exact booking total.',
                'TEST-BANK-ACCOUNT',
                'payment-methods/bank.png',
                true,
                true,
            ),
            self::method(
                6,
                'Inactive fixture',
                null,
                null,
                null,
                false,
                false,
            ),
        ]);

        $response = $this->router->dispatch(
            Request::create('GET', '/api/v1/payment-methods'),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame(
            'application/json; charset=UTF-8',
            $response->headers()['Content-Type'],
        );
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame([
            'success' => true,
            'data' => [[
                'payment_method_id' => 4,
                'name' => 'Bank Transfer',
                'instructions' => 'Transfer the exact booking total.',
                'account_reference' => 'TEST-BANK-ACCOUNT',
                'qr_image_path' => 'payment-methods/bank.png',
                'requires_proof' => true,
            ]],
        ], json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('created_at', $response->body());
        self::assertStringNotContainsString('updated_at', $response->body());
        self::assertStringNotContainsString('is_active', $response->body());
    }

    /** @throws JsonException */
    public function testEmptyActiveCatalogueReturnsAStableEmptyList(): void
    {
        $this->repository->method('findAllActive')->willReturn([]);

        $response = $this->router->dispatch(
            Request::create('GET', '/api/v1/payment-methods'),
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame(
            ['success' => true, 'data' => []],
            json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    /** @throws JsonException */
    public function testRepositoryFailureReturnsGeneric500WithoutDisclosure(): void
    {
        $this->repository->method('findAllActive')->willThrowException(
            new RuntimeException('SQLSTATE private account details'),
        );

        $response = $this->router->dispatch(
            Request::create('GET', '/api/v1/payment-methods'),
        );

        self::assertSame(500, $response->statusCode());
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame([
            'success' => false,
            'message' => 'Internal Server Error.',
        ], json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('SQLSTATE', $response->body());
        self::assertStringNotContainsString('account details', $response->body());
    }

    public function testEndpointIsReadOnlyAndRequiresNoAdministratorMiddleware(): void
    {
        $response = $this->router->dispatch(
            Request::create('POST', '/api/v1/payment-methods'),
        );

        self::assertSame(405, $response->statusCode());
        self::assertSame('GET, HEAD', $response->headers()['Allow']);
    }

    private static function method(
        int $id,
        string $name,
        ?string $instructions,
        ?string $accountReference,
        ?string $qrImagePath,
        bool $requiresProof,
        bool $active,
    ): PaymentMethod {
        $timestamp = new DateTimeImmutable('2026-07-28 06:00:00 UTC');

        return new PaymentMethod(
            $id,
            $name,
            $instructions,
            $accountReference,
            $qrImagePath,
            $requiresProof,
            $active,
            $timestamp,
            $timestamp,
        );
    }
}
