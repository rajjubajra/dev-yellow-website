<?php

namespace Drupal\Tests\simple_oauth\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use GuzzleHttp\Psr7\Query;
use League\OAuth2\Server\CryptTrait;

/**
 * The refresh tests.
 *
 * @group simple_oauth
 */
class RefreshFunctionalTest extends TokenBearerFunctionalTestBase {

  use CryptTrait;

  /**
   * The refresh token.
   *
   * @var string
   */
  protected string $refreshToken;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->grantPermissions(Role::load(RoleInterface::AUTHENTICATED_ID), [
      'grant simple_oauth codes',
    ]);
    $this->client->set('automatic_authorization', TRUE);
    $this->client->save();
    $this->drupalLogin($this->user);
    $params = [
      'response_type' => 'code',
      'client_id' => $this->client->uuid(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
      'redirect_uri' => $this->redirectUri,
    ];
    $this->drupalGet(Url::fromRoute('oauth2_token.authorize')->toString(), [
      'query' => $params,
    ]);
    $session = $this->getSession();
    $parsed_url = parse_url($session->getCurrentUrl());
    $parsed_query = Query::parse($parsed_url['query']);
    $code = $parsed_query['code'];
    $payload = [
      'grant_type' => 'authorization_code',
      'client_id' => $this->client->uuid(),
      'client_secret' => $this->clientSecret,
      'code' => $code,
      'scope' => $this->scope,
      'redirect_uri' => $this->redirectUri,
    ];
    $response = $this->post($this->url, $payload);
    $body = (string) $response->getBody();
    $parsed_response = Json::decode($body);
    $this->refreshToken = $parsed_response['refresh_token'];
  }

  /**
   * Test the valid Refresh grant.
   */
  public function testRefreshGrant(): void {
    // 1. Test the valid response.
    $valid_payload = [
      'grant_type' => 'refresh_token',
      'client_id' => $this->client->uuid(),
      'client_secret' => $this->clientSecret,
      'refresh_token' => $this->refreshToken,
      'scope' => $this->scope,
    ];
    $response = $this->post($this->url, $valid_payload);
    $this->assertValidTokenResponse($response, TRUE);

    // 2. Test the valid without scopes.
    // We need to use the new refresh token, the old one is revoked.
    $parsed_response = Json::decode((string) $response->getBody());
    $valid_payload = [
      'grant_type' => 'refresh_token',
      'client_id' => $this->client->uuid(),
      'client_secret' => $this->clientSecret,
      'refresh_token' => $parsed_response['refresh_token'],
      'scope' => $this->scope,
    ];
    $response = $this->post($this->url, $valid_payload);
    $this->assertValidTokenResponse($response, TRUE);

    // 3. Test that the token was revoked.
    $valid_payload = [
      'grant_type' => 'refresh_token',
      'client_id' => $this->client->uuid(),
      'client_secret' => $this->clientSecret,
      'refresh_token' => $this->refreshToken,
    ];
    $response = $this->post($this->url, $valid_payload);
    $parsed_response = Json::decode((string) $response->getBody());
    $this->assertSame(401, $response->getStatusCode());
    $this->assertSame('invalid_request', $parsed_response['error']);
  }

  /**
   * Data provider for ::testMissingRefreshGrant.
   */
  public function missingRefreshGrantProvider(): array {
    return [
      'grant_type' => [
        'grant_type',
        'unsupported_grant_type',
        400,
      ],
      'client_id' => [
        'client_id',
        'invalid_request',
        400,
      ],
      'client_secret' => [
        'client_secret',
        'invalid_client',
        401,
      ],
      'refresh_token' => [
        'refresh_token',
        'invalid_request',
        400,
      ],
    ];
  }

  /**
   * Test invalid Refresh grant.
   *
   * @dataProvider missingRefreshGrantProvider
   */
  public function testMissingRefreshGrant(string $key, string $error, int $code): void {
    $valid_payload = [
      'grant_type' => 'refresh_token',
      'client_id' => $this->client->uuid(),
      'client_secret' => $this->clientSecret,
      'refresh_token' => $this->refreshToken,
      'scope' => $this->scope,
    ];

    $invalid_payload = $valid_payload;
    unset($invalid_payload[$key]);
    $response = $this->post($this->url, $invalid_payload);
    $parsed_response = Json::decode((string) $response->getBody());
    $this->assertSame($error, $parsed_response['error'], sprintf('Correct error code %s', $error));
    $this->assertSame($code, $response->getStatusCode(), sprintf('Correct status code %d', $code));
  }

  /**
   * Data provider for ::invalidRefreshProvider.
   */
  public function invalidRefreshProvider(): array {
    return [
      'grant_type' => [
        'grant_type',
        'unsupported_grant_type',
        400,
      ],
      'client_id' => [
        'client_id',
        'invalid_client',
        401,
      ],
      'client_secret' => [
        'client_secret',
        'invalid_client',
        401,
      ],
      'refresh_token' => [
        'refresh_token',
        'invalid_request',
        401,
      ],
    ];
  }

  /**
   * Test invalid Refresh grant.
   *
   * @dataProvider invalidRefreshProvider
   */
  public function testInvalidRefreshGrant(string $key, string $error, int $code): void {
    $valid_payload = [
      'grant_type' => 'refresh_token',
      'client_id' => $this->client->uuid(),
      'client_secret' => $this->clientSecret,
      'refresh_token' => $this->refreshToken,
      'scope' => $this->scope,
    ];

    $invalid_payload = $valid_payload;
    $invalid_payload[$key] = $this->getRandomGenerator()->string(8, TRUE);
    $response = $this->post($this->url, $invalid_payload);
    $parsed_response = Json::decode((string) $response->getBody());
    $this->assertSame($error, $parsed_response['error'], sprintf('Correct error code %s', $error));
    $this->assertSame($code, $response->getStatusCode(), sprintf('Correct status code %d', $code));
  }

}
