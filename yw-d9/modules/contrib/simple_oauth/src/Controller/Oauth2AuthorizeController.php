<?php

namespace Drupal\simple_oauth\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\simple_oauth\Entities\ScopeEntity;
use Drupal\simple_oauth\Entities\UserEntity;
use Drupal\simple_oauth\Form\Oauth2AuthorizeForm;
use Drupal\simple_oauth\KnownClientsRepositoryInterface;
use Drupal\simple_oauth\Server\AuthorizationServerFactoryInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class OAuth2 Authorize Controller.
 */
class Oauth2AuthorizeController extends ControllerBase {

  /**
   * The message factory.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface
   */
  protected HttpMessageFactoryInterface $httpMessageFactory;

  /**
   * The authorization server factory.
   *
   * @var \Drupal\simple_oauth\Server\AuthorizationServerFactoryInterface
   */
  protected AuthorizationServerFactoryInterface $authorizationServerFactory;

  /**
   * The known client repository service.
   *
   * @var \Drupal\simple_oauth\KnownClientsRepositoryInterface
   */
  protected KnownClientsRepositoryInterface $knownClientRepository;

  /**
   * Oauth2AuthorizeController construct.
   *
   * @param \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface $http_message_factory
   *   The PSR-7 converter.
   * @param \Drupal\simple_oauth\Server\AuthorizationServerFactoryInterface $authorization_server_factory
   *   The authorization server factory.
   * @param \Drupal\simple_oauth\KnownClientsRepositoryInterface $known_clients_repository
   *   The known client repository service.
   */
  public function __construct(
    HttpMessageFactoryInterface $http_message_factory,
    AuthorizationServerFactoryInterface $authorization_server_factory,
    KnownClientsRepositoryInterface $known_clients_repository
  ) {
    $this->httpMessageFactory = $http_message_factory;
    $this->authorizationServerFactory = $authorization_server_factory;
    $this->knownClientRepository = $known_clients_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('psr7.http_message_factory'),
      $container->get('simple_oauth.server.authorization_server.factory'),
      $container->get('simple_oauth.known_clients')
    );
  }

  /**
   * Authorizes the code generation or prints the confirmation form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return mixed
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function authorize(Request $request) {
    $client_uuid = $request->get('client_id');
    $scopes_query_string = $request->get('scope');
    $server_response = new Response();

    try {
      $server_request = $this->httpMessageFactory->createRequest($request);
      if (empty($client_uuid)) {
        throw OAuthServerException::invalidRequest('client_id');
      }
      // Omitting scopes is not allowed.
      if (empty($scopes_query_string)) {
        throw OAuthServerException::invalidRequest('scope');
      }
      $consumer_storage = $this->entityTypeManager()->getStorage('consumer');
      /** @var \Drupal\consumers\Entity\Consumer[] $clients */
      $clients = $consumer_storage->loadByProperties(['uuid' => $client_uuid]);
      if (empty($clients)) {
        throw OAuthServerException::invalidClient($server_request);
      }
      $client = reset($clients);
      $automatic_authorization = (bool) $client->get('automatic_authorization')->value;

      $server = $this->authorizationServerFactory->get($client);
      $auth_request = $server->validateAuthorizationRequest($server_request);

      // Validate scopes.
      $scope_names = array_map(function ($scope) {
        if ($scope instanceof ScopeEntity && !$scope->getScopeObject()->isGrantTypeEnabled('authorization_code')) {
          throw OAuthServerException::invalidScope($scope->getIdentifier());
        }
        return $scope->getIdentifier();
      }, $auth_request->getScopes());

      if ($this->currentUser()->isAnonymous()) {
        return $this->redirectAnonymous($request);
      }

      // Once the user has logged in set the user on the AuthorizationRequest.
      $user_entity = new UserEntity();
      $user_entity->setIdentifier($this->currentUser->id());
      $auth_request->setUser($user_entity);

      // User may skip the grant step if the client has automatic authorization
      // enabled or is known.
      if ($automatic_authorization || $this->knownClientRepository->isAuthorized($this->currentUser()->id(), $client, $scope_names)) {
        $can_grant = $this->currentUser()->hasPermission('grant simple_oauth codes');
        $auth_request->setAuthorizationApproved($can_grant);
        $response = $server->completeAuthorizationRequest($auth_request, $server_response);
      }
      else {
        $response = $this->formBuilder()->getForm(Oauth2AuthorizeForm::class, $server, $auth_request);
      }
    }
    catch (OAuthServerException $exception) {
      watchdog_exception('simple_oauth', $exception);
      $response = $exception->generateHttpResponse($server_response);
    }

    return $response;
  }

  /**
   * Redirect anonymous user to user login.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns redirect response.
   */
  protected function redirectAnonymous(Request $request): RedirectResponse {
    $message = $this->t('An external client application is requesting access to your data in this site. Please log in first to authorize the operation.');
    $this->messenger()->addStatus($message);
    // If the user is not logged in.
    $destination = Url::fromRoute('oauth2_token.authorize', [], [
      'query' => UrlHelper::parse('/?' . $request->getQueryString())['query'],
    ]);
    $url = Url::fromRoute('user.login', [], [
      'query' => ['destination' => $destination->toString()],
    ]);
    // Client ID and secret may be passed as Basic Auth. Copy the headers.
    return RedirectResponse::create($url->toString(), 302, $request->headers->all());
  }

}
