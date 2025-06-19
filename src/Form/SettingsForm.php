<?php

namespace Drupal\manitoba_custom\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
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
   * The configuration name.
   */
  public const CONFIG_NAME = 'manitoba_custom.settings';

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *  The configuration factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *  The typed configuration manager service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    protected $typedConfigManager = NULL,
    EntityTypeBundleInfoInterface $bundleInfo,
    EntityFieldManagerInterface $entityFieldManager,
  )
  {
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeBundleInfo = $bundleInfo;
    parent::__construct($configFactory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager')
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
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('manitoba_custom.settings');

    $bundles = $this->entityTypeBundleInfo->getBundleInfo('node');
    $bundle_options = ['' => $this->t('- Select -')];
    foreach ($bundles as $bundle_machine_name => $bundle_info) {
      $bundle_options[$bundle_machine_name] = $bundle_info['label'];
    }
    $bundle_choice = $form_state->getValue('redirect_node_type', $config->get('redirect_node_type'));
    $form['pid_redirector'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Islandora Legacy Redirector'),
      '#description' => $this->t('Settings for the Islandora Legacy Redirector.'),
      '#prefix' => '<div id="pid-redirector-wrapper">',
      '#suffix' => '</div>',
      'redirect_node_type' => [
        '#type' => 'select',
        '#title' => $this->t('Node Type'),
        '#description' => $this->t('The node type to use for the legacy redirector.'),
        '#options' => $bundle_options,
        '#default_value' => $bundle_choice,
        '#ajax' => [
          'callback' => '::updateRedirectFields',
          'wrapper' => 'pid-redirector-wrapper',
          'event' => 'change',
        ],
      ],
    ];
    $fields = null;
    if (!empty($bundle_choice)) {
      $bundle_fields = $this->entityFieldManager->getFieldDefinitions('node', $bundle_choice);
      $fields = ['' => $this->t('- Select -')];
      foreach ($bundle_fields as $field_name => $field_definition) {
        if ($field_definition->getType() == 'string') {
          $fields[$field_name] = $this->t($field_definition->getLabel());
        }
      }
      if (!array_key_exists('nid', $bundle_fields)) {
        $form_state->setErrorByName(
          'redirect_node_type',
          $this->t('The selected node type does not contain the an ID (nid) field.')
        );
      }
    }
    $form['pid_redirector']['redirect_node_field'] = [
      '#type' => (!empty($bundle_choice) ? 'select' : 'hidden'),
      '#title' => $this->t('Redirect Node Field'),
      '#description' => $this->t('The field containing the legacy PID.'),
      '#options' => $fields,
      '#default_value' => $form_state->getValue('redirect_node_field', $config->get('redirect_node_field')),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('manitoba_custom.settings')
      ->set('redirect_node_type', $form_state->getValue('redirect_node_type'))
      ->set('redirect_node_field', $form_state->getValue('redirect_node_field'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback to update the PID field select based on the selected content bundle.
   */
  public function updateRedirectFields(array &$form, FormStateInterface $form_state) {
    return $form['pid_redirector'];
  }

}
