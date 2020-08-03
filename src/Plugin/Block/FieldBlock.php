<?php

namespace Drupal\layout_builder_blocks\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\bootstrap_styles\StylesGroup\StylesGroupManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\layout_builder\Plugin\Block\FieldBlock as BaseFieldBlock;

/**
 * Provides a block that renders a field from an entity.
 */
class FieldBlock extends BaseFieldBlock {

  /**
   * The styles group plugin manager.
   *
   * @var \Drupal\bootstrap_styles\StylesGroup\StylesGroupManager
   */
  protected $stylesGroupManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new FieldBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Field\FormatterPluginManager $formatter_manager
   *   The formatter manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\bootstrap_styles\StylesGroup\StylesGroupManager $styles_group_manager
   *   The styles group plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager, FormatterPluginManager $formatter_manager, ModuleHandlerInterface $module_handler, LoggerInterface $logger, StylesGroupManager $styles_group_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager, $formatter_manager, $module_handler, $logger);
    $this->stylesGroupManager = $styles_group_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.formatter'),
      $container->get('module_handler'),
      $container->get('logger.channel.layout_builder'),
      $container->get('plugin.manager.bootstrap_styles_group'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $default_configuration = parent::defaultConfiguration();
    return $default_configuration + [
      'bootstrap_styles' => [
        'block_style' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $parent_form = parent::buildConfigurationForm($form, $form_state);
    // Check for restrictions.
    $config = $this->configFactory->get('layout_builder_blocks.styles');
    if (
      $config->get('block_restrictions')
      && count($config->get('block_restrictions')) > 0
      && !in_array($this->getPluginId(), $config->get('block_restrictions'))
    ) {
      // Return parent form.
      return $parent_form;
    }

    $form = [
      '#tree' => FALSE,
      '#parents' => [],
    ];

    // Our main set of tabs.
    $form['ui'] = [
      '#type' => 'container',
      '#weight' => -100,
      '#attributes' => [
        'id' => 'blb_ui',
      ],
      '#tree' => FALSE,
      '#parents' => ['ui'],
    ];

    $tabs = [
      [
        'machine_name' => 'content',
        'icon' => 'layout.svg',
        'title' => $this->t('Content'),
        'active' => TRUE,
      ],
      [
        'machine_name' => 'appearance',
        'icon' => 'appearance.svg',
        'title' => $this->t('Style'),
      ],
    ];

    // Create our tabs from above.
    $form['ui']['nav_tabs'] = [
      '#type' => 'html_tag',
      '#tag' => 'ul',
      '#attributes' => [
        'class' => 'bs_nav-tabs',
        'id' => 'bs_nav-tabs',
        'role' => 'tablist',
      ],
    ];

    $form['ui']['tab_content'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => 'blb_tab-content',
        'id' => 'bs_tabContent',
      ],
      '#tree' => FALSE,
      '#parents' => ['tab_content'],
    ];

    // Create our tab & tab panes.
    foreach ($tabs as $tab) {
      $form['ui']['nav_tabs'][$tab['machine_name']] = [
        '#type' => 'inline_template',
        '#template' => '<li><a data-target="{{ target|clean_class }}" class="{{active}}">{{ icon }}<div class="bs_tooltip" data-placement="bottom" role="tooltip">{{ title }}</div></a></li>',
        '#context' => [
          'title' => $tab['title'],
          'target' => $tab['machine_name'],
          'active' => isset($tab['active']) && $tab['active'] == TRUE ? 'active' : '',
          'icon' => t('<img class="blb_icon" src="/' . drupal_get_path('module', 'bootstrap_styles') . '/images/ui/' . ($tab['icon'] ? $tab['icon'] : 'default.svg') . '" />'),
        ],
      ];

      $form['ui']['tab_content'][$tab['machine_name']] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'bs_tab-pane',
            'bs_tab-pane--' . $tab['machine_name'],
            isset($tab['active']) && $tab['active'] == TRUE ? 'active' : '',
          ],
        ],
        '#tree' => FALSE,
        '#parents' => [$tab['machine_name']],
      ];
    }

    $form['ui']['tab_content']['content'] += $parent_form;

    $form['ui']['tab_content']['appearance']['#tree'] = TRUE;
    $form['ui']['tab_content']['appearance'] += $this->stylesGroupManager->buildStylesFormElements($form['ui']['tab_content']['appearance'], $form_state, $this->configuration['bootstrap_styles']['block_style'], 'layout_builder_blocks.styles');

    // Attach Bootstrap Styles base library.
    $form['#attached']['library'][] = 'bootstrap_styles/layout_builder_form_style';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    return parent::blockForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    // Styles tab.
    $style_tab = ['appearance'];
    $this->configuration['bootstrap_styles']['block_style'] = $this->stylesGroupManager->submitStylesFormElements($form, $form_state, $style_tab, $this->configuration['bootstrap_styles']['block_style']);
  }

}
