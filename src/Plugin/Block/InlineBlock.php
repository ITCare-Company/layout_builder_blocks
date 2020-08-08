<?php

namespace Drupal\layout_builder_blocks\Plugin\Block;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\bootstrap_styles\StylesGroup\StylesGroupManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\SubformState;

use Drupal\layout_builder\Plugin\Block\InlineBlock as BaseInlineBlock;

/**
 * Defines an inline block plugin type.
 */
class InlineBlock extends BaseInlineBlock {

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
   * Constructs a new InlineBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\bootstrap_styles\StylesGroup\StylesGroupManager $styles_group_manager
   *   The styles group plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository, AccountInterface $current_user = NULL, StylesGroupManager $styles_group_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_display_repository, $current_user);
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
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('current_user'),
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
  public function blockValidate($form, FormStateInterface $form_state) {
    // Pass the subform original inline block to the parent blockValidate.
    $sub_form_state = SubformState::createForSubform($form['ui']['tab_content']['content'], $form, $form_state);
    parent::blockValidate($form['ui']['tab_content']['content'], $sub_form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Pass the subform original inline block to the parent blockSubmit.
    // @todo Remove ['settings'] when https://www.drupal.org/project/drupal/issues/2948549 is closed.
    $sub_form_state = SubformState::createForSubform($form['settings']['ui']['tab_content']['content'], $form, $form_state);
    parent::blockSubmit($form['settings']['ui']['tab_content']['content'], $sub_form_state);

    // Save styles tab configs.
    $style_tab = ['appearance'];
    $this->configuration['bootstrap_styles']['block_style'] = $this->stylesGroupManager->submitStylesFormElements($form, $form_state, $style_tab, $this->configuration['bootstrap_styles']['block_style']);
  }

}
