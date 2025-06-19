<?php

namespace Drupal\manitoba_custom\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class SimpleLegacyController extends ControllerBase
{
  protected $entityTypeManager;

  protected $configFactory;

  /**
   * The field name that contains the Legacy PID to use for the redirect.
   *
   * @var string
   */
  private $field_name;


  /**
   * SimpleLegacyConnector constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
    );
  }

  /**
   * Lazy load the PID field name.
   * @return string
   */
  private function getFieldName(): string {
    if (empty($this->field_name)) {
      $config = $this->configFactory->get('manitoba_custom.settings');
      $this->field_name = $config->get('redirect_node_field');
    }
    return $this->field_name;
  }

  public function pidRedirect(Request $request, string $pid = NULL) {
    $node = $this->entityTypeManager->getStorage('node')->loadByProperties([
      $this->getFieldName() => $pid
    ]);

    if (!empty($node)) {
      $node = reset($node);
      return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
    }
    else {
      $cache_metadata = new CacheableMetadata();
      $cache_metadata->addCacheTags($request->query->all());
      throw new CacheableNotFoundHttpException($cache_metadata);
    }
  }
}
