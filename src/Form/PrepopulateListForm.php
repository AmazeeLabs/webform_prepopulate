<?php

namespace Drupal\webform_prepopulate\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\webform_prepopulate\WebformPrepopulateStorage;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form that lists prepopulate data for a Webform.
 */
class PrepopulateListForm extends FormBase {

  /**
   * Drupal\webform_prepopulate\WebformPrepopulateStorage definition.
   *
   * @var \Drupal\webform_prepopulate\WebformPrepopulateStorage
   */
  protected $webformPrepopulateStorage;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new WebformPrepopulateController object.
   *
   * @param \Drupal\webform_prepopulate\WebformPrepopulateStorage
   *   The Webform prepopulate storage.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date Formatter service.
   */
  public function __construct(WebformPrepopulateStorage $webform_prepopulate_storage, DateFormatterInterface $date_formatter) {
    $this->webformPrepopulateStorage = $webform_prepopulate_storage;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('webform_prepopulate.storage'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webform_prepopulate_list_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $webform_id = 'contact'; // @todo get webform
    $search = $this->getRequest()->get('hash');
    $form['#attributes'] = ['class' => ['search-form']];

    $form['basic'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Filter hash'),
      '#attributes' => ['class' => ['container-inline']],
    ];
    $form['basic']['filter'] = [
      '#type' => 'textfield',
      '#title' => '',
      '#default_value' => $search,
      '#maxlength' => 128,
      '#size' => 25,
    ];
    $form['basic']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#action' => 'filter',
    ];
    if ($search) {
      $form['basic']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#action' => 'reset',
      ];
    }

    $header = [
      ['data' => $this->t('Hash'), 'field' => 'hash', 'sort' => 'asc'],
      ['data' => $this->t('Data'), 'field' => 'prepopulate_data'],
      ['data' => $this->t('Imported'), 'field' => 'timestamp',],
      ['data' => $this->t('Operations')],
    ];

    $rows = [];
    $results = $this->webformPrepopulateStorage->listData($webform_id, $header, $search);
    foreach ($results as $result) {
      $row = [];
      $row['hash'] = $result->hash;
      $row['prepopulate_data'] = implode(', ', unserialize($result->data));
      $row['timestamp'] = $this->dateFormatter->format($result->timestamp, 'short');

      $operations = [];
      //if ($this->entityTypeManager->getAccessControlHandler('webform')->...)) {
      $operations['view'] = [
        'title' => $this->t('Prepopulate'),
        // @todo get webform link.
        'url' => Url::fromUserInput('/'),
      ];
      $row['operations'] = [
        'data' => [
          '#type' => 'operations',
          '#links' => $operations,
        ],
      ];

      $rows[] = $row;
    }

    $form['prepopulate_table']  = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      // @todo if empty, add a link to upload the file
      '#empty' => $this->t('There are no prepopulate data yet.'),
    ];
    $form['prepopulate_pager'] = ['#type' => 'pager'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo get webform
    if ($form_state->getTriggeringElement()['#action'] == 'filter') {
      //$form_state->setRedirect('entity.webform_prepopulate.prepopulate_form', [], ['query' => ['search' => trim($form_state->getValue('filter'))]]);
    }
    else {
      //$form_state->setRedirect('entity.webform_prepopulate.prepopulate_form');
    }
  }

}
