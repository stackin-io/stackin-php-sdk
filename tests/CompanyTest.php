<?php

declare(strict_types=1);

namespace Stackin\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Stackin\Client;
use Stackin\DocumentType;
use Stackin\Errors\ApiError;
use Stackin\Errors\CompanyNotInScopeError;
use Stackin\Errors\CompanyRequiredError;
use Stackin\Errors\InsufficientPermissionError;
use Stackin\FiscalReference;
use Stackin\Invoice;
use Stackin\Taxpayer;

final class CompanyTest extends TestCase
{
    private const COMPANY = '7b0c1f9e-3a52-4d8e-9f61-2c4b8a1d5e70';

    /** @var list<array{request: Request}> */
    private array $sent = [];

    /**
     * @template T of Client
     * @param T $client
     * @param array<array-key, mixed> $body
     * @return T
     */
    private function mocked(Client $client, array $body = [], int $status = 200): Client
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response($status, [], (string) json_encode($body)),
        ]));
        $stack->push(Middleware::history($this->sent));

        $property = new ReflectionProperty(Client::class, 'http');
        $property->setAccessible(true);
        $property->setValue($client, new HttpClient([
            'handler' => $stack,
            'http_errors' => false,
        ]));

        return $client;
    }

    private function invoice(?string $company = null): Invoice
    {
        return new Invoice(apiKey: 'k', baseUrl: 'https://sdk.test', company: $company);
    }

    private function request(): Request
    {
        self::assertCount(1, $this->sent);

        return $this->sent[0]['request'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function listing(): array
    {
        return [
            [
                'company_id' => self::COMPANY,
                'name' => 'ACME',
                'tax_id' => '00000000000191',
                'permissions' => ['invoices:read'],
            ],
        ];
    }

    public function testNoCompanySendsNoHeader(): void
    {
        $client = $this->mocked($this->invoice());

        $client->consult('abc', DocumentType::NFE);

        $request = $this->request();
        self::assertFalse($request->hasHeader(Client::COMPANY_HEADER));
        self::assertSame(
            ['User-Agent', 'Host', 'Authorization'],
            array_keys($request->getHeaders()),
        );
    }

    public function testNoCompanyIsNull(): void
    {
        self::assertNull($this->invoice()->company());
    }

    public function testAnEmptyCompanySendsNoHeader(): void
    {
        $client = $this->mocked($this->invoice(''));

        $client->consult('abc', DocumentType::NFE);

        self::assertFalse($this->request()->hasHeader(Client::COMPANY_HEADER));
    }

    public function testTheClientCompanyGoesOnEveryRequest(): void
    {
        $client = $this->mocked($this->invoice(self::COMPANY));

        $client->history();

        self::assertSame(self::COMPANY, $this->request()->getHeaderLine('X-Stackin-Company'));
        self::assertSame(self::COMPANY, $client->company());
    }

    public function testTheClientCompanyGoesOnAWrite(): void
    {
        $client = $this->mocked($this->invoice(self::COMPANY));

        $client->cancel('abc', DocumentType::NFE, 'cancelled by the buyer', 'idem-1');

        $request = $this->request();
        self::assertSame(self::COMPANY, $request->getHeaderLine(Client::COMPANY_HEADER));
        self::assertSame('idem-1', $request->getHeaderLine('Idempotency-Key'));
    }

    public function testTheClientCompanyGoesOnABinaryRead(): void
    {
        $client = $this->mocked($this->invoice(self::COMPANY));

        $client->pdf('abc', DocumentType::NFSE);

        self::assertSame(self::COMPANY, $this->request()->getHeaderLine(Client::COMPANY_HEADER));
    }

    public function testOneCallCanNameItsOwnCompany(): void
    {
        $client = $this->mocked($this->invoice());

        $client->consult('abc', DocumentType::NFE, company: '00.000.000/0001-91');

        self::assertSame(
            '00.000.000/0001-91',
            $this->request()->getHeaderLine(Client::COMPANY_HEADER),
        );
    }

    public function testOneCallOverridesTheClientCompany(): void
    {
        $client = $this->mocked($this->invoice(self::COMPANY));

        $client->received(company: '00000000000191');

        self::assertSame('00000000000191', $this->request()->getHeaderLine(Client::COMPANY_HEADER));
    }

    public function testATaxpayerLookupCarriesTheCompanyToo(): void
    {
        $client = $this->mocked(new Taxpayer(apiKey: 'k', company: self::COMPANY));

        $client->get('00000000000191');

        self::assertSame(self::COMPANY, $this->request()->getHeaderLine(Client::COMPANY_HEADER));
    }

    public function testAFiscalReferenceAccessorCarriesTheCompanyToo(): void
    {
        $client = $this->mocked(new FiscalReference(apiKey: 'k', company: self::COMPANY));

        $client->ncm->get('12345678');

        self::assertSame(self::COMPANY, $this->request()->getHeaderLine(Client::COMPANY_HEADER));
    }

    public function testAFiscalReferenceKindTakesACompanyPerCall(): void
    {
        $client = $this->mocked(new FiscalReference(apiKey: 'k'));

        $client->cfop->search('venda', company: self::COMPANY);

        self::assertSame(self::COMPANY, $this->request()->getHeaderLine(Client::COMPANY_HEADER));
    }

    public function testCompaniesListsWhatTheCredentialReaches(): void
    {
        $body = ['environment' => 'production', 'companies' => self::listing()];
        $client = $this->mocked($this->invoice(), $body);

        $result = $client->companies();

        $request = $this->request();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/api/v1/me/companies', $request->getUri()->getPath());
        self::assertSame($body, $result);
    }

    public function testCompaniesCarriesTheClientCompany(): void
    {
        $client = $this->mocked($this->invoice(self::COMPANY), ['companies' => []]);

        $client->companies();

        self::assertSame(self::COMPANY, $this->request()->getHeaderLine(Client::COMPANY_HEADER));
    }

    public function testCompanyRequiredIsTypedAndListsTheCompanies(): void
    {
        $client = $this->mocked($this->invoice(), ['detail' => [
            'error' => 'company_required',
            'error_description' => 'This credential covers several companies.',
            'companies' => self::listing(),
        ]], 422);

        try {
            $client->history();
            self::fail('expected CompanyRequiredError');
        } catch (CompanyRequiredError $error) {
            self::assertSame(422, $error->statusCode);
            self::assertSame(self::listing(), $error->companies);
            self::assertInstanceOf(ApiError::class, $error);
        }
        self::assertCount(1, $this->sent);
    }

    public function testCompanyNotInScopeIsTypedAndListsTheCompanies(): void
    {
        $client = $this->mocked($this->invoice('11111111000111'), ['detail' => [
            'error' => 'company_not_in_scope',
            'error_description' => 'This credential does not cover company 11111111000111.',
            'companies' => self::listing(),
        ]], 403);

        try {
            $client->history();
            self::fail('expected CompanyNotInScopeError');
        } catch (CompanyNotInScopeError $error) {
            self::assertSame(403, $error->statusCode);
            self::assertSame(self::listing(), $error->companies);
            self::assertInstanceOf(ApiError::class, $error);
        }
        self::assertCount(1, $this->sent);
    }

    public function testInsufficientPermissionIsTyped(): void
    {
        $description = 'INSUFFICIENT_PERMISSION: needs invoices:write, has invoices:read, '
            . 'company=' . self::COMPANY;
        $client = $this->mocked($this->invoice(self::COMPANY), ['detail' => [
            'error' => 'insufficient_scope',
            'error_description' => $description,
            'code' => 'INSUFFICIENT_PERMISSION',
        ]], 403);

        try {
            $client->reissue('inv-1');
            self::fail('expected InsufficientPermissionError');
        } catch (InsufficientPermissionError $error) {
            self::assertSame(403, $error->statusCode);
            self::assertSame($description, $error->description);
            self::assertStringContainsString('INSUFFICIENT_PERMISSION', $error->detail);
            self::assertInstanceOf(ApiError::class, $error);
        }
        self::assertCount(1, $this->sent);
    }

    public function testAnyOther403StaysAPlainApiError(): void
    {
        $client = $this->mocked($this->invoice(), ['detail' => [
            'error' => 'insufficient_scope',
            'error_description' => 'This operation is not available to an authorized app',
        ]], 403);

        try {
            $client->history();
            self::fail('expected ApiError');
        } catch (ApiError $error) {
            self::assertSame(ApiError::class, $error::class);
            self::assertSame(403, $error->statusCode);
        }
    }

    public function testAStringDetailStaysAPlainApiError(): void
    {
        $client = $this->mocked($this->invoice(), ['detail' => 'company_required'], 422);

        try {
            $client->history();
            self::fail('expected ApiError');
        } catch (ApiError $error) {
            self::assertSame(ApiError::class, $error::class);
            self::assertSame('company_required', $error->detail);
        }
    }
}
