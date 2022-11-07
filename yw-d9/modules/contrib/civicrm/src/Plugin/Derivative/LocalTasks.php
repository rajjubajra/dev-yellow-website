<?php

namespace Drupal\civicrm\Plugin\Derivative;

use Drupal\civicrm\Civicrm;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives CiviCRM local tasks to Drupal.
 */
class LocalTasks extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Class constructor.
   */
  public function __construct(Civicrm $civicrm) {
    $civicrm->initialize();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('civicrm')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $uf_groups = \CRM_Core_BAO_UFGroup::getModuleUFGroup('User Account');

    foreach ($uf_groups as $key => $uf_group) {
      if ($uf_group['is_active']) {
        $this->derivatives["civicrm.{$key}"] = $base_plugin_definition;
        $this->derivatives["civicrm.{$key}"]['title'] = empty($uf_group['frontend_title']) ? $uf_group['title'] : $uf_group['frontend_title'];
        $this->derivatives["civicrm.{$key}"]['route_parameters'] = ['profile' => $key];
      }
    }

    return $this->derivatives;
  }

}
