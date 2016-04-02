<?php

namespace Drupal\rest_entity_display;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;

class RestEntityDisplayRenderer {

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Creates a new RestEntityDisplayRenderer instance.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  public function render(EntityViewBuilderInterface $viewBuilder, FieldableEntityInterface $entity, $view_mode) {
    $build = $viewBuilder->view($entity, $view_mode);

    $render_context = new RenderContext();
    $this->renderer->executeInRenderContext($render_context, function () use (&$build) {
      return $this->renderer->render($build);
    });

    // @todo should we use components of the entiy display instead?
    $renderer_components = [];
    foreach ($entity->getFieldDefinitions() as $name => $field_definition) {
      if (!isset($build[$name])) {
        continue;
      }

      $field_build = $build[$name];
      $render_context = new RenderContext();
      $renderer_components[$name] = $this->renderer->executeInRenderContext($render_context, function() use ($field_build, &$renderer_components) {
        $this->renderer->render($field_build);
        return [
          'markup' => $field_build['#markup'],
          'items' => [
            'markup' => array_map(function ($item) { return $item['#markup']; }, array_intersect_key(
              $field_build,
              array_flip(Element::children($field_build))
            )),
            'data' => $field_build['#items'],
          ],
        ];
      });
      $render_context->update($renderer_components);
    }
    return $renderer_components;
  }

}
