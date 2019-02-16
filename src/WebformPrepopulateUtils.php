<?php

namespace Drupal\webform_prepopulate;

use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\webform_prepopulate\WebformPrepopulateStorage;

/**
 * Class WebformPrepopulateUtils.
 */
class WebformPrepopulateUtils {

  const MAX_HASH_ACCESS = 10;

  /**
   * Drupal\Core\TempStore\PrivateTempStoreFactory definition.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempstorePrivate;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\webform_prepopulate\WebformPrepopulateStorage definition.
   *
   * @var \Drupal\webform_prepopulate\WebformPrepopulateStorage
   */
  protected $webformPrepopulateStorage;

  /**
   * Constructs a new WebformPrepopulateUtils object.
   */
  public function __construct(PrivateTempStoreFactory $tempstore_private, ConfigFactoryInterface $config_factory, WebformPrepopulateStorage $webform_prepopulate_storage) {
    $this->tempstorePrivate = $tempstore_private;
    $this->configFactory = $config_factory;
    $this->webformPrepopulateStorage = $webform_prepopulate_storage;
  }

  /**
   * Checks the amount of hash access for a Webform within a session.
   *
   * @param $hash
   * @param $webform_id
   *
   * @return bool
   */
  public function hasHashAccess($hash, $webform_id) {
    // @todo if user bypass permission.
    // @todo if set in configuration for this Webform.
    $maxHashAccess = self::MAX_HASH_ACCESS;
    $tempStore = $this->tempstorePrivate->get('webform_prepopulate');
    $accessedHashes = [];
    try {
      if (empty($tempStore->get('accessed_hashes_'. $webform_id))) {
        $accessedHashes[] = $hash;
        $tempStore->set('accessed_hashes_'. $webform_id, $accessedHashes);
      }
      else {
        $accessedHashes = $tempStore->get('accessed_hashes_'. $webform_id);
        if (!in_array($hash, $accessedHashes)) {
          $accessedHashes[] = $hash;
          $tempStore->set('accessed_hashes_'. $webform_id, $accessedHashes);
        }
      }
    }
    catch (\Drupal\Core\TempStore\TempStoreException $exception) {
      \Drupal::logger('webform_prepopulate')->warning($exception->getMessage());
    }
    return count($accessedHashes) <= $maxHashAccess;
  }

}
