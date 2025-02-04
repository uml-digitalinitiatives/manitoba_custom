<?php

namespace Drupal\linked_data_field\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\views\Views;

/**
 * Plugin implementation of the 'views page' formatter.
 *
 * @FieldFormatter(
 *   id = "views_page_formatter",
 *   label = @Translation("Prints a Linked Data field as a link to an internal view."),
 *   field_types = {
 *     "linked_data_field",
 *     "lcsubject_field",
 *     "grid_id_field",
 *     "crossref_funder_field",
 *   }
 * )
 */
class ViewsPageLinkFormatter extends FormatterBase
{
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'view_to_use' => null,
        'view_display_to_use' => null,
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $views = Views::getAllViews();
    $options = [];
    foreach ($views as $view) {
      $options[$view->id()] = $view->label();
    }
    $form['view_to_use'] = [
      '#type' => 'select',
      '#title' => $this->t('View to use'),
      '#description' => $this->t('The view to use for the field, must have a display which accepts the Field as a URL part.'),
      '#default_value' => $this->getSetting('view_to_use'),
      '#required' => TRUE,
      '#options' => $options,
      '#ajax' => [
        'callback' => [$this, 'getViewsParts'],
        'wrapper' => 'views_to_use_wrapper',
      ]
    ];
    $form['view_display_to_use'] = [
      '#prefix' => '<div id="views_to_use_wrapper">',
      '#suffix' => '</div>',
      '#title' => $this->t('View display to use'),
      '#description' => $this->t('The view display to use for the field.')
    ];

    $field_name = $this->fieldDefinition->getName();
    $value = NestedArray::getValue($form_state->getUserInput(), ['fields', $field_name, 'settings_edit_form', 'settings', 'view_to_use']);
    if (is_null($value)) {
      $value = $this->getSetting('view_to_use');
    }
    if ($value) {
      $view = Views::getView($value);
      if ($view) {
        $options = [];
        foreach ($view->storage->get('display') as $display_id => $display) {
          $options[$display_id] = $display['display_title'];
        }
        $form['view_display_to_use']['#type'] = 'select';
        $form['view_display_to_use']['#required'] = TRUE;
        $form['view_display_to_use']['#options'] = $options;
        if (!is_null($this->getSetting('view_display_to_use'))) {
          $form['view_display_to_use']['#default_value'] = $this->getSetting('view_display_to_use');
        }
      }
      else {
        $form['view_display_to_use']['#markup'] = $this->t('Please select a view to use first.');
      }
    }
    // On initial page load, retrieve the default setting
    else {
      $form['view_display_to_use']['#markup'] = $this->t('Please select a view to use first.');
    }

    return $form;
  }

  /**
   * AJAX callback to populate the view display select element.
   * @param array $form Form array.
   * @param FormStateInterface $form_state Form state.
   * @return mixed Form element to replace.
   */
  public function getViewsParts(array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getItemDefinition()->getFieldDefinition()->getName();
    return NestedArray::getValue($form, ['fields', $field_name, 'plugin', 'settings_edit_form', 'settings', 'view_display_to_use']);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $vid = $this->getSetting('view_to_use');
    if ($vid) {
      $view = Views::getView($vid);
      $view->setDisplay($this->getSetting('view_display_to_use'));
      $summary[] = "Display using the view: " . $view->getTitle();
    }
    else {
      $summary[] = $this->t('No view selected');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array
  {
    $user = User::load(\Drupal::currentUser()->id());
    $view = Views::getView($this->getSetting('view_to_use'));
    $elements = [];
    $display_id = $this->getSetting('view_display_to_use');
    if ($view && $view->access($display_id, $user)) {
      $view->setDisplay($display_id);
      foreach ($items as $item) {
        $elements[] = [
          '#type' => 'link',
          '#title' => $item->value,
          '#url' => $view->getUrl([$item->value], $display_id)
        ];
      }
    }
    return $elements;
  }
}
