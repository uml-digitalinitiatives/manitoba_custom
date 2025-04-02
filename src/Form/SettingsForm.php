<?php

namespace Drupal\manitoba_custom\Form;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SettingsForm extends ConfigFormBase
{

  /**
   * The entity storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $storage;

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage service.
   */
  public function __construct(EntityStorageInterface $storage)
  {
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('entity_type.manager')->getStorage('search_api_index')
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
    return 'manitoba_custom_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('manitoba_custom.settings');

    // Load all the Solr indexes.
    $entity_index = $this->storage->loadMultiple();
    $indexes = [];
    foreach ($entity_index as $index) {
      $indexes[$index->id()] = $this->t($index->label());
    }

    $pid_field = $form_state->getValue('pid_field', $config->get('pid_field'));
    $solr_index = $form_state->getValue('solr_index', $config->get('solr_index'));

    if (empty($indexes)) {
      $form['no_indexes'] = [
        '#markup' => $this->t('No Solr indexes available.'),
      ];
    } else {
      $indexes = ['' => $this->t('- Select -')] + $indexes;
      $form['solr_index'] = [
        '#type' => 'select',
        '#title' => $this->t('Solr Index'),
        '#description' => $this->t('The index containing both the PID field and the current Node ID.'),
        '#options' => $indexes,
        '#default_value' => $solr_index,
        '#ajax' => [
          'callback' => '::updatePidField',
          'wrapper' => 'pid-field-wrapper',
          'event' => 'change',
        ],
      ];
      $fields = ['' => $this->t('- Select -')];
      if (!empty($solr_index)) {
        $selected_index = $this->storage->load($solr_index)->getFields();
        foreach ($selected_index as $index) {
          $fields[$index->getFieldIdentifier()] = $this->t($index->getLabel());
        }
        if (!array_key_exists('nid', $fields)) {
          $form_state->setErrorByName('solr_index', $this->t('The selected index does not contain the an ID (nid) field.'));
        }
      }

      $form['pid_field'] = [
        '#type' => (!empty($solr_index) ? 'select' : 'hidden'),
        '#title' => $this->t('PID Field'),
        '#prefix' => '<div id="pid-field-wrapper">',
        '#suffix' => '</div>',
        '#default_value' => $pid_field,
        '#description' => $this->t('The field containing the PID.'),
        '#options' => $fields,
      ];

    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
    if (empty($form_state->getValue('solr_index'))) {
      $form_state->setErrorByName('solr_index', $this->t('The Solr index is required.'));
    }
    if (empty($form_state->getValue('pid_field'))) {
      $form_state->setErrorByName('pid_field', $this->t('The PID field is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('manitoba_custom.settings')
      ->set('solr_index', $form_state->getValue('solr_index'))
      ->set('pid_field', $form_state->getValue('pid_field'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback to update the PID field based on the selected Solr index.
   */
  public function updatePidField(array &$form, FormStateInterface $form_state)
  {
    return $form['pid_field'];
  }
}
