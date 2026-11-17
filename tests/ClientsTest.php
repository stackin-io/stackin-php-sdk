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
use Stackin\Address;
use Stackin\Br\Product;
use Stackin\Client;
use Stackin\Clients;
use Stackin\DocumentType;
use Stackin\Errors\ApiError;
use Stackin\Errors\ClientAlreadyExistsError;
use Stackin\Errors\InvoiceError;
use Stackin\Invoice;

final class ClientsTest extends TestCase
{
    private const CLIENT_ID = '0b6c3d1e-7a2f-4e59-9c10-5d8e2f3a4b6c';
    private const BASE = 'https://sdk.test/api/v1';

    /** @var list<array{request: Request}> */
    private array $sent = [];

    /**
     * @template T of Client
     * @param T $client
     * @param array<string, mixed> $body
     * @return T
     */
    private function wire(
        Client $client,
        array $body = [],
        int $status = 200,
        int $replies = 1,
    ): Client {
        $this->sent = [];
        $responses = [];
        for ($i = 0; $i < $replies; $i++) {
            $text = $status === 204 ? '' : (string) json_encode($body);
            $responses[] = new Response($status, [], $text);
        }
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->sent));

        $property = new ReflectionProperty(Client::class, 'http');
        $property->setAccessible(true);
        $property->setValue($client, new HttpClient([
            'handler' => $stack,
            'http_errors' => false,
        ]));

        return $client;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function clients(
        array $body = [],
        int $status = 200,
        ?string $company = null,
        int $replies = 1,
    ): Clients {
        return $this->wire(
            new Clients(apiKey: 'k', baseUrl: 'https://sdk.test', company: $company),
            $body,
            $status,
            $replies,
        );
    }

    private function request(int $index = 0): Request
    {
        return $this->sent[$index]['request'];
    }

    private function url(int $index = 0): string
    {
        $uri = $this->request($index)->getUri();

        return "{$uri->getScheme()}://{$uri->getHost()}{$uri->getPath()}";
    }

    /** @return array<string, string> */
    private function query(int $index = 0): array
    {
        parse_str($this->request($index)->getUri()->getQuery(), $parsed);

        /** @var array<string, string> $parsed */
        return $parsed;
    }

    /** @return array<string, mixed> */
    private function body(int $index = 0): array
    {
        $decoded = json_decode((string) $this->request($index)->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function testListSendsNoParamsWithoutAFilter(): void
    {
        $this->clients()->list();

        self::assertSame('GET', $this->request()->getMethod());
        self::assertSame(self::BASE . '/clients', $this->url());
        self::assertSame([], $this->query());
        self::assertSame('', (string) $this->request()->getBody());
    }

    public function testListSendsEveryFilterByItsApiName(): void
    {
        $this->clients()->list(
            search: 'acme',
            source: 'issued',
            isActive: false,
            limit: 10,
            offset: 20,
            sortBy: 'last_used_at',
            orderBy: 'asc',
        );

        self::assertSame([
            'search' => 'acme',
            'source' => 'issued',
            'is_active' => 'false',
            'limit' => '10',
            'offset' => '20',
            'sort_by' => 'last_used_at',
            'order_by' => 'asc',
        ], $this->query());
    }

    public function testListReturnsThePage(): void
    {
        $page = ['data' => [['id' => self::CLIENT_ID]], 'total' => 1];

        self::assertSame($page, $this->clients($page)->list());
    }

    public function testGetReadsOneClient(): void
    {
        $this->clients()->get(self::CLIENT_ID);

        self::assertSame('GET', $this->request()->getMethod());
        self::assertSame(self::BASE . '/clients/' . self::CLIENT_ID, $this->url());
    }

    public function testGetRefusesAnIdThatLeavesItsSegment(): void
    {
        $this->expectException(InvoiceError::class);

        $this->clients()->get('..');
    }

    public function testCreateSendsOnlyTheFieldsGiven(): void
    {
        $this->clients(status: 201)->create(name: 'ACME', taxId: '11.222.333/0001-81');

        self::assertSame('POST', $this->request()->getMethod());
        self::assertSame(self::BASE . '/clients', $this->url());
        self::assertSame(['name' => 'ACME', 'tax_id' => '11.222.333/0001-81'], $this->body());
    }

    public function testCreateSendsEveryFieldByItsApiName(): void
    {
        $address = new Address(state: 'SP', city: 'Sao Paulo', zipCode: '01001000');

        $this->clients(status: 201)->create(
            name: 'ACME',
            taxId: '11222333000181',
            stateRegistration: 'ISENTO',
            municipalRegistration: '123',
            email: 'a@acme.test',
            phone: '+5511999999999',
            address: $address,
            notes: 'vip',
        );

        self::assertSame([
            'name' => 'ACME',
            'tax_id' => '11222333000181',
            'state_registration' => 'ISENTO',
            'municipal_registration' => '123',
            'email' => 'a@acme.test',
            'phone' => '+5511999999999',
            'address' => $address->toArray(),
            'notes' => 'vip',
        ], $this->body());
    }

    public function testCreateIdentifiesABuyerAbroadByForeignId(): void
    {
        $this->clients(status: 201)->create(
            name: 'Globex',
            foreignId: 'P1234567',
            address: new Address(state: 'EX', countryCode: '2496'),
        );

        self::assertSame([
            'name' => 'Globex',
            'foreign_id' => 'P1234567',
            'address' => ['state' => 'EX', 'country_code' => '2496'],
        ], $this->body());
    }

    public function testUpdateSendsOnlyTheFieldsGiven(): void
    {
        $this->clients()->update(self::CLIENT_ID, ['email' => 'new@acme.test']);

        self::assertSame('PATCH', $this->request()->getMethod());
        self::assertSame(self::BASE . '/clients/' . self::CLIENT_ID, $this->url());
        self::assertSame(['email' => 'new@acme.test'], $this->body());
    }

    public function testUpdateSendsNullToClearAndAnAddressWhole(): void
    {
        $this->clients()->update(self::CLIENT_ID, [
            'phone' => null,
            'address' => new Address(state: 'RJ', city: 'Rio de Janeiro'),
        ]);

        self::assertSame([
            'phone' => null,
            'address' => ['state' => 'RJ', 'city' => 'Rio de Janeiro'],
        ], $this->body());
    }

    public function testDeactivateDeletes(): void
    {
        $this->clients(status: 204)->deactivate(self::CLIENT_ID);

        self::assertSame('DELETE', $this->request()->getMethod());
        self::assertSame(self::BASE . '/clients/' . self::CLIENT_ID, $this->url());
        self::assertSame('', (string) $this->request()->getBody());
    }

    public function testReactivatePatchesIsActive(): void
    {
        $reply = ['id' => self::CLIENT_ID, 'is_active' => true];

        $result = $this->clients($reply)->reactivate(self::CLIENT_ID);

        self::assertSame('PATCH', $this->request()->getMethod());
        self::assertSame(self::BASE . '/clients/' . self::CLIENT_ID, $this->url());
        self::assertSame(['is_active' => true], $this->body());
        self::assertSame($reply, $result);
    }

    public function testTheClientsCompanyGoesOnEveryCall(): void
    {
        $client = $this->clients(company: '11222333000181', replies: 6);

        $client->list();
        $client->get(self::CLIENT_ID);
        $client->create(name: 'ACME', taxId: '11222333000181');
        $client->update(self::CLIENT_ID, ['name' => 'ACME']);
        $client->deactivate(self::CLIENT_ID);
        $client->reactivate(self::CLIENT_ID);

        self::assertCount(6, $this->sent);
        foreach ($this->sent as $entry) {
            $header = $entry['request']->getHeaderLine('X-Stackin-Company');
            self::assertSame('11222333000181', $header);
        }
    }

    public function testOneCallCanNameAnotherCompany(): void
    {
        $this->clients(company: 'mine')->get(self::CLIENT_ID, company: 'other');

        self::assertSame('other', $this->request()->getHeaderLine('X-Stackin-Company'));
    }

    public function testNoCompanySendsNoHeader(): void
    {
        $this->clients()->list();

        self::assertFalse($this->request()->hasHeader('X-Stackin-Company'));
    }

    /** @return array<string, mixed> */
    private static function duplicate(): array
    {
        return ['detail' => [
            'code' => 'CLIENT_EXISTS',
            'message' => 'This company already has a client with tax_id 11222333000181.',
            'client_id' => self::CLIENT_ID,
        ]];
    }

    public function testADuplicateCarriesTheExistingClientId(): void
    {
        try {
            $this->clients(self::duplicate(), 409)
                ->create(name: 'ACME', taxId: '11222333000181');
            self::fail('expected ClientAlreadyExistsError');
        } catch (ClientAlreadyExistsError $error) {
            self::assertSame(self::CLIENT_ID, $error->clientId);
            self::assertSame(409, $error->statusCode);
            self::assertInstanceOf(ApiError::class, $error);
        }
    }

    public function testAnUpdateTakingAnotherTaxIdRaisesItToo(): void
    {
        $this->expectException(ClientAlreadyExistsError::class);

        $this->clients(self::duplicate(), 409)
            ->update(self::CLIENT_ID, ['tax_id' => '11222333000181']);
    }

    public function testAnotherConflictStaysAPlainApiError(): void
    {
        try {
            $this->clients(['detail' => 'conflict'], 409)
                ->create(name: 'ACME', taxId: '11222333000181');
            self::fail('expected ApiError');
        } catch (ApiError $error) {
            self::assertNotInstanceOf(ClientAlreadyExistsError::class, $error);
        }
    }

    private function invoice(): Invoice
    {
        return $this->wire(new Invoice(apiKey: 'k', baseUrl: 'https://sdk.test'));
    }

    private static function nfeItem(): Product
    {
        return new Product(description: 'Teclado', amount: 10.0, ncm: '84716052', cfop: '5102');
    }

    private static function nfseItem(bool $taxRetained = false): Product
    {
        return new Product(description: 'svc', amount: 10.0, taxRetained: $taxRetained);
    }

    public function testIssueWithClientIdAloneNamesTheRecipient(): void
    {
        $this->invoice()->issue(
            documentType: DocumentType::NFE,
            clientName: null,
            taxId: null,
            items: [self::nfeItem()],
            clientId: self::CLIENT_ID,
        );

        self::assertSame(
            ['document_type', 'client_id', 'items'],
            array_keys($this->body()),
        );
        self::assertSame(self::CLIENT_ID, $this->body()['client_id']);
    }

    public function testIssueWithClientIdTakesTheClientsAddressOnRetainedIss(): void
    {
        $this->invoice()->issue(
            documentType: DocumentType::NFSE,
            clientName: null,
            taxId: null,
            items: [self::nfseItem(true)],
            clientId: self::CLIENT_ID,
        );

        self::assertArrayNotHasKey('recipient_address', $this->body());
    }

    public function testIssueWithClientIdSendsTheFieldsGivenSoTheyWin(): void
    {
        $this->invoice()->issue(
            documentType: DocumentType::NFSE,
            clientName: 'ACME Filial',
            taxId: '11222333000181',
            items: [self::nfseItem()],
            clientId: self::CLIENT_ID,
        );

        $body = $this->body();
        self::assertSame(self::CLIENT_ID, $body['client_id']);
        self::assertSame('ACME Filial', $body['client_name']);
        self::assertSame('11222333000181', $body['tax_id']);
    }

    public function testIssueWithClientIdStillRefusesAPartialAddress(): void
    {
        $this->expectException(InvoiceError::class);

        $this->invoice()->issue(
            documentType: DocumentType::NFE,
            clientName: null,
            taxId: null,
            items: [self::nfeItem()],
            recipientAddress: new Address(state: 'SP'),
            clientId: self::CLIENT_ID,
        );
    }

    public function testIssueSendsUpdateClientOnlyWhenTrue(): void
    {
        $this->invoice()->issue(
            documentType: DocumentType::NFE,
            clientName: null,
            taxId: null,
            items: [self::nfeItem()],
            clientId: self::CLIENT_ID,
            updateClient: true,
        );
        self::assertTrue($this->body()['update_client']);

        $this->invoice()->issue(
            documentType: DocumentType::NFE,
            clientName: null,
            taxId: null,
            items: [self::nfeItem()],
            clientId: self::CLIENT_ID,
        );
        self::assertArrayNotHasKey('update_client', $this->body());
    }

    public function testWithoutClientIdClientNameIsStillRequired(): void
    {
        $this->expectException(InvoiceError::class);

        $this->invoice()->issue(
            documentType: DocumentType::NFSE,
            clientName: null,
            taxId: '11222333000181',
            items: [self::nfseItem()],
        );
    }

    public function testWithoutClientIdTaxIdIsStillRequired(): void
    {
        $this->expectException(InvoiceError::class);

        $this->invoice()->issue(
            documentType: DocumentType::NFSE,
            clientName: 'ACME',
            taxId: null,
            items: [self::nfseItem()],
        );
    }

    public function testWithoutClientIdTheBodyIsWhatItWas(): void
    {
        $this->invoice()->issue(
            documentType: DocumentType::NFSE,
            clientName: 'ACME',
            taxId: '11222333000181',
            items: [self::nfseItem()],
        );

        self::assertSame(
            ['document_type', 'client_name', 'tax_id', 'items'],
            array_keys($this->body()),
        );
    }
}
