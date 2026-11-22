<?php

declare(strict_types=1);

namespace Stackin\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Stackin\Client;
use Stackin\Company;
use Stackin\Errors\ApiError;
use Stackin\Errors\InsufficientPermissionError;
use Stackin\Errors\InvoiceError;

final class CompanyUpdateTest extends TestCase
{
    private const BASE = 'https://sdk.test/api/v1';

    /** @var list<array{request: Request}> */
    private array $sent = [];

    private function company(Response $response): Company
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::history($this->sent));

        $client = new Company(apiKey: 'k', baseUrl: 'https://sdk.test');
        $property = new ReflectionProperty(Client::class, 'http');
        $property->setAccessible(true);
        $property->setValue($client, new HttpClient([
            'handler' => $stack,
            'http_errors' => false,
        ]));

        return $client;
    }

    /**
     * @param array<array-key, mixed> $body
     * @param array<string, string> $headers
     */
    private static function json(array $body, int $status = 200, array $headers = []): Response
    {
        return new Response($status, $headers, (string) json_encode($body));
    }

    private function request(): Request
    {
        $this->assertCount(1, $this->sent);

        return $this->sent[0]['request'];
    }

    public function testUpdateSendsBothGroups(): void
    {
        $this->company(self::json(['ready' => true]))->update(
            address: [
                'street' => 'Av. Paulista',
                'number' => '1000',
                'neighborhood' => 'Bela Vista',
                'municipality_code' => '3550308',
                'municipality_name' => 'São Paulo',
                'zip_code' => '01310100',
            ],
            fiscalFields: [
                'tax_regime' => '1',
                'simples_nacional' => '3',
                'simples_apuracao' => '1',
                'state_registration' => 'ISENTO',
                'service_code' => '01.07.00',
            ],
        );

        $request = $this->request();
        $uri = $request->getUri();
        $this->assertSame('PATCH', $request->getMethod());
        $url = $uri->getScheme() . '://' . $uri->getHost() . $uri->getPath();
        $this->assertSame(self::BASE . '/company', $url);
        $this->assertSame([
            'address' => [
                'street' => 'Av. Paulista',
                'number' => '1000',
                'neighborhood' => 'Bela Vista',
                'municipality_code' => '3550308',
                'municipality_name' => 'São Paulo',
                'zip_code' => '01310100',
            ],
            'fiscal_fields' => [
                'tax_regime' => '1',
                'simples_nacional' => '3',
                'simples_apuracao' => '1',
                'state_registration' => 'ISENTO',
                'service_code' => '01.07.00',
            ],
        ], json_decode((string) $request->getBody(), true));
    }

    public function testTheIbsCbsApuracaoIsSentAsGiven(): void
    {
        $this->company(self::json([]))->update(
            fiscalFields: ['simples_apuracao_ibs_cbs' => '3'],
        );

        $this->assertSame(
            ['fiscal_fields' => ['simples_apuracao_ibs_cbs' => '3']],
            json_decode((string) $this->request()->getBody(), true),
        );
    }

    public function testTheZipMaskIsStrippedAndNullsLeftOut(): void
    {
        $this->company(self::json([]))->update(
            address: ['zip_code' => '01310-100', 'street' => null],
            fiscalFields: ['service_code' => null],
        );

        $this->assertSame(
            ['address' => ['zip_code' => '01310100']],
            json_decode((string) $this->request()->getBody(), true),
        );
    }

    public function testNothingSendsAnEmptyObject(): void
    {
        $this->company(self::json(['ready' => false]))->update();

        $this->assertSame('{}', (string) $this->request()->getBody());
    }

    public function testEmptyGroupsAreNotSent(): void
    {
        $this->company(self::json([]))->update(address: [], fiscalFields: ['tax_regime' => null]);

        $this->assertSame('{}', (string) $this->request()->getBody());
    }

    public function testTheCompanyHeaderIsSent(): void
    {
        $this->company(self::json([]))->update(address: ['number' => '12'], company: 'acme');

        $this->assertSame('acme', $this->request()->getHeaderLine('X-Stackin-Company'));
    }

    public function testTheResponseComesBackUntouched(): void
    {
        $answer = [
            'company_id' => 'c1',
            'ready' => true,
            'missing' => [],
            'by_document_type' => ['nfse' => ['ready' => true, 'missing' => [], 'warnings' => []]],
            'fix_url' => null,
            'suggested_fiscal_fields' => ['tax_regime' => '4'],
            'address' => ['street' => 'Av. Paulista', 'state' => 'SP'],
            'fiscal_fields' => ['tax_regime' => '1'],
            'something_new' => 1,
        ];

        $result = $this->company(self::json($answer))->update(address: ['number' => '12']);

        $this->assertSame($answer, $result);
    }

    /**
     * @return array<string, array{0: ?array<string, string>, 1: ?array<string, string>, 2: string}>
     */
    public static function refusals(): array
    {
        return [
            'complement' => [['complement' => 'Sala 1'], null, 'complement'],
            'state' => [['state' => 'SP'], null, 'state'],
            'unknown fiscal key' => [null, ['crt' => '1'], 'crt'],
            'short city code' => [['municipality_code' => '355030'], null, 'municipality_code'],
            'lettered city code' => [['municipality_code' => '355030X'], null, 'municipality_code'],
            'short zip_code' => [['zip_code' => '0131010'], null, 'zip_code'],
            'tax_regime 5' => [null, ['tax_regime' => '5'], 'tax_regime'],
            'simples_nacional 4' => [null, ['simples_nacional' => '4'], 'simples_nacional'],
            'simples_apuracao 0' => [null, ['simples_apuracao' => '0'], 'simples_apuracao'],
            'simples_apuracao_ibs_cbs 2' => [
                null,
                ['simples_apuracao_ibs_cbs' => '2'],
                'simples_apuracao_ibs_cbs',
            ],
        ];
    }

    /**
     * @param array<string, string>|null $address
     * @param array<string, string>|null $fiscal
     */
    #[DataProvider('refusals')]
    public function testRefusedBeforeTheCall(?array $address, ?array $fiscal, string $named): void
    {
        $client = $this->company(self::json([]));

        try {
            $client->update(address: $address, fiscalFields: $fiscal);
            $this->fail('expected an InvoiceError');
        } catch (InvoiceError $error) {
            $this->assertStringContainsString($named, $error->getMessage());
        }
        $this->assertCount(0, $this->sent);
    }

    public function testANonOwnerIsAnApiError403(): void
    {
        $client = $this->company(
            self::json(['detail' => 'Only the company owner may do this.'], 403),
        );

        try {
            $client->update(fiscalFields: ['tax_regime' => '1']);
            $this->fail('expected an ApiError');
        } catch (ApiError $error) {
            $this->assertNotInstanceOf(InsufficientPermissionError::class, $error);
            $this->assertSame(403, $error->statusCode);
        }
    }

    public function testAMissingScopeIsAnInsufficientPermissionError(): void
    {
        $client = $this->company(self::json(['detail' => [
            'error' => 'insufficient_scope',
            'code' => 'INSUFFICIENT_PERMISSION',
            'error_description' => 'INSUFFICIENT_PERMISSION: needs company:write',
        ]], 403));

        $this->expectException(InsufficientPermissionError::class);
        $client->update(address: ['number' => '12']);
    }

    public function testAnUnreadableCityTableIsAnApiError503(): void
    {
        $client = $this->company(self::json(
            ['detail' => 'The municipality could not be checked right now; nothing was changed.'],
            503,
            ['Retry-After' => '30'],
        ));

        try {
            $client->update(address: ['municipality_code' => '3550308']);
            $this->fail('expected an ApiError');
        } catch (ApiError $error) {
            $this->assertSame(503, $error->statusCode);
        }
    }
}
