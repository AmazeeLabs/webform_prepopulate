<?php

namespace Drupal\webform_prepopulate;

use Drupal\Component\Utility\Xss;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\webform_prepopulate\WebformPrepopulateStorage;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class WebformPrepopulateUtils.
 */
class WebformPrepopulateUtils {

  const MAX_HASH_ACCESS = 5;

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
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new WebformPrepopulateUtils object.
   */
  public function __construct(PrivateTempStoreFactory $tempstore_private, ConfigFactoryInterface $config_factory, WebformPrepopulateStorage $webform_prepopulate_storage, EntityTypeManagerInterface $entity_type_manager) {
    $this->tempstorePrivate = $tempstore_private;
    $this->configFactory = $config_factory;
    $this->webformPrepopulateStorage = $webform_prepopulate_storage;
    $this->entityTypeManager = $entity_type_manager;
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
    // Bypass by site wide permission.
    if (\Drupal::currentUser()->hasPermission('bypass webform prepopulate hash access limit')) {
      return TRUE;
    }

    // Bypass by Webform configuration.
    if ($this->getWebformSetting('disable_hash_access_limit', $webform_id) === 1) {
      return TRUE;
    }

    // Exclude bots, as they will use several sessions,
    // the tempStore is not useful here.
    $userAgent = Xss::filter(\Drupal::request()->headers->get('user-agent'));
    if(
      empty($userAgent) ||
      // @todo review bots list
      (!empty($userAgent) && preg_match('~(bot|crawl|python)~i', $userAgent))
    ){
      \Drupal::logger('webform_prepopulate')->warning('Bot access blocked for user agent @agent.', [
        '@agent' => $userAgent,
      ]);
      return FALSE;
    }

    // Main case for a single session.
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

    $result = count($accessedHashes) <= self::MAX_HASH_ACCESS;

    if (!$result) {
      \Drupal::logger('webform_prepopulate')->warning(t('Hash access limit reached for ip @ip.', [
        '@ip' => \Drupal::request()->getClientIp(),
      ]));
    }

    return $result;
  }

  /**
   * Check if prepopulate from a file data source is enabled.
   *
   * @param string $webform_id
   *
   * @return bool
   */
  public function isFilePrepopulateEnabled($webform_id) {
    return $this->getWebformSetting('form_prepopulate_enable_file', $webform_id) === 1;
  }

  /**
   * Get setting defined via the hook_form_webform_settings_form_form_alter().
   *
   * @param string $setting
   * @param string $webform_id
   *
   * @return mixed
   */
  public function getWebformSetting($setting, $webform_id) {
    $result = NULL;
    try {
      /** @var \Drupal\webform\Entity\Webform $webformEntity */
      $webformEntity = $this->entityTypeManager->getStorage('webform')->load($webform_id);
      if (
        !empty($webformEntity) &&
        !empty($settings = $webformEntity->getThirdPartySettings('webform_prepopulate')) &&
        isset($settings[$setting])
      ) {
        $result = $settings[$setting];
      }
    }
    catch (\Throwable $exception) {
      \Drupal::logger('webform_prepopulate')->error($exception->getMessage());
    }
    return $result;
  }

}
