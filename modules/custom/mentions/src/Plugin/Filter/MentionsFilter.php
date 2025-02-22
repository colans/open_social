<?php

namespace Drupal\mentions\Plugin\Filter;

use Drupal\filter\Plugin\FilterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\mentions\MentionsPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\mentions\MentionsPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\filter\Entity\FilterFormat;

/**
 * Class FilterMentions.
 *
 * @package Drupal\mentions\Plugin\Filter
 *
 * @Filter(
 * id = "filter_mentions",
 * title = @Translation("Mentions Filter"),
 * description = @Translation("Configure via the <a href='/admin/structure/mentions'>Mention types</a> page."),
 * type = Drupal\filter\Plugin\FilterInterface::TYPE_HTML_RESTRICTOR,
 * settings = {
 *   "mentions_filter" = {}
 * },
 * weight = -10
 * )
 */
class MentionsFilter extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityManager;

  /**
   * The renderer service.
   */
  protected RendererInterface $renderer;

  /**
   * The config factory.
   */
  protected ConfigFactory $config;

  /**
   * The mentions plugin manager.
   */
  protected MentionsPluginManager $mentionsManager;

  /**
   * The available mention types.
   *
   * @var string[]
   */
  private array $mentionTypes = [];

  /**
   * The input settings per config.
   */
  private array $inputSettings = [];

  /**
   * The output settings per config.
   */
  private array $outputSettings = [];


  /**
   * The text format id used for mentions.
   */
  private ?string $textFormat;

  /**
   * MentionsFilter constructor.
   *
   * @param array $configuration
   *   Config array.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $render
   *   The renderer.
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   The config factory.
   * @param \Drupal\mentions\MentionsPluginManager $mentions_manager
   *   The mentions manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_manager, RendererInterface $render, ConfigFactory $config, MentionsPluginManager $mentions_manager) {
    $this->entityManager = $entity_manager;
    $this->mentionsManager = $mentions_manager;
    $this->renderer = $render;
    $this->config = $config;

    if (!isset($plugin_definition['provider'])) {
      $plugin_definition['provider'] = 'mentions';
    }

    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $entity_manager = $container->get('entity_type.manager');
    $renderer = $container->get('renderer');
    $config = $container->get('config.factory');
    $mentions_manager = $container->get('plugin.manager.mentions');

    return new static($configuration,
      $plugin_id,
      $plugin_definition,
      $entity_manager,
      $renderer,
      $config,
      $mentions_manager,
    );
  }

  /**
   * Returns the settings.
   *
   * @return array
   *   A list of settings.
   */
  public function getSettings() {
    return $this->settings;
  }

  /**
   * Checks if there are mentionTypes.
   *
   * @return bool
   *   TRUE if there are mentionTypes, otherwise FALSE.
   */
  public function checkMentionTypes() {
    $settings = $this->settings;

    if (isset($settings['mentions_filter'])) {
      $configs = $this->config->listAll('mentions.mentions_type');

      foreach ($configs as $config) {
        $this->mentionTypes[] = str_replace('mentions.mentions_type.', '', $config);
      }
    }

    return !empty($this->mentionTypes);
  }

  /**
   * Checks if a textFormat filter should be applied.
   *
   * @return bool
   *   TRUE if filter should applied, otherwise FALSE.
   */
  public function shouldApplyFilter() {
    if ($this->checkMentionTypes()) {
      return TRUE;
    }
    elseif ($this->textFormat && ($format = FilterFormat::load($this->textFormat))) {
      $filters = $format->get('filters');

      if (!empty($filters['filter_mentions']['status'])) {
        $this->settings = $filters['filter_mentions']['settings'];

        return $this->checkMentionTypes();
      }
    }

    return FALSE;
  }

  /**
   * Gets the mentions in text.
   *
   * @param string $text
   *   The text to find mentions in.
   *
   * @return array
   *   A list of mentions.
   */
  public function getMentions($text) {
    $mentions = [];
    $config_names = $this->mentionTypes;

    foreach ($config_names as $config_name) {
      $settings = $this->config->get('mentions.mentions_type.' . $config_name);
      $input_settings = [
        'prefix' => $settings->get('input.prefix'),
        'suffix' => $settings->get('input.suffix'),
        'entity_type' => $settings->get('input.entity_type'),
        'value' => $settings->get('input.inputvalue'),
      ];
      $this->inputSettings[$config_name] = $input_settings;

      if (!isset($input_settings['entity_type']) || empty($this->settings['mentions_filter'][$config_name])) {
        continue;
      }

      $output_settings = [
        'value' => $settings->get('output.outputvalue'),
        'renderlink' => (bool) $settings->get('output.renderlink'),
        'rendertextbox' => $settings->get('output.renderlinktextbox'),
      ];
      $this->outputSettings[$config_name] = $output_settings;
      $mention_type = $settings->get('mention_type');
      if ($this->mentionsManager->hasDefinition($mention_type)) {
        $mention = $this->mentionsManager->createInstance($mention_type);

        if ($mention instanceof MentionsPluginInterface) {
          $pattern = '/(?:' . preg_quote($this->inputSettings[$config_name]['prefix']) . ')([ a-z0-9@+_.\'-]+)' . preg_quote($this->inputSettings[$config_name]['suffix']) . '/';

          preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

          foreach ($matches as $match) {
            $target = $mention->targetCallback($match[1], $input_settings);

            if ($target !== FALSE) {
              $mentions[$match[0]] = [
                'type' => $mention_type,
                'source' => [
                  'string' => $match[0],
                  'match' => $match[1],
                ],
                'target' => $target,
                'config_name' => $config_name,
              ];
            }
          }
        }
      }
    }

    return $mentions;
  }

  /**
   * Filters mentions in a text.
   *
   * @param string $text
   *   The text containing the possible mentions.
   *
   * @return string
   *   The processed text.
   */
  public function filterMentions($text) {
    $mentions = $this->getMentions($text);

    foreach ($mentions as $match) {
      if ($this->mentionsManager->hasDefinition($match['type'])) {
        $mention = $this->mentionsManager->createInstance($match['type']);

        if ($mention instanceof MentionsPluginInterface) {
          $output_settings = $this->outputSettings[$match['config_name']];
          $output = $mention->outputCallback($match, $output_settings);
          $build = [
            '#theme' => 'mention_link',
            '#mention_id' => $match['target']['entity_id'],
            '#link' => base_path() . $output['link'],
            '#render_link' => $output_settings['renderlink'],
            '#render_value' => $output['value'],
            '#render_plain' => $output['render_plain'] ?? FALSE,
          ];
          $mentions = $this->renderer->render($build);
          $text = str_replace($match['source']['string'], $mentions, $text);
        }
      }
    }

    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    if ($this->shouldApplyFilter()) {
      $text = $this->filterMentions($text);

      return new FilterProcessResult($text);
    }

    return new FilterProcessResult($text);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $configs = $this->config->listAll('mentions.mentions_type');
    $candidate_entitytypes = [];

    foreach ($configs as $config) {
      $mentions_name = str_replace('mentions.mentions_type.', '', $config);
      $candidate_entitytypes[$mentions_name] = $mentions_name;
    }

    if (count($candidate_entitytypes) == 0) {
      return NULL;
    }

    $form['mentions_filter'] = [
      '#type' => 'checkboxes',
      '#options' => $candidate_entitytypes,
      '#default_value' => $this->settings['mentions_filter'],
      '#title' => $this->t('Mentions types'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function setTextFormat($text_format) {
    $this->textFormat = $text_format;
  }

}
