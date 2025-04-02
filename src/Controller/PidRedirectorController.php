<?php

namespace Drupal\manitoba_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\search_api\IndexInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class PidRedirectorController extends ControllerBase
{
  private $index;

  private $pid_field;

  public function __construct(?IndexInterface $index, string $pid_field)
  {
    $this->index = $index;
    $this->pid_field = $pid_field;
  }


  public static function create(ContainerInterface $container)
  {
    $config = $container->get('config.factory')->getEditable('manitoba_custom.settings');
    $solr_index = $config->get('solr_index');
    $pid_field = $config->get('pid_field');
    $index = NULL;
    if ($solr_index && $pid_field) {
      $index = $container->get('entity_type.manager')->getStorage('search_api_index')->load($solr_index);
    }
    return new static(
      $index,
      $pid_field
    );
  }

  /**
   * Redirects from the Islandora Legacy PID to the new Drupal node based on the results of a Search API query.
   *
   * @param Request $request Request object.
   * @param string|null $pid The PID to redirect to.
   * @return RedirectResponse Redirect response.
   * @throws \Drupal\search_api\SearchApiException
   */
  public function pidRedirect(Request $request, string $pid = NULL) {
    if (!empty($pid) && $this->index && $this->pid_field) {
      $query = $this->index->query();
      $query->addCondition($this->pid_field, $pid);
      $query->range(0, 10);
      $results = $query->execute();
      if ($results->getResultCount() == 1) {
        $result_holder = $results->getResultItems();
        $result = reset($result_holder);
        $item = $result->getFields();
        $values = $item['nid']->getValues();
        $id = reset($values);
        return $this->redirect('entity.node.canonical', ['node' => $id]);
      }
      if ($results->getResultCount() > 1) {
          $this->messenger()->addError(t('Multiple results found for PID: @pid', ['@pid' => $pid]));
      }
    }
    return new RedirectResponse('/');
  }
}
