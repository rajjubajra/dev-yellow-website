<?php

namespace Drupal\rules_http_client\Plugin\RulesAction;

use Drupal\rules\Core\RulesActionBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Provides "Rules Http client" rules action.
 *
 * @RulesAction(
 *   id = "rules_http_client",
 *   label = @Translation("Request HTTP data"),
 *   category = @Translation("Data"),
 *   context_definitions = {
 *     "url" = @ContextDefinition("string",
 *       label = @Translation("URL"),
 *       description = @Translation("Url address where to post, get and delete request send."),
 *       required = TRUE,
 *       multiple = TRUE,
 *     ),
 *     "headers" = @ContextDefinition("string",
 *       label = @Translation("Headers"),
 *       description = @Translation("Request headers to send as 'name: value' pairs, one per line (e.g., Accept: text/plain). See <a href='https://www.wikipedia.org/wiki/List_of_HTTP_header_fields'>wikipedia.org/wiki/List_of_HTTP_header_fields</a> for more information."),
 *       required = FALSE,
 *      ),
 *     "method" = @ContextDefinition("string",
 *       label = @Translation("Method"),
 *       description = @Translation("The HTTP request methods like'HEAD','POST','PUT','DELETE','TRACE','OPTIONS','CONNECT','PATCH' etc."),
 *       required = FALSE,
 *     ),
 *     "data" = @ContextDefinition("string",
 *       label = @Translation("Data"),
 *       description = @Translation("The request body, formatter as 'param=value&param=value&...' or one 'param=value' per line.."),
 *       required = FALSE,
 *       multiple = TRUE,
 *       assignment_restriction = "data",
 *     ),
 *     "max_redirects" = @ContextDefinition("integer",
 *       label = @Translation("Max Redirect"),
 *       description = @Translation("How many times a redirect may be followed."),
 *       default_value = 3,
 *       required = FALSE,
 *       assignment_restriction = "input",
 *     ),
 *     "timeout" = @ContextDefinition("float",
 *       label = @Translation("Timeout"),
 *       description = @Translation("The maximum number of seconds the request may take.."),
 *       default_value = 30,
 *       required = FALSE,
 *     ),
 *   },
 *   provides = {
 *     "http_response" = @ContextDefinition("string",
 *       label = @Translation("HTTP data")
 *     )
 *   }
 * )
 *
 * @todo: Define that message Context should be textarea comparing with textfield Subject
 * @todo: Add access callback information from Drupal 7.
 */
class RulesHttpClient extends RulesActionBase implements ContainerFactoryPluginInterface {

  /**
   * The logger for the rules channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a httpClient object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param GuzzleHttp\ClientInterface $http_client
   *   The guzzle http client instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger_factory, ClientInterface $http_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger_factory->get('rules_http_client');
    $this->http_client = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('http_client')
    );
  }

  /**
   * Send a system email.
   *
   * @param string[] $url
   *   Url addresses HTTP request.
   * @param string|null $headers
   *   (optional) Header information of HTTP Request.
   * @param string $method
   *   (optional) Method of HTTP request.
   * @param string|null $data
   *   (optional) Raw data of HTTP request.
   * @param int|null $max_redirects
   *   (optional) Max redirect for HTTP request.
   * @param int|null $timeout
   *   (optional) Time Out for HTTP request.
   */
  protected function doExecute(array $url, $headers, $method, $data = NULL, $max_redirects = 3, $timeout = 30) {
    // Headers section.
    $headers = explode("\r\n", $headers);
    if (is_array($headers)) {
      foreach ($headers as $header) {
        if (!empty($header) && strpos($header, ':') !== FALSE) {
          list($name, $value) = explode(':', $header, 2);
          if (!empty($name)) {
            $options['headers'][$name] = ltrim($value);
          }
        }
      }
    }
    $finalArray = [];
    if (is_array($data)) {
      // Data section.
      foreach ($data as $singleArray) {
        $finalSingleArray = explode('=', $singleArray);
        $finalArray[$finalSingleArray[0]] = $finalSingleArray[1];
      }

      // Json decode array.
      $finalArray = json_encode($finalArray);
    }

    // Payload data.
    $options['data'] = $finalArray;

    // Max redirects.
    $options['max_redirects'] = empty($max_redirects) ? 3 : $max_redirects;

    // Timeout.
    $options['timeout'] = empty($timeout) ? 30 : $timeout;

    $postUrl = $url[0];
    // Method.
    $method = strtoupper($method);
    // Options.
    $options['method'] = $method == 'POST' ? 'POST' : 'GET';

    $options['body'] = $finalArray;

    $client = $this->http_client;

    try {
      $response = $client->request($method, $postUrl, $options);

      // Status of request.
      $status = $response->getStatusCode();
      // Check if we succesfully get the output.
      if ($status == '200') {
        $stream = $response->getBody();
        $stream->rewind();
        $output = $stream->getContents();
        // Set the response output from service call.
        $this->setProvidedValue('http_response', $output);
      }
    }
    catch (RequestException $e) {
      $variables = [
        '@message' => 'Request error',
        '@error_message' => $e->getMessage(),
      ];
      $this->logger->error('@message. Details: @error_message', $variables);
    }
  }

}
