<?php

namespace Drupal\manitoba_custom\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SettingsForm extends ConfigFormBase
{

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The configuration name.
   */
  public const CONFIG_NAME = 'manitoba_custom.settings';

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *  The configuration factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface|null $typedConfigManager
   *  The typed configuration manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *  The module handler service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    EntityTypeBundleInfoInterface $bundleInfo,
    EntityFieldManagerInterface $entityFieldManager,
    TypedConfigManagerInterface $typedConfigManager = NULL,
    ModuleHandlerInterface $moduleHandler
  )
  {
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeBundleInfo = $bundleInfo;
    $this->moduleHandler = $moduleHandler;
    parent::__construct($configFactory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager'),
      $container->get('config.typed'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return ['manitoba_custom.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return self::CONFIG_NAME;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config(self::CONFIG_NAME);
    $form = parent::buildForm($form, $form_state);
    $form['pid_redirector'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Islandora Legacy Redirector'),
      '#description' => $this->t('Settings for the Islandora Legacy Redirector.'),
      '#prefix' => '<div id="redirect-mappings-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
      'redirect_mappings' => [
        '#type' => 'table',
        '#title' => $this->t('Redirect Mappings'),
        '#header' => [
          $this->t('Node Type'),
          $this->t('Redirect Field'),
          $this->t('Operations'),
        ],
        '#rows' => [],
      ],
    ];

    $mappings = $config->get('redirect_mappings') ?? [];
    $num_rows = $form_state->get('num_redirect_mappings') ?? count($mappings) ?: 1;
    $form_state->set('num_redirect_mappings', $num_rows);
    if (!$form_state->hasValue(['pid_redirector', 'redirect_mappings'])) {
      $form_state->setValue(['pid_redirector', 'redirect_mappings'], $mappings);
    }

    $bundles = $this->entityTypeBundleInfo->getBundleInfo('node');
    $bundle_options = ['' => $this->t('- Select -')];
    foreach ($bundles as $bundle_machine_name => $bundle_info) {
      $bundle_options[$bundle_machine_name] = $bundle_info['label'];
    }

    $user_input = $form_state->getUserInput();
    $triggering_element = $form_state->getTriggeringElement();
    for ($i = 0; $i < $num_rows; $i++) {
      $redirect_bundle = $form_state->getValue(['pid_redirector', 'redirect_mappings', $i, 'node_type']) ?? '';
      $redirect_field = $form_state->getValue(['pid_redirector', 'redirect_mappings', $i, 'field']) ?? '';
      if (isset($triggering_element['#name']) && !preg_match('/remove_row_(\d+)$/', $triggering_element['#name'])) {
        // If the user is removing a row, skip using user input as it won't have the latest values.
        if (isset($user_input['pid_redirector']['redirect_mappings'][$i]['node_type']) &&
        $user_input['pid_redirector']['redirect_mappings'][$i]['node_type'] !== $redirect_bundle) {
          $redirect_bundle = $user_input['pid_redirector']['redirect_mappings'][$i]['node_type'];
        }
        if (isset($user_input['pid_redirector']['redirect_mappings'][$i]['field']) &&
        $user_input['pid_redirector']['redirect_mappings'][$i]['field'] !== $redirect_field) {
          $redirect_field = $user_input['pid_redirector']['redirect_mappings'][$i]['field'];
        }
      }
      $redirect_fields = ['' => $this->t('- Select -')];

      if ($redirect_bundle) {
        $definitions = $this->entityFieldManager->getFieldDefinitions('node', $redirect_bundle);
        foreach ($definitions as $field_name => $field_def) {
          if ($field_def->getType() == 'string') {
            $redirect_fields[$field_name] = $field_def->getLabel();
          }
        }
      }

      $form['pid_redirector']['redirect_mappings'][$i]['node_type'] = [
        '#type' => 'select',
        '#options' => $bundle_options,
        '#default_value' => $redirect_bundle,
        '#ajax' => [
          'callback' => '::updateRedirectFields',
          'event' => 'change',
          'wrapper' => 'redirect-mappings-wrapper',
        ],
      ];

      $form['pid_redirector']['redirect_mappings'][$i]['field'] = [
        '#type' => 'select',
        '#options' => $redirect_fields,
        '#default_value' => $redirect_field,
      ];

      $form['pid_redirector']['redirect_mappings'][$i]['remove'] = [
        '#type' => 'submit',
        '#name' => "remove_row_$i",
        '#value' => $this->t('Remove'),
        '#submit' => ['::removeMappingCallback'],
        '#ajax' => [
          'callback' => '::updateRedirectFields',
          'wrapper' => 'redirect-mappings-wrapper',
        ],
      ];
    }

    $form['pid_redirector']['add_row'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Mapping'),
      '#submit' => ['::addMappingCallback'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::updateRedirectFields',
        'wrapper' => 'redirect-mappings-wrapper',
      ],
    ];

    $form['pathauto_skipper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Islandora Pathauto Skipping'),
      '#description' => $this->t('Settings for the Islandora Pathauto Skipping.'),
      '#prefix' => '<div id="pathauto-skipping-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];
    if ($this->moduleHandler->moduleExists('pathauto')) {
      $form['pathauto_skipper']['description'] = [
        '#markup' => $this->t('The following models are available for skipping pathauto generation.'),
      ];
      $pathauto_bundle = $form_state->getValue(['pathauto_skipper', 'node_bundle']) ?? '';
      $pathauto_field = $form_state->getValue(['pathauto_skipper', 'node_field']) ?? '';
      $form['pathauto_skipper']['node_bundle'] = [
        '#type' => 'select',
        '#options' => $bundle_options,
        '#default_value' => $pathauto_bundle,
        '#ajax' => [
          'callback' => '::updatePathAuto',
          'event' => 'change',
          'wrapper' => 'pathauto-skipping-wrapper',
        ],
      ];

      $pathauto_fields = ['' => $this->t('- Select -')];

      if ($pathauto_bundle) {
        $definitions = $this->entityFieldManager->getFieldDefinitions('node', $pathauto_bundle);
        foreach ($definitions as $field_name => $field_def) {
          if ($field_def->getType() == 'string') {
            $pathauto_fields[$field_name] = $field_def->getLabel();
          }
        }
      }

      $form['pathauto_skipper']['node_field'] = [
        '#type' => 'select',
        '#options' => $pathauto_fields,
        '#default_value' => $pathauto_field,
        '#ajax' => [
          'callback' => '::updatePathAuto',
          'event' => 'change',
          'wrapper' => 'pathauto-skipping-wrapper',
        ],
      ];

      $form['pathauto_skipper']['skipped_terms'] = [
        '#type' => 'table',
        '#title' => $this->t('Islandora Model Terms'),
        '#header' => [
          '',
          $this->t('Model Term'),
        ],
        '#rows' => [],
      ];

      if ($pathauto_field) {
        $models = $this->getModelTerms($pathauto_bundle, $pathauto_field);
        $skipped_models = $config->get('pathauto_skipped_models') ?? [];
        if (!$form_state->hasValue(['pathauto_skipper', 'skipped_terms'])) {
          $form_state->setValue(['pathauto_skipper', 'skipped_terms'], $skipped_models);
        }
        $counter = 0;
        foreach ($models as $term) {
          $form['pathauto_skipper']['skipped_terms'][$counter]['action'] = [
            'checkbox' => [
              '#type' => 'checkbox',
              '#default_value' => in_array($term->id(), $skipped_models),
              '#return_value' => $term->id(),
            ],
          ];
          $form['pathauto_skipper']['skipped_terms'][$counter]['model_name'] = [
            '#markup' => $term->label(),
          ];
          $counter += 1;
        }
      }
    } else {
      $form['pathauto_skipper']['description'] = [
        '#markup' => $this->t('The Pathauto module is not enabled. Please enable it to use this feature.'),
      ];
      $form['pathauto_skipper']['skipped_terms'] = [];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue(['pid_redirector', 'redirect_mappings']);
    $triggering_element = $form_state->getTriggeringElement();
    $skip = false;
    if (isset($triggering_element['#type']) &&
      ($triggering_element['#type'] === 'select' ||
      ($triggering_element['#type'] === 'submit' && str_starts_with($triggering_element['#name'], 'remove_row_'))
      )) {
      if ($triggering_element['#type'] === 'submit') {
        // Ensure we are not removing the only row.
        $num_rows = $form_state->get('num_redirect_mappings') ?? 1;
        if ($num_rows <= 1 && $triggering_element['#name'] === 'remove_row_0') {
          $form_state->setErrorByName('pid_redirector][redirect_mappings][0]', $this->t('At least one redirect mapping is required.'));
          return;
        }
      }
      // If removing a row, or changing a select box skip validation.
      $skip = true;
    }

    if (!$skip) {
      foreach ($values as $index => $mapping) {
        $errors = [];
        if (empty($mapping['node_type'])) {
          $errors[] = $this->t('Node type is required for mapping @index.', ['@index' => $index + 1]);
        }
        if (empty($mapping['field'])) {
          $errors[] = $this->t('Redirect field is required for mapping @index.', ['@index' => $index + 1]);
        }
        if (count($errors) > 0) {
          foreach ($errors as $error) {
            $form_state->setErrorByName("pid_redirector][redirect_mappings][$index]", $error);
          }
        }
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue(['pid_redirector', 'redirect_mappings']);
    array_walk($values, function (&$value, $key) {
      if (is_array($value)) {
        // Ensure we only keep the necessary fields.
        $value = [
          'node_type' => $value['node_type'] ?? '',
          'field' => $value['field'] ?? '',
        ];
      } else {
        // If not an array, reset to empty.
        $value = [];
      }
    });
    $values = array_filter($values); // Filter empty mappings.
    $this->config('manitoba_custom.settings')
      ->set('redirect_mappings', $values);
    $skip_bundle = $form_state->getValue(['pathauto_skipper', 'node_bundle']);
    $skip_field = $form_state->getValue(['pathauto_skipper', 'node_field']);
    $skip_values = $form_state->getValue(['pathauto_skipper', 'skipped_terms']);
    array_walk($skip_values, function (&$value, $key) {
      if (is_array($value) && isset($value['action']) && isset($value['action']['checkbox'])) {
        $value = $value['action']['checkbox'];
      } else {
        $value = null;
      }
    });
    $skip_values = array_filter($skip_values);
    if ($skip_bundle && $skip_field && !empty($skip_values)) {
      $this->config('manitoba_custom.settings')
        ->set('pathauto_skipped_models', $skip_values)
        ->set('pathauto_bundle_name', $skip_bundle)
        ->set('pathauto_field_name', $skip_field);
    }
    $this->config('manitoba_custom.settings')->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback to add a new redirect mapping row.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addMappingCallback(array &$form, FormStateInterface $form_state) {
    $count = $form_state->get('num_redirect_mappings') ?? 1;
    $form_state->set('num_redirect_mappings', $count + 1);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Ajax callback to remove a redirect mapping row.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function removeMappingCallback(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement()['#name'];
    if (preg_match('/remove_row_(\d+)$/', $trigger, $matches)) {
      $index = (int) $matches[1];
      $mappings = $form_state->getValue(['pid_redirector', 'redirect_mappings']);
      $user_input = $form_state->getUserInput();
      unset($mappings[$index]);
      if (isset($user_input['pid_redirector']['redirect_mappings'][$index])) {
        unset($user_input['pid_redirector']['redirect_mappings'][$index]);
        $form_state->setUserInput($user_input);
      }
      $form_state->setValue(['pid_redirector', 'redirect_mappings'], array_values($mappings));
      $form_state->set('num_redirect_mappings', count($mappings));
      $form_state->setRebuild(TRUE);
    }
  }


  /**
   * Ajax callback to update the PID field select based on the selected content bundle.
   */
  public function updateRedirectFields(array &$form, FormStateInterface $form_state) {
    return $form['pid_redirector'];
  }

  /**
   * Ajax callback to update the Pathauto skipping fields based on the selected content bundle and field.
   */
  public function updatePathAuto(array &$form, FormStateInterface $form_state) {
    return $form['pathauto_skipper'];
  }

  /**
   * Loads taxonomy terms from the vocabulary targeted by field_model.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Taxonomy terms in the vocabulary referenced by field_model on the
   *   islandora_object content type.
   */
  public function getModelTerms(string $bundle_name, string $field_name): array {
    $field_definition = $this->entityFieldManager
      ->getFieldDefinitions('node', $bundle_name)[$field_name] ?? NULL;

    if (!$field_definition || $field_definition->getType() !== 'entity_reference') {
      return [];
    }

    $settings = $field_definition->getSettings();
    if (($settings['target_type'] ?? NULL) !== 'taxonomy_term') {
      return [];
    }

    $handler_settings = $settings['handler_settings'] ?? [];
    $target_bundles = array_values($handler_settings['target_bundles'] ?? []);
    $vocabulary_id = $target_bundles[0] ?? NULL;

    if (!$vocabulary_id || !Vocabulary::load($vocabulary_id)) {
      return [];
    }

    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    return $term_storage->loadTree($vocabulary_id, 0, NULL, TRUE);
  }
}
