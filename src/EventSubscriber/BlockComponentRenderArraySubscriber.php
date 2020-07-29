<?php

namespace Drupal\layout_builder_blocks\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\bootstrap_styles\StylesGroup\StylesGroupManager;

/**
 * Class BlockComponentRenderArraySubscriber.
 */
class BlockComponentRenderArraySubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * BlockComponentRenderArraySubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Access configuration.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $config_factory, StylesGroupManager $styles_group_manager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $config_factory;
    $this->stylesGroupManager = $styles_group_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Layout Builder also subscribes to this event to build the initial render
    // array. We use a higher weight so that we execute after it.
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = ['onBuildRender', 50];
    return $events;
  }

  /**
   * Add each component's block styles to the render array.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The section component render event.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
    $build = $event->getBuild();

    $block_style_configs = [];
    if (isset($build['#configuration']['bootstrap_styles']['block_style'])) {
      $block_style_configs = $build['#configuration']['bootstrap_styles']['block_style'];
    }

    // Build dynamic styles.
    $build = $this->stylesGroupManager->buildStyles(
      $build,
      $block_style_configs, // Storage.
      NULL, // Theme wrapper that we need to apply styles to it.
    );

    $event->setBuild($build);
  }

}
