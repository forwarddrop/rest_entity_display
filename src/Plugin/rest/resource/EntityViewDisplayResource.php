<?php

namespace Drupal\rest_entity_display\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\rest_entity_display\RestEntityViewDisplayRenderer;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a resource for rendering field formatters for REST.
 *
 * @RestResource(
 *   id = "rest_view_entity_display",
 *   label = @Translation("TODO"),
 *   uri_paths = {
 *     "canonical" = "/rest_entity_view_display/{entity_type_id}/{id}/{view_mode}"
 *   }
 * )
 */
class EntityViewDisplayResource extends ResourceBase {

  /**
   * @var \Drupal\rest_entity_display\RestEntityViewDisplayRenderer
   */
  protected $renderer;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, EntityTypeManagerInterface $entityTypeManager, RestEntityViewDisplayRenderer $restRenderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->entityTypeManager = $entityTypeManager;
    $this->renderer = $restRenderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity_type.manager'),
      $container->get('rest_entity_display.view_renderer')
    );
  }

  /**
   * Responds to GET requests.
   *
   * Returns a watchdog log entry for the specified ID.
   *
   * @param null $entity_type_id
   * @param int $id
   *   The ID of the watchdog log entry.
   *
   * @param null $view_mode
   *
   * @return \Drupal\rest\ResourceResponse The response containing the log entry.
   * The response containing the log entry.
   */
  public function get($entity_type_id = NULL, $id = NULL, $view_mode = NULL) {
    if (!$entity_type_id) {
      throw new BadRequestHttpException(t('No entity type ID was provided'));
    }
    if (!$id) {
      throw new BadRequestHttpException(t('No entity ID was provided'));
    }
    if (!$view_mode) {
      throw new BadRequestHttpException(t('No view mode ID was provided'));
    }

    if ($id) {
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);
      $view_builder = $this->entityTypeManager->getViewBuilder($entity_type_id);

      $result = $this->renderer->render($view_builder, $entity, $view_mode);

      if (!empty($result)) {
        $result_copy = $result;
        unset($result_copy['#attached']);
        unset($result_copy['#cache']);
  
        $response = new ResourceResponse($result_copy);
        $cacheable_metadata = BubbleableMetadata::createFromRenderArray($result);
        $response->addCacheableDependency($cacheable_metadata);
  
        return $response;
      }

      throw new NotFoundHttpException(t('Entity with ID @id was not found', array('@id' => $id)));
    }

  }
}
